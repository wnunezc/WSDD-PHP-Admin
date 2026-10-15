<?php

namespace Wsdd\PhpAdmin\Runtime;

use Wsdd\PhpAdmin\Support\AppConfig;
use Wsdd\PhpAdmin\Support\CommandRunner;
use Wsdd\PhpAdmin\Support\ExecutableFinder;
use Wsdd\PhpAdmin\Support\InstanceLock;
use Wsdd\PhpAdmin\Profile\ProfileRepository;
use Wsdd\PhpAdmin\Reconciliation\ManagedReconciler;
use Wsdd\PhpAdmin\Ini\ManagedIniRepository;
use Wsdd\PhpAdmin\Extensions\ExtensionInstaller;
use Wsdd\PhpAdmin\Extensions\ExtensionCatalog;
use Wsdd\PhpAdmin\Service\ServiceReloader;

final class RuntimeStarter
{
    private $config;
    private $root;
    private $children = [];
    private $startId;

    public function __construct(AppConfig $config, $root)
    {
        $this->config = $config;
        $this->root = realpath($root);
        if ($this->root === false) throw new \RuntimeException('Runtime root does not exist.');
    }

    public function start($timeoutSeconds = 45)
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0 || !function_exists('posix_kill')) {
            throw new \RuntimeException('Runtime start requires root and POSIX process control.');
        }
        if (!is_numeric($timeoutSeconds) || $timeoutSeconds <= 0 || $timeoutSeconds > 120) throw new \InvalidArgumentException('Invalid readiness timeout.');
        foreach (['nohup', 'setsid'] as $tool) {
            if (ExecutableFinder::find($tool) === null) throw new \RuntimeException('Required runtime tool is unavailable: ' . $tool);
        }
        if (!is_file($this->root . '/public/runtime-health.php')) throw new \RuntimeException('Runtime health endpoint is missing.');
        $directory = $this->config->persistentRoot() . '/runtime';
        $startup = new InstanceLock($directory . '/startup.lock');
        $reservation = null;
        $this->startId = bin2hex(random_bytes(32));
        try {
            $reservation = new InstanceLock($directory . '/agent.lock');
            if ($this->legacyAgentRunning()) throw new \RuntimeException('An agent for this root and profile is already running.');
            $this->assertPortFree();
            $this->prepareDirectories();
            $profiles = new ProfileRepository($this->config);
            $profiles->loadOrInitialize((new RuntimeDetector())->detect());
            $reconciler = new ManagedReconciler($profiles, new ManagedIniRepository($this->config), new ServiceReloader(), new ExtensionInstaller(new ExtensionCatalog()), new RuntimeVerifier(new ApacheRuntimeProbe($this->config)));
            $report = $reconciler->apply();
            if (file_put_contents($this->config->agentLog(), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                throw new \RuntimeException('Unable to record startup reconciliation.');
            }
            if ($report['status'] !== 'success') throw new \RuntimeException('Startup reconciliation did not satisfy the desired profile; inspect agent.log.');
            // The reservation prevents a direct agent from racing reconciliation; the child takes its own lifetime lease.
            $reservation->release();
            $reservation = null;
            $agent = $this->launch([PHP_BINARY, $this->root . '/bin/wsdd-php-admin', 'agent'], $this->config->agentLog());
            $web = $this->launch([PHP_BINARY, $this->root . '/bin/wsdd-php-admin', 'runtime:web'], $this->config->operationLogDir() . '/web.log');
            $this->awaitReady($agent, $web, $timeoutSeconds);
            return ['status' => 'success', 'root' => $this->root, 'agent_pid' => $agent['pid'], 'web_group_pid' => $web['pid'], 'port' => $this->config->serverPort(), 'readiness' => 'verified'];
        } catch (\Exception $error) {
            $this->stopOwnedChildren();
            throw $error;
        } catch (\Throwable $error) {
            $this->stopOwnedChildren();
            throw $error;
        } finally {
            if ($reservation !== null) $reservation->release();
            $startup->release();
        }
    }

    private function awaitReady(array $agent, array $web, $timeoutSeconds)
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $ready = false;
        try {
            do {
                if (!$this->owns($agent) || !$this->owns($web)) throw new \RuntimeException('A runtime child exited before becoming ready.');
                if ($this->agentReady($agent['pid']) && $this->webReady()) {
                    $ready = true;
                    return;
                }
                usleep(100000);
            } while (microtime(true) < $deadline);
            throw new \RuntimeException('Runtime readiness timed out; agent and web were not both verified.');
        } finally {
            if (!$ready) $this->stopOwnedChildren();
        }
    }

    private function prepareDirectories()
    {
        foreach ([$this->config->persistentRoot(), $this->config->operationsDir(), $this->config->operationLogDir(), $this->config->backupDir()] as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) throw new \RuntimeException('Unable to create runtime directory: ' . $directory);
        }
        foreach ([$this->config->persistentRoot(), $this->config->operationLogDir()] as $directory) {
            if (!chown($directory, 'www-data') || !chgrp($directory, 'www-data')) throw new \RuntimeException('Unable to set runtime directory ownership.');
        }
    }

    private function assertPortFree()
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:' . $this->config->serverPort(), $errno, $message);
        if ($socket === false) throw new \RuntimeException('Internal web port is unavailable: ' . $message);
        fclose($socket);
    }

    private function launch(array $command, $log)
    {
        $shell = implode(' ', array_map('escapeshellarg', array_merge([ExecutableFinder::find('nohup'), ExecutableFinder::find('setsid')], $command)));
        $environment = ['WSDD_PHP_ADMIN_START_ID' => $this->startId, 'WSDD_PHP_ADMIN_PROFILE_DIR' => $this->config->persistentRoot(), 'WSDD_PHP_ADMIN_INI_DIR' => $this->config->managedIniDir(), 'WSDD_PHP_ADMIN_INI_PREFIX' => $this->config->managedIniPrefix(), 'WSDD_PHP_ADMIN_LOG_DIR' => $this->config->operationLogDir(), 'WSDD_PHP_ADMIN_AGENT_LOG' => $this->config->agentLog(), 'WSDD_PHP_ADMIN_AUDIT_LOG' => $this->config->auditLog(), 'WSDD_PHP_ADMIN_PORT' => (string) $this->config->serverPort()];
        $result = (new CommandRunner())->run(['/bin/sh', '-c', $shell . ' </dev/null >>' . escapeshellarg($log) . ' 2>&1 & echo $!'], 5, $environment);
        $pid = trim($result['stdout']);
        if ($result['exit_code'] !== 0 || !ctype_digit($pid) || (int) $pid < 2) throw new \RuntimeException('Unable to dispatch runtime child.');
        $child = ['pid' => (int) $pid, 'identity' => $this->identity((int) $pid)];
        $this->children[] = $child;
        $deadline = microtime(true) + 2;
        do {
            if ($this->owns($child)) return $child;
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new \RuntimeException('Runtime child did not enter its isolated process group.');
    }

    private function identity($pid)
    {
        $stat = @file_get_contents('/proc/' . $pid . '/stat');
        if ($stat === false) return null;
        $fields = explode(' ', substr($stat, strrpos($stat, ') ') + 2));
        return isset($fields[19]) ? $fields[19] : null;
    }

    private function owns(array $child)
    {
        $stat = @file_get_contents('/proc/' . $child['pid'] . '/stat');
        if ($stat === false) return false;
        $fields = explode(' ', substr($stat, strrpos($stat, ') ') + 2));
        // PID/start-time and a private process group remain readable after dropping UID.
        return $child['identity'] !== null && isset($fields[0], $fields[2], $fields[19])
            && $fields[0] !== 'Z' && (int) $fields[2] === $child['pid'] && $fields[19] === $child['identity'];
    }

    private function stopOwnedChildren()
    {
        foreach (array_reverse($this->children) as $child) {
            if ($this->owns($child)) posix_kill(-$child['pid'], 15);
        }
        usleep(100000);
        foreach (array_reverse($this->children) as $child) {
            if ($this->owns($child)) posix_kill(-$child['pid'], 9);
        }
    }

    private function agentReady($pid)
    {
        $path = $this->config->persistentRoot() . '/runtime/agent-status.json';
        $status = json_decode((string) @file_get_contents($path), true);
        return is_array($status) && isset($status['pid'], $status['root'], $status['start_id'], $status['state'], $status['updated_at'])
            && $status['pid'] === $pid && $status['root'] === $this->root && $status['start_id'] === $this->startId
            && $status['state'] === 'ready' && abs(microtime(true) - $status['updated_at']) < 10;
    }

    private function webReady()
    {
        $context = stream_context_create(['http' => ['timeout' => 1, 'header' => 'X-WSDD-Runtime-Probe: ' . $this->startId . "\r\n", 'ignore_errors' => true, 'follow_location' => 0]]);
        $body = @file_get_contents('http://127.0.0.1:' . $this->config->serverPort() . '/runtime-health.php', false, $context, 0, 4096);
        $status = $body === false ? null : json_decode($body, true);
        return isset($http_response_header[0]) && preg_match('/^HTTP\/\S+ 200\b/', $http_response_header[0]) === 1
            && is_array($status) && isset($status['root'], $status['start_id'], $status['user'])
            && $status['root'] === $this->root && $status['start_id'] === $this->startId && $status['user'] === 'www-data';
    }

    private function legacyAgentRunning()
    {
        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $path) {
            $args = explode("\0", (string) @file_get_contents($path));
            if (!isset($args[1], $args[2]) || $args[2] !== 'agent' || realpath($args[1]) !== $this->root . '/bin/wsdd-php-admin') continue;
            $env = explode("\0", (string) @file_get_contents(dirname($path) . '/environ'));
            $profile = '/var/lib/wsdd-php-admin';
            foreach ($env as $value) if (strpos($value, 'WSDD_PHP_ADMIN_PROFILE_DIR=') === 0) $profile = substr($value, 27);
            if (realpath($profile) === realpath($this->config->persistentRoot())) return true;
        }
        return false;
    }
}

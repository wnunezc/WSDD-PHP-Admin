<?php

namespace Wsdd\PhpAdmin\Runtime;

use Wsdd\PhpAdmin\Support\AppConfig;
final class RuntimeDetector
{
    /**
     * @return Wsdd\PhpAdmin\Runtime\RuntimeDetection
     */
    public function detect()
    {
        $snapshot = (PHP_SAPI === 'apache2handler') ? RuntimeSnapshot::current() : RuntimeSnapshot::freshCli();
        $observations = ['source' => (PHP_SAPI === 'apache2handler') ? 'apache-request' : 'fresh-cli', 'cli' => null, 'apache' => null, 'errors' => []];
        if (PHP_SAPI === 'cli-server') {
            $observations['cli'] = $snapshot;
            try {
                $snapshot = (new ApacheRuntimeProbe(AppConfig::loadDefault()))->observe();
                $observations['apache'] = $snapshot;
                $observations['source'] = 'apache-probe';
            } catch (\Exception $exception) {
                $observations['errors'][] = $exception->getMessage();
            } catch (\Throwable $exception) {
                $observations['errors'][] = $exception->getMessage();
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new RuntimeDetection($this->detectPhpBinary(), $snapshot['version'], $snapshot['sapi'], $snapshot['runtime'], isset($snapshot['ini_file']) ? $snapshot['ini_file'] : null, isset($snapshot['ini_scan_dir']) ? $snapshot['ini_scan_dir'] : null, $snapshot['extensions'], PHP_OS_FAMILY, $this->detectEffectiveUser(), $this->detectTools(['docker-php-ext-install', 'docker-php-ext-enable', 'pecl', 'apache2ctl', 'apachectl', 'php-fpm']), $this->detectTools(['apt-get', 'apk', 'dnf', 'yum']), $observations), 'Wsdd\PhpAdmin\Runtime\RuntimeDetection');
    }
    /**
     * @return string
     */
    private function detectPhpBinary()
    {
        if (PHP_BINARY !== '') {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(PHP_BINARY, 'string');
        }
        $path = $this->commandPath('php');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(isset($path) ? $path : '', 'string');
    }
    /**
     * @return string|null
     */
    private function detectEffectiveUser()
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $user = posix_getpwuid(posix_geteuid());
            if (is_array($user) && isset($user['name']) && is_string($user['name'])) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($user['name'], '?string');
            }
        }
        $user = getenv('USER') ?: getenv('USERNAME');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->emptyStringToNull($user), '?string');
    }
    /**
     * @param list<string> $commands
     * @return array<string, bool>
     */
    private function detectTools(array $commands)
    {
        $tools = [];
        foreach ($commands as $command) {
            $tools[$command] = $this->commandExists($command);
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($tools, 'array');
    }
    /**
     * @param string $command
     * @return bool
     */
    private function commandExists($command)
    {
        $command = \Wsdd\PhpAdmin\Support\TypeGuard::check($command, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->commandPath($command) !== null, 'bool');
    }
    /**
     * @param string $command
     * @return string|null
     */
    private function commandPath($command)
    {
        $command = \Wsdd\PhpAdmin\Support\TypeGuard::check($command, 'string');
        return \Wsdd\PhpAdmin\Support\ExecutableFinder::find($command);
    }
    /** @param string|false|null $value  * @return string|null
     */
    private function emptyStringToNull($value)
    {
        if ($value === false || $value === null || trim($value) === '') {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?string');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($value, '?string');
    }
}

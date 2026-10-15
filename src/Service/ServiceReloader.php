<?php

namespace Wsdd\PhpAdmin\Service;

final class ServiceReloader
{
    /**
     * @return array<string, mixed>
     */
    public function gracefulReload()
    {
        $command = $this->firstAvailableCommand(['apache2ctl', 'apachectl']);
        if ($command === null) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['attempted' => false, 'command' => null, 'status' => 'skipped', 'exit_code' => null, 'message' => 'No supported Apache control command is available.'], 'array');
        }
        $result = $this->run([$command, 'graceful']);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['attempted' => true, 'command' => basename($command) . ' graceful', 'status' => ($result['exit_code'] === 0) ? 'success' : 'failed', 'exit_code' => $result['exit_code'], 'message' => ($result['output'] === '') ? 'Apache graceful reload completed.' : $result['output']], 'array');
    }
    /**
     * @param list<string> $commands
     * @return string|null
     */
    private function firstAvailableCommand(array $commands)
    {
        foreach ($commands as $command) {
            $path = $this->commandPath($command);
            if ($path !== null) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($path, '?string');
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?string');
    }
    /**
     * @param string $command
     * @return string|null
     */
    private function commandPath($command)
    {
        $command = \Wsdd\PhpAdmin\Support\TypeGuard::check($command, 'string');
        if (PHP_OS_FAMILY === 'Windows') {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?string');
        }
        if (!in_array($command, ['apache2ctl', 'apachectl'], true)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?string');
        }
        $output = [];
        $exitCode = 1;
        exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0 || !isset($output[0]) || trim($output[0]) === '') {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?string');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(trim($output[0]), '?string');
    }
    /**
     * @param list<string> $command
     * @return array{exit_code:int, output:string}
     */
    private function run(array $command)
    {
        $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = \Wsdd\PhpAdmin\Support\Process::open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['exit_code' => 127, 'output' => 'Unable to start Apache control command.'], 'array');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $output = trim((string) $stdout . "\n" . (string) $stderr);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['exit_code' => is_int($exitCode) ? $exitCode : 1, 'output' => $output], 'array');
    }
}

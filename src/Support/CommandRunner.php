<?php

namespace Wsdd\PhpAdmin\Support;

final class CommandRunner
{
    /** @param list<string> $command  * @param float $timeoutSeconds
     * @param array|null $environment
     * @return array
     */
    public function run(array $command, $timeoutSeconds = 10, $environment = null)
    {
        $timeoutSeconds = \Wsdd\PhpAdmin\Support\TypeGuard::check($timeoutSeconds, 'float');
        $environment = \Wsdd\PhpAdmin\Support\TypeGuard::check($environment, '?array');
        $process = Process::open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $environment);
        if (!is_resource($process)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['exit_code' => 127, 'stdout' => '', 'stderr' => 'Unable to start command.'], 'array');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = [1 => '', 2 => ''];
        $deadline = microtime(true) + $timeoutSeconds;
        try {
            do {
                foreach ($output as $index => $value) {
                    $output[$index] = substr($value . stream_get_contents($pipes[$index]), -1048576);
                }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    foreach ($output as $index => $value) {
                        $output[$index] = substr($value . stream_get_contents($pipes[$index]), -1048576);
                    }
                    return \Wsdd\PhpAdmin\Support\TypeGuard::check(['exit_code' => $status['exitcode'], 'stdout' => $output[1], 'stderr' => $output[2]], 'array');
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['exit_code' => 124, 'stdout' => $output[1], 'stderr' => $output[2] . "\nCommand timed out."], 'array');
        } finally {
            if (isset($status['running']) ? $status['running'] : true) {
                proc_terminate($process);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }
}

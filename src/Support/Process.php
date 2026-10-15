<?php

namespace Wsdd\PhpAdmin\Support;

final class Process
{
    /** Opens only an argument vector; environment values override inherited values. */
    public static function open(array $command, array $descriptors, &$pipes, $environment = null)
    {
        if ($command === array()) throw new \InvalidArgumentException('An executable is required.');
        foreach ($command as $argument) {
            if (!is_string($argument) || strpos($argument, "\0") !== false) throw new \InvalidArgumentException('Invalid process argument.');
        }
        if ($command[0] === '') throw new \InvalidArgumentException('An executable is required.');
        $previous = array();
        try {
            foreach ($environment === null ? array() : $environment as $name => $value) {
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) || !is_string($value) || strpos($value, "\0") !== false) throw new \InvalidArgumentException('Invalid process environment.');
                $previous[$name] = getenv($name);
                putenv($name . '=' . $value);
            }
            if (PHP_VERSION_ID >= 70400) {
                return proc_open($command, $descriptors, $pipes);
            }
            if (PHP_OS_FAMILY === 'Windows') throw new \RuntimeException('Legacy process execution requires the supported Linux container runtime.');
            return proc_open('exec ' . implode(' ', array_map('escapeshellarg', $command)), $descriptors, $pipes);
        } finally {
            foreach ($previous as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
        }
    }
}

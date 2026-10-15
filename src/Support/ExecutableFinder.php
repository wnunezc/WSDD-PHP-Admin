<?php

namespace Wsdd\PhpAdmin\Support;

final class ExecutableFinder
{
    public static function find($command)
    {
        if (!is_string($command) || !preg_match('/^[a-zA-Z0-9_.-]+$/D', $command)) return null;
        if (PHP_OS_FAMILY === 'Windows') {
            $output = array();
            exec('where ' . escapeshellarg($command) . ' 2>NUL', $output, $exit);
            return $exit === 0 && isset($output[0]) ? trim($output[0]) : null;
        }
        $directories = array_merge(explode(PATH_SEPARATOR, (string) getenv('PATH')), array(PHP_BINDIR, '/usr/local/bin', '/usr/local/sbin', '/usr/bin', '/usr/sbin', '/bin', '/sbin'));
        foreach (array_unique($directories) as $directory) {
            if ($directory === '' || $directory[0] !== '/') continue;
            $path = rtrim($directory, '/') . '/' . $command;
            if (is_file($path) && is_executable($path)) return $path;
        }
        return null;
    }
}

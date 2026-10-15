<?php

namespace Wsdd\PhpAdmin\Support;

final class JsonFile
{
    /**
     * @param array<string, mixed> $payload
     * @param string $path
     * @param int $mode
     * @return void
     */
    public static function writeAtomic($path, array $payload, $mode = 0644)
    {
        $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
        $mode = \Wsdd\PhpAdmin\Support\TypeGuard::check($mode, 'int');
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s".', $directory));
        }
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode JSON document.');
        }
        $temporary = tempnam($directory, '.tmp-json-');
        if ($temporary === false) {
            throw new \RuntimeException(sprintf('Unable to create temporary file in "%s".', $directory));
        }
        try {
            if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
                throw new \RuntimeException(sprintf('Unable to write temporary file "%s".', $temporary));
            }
            chmod($temporary, $mode);
            if (!rename($temporary, $path)) {
                throw new \RuntimeException(sprintf('Unable to move "%s" to "%s".', $temporary, $path));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}

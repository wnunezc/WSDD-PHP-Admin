<?php

namespace Wsdd\PhpAdmin\Support;

/** A kernel-owned lease: never unlink a lock file while another process may hold it. */
final class InstanceLock
{
    private $stream;

    public function __construct($path)
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create runtime lock directory.');
        }
        $stream = @fopen($path, 'c+e');
        if ($stream === false) throw new \RuntimeException('Unable to open runtime lock: ' . $path);
        if (!flock($stream, LOCK_EX | LOCK_NB)) {
            fclose($stream);
            throw new \RuntimeException('Runtime instance is already active or starting: ' . $path);
        }
        $this->stream = $stream;
    }

    public function release()
    {
        if (is_resource($this->stream)) {
            flock($this->stream, LOCK_UN);
            fclose($this->stream);
        }
        $this->stream = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}

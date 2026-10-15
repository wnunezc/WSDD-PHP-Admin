<?php

namespace Wsdd\PhpAdmin\Runtime;

use Wsdd\PhpAdmin\Support\AppConfig;
final class ApacheRuntimeProbe
{
    /**
     * @var \Wsdd\PhpAdmin\Support\AppConfig
     */
    private $config;
    public function __construct(AppConfig $config)
    {
        $this->config = $config;
    }
    /**
     * @return string
     */
    public function key()
    {
        $directory = $this->config->persistentRoot() . '/runtime';
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create runtime probe directory.');
        }
        $path = $directory . '/probe.key';
        $file = @fopen($path, 'x');
        if ($file !== false) {
            try {
                chmod($path, 0640);
                if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                    chgrp($directory, 'www-data');
                    chgrp($path, 'www-data');
                }
                if (fwrite($file, bin2hex(random_bytes(32))) !== 64) {
                    throw new \RuntimeException('Unable to write runtime probe key.');
                }
            } finally {
                fclose($file);
            }
        }
        $key = @file_get_contents($path);
        if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) {
            throw new \RuntimeException('Runtime probe key is missing or invalid.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($key, 'string');
    }
    /**
     * @return array
     */
    public function observe()
    {
        $root = dirname(dirname(__DIR__));
        $host = getenv('WSDD_PHP_ADMIN_APACHE_PROBE_HOST') ?: basename($root);
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z0-9.-]+$/D', $host) !== 1) {
            throw new \RuntimeException('Apache probe host must identify the direct application vhost.');
        }
        $nonce = bin2hex(random_bytes(16));
        $context = stream_context_create(['http' => ['method' => 'GET', 'header' => "Host: {$host}\r\nConnection: close\r\nX-WSDD-Probe-Key: " . $this->key() . "\r\n", 'timeout' => 2, 'follow_location' => 0, 'ignore_errors' => true]]);
        $response = @file_get_contents('http://127.0.0.1/runtime-probe.php?nonce=' . $nonce, false, $context);
        if ($response === false) {
            throw new \RuntimeException('Apache runtime observation is unavailable.');
        }
        if (!isset($http_response_header[0]) || preg_match('/^HTTP\/\S+ 200\b/', $http_response_header[0]) !== 1) {
            throw new \RuntimeException('Apache runtime observation failed: ' . (isset($http_response_header[0]) ? $http_response_header[0] : 'no HTTP status'));
        }
        $snapshot = RuntimeSnapshot::decode($response);
        if ((isset($snapshot['nonce']) ? $snapshot['nonce'] : null) !== $nonce || (isset($snapshot['app_root']) ? $snapshot['app_root'] : null) !== realpath($root) || $snapshot['sapi'] !== 'apache2handler') {
            throw new \RuntimeException('Apache probe did not return the selected application runtime.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($snapshot, 'array');
    }
}

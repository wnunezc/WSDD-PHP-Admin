<?php

namespace Wsdd\PhpAdmin\Runtime;

final class RuntimeSnapshot
{
    /**
     * @return array
     */
    public static function current()
    {
        $extensions = get_loaded_extensions();
        sort($extensions, SORT_NATURAL | SORT_FLAG_CASE);
        $files = php_ini_scanned_files();
        $scanDir = getenv('PHP_INI_SCAN_DIR');
        $runtimes = ['apache2handler' => 'apache', 'fpm-fcgi' => 'fpm'];
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['version' => PHP_VERSION, 'sapi' => PHP_SAPI, 'runtime' => isset($runtimes[PHP_SAPI]) ? $runtimes[PHP_SAPI] : PHP_SAPI, 'ini_file' => php_ini_loaded_file() ?: null, 'ini_scan_dir' => ($scanDir !== false && $scanDir !== '') ? $scanDir : ($files ? dirname(trim(explode(',', $files)[0])) : (defined('PHP_CONFIG_FILE_SCAN_DIR') ? PHP_CONFIG_FILE_SCAN_DIR : null)), 'extensions' => $extensions, 'observed_at' => gmdate('c')], 'array');
    }
    /**
     * @param string $json
     * @return array
     */
    public static function decode($json)
    {
        $json = \Wsdd\PhpAdmin\Support\TypeGuard::check($json, 'string');
        $snapshot = \Wsdd\PhpAdmin\Support\JsonCodec::decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(json_last_error_msg());
        }
        if (!is_array($snapshot) || !is_string(isset($snapshot['version']) ? $snapshot['version'] : null) || !is_string(isset($snapshot['sapi']) ? $snapshot['sapi'] : null) || !is_string(isset($snapshot['runtime']) ? $snapshot['runtime'] : null) || !is_array(isset($snapshot['extensions']) ? $snapshot['extensions'] : null) || array_filter($snapshot['extensions'], static function ($name) {
            return !is_string($name);
        }) !== []) {
            throw new \RuntimeException('Invalid runtime snapshot.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($snapshot, 'array');
    }
    /**
     * @return array
     */
    public static function freshCli()
    {
        $binary = PHP_SAPI === 'apache2handler' ? PHP_BINDIR . '/php' : PHP_BINARY;
        $code = 'require ' . var_export(dirname(__DIR__) . '/Support/bootstrap.php', true)
            . '; echo \\Wsdd\\PhpAdmin\\Support\\JsonCodec::encode(\\Wsdd\\PhpAdmin\\Runtime\\RuntimeSnapshot::current());';
        $result = (new \Wsdd\PhpAdmin\Support\CommandRunner())->run([$binary, '-r', $code], 5);
        if ($result['exit_code'] !== 0 || trim($result['stderr']) !== '') {
            throw new \RuntimeException('Fresh PHP observation failed: ' . trim($result['stderr'] . "\n" . $result['stdout']));
        }
        return self::decode($result['stdout']);
    }
}

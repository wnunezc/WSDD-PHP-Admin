<?php

if (!class_exists('JsonException', false)) {
    class JsonException extends Exception {}
}

if (!defined('PHP_OS_FAMILY')) {
    define('PHP_OS_FAMILY', DIRECTORY_SEPARATOR === '\\' ? 'Windows' : (PHP_OS === 'Darwin' ? 'Darwin' : (stripos(PHP_OS, 'BSD') !== false ? 'BSD' : (stripos(PHP_OS, 'Linux') !== false ? 'Linux' : 'Unknown'))));
}

if (!function_exists('random_bytes')) {
    function random_bytes($length)
    {
        if (!is_int($length) || $length < 1) throw new InvalidArgumentException('Random byte length must be a positive integer.');
        if (!function_exists('openssl_random_pseudo_bytes')) throw new RuntimeException('A cryptographically secure random source is required.');
        $strong = false;
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if (!$strong || !is_string($bytes) || strlen($bytes) !== $length) throw new RuntimeException('Cryptographic random generation failed.');
        return $bytes;
    }
}

if (!function_exists('str_contains')) {
    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    function str_contains($haystack, $needle)
    {
        $haystack = \Wsdd\PhpAdmin\Support\TypeGuard::check($haystack, 'string');
        $needle = \Wsdd\PhpAdmin\Support\TypeGuard::check($needle, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($needle === '' || strpos($haystack, $needle) !== false, 'bool');
    }
}
if (!function_exists('str_starts_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    function str_starts_with($haystack, $needle)
    {
        $haystack = \Wsdd\PhpAdmin\Support\TypeGuard::check($haystack, 'string');
        $needle = \Wsdd\PhpAdmin\Support\TypeGuard::check($needle, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0, 'bool');
    }
}
if (!function_exists('str_ends_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    function str_ends_with($haystack, $needle)
    {
        $haystack = \Wsdd\PhpAdmin\Support\TypeGuard::check($haystack, 'string');
        $needle = \Wsdd\PhpAdmin\Support\TypeGuard::check($needle, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($needle === '' || (string) substr($haystack, -strlen($needle)) === $needle, 'bool');
    }
}

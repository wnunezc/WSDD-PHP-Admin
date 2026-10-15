<?php

require_once __DIR__ . '/TypeGuard.php';
require_once __DIR__ . '/compatibility.php';
spl_autoload_register(static function ($class) {
    $class = \Wsdd\PhpAdmin\Support\TypeGuard::check($class, 'string');
    $prefix = 'Wsdd\PhpAdmin\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relativeClass = (string) substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

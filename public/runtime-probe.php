<?php

use Wsdd\PhpAdmin\Runtime\RuntimeSnapshot;
use Wsdd\PhpAdmin\Support\AppConfig;
require dirname(__DIR__) . '/src/Support/bootstrap.php';
header('Cache-Control: no-store');
$key = @file_get_contents(AppConfig::loadDefault()->persistentRoot() . '/runtime/probe.key');
$sent = isset($_SERVER['HTTP_X_WSDD_PROBE_KEY']) ? $_SERVER['HTTP_X_WSDD_PROBE_KEY'] : '';
$nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
if (PHP_SAPI !== 'apache2handler' || !in_array(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', ['127.0.0.1', '::1'], true) || !is_string($key) || strlen($key) !== 64 || !is_string($sent) || !hash_equals($key, $sent) || !is_string($nonce) || preg_match('/^[a-f0-9]{32}$/D', $nonce) !== 1) {
    http_response_code(403);
    exit;
}
header('Content-Type: application/json');
echo \Wsdd\PhpAdmin\Support\JsonCodec::encode(RuntimeSnapshot::current() + ['nonce' => $nonce, 'app_root' => realpath(dirname(__DIR__))]);

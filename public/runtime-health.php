<?php

require dirname(__DIR__) . '/src/Support/bootstrap.php';

$expected = getenv('WSDD_PHP_ADMIN_START_ID');
$sent = isset($_SERVER['HTTP_X_WSDD_RUNTIME_PROBE']) ? $_SERVER['HTTP_X_WSDD_RUNTIME_PROBE'] : '';
header('Cache-Control: no-store');
if (!in_array(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', ['127.0.0.1', '::1'], true)
    || !is_string($expected) || preg_match('/^[a-f0-9]{64}$/D', $expected) !== 1
    || !is_string($sent) || !hash_equals($expected, $sent)) {
    http_response_code(403);
    exit;
}
header('Content-Type: application/json');
echo json_encode(['root' => realpath(dirname(__DIR__)), 'pid' => getmypid(), 'version' => PHP_VERSION, 'start_id' => $expected, 'user' => \Wsdd\PhpAdmin\Support\ProcessIdentity::effectiveUser()]);

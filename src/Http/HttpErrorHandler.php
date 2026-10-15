<?php

namespace Wsdd\PhpAdmin\Http;

final class HttpErrorHandler
{
    public static function register()
    {
        set_exception_handler(array(__CLASS__, 'render'));
    }

    public static function render($error)
    {
        error_log('WSDD-PHP-Admin request failed: ' . $error->getMessage());
        http_response_code(503);
        header('Cache-Control: no-store');
        if (isset($_GET['api'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => 'Runtime temporarily unavailable. Inspect the agent and web logs.'));
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Runtime unavailable</title><h1>Runtime unavailable</h1><p>Inspect the agent and web logs.</p></html>';
        }
    }
}

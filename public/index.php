<?php

use Wsdd\PhpAdmin\Http\Application;
require dirname(__DIR__) . '/src/Support/bootstrap.php';
\Wsdd\PhpAdmin\Http\HttpErrorHandler::register();
$app = new Application();
$app->handle();

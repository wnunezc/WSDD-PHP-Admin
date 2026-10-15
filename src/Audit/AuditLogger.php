<?php

namespace Wsdd\PhpAdmin\Audit;

use Wsdd\PhpAdmin\Support\AppConfig;
final class AuditLogger
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
     * @param array<string, mixed> $context
     * @param string $actor
     * @param string $action
     * @param string $subject
     * @param string $status
     * @return void
     */
    public function record($actor, $action, $subject, $status, array $context = [])
    {
        $actor = \Wsdd\PhpAdmin\Support\TypeGuard::check($actor, 'string');
        $action = \Wsdd\PhpAdmin\Support\TypeGuard::check($action, 'string');
        $subject = \Wsdd\PhpAdmin\Support\TypeGuard::check($subject, 'string');
        $status = \Wsdd\PhpAdmin\Support\TypeGuard::check($status, 'string');
        $path = $this->config->auditLog();
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }
        $entry = ['at' => gmdate('c'), 'actor' => $actor, 'action' => $action, 'subject' => $subject, 'status' => $status, 'context' => ($context === []) ? new \stdClass() : $context];
        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return;
        }
        @file_put_contents($path, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

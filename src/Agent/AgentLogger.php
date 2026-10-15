<?php

namespace Wsdd\PhpAdmin\Agent;

use Wsdd\PhpAdmin\Support\AppConfig;
final class AgentLogger
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
     * @param string $message
     * @return void
     */
    public function info($message)
    {
        $message = \Wsdd\PhpAdmin\Support\TypeGuard::check($message, 'string');
        $this->write('info', $message);
    }
    /**
     * @param string $message
     * @return void
     */
    public function error($message)
    {
        $message = \Wsdd\PhpAdmin\Support\TypeGuard::check($message, 'string');
        $this->write('error', $message);
    }
    /**
     * @param string $level
     * @param string $message
     * @return void
     */
    private function write($level, $message)
    {
        $level = \Wsdd\PhpAdmin\Support\TypeGuard::check($level, 'string');
        $message = \Wsdd\PhpAdmin\Support\TypeGuard::check($message, 'string');
        $path = $this->config->agentLog();
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }
        $line = sprintf("[%s] %s %s\n", gmdate('c'), strtoupper($level), $message);
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}

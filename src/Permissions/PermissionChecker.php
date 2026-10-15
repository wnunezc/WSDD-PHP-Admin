<?php

namespace Wsdd\PhpAdmin\Permissions;

use Wsdd\PhpAdmin\Support\AppConfig;
use Wsdd\PhpAdmin\Support\ProcessIdentity;
final class PermissionChecker
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
     * @return Wsdd\PhpAdmin\Permissions\PermissionReport
     */
    public function check()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new PermissionReport([$this->inspect('Persistent profile root', $this->config->persistentRoot()), $this->inspect('Profile file', $this->config->profilePath()), $this->inspect('Operations directory', $this->config->operationsDir()), $this->inspect('Managed INI directory', $this->config->managedIniDir()), $this->inspect('Logs directory', $this->config->operationLogDir()), $this->inspect('Agent log file', $this->config->agentLog()), $this->inspect('Audit log file', $this->config->auditLog())], ProcessIdentity::isRoot(), is_file(dirname(dirname(__DIR__)) . '/bin/wsdd-php-admin')), 'Wsdd\PhpAdmin\Permissions\PermissionReport');
    }
    /**
     * @param string $label
     * @param string $path
     * @return Wsdd\PhpAdmin\Permissions\PathPermission
     */
    private function inspect($label, $path)
    {
        $label = \Wsdd\PhpAdmin\Support\TypeGuard::check($label, 'string');
        $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
        $exists = file_exists($path);
        $parent = dirname($path);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new PathPermission($label, $path, $exists, $exists && is_dir($path), $exists && is_file($path), $exists && is_readable($path), $exists && is_writable($path), is_dir($parent) && is_writable($parent)), 'Wsdd\PhpAdmin\Permissions\PathPermission');
    }
}

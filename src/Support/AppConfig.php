<?php

namespace Wsdd\PhpAdmin\Support;

final class AppConfig
{
    /**
     * @var mixed[]
     */
    private $values;
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }
    /**
     * @return Wsdd\PhpAdmin\Support\AppConfig
     */
    public static function loadDefault()
    {
        $path = dirname(dirname(__DIR__)) . '/config/wsdd-php-admin.php';
        $values = require $path;
        if (!is_array($values)) {
            throw new \RuntimeException('Invalid application configuration.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new self($values), 'Wsdd\PhpAdmin\Support\AppConfig');
    }
    /**
     * @return string
     */
    public function persistentRoot()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->string('paths.persistent_root'), 'string');
    }

    public function serverPort()
    {
        $port = $this->get('server.port');
        if ($port === null) $port = 8989;
        if (!ctype_digit((string) $port) || (int) $port < 1 || (int) $port > 65535) {
            throw new \RuntimeException('Invalid internal server port.');
        }
        return (int) $port;
    }
    /**
     * @return string
     */
    public function managedIniDir()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->string('paths.managed_ini_dir'), 'string');
    }
    /**
     * @return string
     */
    public function managedIniPrefix()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->string('paths.managed_ini_prefix'), 'string');
    }
    /**
     * @return string
     */
    public function operationLogDir()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->string('paths.operation_log_dir'), 'string');
    }
    /**
     * @return string
     */
    public function operationsDir()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->joinPath($this->persistentRoot(), 'operations'), 'string');
    }
    /**
     * @return string
     */
    public function agentLog()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->string('paths.agent_log'), 'string');
    }
    /**
     * @return string
     */
    public function auditLog()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->string('paths.audit_log'), 'string');
    }
    /**
     * @return string
     */
    public function backupDir()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->joinPath($this->persistentRoot(), 'backups'), 'string');
    }
    /**
     * @return string
     */
    public function authFile()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->joinPath($this->persistentRoot(), 'auth.json'), 'string');
    }
    /**
     * @return string
     */
    public function defaultUsername()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->string('security.default_username'), 'string');
    }
    /**
     * @return string
     */
    public function defaultPassword()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->string('security.default_password'), 'string');
    }
    /**
     * @return string
     */
    public function sessionName()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->string('security.session_name'), 'string');
    }
    /**
     * @return string
     */
    public function profilePath()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->joinPath($this->persistentRoot(), 'profile.json'), 'string');
    }
    /**
     * @param string $root
     * @param string $leaf
     * @return string
     */
    private function joinPath($root, $leaf)
    {
        $root = \Wsdd\PhpAdmin\Support\TypeGuard::check($root, 'string');
        $leaf = \Wsdd\PhpAdmin\Support\TypeGuard::check($leaf, 'string');
        $separator = (strpos($root, '/') !== false && strpos($root, '\\') === false) ? '/' : DIRECTORY_SEPARATOR;
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(rtrim($root, '/\\') . $separator . ltrim($leaf, '/\\'), 'string');
    }
    /**
     * @param string $key
     * @return string
     */
    private function string($key)
    {
        $key = \Wsdd\PhpAdmin\Support\TypeGuard::check($key, 'string');
        $value = $this->get($key);
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException(sprintf('Missing string configuration value "%s".', $key));
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($value, 'string');
    }
    /**
     * @param string $key
     * @return string
     */
    private function optionalString($key)
    {
        $key = \Wsdd\PhpAdmin\Support\TypeGuard::check($key, 'string');
        $value = $this->get($key);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(is_string($value) ? $value : '', 'string');
    }
    /**
     * @param string $key
     */
    private function get($key)
    {
        $key = \Wsdd\PhpAdmin\Support\TypeGuard::check($key, 'string');
        $current = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return $current;
    }
}

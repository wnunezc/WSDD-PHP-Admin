<?php

namespace Wsdd\PhpAdmin\Security;

use Wsdd\PhpAdmin\Support\AppConfig;
use Wsdd\PhpAdmin\Support\JsonFile;
final class CredentialStore
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
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function verify($username, $password)
    {
        $username = \Wsdd\PhpAdmin\Support\TypeGuard::check($username, 'string');
        $password = \Wsdd\PhpAdmin\Support\TypeGuard::check($password, 'string');
        $credentials = $this->credentials();
        if (!hash_equals($credentials['username'], $username)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        if ($credentials['default']) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(hash_equals($this->config->defaultPassword(), $password), 'bool');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(password_verify($password, $credentials['password_hash']), 'bool');
    }
    /**
     * @return bool
     */
    public function mustChangePassword()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->credentials()['default'], 'bool');
    }
    /**
     * @return string
     */
    public function username()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->credentials()['username'], 'string');
    }
    /**
     * @param string $username
     * @param string $password
     * @return void
     */
    public function updatePassword($username, $password)
    {
        $username = \Wsdd\PhpAdmin\Support\TypeGuard::check($username, 'string');
        $password = \Wsdd\PhpAdmin\Support\TypeGuard::check($password, 'string');
        $username = trim($username);
        if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $username)) {
            throw new \InvalidArgumentException('Username must be 3-64 characters and use only letters, numbers, dot, dash or underscore.');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }
        $directory = dirname($this->config->authFile());
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create auth directory "%s".', $directory));
        }
        JsonFile::writeAtomic($this->config->authFile(), ['schema_version' => 1, 'username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'password_changed_at' => gmdate('c')], 0640);
    }
    /**
     * @return array{username:string, password_hash:string, default:bool}
     */
    private function credentials()
    {
        $path = $this->config->authFile();
        if (!is_file($path)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(['username' => $this->config->defaultUsername(), 'password_hash' => '', 'default' => true], 'array');
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !is_string(isset($data['username']) ? $data['username'] : null) || !is_string(isset($data['password_hash']) ? $data['password_hash'] : null)) {
            throw new \RuntimeException('Auth file is invalid.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['username' => $data['username'], 'password_hash' => $data['password_hash'], 'default' => false], 'array');
    }
}

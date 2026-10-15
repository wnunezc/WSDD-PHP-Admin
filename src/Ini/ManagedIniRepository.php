<?php

namespace Wsdd\PhpAdmin\Ini;

use Wsdd\PhpAdmin\Extensions\ExtensionName;
use Wsdd\PhpAdmin\Support\AppConfig;
final class ManagedIniRepository
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
     * @return bool
     */
    public function canWriteDirectory()
    {
        $directory = $this->config->managedIniDir();
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(is_dir($directory) && is_writable($directory), 'bool');
    }
    /**
     * @param string $extension
     * @return bool
     */
    public function isExtensionAvailable($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        if ($this->isExtensionLoadedByCurrentConfiguration($extension)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(true, 'bool');
        }
        $extensionDir = ini_get('extension_dir');
        if (!is_string($extensionDir) || $extensionDir === '') {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        foreach ([$extension . '.so', $extension . '.dll'] as $fileName) {
            if (is_file($this->joinPath($extensionDir, $fileName))) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check(true, 'bool');
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
    }
    /**
     * @param string $extension
     * @return bool
     */
    public function isExtensionLoadedByCurrentConfiguration($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = \Wsdd\PhpAdmin\Support\Process::open([PHP_BINARY, '-m'], $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        $stdout = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        $extension = strtolower($extension);
        $lines = preg_split('/\R/', strtolower((string) $stdout)) ?: [];
        foreach ($lines as $line) {
            if (ExtensionName::canonical($line) === $extension) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check(true, 'bool');
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
    }
    /**
     * @param string $extension
     * @return string
     */
    public function enableExtension($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        if (!$this->isExtensionAvailable($extension)) {
            throw new \RuntimeException(sprintf('Extension "%s" is not available in the current PHP installation.', $extension));
        }
        $path = $this->managedIniPath($extension);
        $this->writeAtomic($path, $this->iniDirective($extension));
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
    }
    /**
     * @param string $extension
     * @return bool
     */
    public function hasManagedIni($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(is_file($this->managedIniPath($extension)), 'bool');
    }
    /**
     * @param string $extension
     * @return bool
     */
    public function hasAnyIniDirectiveForExtension($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        $directory = $this->config->managedIniDir();
        if (!is_dir($directory)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        $files = glob($this->joinPath($directory, '*.ini'));
        if ($files === false) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        $extension = strtolower($extension);
        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }
            $content = strtolower((string) file_get_contents($file));
            if (preg_match('/^\s*(zend_)?extension\s*=\s*"?[^"\r\n;]*' . preg_quote($extension, '/') . '(\.(so|dll))?"?\s*($|;)/mi', $content) === 1) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check(true, 'bool');
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
    }
    /**
     * @param string $extension
     * @return string|null
     */
    public function adoptDockerGeneratedIni($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        $source = $this->dockerGeneratedIniPath($extension);
        if (!is_file($source)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?string');
        }
        $content = file_get_contents($source);
        if ($content === false || trim($content) === '') {
            $content = $this->iniDirective(strtolower($extension));
        }
        $target = $this->replaceManagedIni($extension, $content);
        if (!unlink($source)) {
            throw new \RuntimeException(sprintf('Unable to remove generated Docker INI file "%s" after adopting it.', $source));
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($target, '?string');
    }
    /**
     * @param string $extension
     * @return string|null
     */
    public function disableExtension($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->removeManagedIni($extension), '?string');
    }
    /**
     * @param string $extension
     * @param string $content
     * @return string
     */
    public function replaceManagedIni($extension, $content)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $content = \Wsdd\PhpAdmin\Support\TypeGuard::check($content, 'string');
        $this->assertSafeExtension($extension);
        $path = $this->managedIniPath($extension);
        $this->writeAtomic($path, $content);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
    }
    /**
     * @param string $extension
     * @return string|null
     */
    public function removeManagedIni($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        $path = $this->managedIniPath($extension);
        if (!is_file($path)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?string');
        }
        if (!unlink($path)) {
            throw new \RuntimeException(sprintf('Unable to remove managed INI file "%s".', $path));
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($path, '?string');
    }
    /**
     * @param string $extension
     * @return string
     */
    public function managedIniPath($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        $directory = $this->config->managedIniDir();
        $prefix = $this->config->managedIniPrefix();
        if ($prefix === '' || strpos($prefix, '/') !== false || strpos($prefix, '\\') !== false) {
            throw new \RuntimeException('Managed INI prefix must be a non-empty filename prefix.');
        }
        $fileName = $prefix . strtolower($extension) . '.ini';
        if (strncmp($fileName, $prefix, strlen($prefix)) !== 0 || strpos($fileName, '..') !== false) {
            throw new \RuntimeException('Managed INI filename failed safety validation.');
        }
        $base = realpath($directory);
        if ($base === false || !is_dir($base)) {
            throw new \RuntimeException(sprintf('Managed INI directory "%s" does not exist.', $directory));
        }
        $path = $this->joinPath($base, $fileName);
        $normalizedBase = rtrim($this->normalize($base), '/') . '/';
        $normalizedPath = $this->normalize($path);
        if (strncmp($normalizedPath, $normalizedBase, strlen($normalizedBase)) !== 0) {
            throw new \RuntimeException('Managed INI target escaped the configured directory.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
    }
    /**
     * @param string $extension
     * @return string
     */
    private function dockerGeneratedIniPath($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $this->assertSafeExtension($extension);
        $directory = $this->config->managedIniDir();
        $base = realpath($directory);
        if ($base === false || !is_dir($base)) {
            throw new \RuntimeException(sprintf('Managed INI directory "%s" does not exist.', $directory));
        }
        $fileName = 'docker-php-ext-' . strtolower($extension) . '.ini';
        $path = $this->joinPath($base, $fileName);
        $normalizedBase = rtrim($this->normalize($base), '/') . '/';
        $normalizedPath = $this->normalize($path);
        if (strncmp($normalizedPath, $normalizedBase, strlen($normalizedBase)) !== 0) {
            throw new \RuntimeException('Generated Docker INI path escaped the configured directory.');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
    }
    /**
     * @param string $path
     * @param string $content
     * @return void
     */
    private function writeAtomic($path, $content)
    {
        $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
        $content = \Wsdd\PhpAdmin\Support\TypeGuard::check($content, 'string');
        $directory = dirname($path);
        $temporary = tempnam($directory, '.tmp-ini-');
        if ($temporary === false) {
            throw new \RuntimeException(sprintf('Unable to create temporary INI file in "%s".', $directory));
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false) {
                throw new \RuntimeException(sprintf('Unable to write temporary INI file "%s".', $temporary));
            }
            chmod($temporary, 0644);
            $this->setRootOwnerWhenApplicable($temporary);
            if (!rename($temporary, $path)) {
                throw new \RuntimeException(sprintf('Unable to move "%s" to "%s".', $temporary, $path));
            }
            chmod($path, 0644);
            $this->setRootOwnerWhenApplicable($path);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
    /**
     * @param string $path
     * @return void
     */
    private function setRootOwnerWhenApplicable($path)
    {
        $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return;
        }
        if (function_exists('chown')) {
            @chown($path, 'root');
        }
        if (function_exists('chgrp')) {
            @chgrp($path, 'root');
        }
    }
    /**
     * @param string $extension
     * @return void
     */
    private function assertSafeExtension($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        if (!ExtensionName::isValid($extension)) {
            throw new \InvalidArgumentException('Extension name is not safe.');
        }
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
     * @param string $path
     * @return string
     */
    private function normalize($path)
    {
        $path = \Wsdd\PhpAdmin\Support\TypeGuard::check($path, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(str_replace('\\', '/', $path), 'string');
    }
    private function iniDirective($extension)
    {
        $zend = strtolower($extension) === 'opcache';
        $binary = $extension;
        if (PHP_VERSION_ID < 70000) {
            $binary .= '.' . PHP_SHLIB_SUFFIX;
            if ($zend) $binary = rtrim(ini_get('extension_dir'), '/\\\\') . DIRECTORY_SEPARATOR . $binary;
        }
        return ($zend ? 'zend_extension=' : 'extension=') . $binary . "\n";
    }
}

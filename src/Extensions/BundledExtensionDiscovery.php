<?php

namespace Wsdd\PhpAdmin\Extensions;

use Wsdd\PhpAdmin\Support\CommandRunner;
final class BundledExtensionDiscovery
{
    /**
     * @var mixed[]
     */
    private static $archiveInventories = [];
    /**
     * @var string
     */
    private $sourceRoot;
    /**
     * @var string
     */
    private $archive;
    /**
     * @param string $sourceRoot
     * @param string $archive
     */
    public function __construct($sourceRoot = '/usr/src/php', $archive = '/usr/src/php.tar.xz')
    {
        $sourceRoot = \Wsdd\PhpAdmin\Support\TypeGuard::check($sourceRoot, 'string');
        $archive = \Wsdd\PhpAdmin\Support\TypeGuard::check($archive, 'string');
        $this->sourceRoot = $sourceRoot;
        $this->archive = $archive;
    }
    /**
     * @param string $phpVersion
     * @return array
     */
    public function discover($phpVersion)
    {
        $phpVersion = \Wsdd\PhpAdmin\Support\TypeGuard::check($phpVersion, 'string');
        $files = [];
        if (is_dir($this->sourceRoot . '/ext')) {
            $header = @file_get_contents($this->sourceRoot . '/main/php_version.h');
            if (!is_string($header) || preg_match('/#define\s+PHP_VERSION\s+"([^"]+)"/', $header, $matches) !== 1 || $matches[1] !== $phpVersion) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->failure('Extracted PHP source version does not match the runtime.'), 'array');
            }
            foreach (glob($this->sourceRoot . '/ext/*/config.m4') ?: [] as $file) {
                $files[] = basename(dirname($file));
            }
            $source = 'source-tree';
        } elseif (is_readable($this->archive)) {
            clearstatcache(true, $this->archive);
            $stamp = stat($this->archive);
            $cacheKey = \Wsdd\PhpAdmin\Support\JsonCodec::encode([$this->archive, $phpVersion, $stamp['ino'], $stamp['size'], $stamp['mtime'], $stamp['ctime']]);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(json_last_error_msg());
            }
            if (isset(self::$archiveInventories[$cacheKey])) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check(self::$archiveInventories[$cacheKey], 'array');
            }
            // Listing the archive does not extract or modify the container's PHP sources.
            $result = (new CommandRunner())->run(['tar', '-tJf', $this->archive, '--wildcards', '*/ext/*/config.m4']);
            if ($result['exit_code'] !== 0) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->failure('PHP source archive could not be inspected.'), 'array');
            }
            foreach (preg_split('/\R/', $result['stdout']) ?: [] as $line) {
                if (preg_match('~^php-([^/]+)/ext/([a-zA-Z][a-zA-Z0-9_]*)/config\.m4$~D', $line, $matches) === 1) {
                    if ($matches[1] !== $phpVersion) {
                        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->failure('PHP source archive version does not match the runtime.'), 'array');
                    }
                    $files[] = strtolower($matches[2]);
                }
            }
            $source = 'source-archive';
        } else {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->failure('No readable matching PHP source tree or archive is available.'), 'array');
        }
        if ($files === []) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->failure('No bundled extension build definitions were discovered.'), 'array');
        }
        $files = array_values(array_unique($files));
        sort($files);
        $inventory = ['extensions' => $files, 'source' => $source, 'error' => null];
        if (isset($cacheKey)) {
            if (count(self::$archiveInventories) >= 8) {
                array_shift(self::$archiveInventories);
            }
            self::$archiveInventories[$cacheKey] = $inventory;
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($inventory, 'array');
    }
    /**
     * @param string $message
     * @return array
     */
    private function failure($message)
    {
        $message = \Wsdd\PhpAdmin\Support\TypeGuard::check($message, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['extensions' => [], 'source' => 'unavailable', 'error' => $message], 'array');
    }
}

<?php

namespace Wsdd\PhpAdmin\Extensions;

final class CatalogEnvironment
{
    use \Wsdd\PhpAdmin\Support\ReadOnlyProperties;
    /**
     * @var string
     */
    private $phpVersion;
    /**
     * @var string
     */
    private $distribution;
    /**
     * @var mixed[]
     */
    private $tools;
    /**
     * @var mixed[]
     */
    private $sources;
    /**
     * @param string $phpVersion
     * @param string $distribution
     */
    public function __construct($phpVersion, $distribution, array $tools, array $sources)
    {
        $phpVersion = \Wsdd\PhpAdmin\Support\TypeGuard::check($phpVersion, 'string');
        $distribution = \Wsdd\PhpAdmin\Support\TypeGuard::check($distribution, 'string');
        $this->phpVersion = $phpVersion;
        $this->distribution = $distribution;
        $this->tools = $tools;
        $this->sources = $sources;
    }
    /**
     * @return Wsdd\PhpAdmin\Extensions\CatalogEnvironment
     */
    public static function detect()
    {
        $os = is_readable('/etc/os-release') ? @parse_ini_file('/etc/os-release', false, INI_SCANNER_RAW) : [];
        $tools = [];
        foreach (['docker-php-ext-install', 'docker-php-ext-enable', 'docker-php-ext-configure', 'apt-get', 'phpize', 'make', 'cc', 'apache2ctl', 'apachectl'] as $tool) {
            $tools[$tool] = \Wsdd\PhpAdmin\Support\ExecutableFinder::find($tool) !== null;
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(new self(PHP_VERSION, is_array($os) ? (string) (isset($os['ID']) ? $os['ID'] : 'unknown') : 'unknown', $tools, (new BundledExtensionDiscovery())->discover(PHP_VERSION)), 'Wsdd\PhpAdmin\Extensions\CatalogEnvironment');
    }
}

<?php

namespace Wsdd\PhpAdmin\Extensions;

use Wsdd\PhpAdmin\Support\CompatibilityPolicy;
final class ExtensionCatalog
{
    /**
     * @var \Wsdd\PhpAdmin\Extensions\CatalogEnvironment|null
     */
    private $environment;
    /**
     * @param Wsdd\PhpAdmin\Extensions\CatalogEnvironment|null $environment
     */
    public function __construct($environment = null)
    {
        $environment = \Wsdd\PhpAdmin\Support\TypeGuard::check($environment, '?Wsdd\PhpAdmin\Extensions\CatalogEnvironment');
        $this->environment = $environment;
    }
    /**
     * @return Wsdd\PhpAdmin\Extensions\CatalogEnvironment
     */
    private function environment()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(isset($this->environment) ? $this->environment : CatalogEnvironment::detect(), 'Wsdd\PhpAdmin\Extensions\CatalogEnvironment');
    }
    /**
     * @return array
     */
    private function recipes(CatalogEnvironment $environment)
    {
        $catalogs = require dirname(dirname(__DIR__)) . '/config/extension-catalog.php';
        $recipes = CompatibilityPolicy::hasRecipes($environment->phpVersion) ? isset($catalogs[$environment->distribution]) ? $catalogs[$environment->distribution] : [] : [];
        $variants = isset($catalogs['variants'][$environment->distribution]) ? $catalogs['variants'][$environment->distribution] : [];
        foreach ($variants as $variant) {
            if (version_compare($environment->phpVersion, $variant['minimum'], '<') || version_compare($environment->phpVersion, $variant['maximum_exclusive'], '>=')) continue;
            foreach ($variant['recipes'] as $name => $override) {
                if (isset($recipes[$name])) $recipes[$name] = array_replace($recipes[$name], $override);
            }
        }
        foreach ($recipes as &$recipe) {
            $recipe += ['origin' => 'core', 'install_method' => 'docker-php-ext-install', 'fallback_methods' => []];
        }
        unset($recipe);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($recipes, 'array');
    }
    /**
     * @param string $extension
     * @return string|null
     */
    private function blockReason($extension, CatalogEnvironment $environment, array $recipes)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        if (!isset($recipes[$extension])) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('Extension is not in the safe install catalog for this PHP version and distribution.', '?string');
        }
        if ($environment->sources['error'] !== null) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($environment->sources['error'], '?string');
        }
        if (!in_array($extension, $environment->sources['extensions'], true)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('Extension build sources are not present for this PHP runtime.', '?string');
        }
        foreach (['docker-php-ext-install', 'docker-php-ext-enable', 'phpize', 'make', 'cc'] as $tool) {
            if (!(isset($environment->tools[$tool]) ? $environment->tools[$tool] : false)) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check('Required tool is unavailable: ' . $tool, '?string');
            }
        }
        if (!(isset($environment->tools['apache2ctl']) ? $environment->tools['apache2ctl'] : false) && !(isset($environment->tools['apachectl']) ? $environment->tools['apachectl'] : false)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('This catalog requires the supported Apache runtime.', '?string');
        }
        $recipe = $recipes[$extension];
        if ($recipe['packages'] !== [] && !(isset($environment->tools['apt-get']) ? $environment->tools['apt-get'] : false)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('Required system dependencies need apt-get.', '?string');
        }
        if ($recipe['configure'] !== [] && !(isset($environment->tools['docker-php-ext-configure']) ? $environment->tools['docker-php-ext-configure'] : false)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('Required tool is unavailable: docker-php-ext-configure', '?string');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(null, '?string');
    }
    /**
     * @param string $extension
     * @return array
     */
    public function resolve($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        $environment = $this->environment();
        $recipes = $this->recipes($environment);
        $name = strtolower($extension);
        $reason = ExtensionName::isValid($name) ? $this->blockReason($name, $environment, $recipes) : 'Extension name is not safe.';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['entry' => ($reason === null) ? $recipes[$name] : null, 'reason' => $reason, 'php_version' => $environment->phpVersion, 'distribution' => $environment->distribution], 'array');
    }
    /**
     * @return list<string>
     */
    public function initialInstallable()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(array_keys(array_filter($this->status([]), static function (array $entry) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($entry['installable'], 'bool');
        })), 'array');
    }
    /**
     * @return array<string, array{wsdd_target:bool, app_runtime:string, install_catalog:string, validation:string}>
     */
    public function compatibilityMatrix()
    {
        $policy = CompatibilityPolicy::load();
        $matrix = [];
        foreach ($policy['targets'] as $version => $validation) {
            $runtimeSupported = version_compare($version, $policy['runtime_minimum'], '>=');
            $matrix[$version] = ['wsdd_target' => true, 'app_runtime' => (!$runtimeSupported) ? 'not-supported-by-current-code' : (($validation === 'pending') ? 'not-validated' : 'supported'), 'install_catalog' => CompatibilityPolicy::hasRecipes($version) ? 'implemented' : 'missing', 'validation' => $validation];
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($matrix, 'array');
    }
    /**
     * @param string $phpVersion
     * @return string
     */
    public function catalogStatusForPhpVersion($phpVersion)
    {
        $phpVersion = \Wsdd\PhpAdmin\Support\TypeGuard::check($phpVersion, 'string');
        $minor = $this->minorVersion($phpVersion);
        $matrix = $this->compatibilityMatrix();
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(isset($matrix[$minor]['install_catalog']) ? $matrix[$minor]['install_catalog'] : 'unknown', 'string');
    }
    /**
     * @param string $extension
     * @return bool
     */
    public function isInstallable($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->entry($extension) !== null, 'bool');
    }
    /**
     * @return array{origin:string, install_method:string, packages:list<string>, configure:list<string>, fallback_methods:list<string>}|null
     * @param string $extension
     */
    public function entry($extension)
    {
        $extension = \Wsdd\PhpAdmin\Support\TypeGuard::check($extension, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->resolve($extension)['entry'], '?array');
    }
    /**
     * @param list<string> $loadedExtensions
     * @return array<string, array<string, mixed>>
     */
    public function status(array $loadedExtensions)
    {
        $loaded = array_fill_keys($this->normalizeList($loadedExtensions), true);
        $available = array_fill_keys($this->availableExtensions(array_keys($loaded)), true);
        $status = [];
        $environment = $this->environment();
        $recipes = $this->recipes($environment);
        $names = array_unique(array_merge(array_keys($recipes), $environment->sources['extensions'], array_keys($available)));
        foreach ($names as $extension) {
            $entry = isset($recipes[$extension]) ? $recipes[$extension] : ['origin' => in_array($extension, $environment->sources['extensions'], true) ? 'core' : 'observed', 'install_method' => null, 'packages' => [], 'fallback_methods' => []];
            $reason = $this->blockReason($extension, $environment, $recipes);
            $status[$extension] = ['loaded' => isset($loaded[$extension]), 'available' => isset($available[$extension]), 'installable' => $reason === null, 'reason' => $reason, 'recipe_available' => isset($recipes[$extension]), 'source_available' => in_array($extension, $environment->sources['extensions'], true), 'discovery_source' => $environment->sources['source'], 'php_version' => $environment->phpVersion, 'distribution' => $environment->distribution, 'origin' => $entry['origin'], 'install_method' => $entry['install_method'], 'packages' => $entry['packages'], 'fallback_methods' => $entry['fallback_methods']];
        }
        ksort($status, SORT_NATURAL);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($status, 'array');
    }
    /**
     * @param list<string> $loadedExtensions
     * @return array<string, list<string>>
     */
    public function operationOptions(array $loadedExtensions)
    {
        $loaded = $this->normalizeList($loadedExtensions);
        $available = $this->availableExtensions($loaded);
        $installable = array_values(array_diff($this->initialInstallable(), $loaded, $available));
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(['install_extension' => $installable, 'enable_extension' => array_values(array_diff($available, $loaded)), 'disable_extension' => $loaded, 'remove_extension' => $loaded, 'rollback_extension' => $loaded, 'reconcile' => []], 'array');
    }
    /**
     * @param list<string> $loaded
     * @return list<string>
     */
    private function availableExtensions(array $loaded)
    {
        $available = $loaded;
        $extensionDir = ini_get('extension_dir');
        if (is_string($extensionDir) && is_dir($extensionDir)) {
            foreach (glob(rtrim($extensionDir, '/\\') . DIRECTORY_SEPARATOR . '*.{so,dll}', GLOB_BRACE) ?: [] as $path) {
                $name = pathinfo($path, PATHINFO_FILENAME);
                if (is_string($name) && ExtensionName::isValid($name)) {
                    $available[] = strtolower($name);
                }
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->normalizeList($available), 'array');
    }
    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    private function normalizeList(array $extensions)
    {
        $normalized = [];
        foreach ($extensions as $extension) {
            $name = ExtensionName::canonical($extension);
            if (ExtensionName::isValid($name)) {
                $normalized[$name] = true;
            }
        }
        $list = array_keys($normalized);
        sort($list, SORT_NATURAL);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($list, 'array');
    }
    /**
     * @param string $phpVersion
     * @return string
     */
    private function minorVersion($phpVersion)
    {
        $phpVersion = \Wsdd\PhpAdmin\Support\TypeGuard::check($phpVersion, 'string');
        if (preg_match('/^(\d+\.\d+)/', $phpVersion, $matches) === 1) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($matches[1], 'string');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($phpVersion, 'string');
    }
}

<?php

namespace Wsdd\PhpAdmin\Support;

final class CompatibilityPolicy
{
    /**
     * @return array
     */
    public static function load()
    {
        $json = file_get_contents(dirname(dirname(__DIR__)) . '/config/php-compatibility.json');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(JsonCodec::decode($json), 'array');
    }
    /**
     * @param string $phpVersion
     * @return bool
     */
    public static function hasRecipes($phpVersion)
    {
        $phpVersion = \Wsdd\PhpAdmin\Support\TypeGuard::check($phpVersion, 'string');
        $policy = self::load();
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(version_compare($phpVersion, $policy['recipe_minimum'], '>=') && version_compare($phpVersion, $policy['recipe_maximum_exclusive'], '<') && preg_match('/^(\d+\.\d+)(?:\.|$)/', $phpVersion, $matches) === 1 && in_array($matches[1], $policy['recipe_versions'], true), 'bool');
    }
}

<?php

namespace Wsdd\PhpAdmin\Extensions;

final class ExtensionName
{
    const PATTERN = '/\A[a-zA-Z][a-zA-Z0-9_]*\z/';
    /**
     * @param string $name
     * @return bool
     */
    public static function isValid($name)
    {
        $name = \Wsdd\PhpAdmin\Support\TypeGuard::check($name, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(preg_match(self::PATTERN, $name) === 1, 'bool');
    }
    /**
     * @param string $name
     * @return string
     */
    public static function canonical($name)
    {
        $name = \Wsdd\PhpAdmin\Support\TypeGuard::check($name, 'string');
        $name = strtolower(trim($name));
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(($name === 'zend opcache') ? 'opcache' : $name, 'string');
    }
}

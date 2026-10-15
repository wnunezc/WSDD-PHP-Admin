<?php

namespace Wsdd\PhpAdmin\Operations;

final class OperationType
{
    const ENABLE_EXTENSION = 'enable_extension';
    const DISABLE_EXTENSION = 'disable_extension';
    const INSTALL_EXTENSION = 'install_extension';
    const REMOVE_EXTENSION = 'remove_extension';
    const ROLLBACK_EXTENSION = 'rollback_extension';
    const RECONCILE = 'reconcile';
    /**
     * @return list<string>
     */
    public static function all()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check([self::INSTALL_EXTENSION, self::ENABLE_EXTENSION, self::DISABLE_EXTENSION, self::REMOVE_EXTENSION, self::ROLLBACK_EXTENSION, self::RECONCILE], 'array');
    }
    /**
     * @param string $type
     * @return bool
     */
    public static function isAllowed($type)
    {
        $type = \Wsdd\PhpAdmin\Support\TypeGuard::check($type, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(in_array($type, self::all(), true), 'bool');
    }
}

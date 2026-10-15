<?php

namespace Wsdd\PhpAdmin\Support;

final class ProcessIdentity
{
    /**
     * @return bool
     */
    public static function isRoot()
    {
        if (function_exists('posix_geteuid')) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(posix_geteuid() === 0, 'bool');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(PHP_OS_FAMILY === 'Windows' && self::effectiveUser() === 'root', 'bool');
    }
    /**
     * @return string|null
     */
    public static function effectiveUser()
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $user = posix_getpwuid(posix_geteuid());
            if (is_array($user) && isset($user['name']) && is_string($user['name'])) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($user['name'], '?string');
            }
        }
        $user = getenv('USER') ?: getenv('USERNAME');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check((is_string($user) && $user !== '') ? $user : null, '?string');
    }
}

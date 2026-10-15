<?php

namespace Wsdd\PhpAdmin\Support;

/** Preserves scalar/return contracts on the shared PHP 5.6 source. */
final class TypeGuard
{
    public static function check($value, $type)
    {
        if ($type[0] === '?') {
            if ($value === null) return null;
            $type = substr($type, 1);
        }
        if ($type === 'float' && is_int($value)) return (float) $value;
        $checks = array('string' => 'is_string', 'int' => 'is_int', 'float' => 'is_float', 'bool' => 'is_bool', 'array' => 'is_array', 'callable' => 'is_callable');
        $valid = isset($checks[$type]) ? call_user_func($checks[$type], $value) : $value instanceof $type;
        if (!$valid) {
            $message = 'Expected ' . $type . ', received ' . gettype($value) . '.';
            if (class_exists('TypeError', false)) throw new \TypeError($message);
            throw new \InvalidArgumentException($message);
        }
        return $value;
    }
}

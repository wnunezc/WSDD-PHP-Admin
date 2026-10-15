<?php

namespace Wsdd\PhpAdmin\Operations;

final class OperationId
{
    const PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i';
    /**
     * @return string
     */
    public static function generate()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0xf | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        $hex = bin2hex($bytes);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), (string) substr($hex, 8, 4), (string) substr($hex, 12, 4), (string) substr($hex, 16, 4), (string) substr($hex, 20)), 'string');
    }
    /**
     * @param string $id
     * @return bool
     */
    public static function isValid($id)
    {
        $id = \Wsdd\PhpAdmin\Support\TypeGuard::check($id, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(preg_match(self::PATTERN, $id) === 1, 'bool');
    }
}

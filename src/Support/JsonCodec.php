<?php

namespace Wsdd\PhpAdmin\Support;

final class JsonCodec
{
    public static function decode($json, $associative = true, $depth = 512, $flags = 0)
    {
        $value = json_decode($json, $associative, $depth, $flags);
        self::check();
        return $value;
    }

    public static function encode($value, $flags = 0, $depth = 512)
    {
        $json = json_encode($value, $flags, $depth);
        self::check();
        return $json;
    }

    private static function check()
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \JsonException(json_last_error_msg(), json_last_error());
        }
    }
}

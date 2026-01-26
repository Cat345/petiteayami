<?php

namespace YOOtheme;

abstract class Memory
{
    /**
     * Try to raise memory_limit.
     */
    public static function raise(string $memory = '512M'): void
    {
        $limit = static::toBytes((string) ini_get('memory_limit'));

        if ($limit !== -1 && $limit < static::toBytes($memory)) {
            @ini_set('memory_limit', $memory);
        }
    }

    /**
     * Converts a shorthand byte value to an integer byte value.
     */
    public static function toBytes(string $value): int
    {
        $bytes = (int) $value;
        $value = substr(strtolower(trim($value)), -1);

        switch ($value) {
            case 'g':
                $bytes *= 1024;
            case 'm':
                $bytes *= 1024;
            case 'k':
                $bytes *= 1024;
        }

        return min($bytes, PHP_INT_MAX);
    }
}

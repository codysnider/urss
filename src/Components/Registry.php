<?php

declare(strict_types=1);

namespace RssApp\Components;

final class Registry
{

    private static $store = [];

    /**
     * @return mixed|null
     */
    public static function get(string $key)
    {
        if (array_key_exists($key, self::$store)) {
            return self::$store[$key];
        }
        return null;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function set(string $key, $value)
    {
        self::$store[$key] = $value;
        return $value;
    }
}

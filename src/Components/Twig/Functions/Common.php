<?php

declare(strict_types=1);

namespace RssApp\Components\Twig\Functions;

class Common
{

    public static function env(string $key)
    {
        return getenv($key);
    }

}

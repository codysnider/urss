<?php

namespace RssApp\Components\Error;

final class Handler
{

    public static function handle($errno, $errstr, $errfile, $errline)
    {
        dump(func_get_args());
        return true;
    }

}

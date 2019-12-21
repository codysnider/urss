<?php

namespace RssApp\Components\Twig;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Twig\TwigFunction;

class Functions
{

    public static function all()
    {
        $functionClasses = [self::class];

        $allFunctionFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.DS.'Functions'));
        $functionFiles    = new RegexIterator($allFunctionFiles, '/\.php$/');
        foreach ($functionFiles as $functionFile) {
            $content   = file_get_contents($functionFile->getRealPath());
            $tokens    = token_get_all($content);
            $namespace = '';
            for ($index = 0; isset($tokens[$index]); $index++) {
                if (!isset($tokens[$index][0])) {
                    continue;
                }
                if (T_NAMESPACE === $tokens[$index][0]) {
                    $index += 2;
                    while (isset($tokens[$index]) && is_array($tokens[$index])) {
                        $namespace .= $tokens[$index++][1];
                    }
                }
                if (T_CLASS === $tokens[$index][0] && T_WHITESPACE === $tokens[$index + 1][0] && T_STRING === $tokens[$index + 2][0]) {
                    $index           += 2;
                    $functionClasses[] = $namespace.'\\'.$tokens[$index][1];
                    break;
                }
            }
        }

        $functions = [];
        foreach ($functionClasses as $functionClass) {
            $methods = get_class_methods($functionClass);
            $key     = array_search('all', $methods);
            if ($key !== false) {
                unset($methods[$key]);
            }

            foreach ($methods as $method) {
                $functions[] = new TwigFunction($method, [$functionClass, $method]);
            }
        }

        return $functions;
    }
}

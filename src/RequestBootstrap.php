<?php

declare(strict_types=1);

namespace RssApp;

use Exception;
use RssApp\Components\Registry;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\MoFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * @internal Intended to be a final abstract (or static) class (https://wiki.php.net/rfc/abstract_final_class)
 */
abstract class RequestBootstrap
{

    /**
     * Runs the class methods in order of declaration
     *
     * @param array $omitMethods
     *
     * @throws Exception
     */
    public static function initialize($omitMethods = []): void
    {
        $methods = get_class_methods(self::class);
        $key = array_search('initialize', $methods);
        if ($key !== false) {
            unset($methods[$key]);
        }
        foreach ($omitMethods as $omitMethod) {
            $omitKey = array_search($omitMethod, $methods);
            if ($omitKey !== false) {
                unset($methods[$key]);
            }
        }
        foreach ($methods as $method) {
            if (self::{$method}() === false) {
                throw new Exception($method.' failed to initialize');
            }
        }
    }

    /**
     * @todo Load language from preferences
     */
    protected static function twigTranslations(): bool
    {
        $twig = Registry::get('twig');
        $translator = new Translator(getenv('DEFAULT_LANGUAGE'));
        $translator->addLoader('moloader', new MoFileLoader());
        $translator->addResource(
            'moloader',
            BASEPATH.DS.'locale'.DS.getenv('DEFAULT_LANGUAGE').DS.'LC_MESSAGES'.DS.'messages.mo',
            getenv('DEFAULT_LANGUAGE')
        );
        $translationExt = new TranslationExtension($translator);

        $twig->addExtension($translationExt);
        return true;
    }

}

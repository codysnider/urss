<?php

declare(strict_types=1);

namespace RssApp;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Exception;
use RssApp\Components\Registry;
use RssApp\Components\Twig\Filters;
use RssApp\Components\Twig\Functions;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;
use Symfony\Component\Translation\Loader\MoFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorTrait;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Zend\Diactoros\ServerRequestFactory;

/**
 * @internal Intended to be a final abstract (or static) class (https://wiki.php.net/rfc/abstract_final_class)
 */
abstract class Bootstrap
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

    protected static function request(): bool
    {
        $request = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        Registry::set('request', $request);
        return true;
    }

    protected static function router(): bool
    {
        $request = Request::createFromGlobals();
        $requestContext = new RequestContext();
        $requestContext->fromRequest($request);
        $logger = Registry::get('log');
        $fileLocator = new FileLocator([
            BASEPATH.DS.'src'.DS.'Controller',
        ]);
        $reader = new AnnotationReader();
        $annotatedLoader = new AnnotatedRouteControllerLoader($reader);
        $loader = new AnnotationDirectoryLoader($fileLocator, $annotatedLoader);
        $router = new Router($loader, BASEPATH.DS.'src'.DS.'Controller', [], $requestContext, $logger);
        $loader = require BASEPATH.DS.'external'.DS.'autoload.php';
        AnnotationRegistry::registerLoader([$loader, 'loadClass']);
        Registry::set('router', $router);
        return true;
    }

    protected static function twig(): bool
    {
        $cache = false;
        if (APPLICATION_ENV !== 'dev') {
            $cache = BASEPATH.DS.'tmp'.DS.'view-cache';
        }
        $loader = new FilesystemLoader(BASEPATH.DS.'views');
        $twig = new Environment($loader, ['cache' => $cache]);

        $twig->addGlobal('request', Registry::get('request'));
        $twig->addGlobal('basePath', BASEPATH);
        $twig->addGlobal('applicationEnv', APPLICATION_ENV);

        foreach (Filters::all() as $filter) {
            $twig->addFilter($filter);
        }
        foreach (Functions::all() as $function) {
            $twig->addFunction($function);
        }

        Registry::set('twig', $twig);
        return true;
    }

}

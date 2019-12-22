<?php

declare(strict_types=1);

namespace RssApp;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Setup;
use Exception;
use JMS\Serializer\SerializerBuilder;
use Redis;
use RssApp\Components\Registry;
use RssApp\Components\Twig\Filters;
use RssApp\Components\Twig\Functions;
use RssApp\Model\Extension\BacktickQuoteStrategy;
use Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;
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

    protected static function redis(): bool
    {
        if (!empty(getenv('CACHE_HOST'))) {
            $redis = new Redis();
            $redis->pconnect(getenv('CACHE_HOST'), (int) getenv('CACHE_PORT'));
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
            $redis->select(1);
            Registry::set('redis', $redis);
        } else {
            Registry::set('redis', false);
        }
        return true;
    }

    /**
     * @throws AnnotationException
     * @throws ORMException
     * @throws \Doctrine\DBAL\DBALException
     */
    protected static function orm(): bool
    {
        $config = Setup::createAnnotationMetadataConfiguration([BASEPATH.DS.'src'], true);

        $config->setAutoGenerateProxyClasses(AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);
        $config->setProxyDir(BASEPATH.DS.'tmp'.DS.'proxies');
        $config->addEntityNamespace('RssApp', 'RssApp\Model');
        $config->setQuoteStrategy(new BacktickQuoteStrategy());
        $config->setNamingStrategy(new UnderscoreNamingStrategy());

        if (Registry::get('redis')) {
            $ormCache = new RedisCache();
            $ormCache->setRedis(Registry::get('redis'));
        } else {
            $ormCache = new ArrayCache();
        }
        $config->setSecondLevelCacheEnabled();
        $config->getSecondLevelCacheConfiguration()
            ->setCacheFactory(new DefaultCacheFactory(new RegionsConfiguration(), $ormCache));
        $config->setMetadataCacheImpl($ormCache);
        $config->setQueryCacheImpl($ormCache);
        $config->setResultCacheImpl($ormCache);
        $reader = new AnnotationReader();
        $driver = new AnnotationDriver($reader, [BASEPATH.DS.'src']);
        $config->setMetadataDriverImpl($driver);

        $slaves = [];
        if (getenv('DB_SLAVES') !== false) {
            $slaveHosts = explode(',', getenv('DB_SLAVES'));
            foreach ($slaveHosts as $host) {
                $slaves[] = [
                    'user'      => getenv('DB_USER'),
                    'password'  => getenv('DB_PASS'),
                    'host'      => $host,
                    'dbname'    => getenv('DB_NAME'),
                ];
            }
        } else {
            $slaves[] = [
                'user'      => getenv('DB_USER'),
                'password'  => getenv('DB_PASS'),
                'host'      => getenv('DB_HOST'),
                'dbname'    => getenv('DB_NAME'),
            ];
        }
        $connection = DriverManager::getConnection([
            'wrapperClass' => 'Doctrine\DBAL\Connections\MasterSlaveConnection',
            'driver' => 'pdo_mysql',
            'master' => [
                'user'      => getenv('DB_USER'),
                'password'  => getenv('DB_PASS'),
                'host'      => getenv('DB_HOST'),
                'dbname'    => getenv('DB_NAME'),
            ],
            'slaves' => $slaves,
        ]);
        $entityManager = EntityManager::create($connection, $config);
        Registry::set('em', $entityManager);

        try {
            $dbPlatform = $entityManager->getConnection()->getDatabasePlatform();
            $dbPlatform->registerDoctrineTypeMapping('enum', 'string');
            $dbPlatform->registerDoctrineTypeMapping('bit', 'boolean');
        } catch (DBALException $e) {
            echo $e->getMessage();
            return false;
        }
        AnnotationRegistry::registerAutoloadNamespace('JMS\Serializer\Annotation',BASEPATH.DS.'external/jms/serializer/src');
        Registry::set('serializer', SerializerBuilder::create()->addMetadataDir(BASEPATH.DS.'src')->build());
        return true;
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

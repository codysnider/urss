<?php

namespace PHPSTORM_META
{
    override( \RssApp\Components\Registry::get(0),
        map([
            "request" => Zend\Diactoros\ServerRequest::class,
            "router" => Symfony\Component\Routing\Router::class,
            "twig" => Environment::class,
        ])
    );

    override( Zend_Controller_Action_HelperBroker::getStaticHelper(o),
        map([
            "url" => Zend_View_Helper_Url::class,
        ])
    );
}

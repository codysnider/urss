<?php

declare(strict_types=1);

namespace RssApp;

use Doctrine\Common\Annotations\AnnotationException;
use Exception;
use Jawira\CaseConverter\CaseConverterException;
use Jawira\CaseConverter\Convert;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RssApp\Components\Registry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Zend\Diactoros\Response\HtmlResponse;

class Controller
{
    /**
     * Handles instantiation of controller, calling method and returning response
     *
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws RouteNotFoundException
     */
    public static function handle()
    {
        $router = Registry::get('router');
        try {
            $matchedRoute = $router->matchRequest(Request::createFromGlobals());
        } catch (Exception $e) {
            throw new RouteNotFoundException();
        }
        [$controllerName, $methodName] = explode('::', $matchedRoute['_controller']);
        $parameters = [];
        foreach ($matchedRoute as $paramName => $paramVal) {
            if (stripos($paramName, '_') !== 0) {
                $parameters[$paramName] = $paramVal;
            }
        }
        $controllerReflection = new ReflectionClass($controllerName);
        if (!$controllerReflection->hasMethod($methodName)) {
            throw new Exception('Controller method unknown');
        }
        $methodReflection        = $controllerReflection->getMethod($methodName);
        $invokeOrderedParameters = [];
        foreach ($methodReflection->getParameters() as $parameterReflection) {
            if (!in_array($parameterReflection->getName(), array_keys($parameters))) {
                throw new Exception('Missing parameter');
            }
            $invokeOrderedParameters[$parameterReflection->getName()] = $parameters[$parameterReflection->getName()];
        }

        $controller       = $controllerReflection->newInstance();
        $methodInvocation = new ReflectionMethod($controllerName, $methodName);
        $response         = $methodInvocation->invokeArgs($controller, $invokeOrderedParameters);

        return $response;
    }

    /**
     * @param array $parameters
     * @param string|null $view
     *
     * @return HtmlResponse
     */
    public function render(array $parameters = [], ?string $view = null): HtmlResponse
    {
        $twig = Registry::get('twig');
        if (!is_null($view)) {
            $body = $twig->render($view, $parameters);
        } else {
            $router       = Registry::get('router');
            $matchedRoute = $router->matchRequest(Request::createFromGlobals());
            [$controllerName, $methodName] = explode('::', $matchedRoute['_controller']);
            $controllerNameParts = explode('\\', $controllerName);
            unset($controllerNameParts[0]);
            unset($controllerNameParts[1]);
            $controllerWithoutNamespace = '';
            foreach ($controllerNameParts as $controllerNamePart) {
                $controllerNamePart = str_replace('Controller', '', $controllerNamePart);
                try {
                    $controllerConverter = new Convert($controllerNamePart);
                    $controllerWithoutNamespace .= $controllerConverter->toKebab().DS;
                } catch (CaseConverterException $e) {
                    $controllerWithoutNamespace .= $controllerNamePart.DS;
                }
            }
            $methodName = str_replace('Action', '', $methodName);
            try {
                $methodConverter = new Convert($methodName);
                $viewFilename    = $controllerWithoutNamespace.$methodConverter->toKebab().'.html.twig';
            } catch (CaseConverterException $e) {
                $viewFilename = $controllerWithoutNamespace.$methodName.'.html.twig';
            }
            $body = $twig->render($viewFilename, $parameters);
        }

        return new HtmlResponse($body);
    }
}

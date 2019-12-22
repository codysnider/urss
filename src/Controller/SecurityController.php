<?php

namespace RssApp\Controller;

use RssApp\Components\Registry;
use RssApp\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Zend\Diactoros\Response\RedirectResponse;

class SecurityController extends Controller
{

    /**
     * @Route("/login", methods={"GET"})
     */
    public function loginAction()
    {
        return $this->render();
    }


    /**
     * @Route("/login", methods={"POST"})
     */
    public function loginSubmitAction()
    {
        $request = Registry::get('request');
        $params = $request->getQueryParams();

        if (array_key_exists('redirect', $params)) {
            return new RedirectResponse($params['redirect']);
        }

        return new RedirectResponse('/home');
    }
}

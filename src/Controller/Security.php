<?php

namespace RssApp\Controller;

use RssApp\Controller;
use Symfony\Component\Routing\Annotation\Route;

class Security extends Controller
{

    /**
     * @Route("/login", methods={"GET"})
     */
    public function loginAction()
    {
        putenv('LC_ALL=en_US');
        setlocale(LC_ALL, 'de_DE');

        return $this->render();
    }
}

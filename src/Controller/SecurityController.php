<?php

namespace RssApp\Controller;

use RssApp\Components\Registry;
use RssApp\Components\User;
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

    /**
     * @Route("/logout", methods={"GET"})
     */
    public function logoutAction()
    {
        return new RedirectResponse('/login');
    }

    /**
     * @Route("/register", methods={"GET"})
     */
    public function registerAction()
    {
        User::clearExpiredConfirmations();

        $qb = Registry::get('em')->createQueryBuilder();
        $userCount = (int) $qb->select('COUNT(u)')
            ->from('RssApp:User', 'u')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render([
            'registrationsEnabled' => (bool) (getenv('ENABLE_REGISTRATION') === 'true'),
            'availableAccounts' => ((int) getenv('REG_MAX_USERS') - $userCount)
        ]);
    }

    /**
     * @Route("/register-feed", methods={"GET"})
     */
    public function registerFeedAction()
    {
        User::clearExpiredConfirmations();

        $qb = Registry::get('em')->createQueryBuilder();
        $userCount = (int) $qb->select('COUNT(u)')
            ->from('RssApp:User', 'u')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render([
            'availableAccounts' => ((int) getenv('REG_MAX_USERS') - $userCount)
        ]);
    }

    /**
     * @Route("/username-check/{username}", methods={"GET"})
     */
    public function usernameCheckAction(string $username)
    {
        $qb = Registry::get('em')->createQueryBuilder();
        $checkCount = $qb->select('COUNT(u)')
            ->from('RssApp:User', 'u')
            ->where('u.login = :login')
            ->setParameter(':login', strtolower(trim($username)))
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render([
            'checkCount' => (int) $checkCount
        ]);
    }
}

<?php

namespace RssApp\Controller;

use RssApp\Components\Registry;
use RssApp\Components\Response\ErrorJson;
use RssApp\Components\Response\SuccessJson;
use RssApp\Components\TuringTest;
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
            'availableAccounts' => ((int) getenv('REG_MAX_USERS') - $userCount),
            'turingQuestion' => TuringTest::getQuestion()
        ]);
    }

    /**
     * @Route("/register", methods={"POST"})
     */
    public function registerSubmitAction()
    {
        $params = Registry::get('request')->getParsedBody();

        dump($params);

        if (User::usernameExists($params['username'])) {
            return new ErrorJson($params, Registry::get('trans')->trans('Sorry, this username is already taken.'));
        }

        return new SuccessJson();
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
        return $this->render([
            'checkCount' => User::usernameExists($username) ? 1 : 0
        ]);
    }
}

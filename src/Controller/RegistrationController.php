<?php

declare(strict_types=1);

namespace RssApp\Controller;

use Exception;
use RssApp\Components\Registry;
use RssApp\Components\Response\ErrorJson;
use RssApp\Components\Response\SuccessJson;
use RssApp\Components\Textcha;
use RssApp\Components\User;
use RssApp\Controller;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends Controller
{
    /**
     * @Route("/register", methods={"GET"})
     */
    public function registerAction()
    {
        User::clearExpiredConfirmations();

        $qb = Registry::get('em')->createQueryBuilder();
        try {
            $userCount = $qb->select('COUNT(u)')
                ->from('RssApp:User', 'u')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (Exception $e) {
            dump($e);
        }

        return $this->render([
            'registrationsEnabled' => (bool) (getenv('ENABLE_REGISTRATION') === 'true'),
            'availableAccounts' => ((int) getenv('REG_MAX_USERS') - $userCount),
            'captchaQuestion' => Textcha::getQuestion()
        ]);
    }

    /**
     * @Route("/register", methods={"POST"})
     */
    public function registerSubmitAction()
    {
        $params = Registry::get('request')->getParsedBody();

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
        if (User::usernameExists($username)) {
            return new ErrorJson([], Registry::get('trans')->trans('Sorry, this username is already taken.'));
        }

        return new SuccessJson([], Registry::get('trans')->trans('This username is available.'));
    }
}

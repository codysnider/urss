<?php

namespace RssApp\Components;

use DateTime;

class User
{
    public static function clearExpiredConfirmations(): void
    {
        $qb = Registry::get('em')->createQueryBuilder();
        $qb->delete('RssApp:User', 'u')
            ->where('u.lastLogin IS NULL')
            ->andWhere('u.created < :oneDayAgo')
            ->andWhere('u.accessLevel = 0')
            ->setParameter(':oneDayAgo', new DateTime('-1 month'))
            ->getQuery()
            ->getResult();
    }

    public static function usernameExists(string $username): bool
    {
        $qb = Registry::get('em')->createQueryBuilder();
        $exists = $qb->select('COUNT(u)')
            ->from('RssApp:User', 'u')
            ->where('u.login = :login')
            ->setParameter(':login', strtolower(trim($username)))
            ->getQuery()
            ->getSingleScalarResult();

        return ($exists === '1');
    }
}

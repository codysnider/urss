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
}

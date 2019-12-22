<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;
use RssApp\Model;

/**
 * @ORM\Entity
 */
class AppPassword extends Model
{
    use Identified,
        Owned;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=250, nullable=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="pwd_hash", type="text", length=65535, nullable=false)
     */
    private $pwdHash;

    /**
     * @var string
     *
     * @ORM\Column(name="service", type="string", length=100, nullable=false)
     */
    private $service;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false)
     */
    private $created;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_used", type="datetime", nullable=true)
     */
    private $lastUsed;
}

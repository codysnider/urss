<?php

declare(strict_types=1);

namespace RssApp\Model\Traits;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model\User;

trait Owned
{
    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="owner_uid", referencedColumnName="id")
     * })
     */
    protected $owner;
}

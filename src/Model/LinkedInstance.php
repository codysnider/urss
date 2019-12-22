<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="access_key", columns={"access_key"})})
 * @ORM\Entity
 */
class LinkedInstance extends Model
{
    use Identified;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_connected", type="datetime", nullable=false)
     */
    private $lastConnected;

    /**
     * @var int
     *
     * @ORM\Column(name="last_status_in", type="integer", nullable=false)
     */
    private $lastStatusIn;

    /**
     * @var int
     *
     * @ORM\Column(name="last_status_out", type="integer", nullable=false)
     */
    private $lastStatusOut;

    /**
     * @var string
     *
     * @ORM\Column(name="access_key", type="string", length=250, nullable=false)
     */
    private $accessKey;

    /**
     * @var string
     *
     * @ORM\Column(name="access_url", type="text", length=65535, nullable=false)
     */
    private $accessUrl;
}

<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Table(indexes={@ORM\Index(name="instance_id", columns={"instance_id"})})
 * @ORM\Entity
 */
class LinkedFeed extends Model
{
    use Identified;

    /**
     * @var string
     *
     * @ORM\Column(name="feed_url", type="text", length=65535, nullable=false)
     */
    private $feedUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="site_url", type="text", length=65535, nullable=false)
     */
    private $siteUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="text", length=65535, nullable=false)
     */
    private $title;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false)
     */
    private $created;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="updated", type="datetime", nullable=false)
     */
    private $updated;

    /**
     * @var int
     *
     * @ORM\Column(name="subscribers", type="integer", nullable=false)
     */
    private $subscribers;

    /**
     * @var LinkedInstance
     *
     * @ORM\ManyToOne(targetEntity="LinkedInstance")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="instance_id", referencedColumnName="id")
     * })
     */
    private $instance;
}

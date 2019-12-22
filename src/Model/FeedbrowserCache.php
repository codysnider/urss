<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Entity
 */
class FeedbrowserCache extends Model
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
     * @var int
     *
     * @ORM\Column(name="subscribers", type="integer", nullable=false)
     */
    private $subscribers;
}

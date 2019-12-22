<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;
use RssApp\Model;

/**
 * @ORM\Table(indexes={@ORM\Index(name="owner_uid", columns={"owner_uid"})})
 * @ORM\Entity
 */
class ArchivedFeed extends Model
{
    use Identified,
        Owned;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false)
     */
    private $created;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=200, nullable=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="feed_url", type="text", length=65535, nullable=false)
     */
    private $feedUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="site_url", type="string", length=250, nullable=false)
     */
    private $siteUrl = '';
}

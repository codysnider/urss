<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;

/**
 * @ORM\Table(indexes={@ORM\Index(name="parent_cat", columns={"parent_cat"}), @ORM\Index(name="owner_uid", columns={"owner_uid"})})
 * @ORM\Entity
 */
class FeedCategory extends Model
{
    use Identified,
        Owned;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=200, nullable=false)
     */
    private $title;

    /**
     * @var bool
     *
     * @ORM\Column(name="collapsed", type="boolean", nullable=false)
     */
    private $collapsed = false;

    /**
     * @var int
     *
     * @ORM\Column(name="order_id", type="integer", nullable=false)
     */
    private $orderId = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="view_settings", type="string", length=250, nullable=false)
     */
    private $viewSettings = '';

    /**
     * @var FeedCategory
     *
     * @ORM\ManyToOne(targetEntity="FeedCategory")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_cat", referencedColumnName="id")
     * })
     */
    private $parent;
}

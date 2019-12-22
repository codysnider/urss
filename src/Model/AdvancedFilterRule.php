<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Table(indexes={@ORM\Index(name="filter_id", columns={"filter_id"}), @ORM\Index(name="feed_id", columns={"feed_id"}), @ORM\Index(name="filter_type", columns={"filter_type"}), @ORM\Index(name="cat_id", columns={"cat_id"})})
 * @ORM\Entity
 */
class AdvancedFilterRule extends Model
{
    use Identified;

    /**
     * @var string
     *
     * @ORM\Column(name="reg_exp", type="text", length=65535, nullable=false)
     */
    private $regExp;

    /**
     * @var bool
     *
     * @ORM\Column(name="inverse", type="boolean", nullable=false)
     */
    private $inverse = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="cat_filter", type="boolean", nullable=false)
     */
    private $catFilter = false;

    /**
     * @var string|null
     *
     * @ORM\Column(name="match_on", type="text", length=65535, nullable=true)
     */
    private $matchOn;

    /**
     * @var Filter
     *
     * @ORM\ManyToOne(targetEntity="Filter")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="filter_id", referencedColumnName="id")
     * })
     */
    private $filter;

    /**
     * @var FilterType
     *
     * @ORM\ManyToOne(targetEntity="FilterType")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="filter_type", referencedColumnName="id")
     * })
     */
    private $filterType;

    /**
     * @var Feed
     *
     * @ORM\ManyToOne(targetEntity="Feed")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="feed_id", referencedColumnName="id")
     * })
     */
    private $feed;

    /**
     * @var FeedCategory
     *
     * @ORM\ManyToOne(targetEntity="FeedCategory")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="cat_id", referencedColumnName="id")
     * })
     */
    private $category;
}

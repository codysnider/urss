<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Table(indexes={@ORM\Index(name="filter_id", columns={"filter_id"}), @ORM\Index(name="action_id", columns={"action_id"})})
 * @ORM\Entity
 */
class AdvancedFilterAction extends Model
{
    use Identified;

    /**
     * @var string
     *
     * @ORM\Column(name="action_param", type="string", length=250, nullable=false)
     */
    private $actionParam = '';

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
     * @var FilterAction
     *
     * @ORM\ManyToOne(targetEntity="FilterAction")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="action_id", referencedColumnName="id")
     * })
     */
    private $action;
}

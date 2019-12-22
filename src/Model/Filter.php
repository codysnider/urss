<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;

/**
 * @ORM\Table(indexes={@ORM\Index(name="owner_uid", columns={"owner_uid"})})
 * @ORM\Entity
 */
class Filter extends Model
{
    use Identified,
        Owned;

    /**
     * @var bool
     *
     * @ORM\Column(name="match_any_rule", type="boolean", nullable=false)
     */
    private $matchAnyRule = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="enabled", type="boolean", nullable=false)
     */
    private $enabled = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="inverse", type="boolean", nullable=false)
     */
    private $inverse = false;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=250, nullable=false)
     */
    private $title = '';

    /**
     * @var int
     *
     * @ORM\Column(name="order_id", type="integer", nullable=false)
     */
    private $orderId = 0;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="last_triggered", type="datetime", nullable=true)
     */
    private $lastTriggered;
}

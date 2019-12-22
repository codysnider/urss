<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Entity
 */
class PreferenceSection extends Model
{
    use Identified;

    /**
     * @var int
     *
     * @ORM\Column(name="order_id", type="integer", nullable=false)
     */
    private $orderId;
}

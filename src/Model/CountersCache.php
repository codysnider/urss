<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;
use RssApp\Model;

/**
 * @ORM\Table(indexes={@ORM\Index(name="owner_uid", columns={"owner_uid"}), @ORM\Index(name="ttrss_counters_cache_value_idx", columns={"value"}), @ORM\Index(name="ttrss_counters_cache_feed_id_idx", columns={"feed_id"})})
 * @ORM\Entity
 */
class CountersCache extends Model
{
    use Identified,
        Owned;

    /**
     * @var int
     *
     * @ORM\Column(name="feed_id", type="integer", nullable=false)
     */
    private $feedId;

    /**
     * @var int
     *
     * @ORM\Column(name="value", type="integer", nullable=false)
     */
    private $value = '0';

    /**
     * @var DateTime
     *
     * @ORM\Column(name="updated", type="datetime", nullable=false)
     */
    private $updated;
}

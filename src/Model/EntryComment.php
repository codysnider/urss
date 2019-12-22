<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;

/**
 * @ORM\Table(indexes={@ORM\Index(name="ref_id", columns={"ref_id"}), @ORM\Index(name="owner_uid", columns={"owner_uid"})})
 * @ORM\Entity
 */
class EntryComment extends Model
{
    use Identified,
        Owned;

    /**
     * @var bool
     *
     * @ORM\Column(name="private", type="boolean", nullable=false)
     */
    private $private = false;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date_entered", type="datetime", nullable=false)
     */
    private $dateEntered;

    /**
     * @var Entry
     *
     * @ORM\ManyToOne(targetEntity="Entry")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="ref_id", referencedColumnName="id")
     * })
     */
    private $entry;
}

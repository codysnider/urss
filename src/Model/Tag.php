<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;

/**
 * @ORM\Table(indexes={@ORM\Index(name="post_id", columns={"post_id"}), @ORM\Index(name="owner_uid", columns={"owner_uid"})})
 * @ORM\Entity
 */
class Tag extends Model
{
    use Identified,
        Owned;

    /**
     * @var string
     *
     * @ORM\Column(name="tag_name", type="string", length=250, nullable=false)
     */
    private $tagName;

    /**
     * @var UserEntry
     *
     * @ORM\ManyToOne(targetEntity="UserEntry")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="post_id", referencedColumnName="id")
     * })
     */
    private $userEntry;
}

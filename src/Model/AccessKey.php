<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;
use RssApp\Model;

/**
 * @ORM\Table(indexes={@ORM\Index(name="owner_uid", columns={"owner_uid"})})
 * @ORM\Entity
 */
class AccessKey extends Model
{
    use Identified,
        Owned;

    /**
     * @var string
     *
     * @ORM\Column(name="access_key", type="string", length=250, nullable=false)
     */
    private $accessKey;

    /**
     * @var string
     *
     * @ORM\Column(name="feed_id", type="string", length=250, nullable=false)
     */
    private $feedId;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_cat", type="boolean", nullable=false)
     */
    private $isCat = false;
}

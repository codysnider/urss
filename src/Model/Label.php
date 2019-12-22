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
class Label extends Model
{
    use Identified,
        Owned;

    /**
     * @var string
     *
     * @ORM\Column(name="caption", type="string", length=250, nullable=false)
     */
    private $caption;

    /**
     * @var string
     *
     * @ORM\Column(name="fg_color", type="string", length=15, nullable=false)
     */
    private $fgColor = '';

    /**
     * @var string
     *
     * @ORM\Column(name="bg_color", type="string", length=15, nullable=false)
     */
    private $bgColor = '';
}

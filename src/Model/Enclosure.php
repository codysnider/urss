<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model\Traits\Identified;
use RssApp\Model;

/**
 * @ORM\Table(indexes={@ORM\Index(name="post_id", columns={"post_id"})})
 * @ORM\Entity
 */
class Enclosure extends Model
{
    use Identified;

    /**
     * @var string
     *
     * @ORM\Column(name="content_url", type="text", length=65535, nullable=false)
     */
    private $contentUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="content_type", type="string", length=250, nullable=false)
     */
    private $contentType;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="text", length=65535, nullable=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="duration", type="text", length=65535, nullable=false)
     */
    private $duration;

    /**
     * @var int
     *
     * @ORM\Column(name="width", type="integer", nullable=false)
     */
    private $width = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="height", type="integer", nullable=false)
     */
    private $height = 0;

    /**
     * @var Entry
     *
     * @ORM\ManyToOne(targetEntity="Entry")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="post_id", referencedColumnName="id")
     * })
     */
    private $post;
}

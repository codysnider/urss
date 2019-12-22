<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Table(indexes={@ORM\Index(name="label_id", columns={"label_id"}), @ORM\Index(name="article_id", columns={"article_id"})})
 * @ORM\Entity
 */
class UserLabel extends Model
{
    use Identified;

    /**
     * @var Label
     *
     * @ORM\ManyToOne(targetEntity="Label")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="label_id", referencedColumnName="id")
     * })
     */
    private $label;

    /**
     * @var Entry
     *
     * @ORM\ManyToOne(targetEntity="Entry")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="article_id", referencedColumnName="id")
     * })
     */
    private $article;
}

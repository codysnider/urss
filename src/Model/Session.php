<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;

/**
 * @ORM\Table(indexes={@ORM\Index(name="expire", columns={"expire"})})
 * @ORM\Entity
 */
class Session extends Model
{
    /**
     * @var string
     *
     * @ORM\Column(name="id", type="string", length=250, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(name="data", type="text", length=65535, nullable=true)
     */
    private $data;

    /**
     * @var int
     *
     * @ORM\Column(name="expire", type="integer", nullable=false)
     */
    private $expire;
}

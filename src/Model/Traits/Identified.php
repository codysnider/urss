<?php

declare(strict_types=1);

namespace RssApp\Model\Traits;

use Doctrine\ORM\Mapping as ORM;

trait Identified
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;
}

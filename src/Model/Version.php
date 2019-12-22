<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Entity
 */
class Version extends Model
{
    use Identified;

    /**
     * @var int
     *
     * @ORM\Column(name="schema_version", type="integer", nullable=false)
     */
    private $schemaVersion;
}

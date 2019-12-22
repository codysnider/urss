<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Entity
 */
class PreferenceType extends Model
{
    use Identified;

    /**
     * @var string
     *
     * @ORM\Column(name="type_name", type="string", length=100, nullable=false)
     */
    private $typeName;
}

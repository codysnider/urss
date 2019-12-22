<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;

/**
 * @ORM\Table(indexes={@ORM\Index(name="type_id", columns={"type_id"}), @ORM\Index(name="section_id", columns={"section_id"})})
 * @ORM\Entity
 */
class Preference extends Model
{
    /**
     * @var string
     *
     * @ORM\Column(name="pref_name", type="string", length=250, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $prefName;

    /**
     * @var int
     *
     * @ORM\Column(name="access_level", type="integer", nullable=false)
     */
    private $accessLevel = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="def_value", type="text", length=65535, nullable=false)
     */
    private $defValue;

    /**
     * @var PreferenceType
     *
     * @ORM\ManyToOne(targetEntity="PreferenceType")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="type_id", referencedColumnName="id")
     * })
     */
    private $type;

    /**
     * @var PreferenceSection
     *
     * @ORM\ManyToOne(targetEntity="PreferenceSection")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="section_id", referencedColumnName="id")
     * })
     */
    private $section;
}

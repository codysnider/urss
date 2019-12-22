<?php

declare(strict_types=1);

namespace RssApp\Model;

use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;

/**
 * @ORM\Table(indexes={@ORM\Index(name="profile", columns={"profile"}), @ORM\Index(name="pref_name", columns={"pref_name"}), @ORM\Index(name="owner_uid", columns={"owner_uid"})})
 * @ORM\Entity
 */
class UserPreference extends Model
{
    use Identified,
        Owned;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="text", length=0, nullable=false)
     */
    private $value;

    /**
     * @var SettingProfile
     *
     * @ORM\ManyToOne(targetEntity="SettingProfile")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="profile", referencedColumnName="id")
     * })
     */
    private $profile;

    /**
     * @var Preference
     *
     * @ORM\ManyToOne(targetEntity="Preference")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="pref_name", referencedColumnName="pref_name")
     * })
     */
    private $preference;
}

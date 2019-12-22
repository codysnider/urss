<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model\Traits\Identified;
use RssApp\Model;

/**
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="login", columns={"login"})})
 * @ORM\Entity
 */
class User extends Model
{
    use Identified;

    /**
     * @var string
     *
     * @ORM\Column(name="login", type="string", length=120, nullable=false)
     */
    private $login;

    /**
     * @var string
     *
     * @ORM\Column(name="pwd_hash", type="string", length=250, nullable=false)
     */
    private $pwdHash;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_login", type="datetime", nullable=true)
     */
    private $lastLogin;

    /**
     * @var int
     *
     * @ORM\Column(name="access_level", type="integer", nullable=false)
     */
    private $accessLevel = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=250, nullable=false)
     */
    private $email = '';

    /**
     * @var string
     *
     * @ORM\Column(name="full_name", type="string", length=250, nullable=false)
     */
    private $fullName = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="email_digest", type="boolean", nullable=false)
     */
    private $emailDigest = false;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_digest_sent", type="datetime", nullable=true)
     */
    private $lastDigestSent;

    /**
     * @var string
     *
     * @ORM\Column(name="salt", type="string", length=250, nullable=false)
     */
    private $salt = '';

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="created", type="datetime", nullable=true)
     */
    private $created;

    /**
     * @var string|null
     *
     * @ORM\Column(name="twitter_oauth", type="text", length=0, nullable=true)
     */
    private $twitterOauth;

    /**
     * @var bool
     *
     * @ORM\Column(name="otp_enabled", type="boolean", nullable=false)
     */
    private $otpEnabled = false;

    /**
     * @var string|null
     *
     * @ORM\Column(name="resetpass_token", type="string", length=250, nullable=true)
     */
    private $resetpassToken;
}

<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;

/**
 * @ORM\Table(indexes={@ORM\Index(name="owner_uid", columns={"owner_uid"})})
 * @ORM\Entity
 */
class ErrorLog extends Model
{
    use Identified,
        Owned;

    /**
     * @var int
     *
     * @ORM\Column(name="errno", type="integer", nullable=false)
     */
    private $errno;

    /**
     * @var string
     *
     * @ORM\Column(name="errstr", type="text", length=65535, nullable=false)
     */
    private $errstr;

    /**
     * @var string
     *
     * @ORM\Column(name="filename", type="text", length=65535, nullable=false)
     */
    private $filename;

    /**
     * @var int
     *
     * @ORM\Column(name="lineno", type="integer", nullable=false)
     */
    private $lineno;

    /**
     * @var string
     *
     * @ORM\Column(name="context", type="text", length=65535, nullable=false)
     */
    private $context;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false)
     */
    private $createdAt;
}

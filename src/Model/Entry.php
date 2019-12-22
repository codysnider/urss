<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;

/**
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="guid", columns={"guid"})}, indexes={@ORM\Index(name="ttrss_entries_updated_idx", columns={"updated"}), @ORM\Index(name="ttrss_entries_date_entered_index", columns={"date_entered"})})
 * @ORM\Entity
 */
class Entry extends Model
{
    use Identified;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="text", length=65535, nullable=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="guid", type="string", length=255, nullable=false)
     */
    private $guid;

    /**
     * @var string
     *
     * @ORM\Column(name="link", type="text", length=65535, nullable=false)
     */
    private $link;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="updated", type="datetime", nullable=false)
     */
    private $updated;

    /**
     * @var string
     *
     * @ORM\Column(name="content", type="text", length=0, nullable=false)
     */
    private $content;

    /**
     * @var string
     *
     * @ORM\Column(name="content_hash", type="string", length=250, nullable=false)
     */
    private $contentHash;

    /**
     * @var string|null
     *
     * @ORM\Column(name="cached_content", type="text", length=0, nullable=true)
     */
    private $cachedContent;

    /**
     * @var bool
     *
     * @ORM\Column(name="no_orig_date", type="boolean", nullable=false)
     */
    private $noOrigDate = false;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date_entered", type="datetime", nullable=false)
     */
    private $dateEntered;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date_updated", type="datetime", nullable=false)
     */
    private $dateUpdated;

    /**
     * @var int
     *
     * @ORM\Column(name="num_comments", type="integer", nullable=false)
     */
    private $numComments = 0;

    /**
     * @var string|null
     *
     * @ORM\Column(name="plugin_data", type="text", length=0, nullable=true)
     */
    private $pluginData;

    /**
     * @var string|null
     *
     * @ORM\Column(name="lang", type="string", length=2, nullable=true)
     */
    private $lang;

    /**
     * @var string
     *
     * @ORM\Column(name="comments", type="string", length=250, nullable=false)
     */
    private $comments = '';

    /**
     * @var string
     *
     * @ORM\Column(name="author", type="string", length=250, nullable=false)
     */
    private $author = '';
}

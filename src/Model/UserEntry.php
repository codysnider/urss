<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;

/**
 * @ORM\Table(indexes={@ORM\Index(name="ref_id", columns={"ref_id"}), @ORM\Index(name="orig_feed_id", columns={"orig_feed_id"}), @ORM\Index(name="ttrss_user_entries_unread_idx", columns={"unread"}), @ORM\Index(name="feed_id", columns={"feed_id"}), @ORM\Index(name="owner_uid", columns={"owner_uid"})})
 * @ORM\Entity
 */
class UserEntry extends Model
{
    use Identified,
        Owned;

    /**
     * @var string
     *
     * @ORM\Column(name="uuid", type="string", length=200, nullable=false)
     */
    private $uuid;

    /**
     * @var bool
     *
     * @ORM\Column(name="marked", type="boolean", nullable=false)
     */
    private $marked = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="published", type="boolean", nullable=false)
     */
    private $published = false;

    /**
     * @var string
     *
     * @ORM\Column(name="tag_cache", type="text", length=65535, nullable=false)
     */
    private $tagCache;

    /**
     * @var string
     *
     * @ORM\Column(name="label_cache", type="text", length=65535, nullable=false)
     */
    private $labelCache;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_read", type="datetime", nullable=true)
     */
    private $lastRead;

    /**
     * @var int
     *
     * @ORM\Column(name="score", type="integer", nullable=false)
     */
    private $score = 0;

    /**
     * @var string|null
     *
     * @ORM\Column(name="note", type="text", length=0, nullable=true)
     */
    private $note;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_marked", type="datetime", nullable=true)
     */
    private $lastMarked;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_published", type="datetime", nullable=true)
     */
    private $lastPublished;

    /**
     * @var bool
     *
     * @ORM\Column(name="unread", type="boolean", nullable=false)
     */
    private $unread = true;

    /**
     * @var Entry
     *
     * @ORM\ManyToOne(targetEntity="Entry")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="ref_id", referencedColumnName="id")
     * })
     */
    private $entry;

    /**
     * @var Feed
     *
     * @ORM\ManyToOne(targetEntity="Feed")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="feed_id", referencedColumnName="id")
     * })
     */
    private $feed;

    /**
     * @var ArchivedFeed
     *
     * @ORM\ManyToOne(targetEntity="ArchivedFeed")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="orig_feed_id", referencedColumnName="id")
     * })
     */
    private $origFeed;
}

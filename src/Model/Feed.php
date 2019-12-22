<?php

declare(strict_types=1);

namespace RssApp\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use RssApp\Model;
use RssApp\Model\Traits\Identified;
use RssApp\Model\Traits\Owned;

/**
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="feed_url", columns={"feed_url", "owner_uid"})}, indexes={@ORM\Index(name="owner_uid", columns={"owner_uid"}), @ORM\Index(name="parent_feed", columns={"parent_feed"}), @ORM\Index(name="cat_id", columns={"cat_id"})})
 * @ORM\Entity
 */
class Feed extends Model
{
    use Identified,
        Owned;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=200, nullable=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="feed_url", type="string", length=255, nullable=false)
     */
    private $feedUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="icon_url", type="string", length=250, nullable=false)
     */
    private $iconUrl = '';

    /**
     * @var int
     *
     * @ORM\Column(name="update_interval", type="integer", nullable=false)
     */
    private $updateInterval = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="purge_interval", type="integer", nullable=false)
     */
    private $purgeInterval = 0;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_updated", type="datetime", nullable=true)
     */
    private $lastUpdated;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_unconditional", type="datetime", nullable=true)
     */
    private $lastUnconditional;

    /**
     * @var string
     *
     * @ORM\Column(name="last_error", type="string", length=250, nullable=false)
     */
    private $lastError = '';

    /**
     * @var string
     *
     * @ORM\Column(name="last_modified", type="string", length=250, nullable=false)
     */
    private $lastModified = '';

    /**
     * @var string|null
     *
     * @ORM\Column(name="favicon_avg_color", type="string", length=11, nullable=true)
     */
    private $faviconAvgColor;

    /**
     * @var string
     *
     * @ORM\Column(name="site_url", type="string", length=250, nullable=false)
     */
    private $siteUrl = '';

    /**
     * @var string
     *
     * @ORM\Column(name="auth_login", type="string", length=250, nullable=false)
     */
    private $authLogin = '';

    /**
     * @var string
     *
     * @ORM\Column(name="auth_pass", type="string", length=250, nullable=false)
     */
    private $authPass = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="private", type="boolean", nullable=false)
     */
    private $private = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="rtl_content", type="boolean", nullable=false)
     */
    private $rtlContent = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="hidden", type="boolean", nullable=false)
     */
    private $hidden = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="include_in_digest", type="boolean", nullable=false)
     */
    private $includeInDigest = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="cache_images", type="boolean", nullable=false)
     */
    private $cacheImages = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="hide_images", type="boolean", nullable=false)
     */
    private $hideImages = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="cache_content", type="boolean", nullable=false)
     */
    private $cacheContent = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="auth_pass_encrypted", type="boolean", nullable=false)
     */
    private $authPassEncrypted = false;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_viewed", type="datetime", nullable=true)
     */
    private $lastViewed;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="last_update_started", type="datetime", nullable=true)
     */
    private $lastUpdateStarted;

    /**
     * @var bool
     *
     * @ORM\Column(name="always_display_enclosures", type="boolean", nullable=false)
     */
    private $alwaysDisplayEnclosures = false;

    /**
     * @var int
     *
     * @ORM\Column(name="update_method", type="integer", nullable=false)
     */
    private $updateMethod = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="order_id", type="integer", nullable=false)
     */
    private $orderId = 0;

    /**
     * @var bool
     *
     * @ORM\Column(name="mark_unread_on_update", type="boolean", nullable=false)
     */
    private $markUnreadOnUpdate = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="update_on_checksum_change", type="boolean", nullable=false)
     */
    private $updateOnChecksumChange = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="strip_images", type="boolean", nullable=false)
     */
    private $stripImages = false;

    /**
     * @var string
     *
     * @ORM\Column(name="view_settings", type="string", length=250, nullable=false)
     */
    private $viewSettings = '';

    /**
     * @var int
     *
     * @ORM\Column(name="pubsub_state", type="integer", nullable=false)
     */
    private $pubsubState = 0;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(name="favicon_last_checked", type="datetime", nullable=true)
     */
    private $faviconLastChecked;

    /**
     * @var string
     *
     * @ORM\Column(name="feed_language", type="string", length=100, nullable=false)
     */
    private $feedLanguage = '';

    /**
     * @var FeedCategory
     *
     * @ORM\ManyToOne(targetEntity="FeedCategory")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="cat_id", referencedColumnName="id")
     * })
     */
    private $category;

    /**
     * @var Feed
     *
     * @ORM\ManyToOne(targetEntity="Feed")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="parent_feed", referencedColumnName="id")
     * })
     */
    private $parent;
}

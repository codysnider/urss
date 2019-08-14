<?php
class Cache_Starred_Images extends Plugin {

	/* @var PluginHost $host */
	private $host;
	/* @var DiskCache $cache */
	private $cache;
    private $max_cache_attempts = 5; // per-article

	function about() {
		return array(1.0,
			"Automatically cache media files in Starred articles",
			"fox");
	}

	function init($host) {
		$this->host = $host;
		$this->cache = new DiskCache("starred-images");

		if ($this->cache->makeDir())
			chmod($this->cache->getDir(), 0777);

		if (!$this->cache->exists(".no-auto-expiry"))
			$this->cache->touch(".no-auto-expiry");

		if ($this->cache->isWritable()) {
			$host->add_hook($host::HOOK_HOUSE_KEEPING, $this);
			$host->add_hook($host::HOOK_ENCLOSURE_ENTRY, $this);
			$host->add_hook($host::HOOK_SANITIZE, $this);
		} else {
			user_error("Starred cache directory ".$this->cache->getDir()." is not writable.", E_USER_WARNING);
		}
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 */
	function hook_house_keeping() {
		/* since HOOK_UPDATE_TASK is not available to user plugins, this hook is a next best thing */

		Debug::log("caching media of starred articles for user " . $this->host->get_owner_uid() . "...");

		$sth = $this->pdo->prepare("SELECT content, ttrss_entries.title, 
       		ttrss_user_entries.owner_uid, link, site_url, ttrss_entries.id, plugin_data
			FROM ttrss_entries, ttrss_user_entries LEFT JOIN ttrss_feeds ON
				(ttrss_user_entries.feed_id = ttrss_feeds.id)
			WHERE ref_id = ttrss_entries.id AND
				marked = true AND
				site_url != '' AND 
			    ttrss_user_entries.owner_uid = ? AND
				plugin_data NOT LIKE '%starred_cache_images%'
			ORDER BY ".sql_random_function()." LIMIT 100");

		if ($sth->execute([$this->host->get_owner_uid()])) {

			$usth = $this->pdo->prepare("UPDATE ttrss_entries SET plugin_data = ? WHERE id = ?");

			while ($line = $sth->fetch()) {
				Debug::log("processing article " . $line["title"], Debug::$LOG_VERBOSE);

				if ($line["site_url"]) {
					$success = $this->cache_article_images($line["content"], $line["site_url"], $line["owner_uid"], $line["id"]);

					if ($success) {
						$plugin_data = "starred_cache_images,${line['owner_uid']}:" . $line["plugin_data"];

						$usth->execute([$plugin_data, $line['id']]);
					}
				}
			}
		}

		/* actual housekeeping */

		Debug::log("expiring " . $this->cache->getDir() . "...");

		$files = glob($this->cache->getDir() . "/*.{png,mp4,status}", GLOB_BRACE);

		$last_article_id = 0;
		$article_exists = 1;

		foreach ($files as $file) {
			list ($article_id, $hash) = explode("-", basename($file));

			if ($article_id != $last_article_id) {
				$last_article_id = $article_id;

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_entries WHERE id = ?");
				$sth->execute([$article_id]);

				$article_exists = $sth->fetch();
			}

			if (!$article_exists) {
				unlink($file);
			}
		}
	}

	function hook_enclosure_entry($enc, $article_id) {
		$local_filename = $article_id . "-" . sha1($enc["content_url"]);

		if ($this->cache->exists($local_filename)) {
			$enc["content_url"] = $this->cache->getUrl($local_filename);
		}

		return $enc;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id) {
		$xpath = new DOMXpath($doc);

		if ($article_id) {
			$entries = $xpath->query('(//img[@src])|(//video/source[@src])');

			foreach ($entries as $entry) {
				if ($entry->hasAttribute('src')) {
					$src = rewrite_relative_url($site_url, $entry->getAttribute('src'));

					$local_filename = $article_id . "-" . sha1($src);

					if ($this->cache->exists($local_filename)) {
						$entry->setAttribute("src", $this->cache->getUrl($local_filename));
						$entry->removeAttribute("srcset");
					}
				}
			}
		}

		return $doc;
	}

	private function cache_url($article_id, $url) {
		$local_filename = $article_id . "-" . sha1($url);

		if (!$this->cache->exists($local_filename)) {
			Debug::log("cache_images: downloading: $url to $local_filename", Debug::$LOG_VERBOSE);

			$data = fetch_file_contents(["url" => $url, "max_size" => MAX_CACHE_FILE_SIZE]);

			if ($data)
				return $this->cache->put($local_filename, $data);;

		} else {
			//Debug::log("cache_images: local file exists for $url", Debug::$LOG_VERBOSE);

			return true;
		}

		return false;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	private function cache_article_images($content, $site_url, $owner_uid, $article_id) {
		$status_filename = $article_id . "-" . sha1($site_url) . ".status";

		/* housekeeping might run as a separate user, in this case status/media might not be writable */
		if (!$this->cache->isWritable($status_filename)) {
			Debug::log("status not writable: $status_filename", Debug::$LOG_VERBOSE);
			return false;
		}

		Debug::log("status: $status_filename", Debug::$LOG_VERBOSE);

        if ($this->cache->exists($status_filename))
            $status = json_decode($this->cache->get($status_filename), true);
        else
            $status = [];

        $status["attempt"] += 1;

        // only allow several download attempts for article
        if ($status["attempt"] > $this->max_cache_attempts) {
            Debug::log("too many attempts for $site_url", Debug::$LOG_VERBOSE);
            return false;
        }

        if (!$this->cache->put($status_filename, json_encode($status))) {
            user_error("unable to write status file: $status_filename", E_USER_WARNING);
            return false;
        }

		$doc = new DOMDocument();

		$has_images = false;
		$success = false;

        if ($doc->loadHTML('<?xml encoding="UTF-8">' . $content)) {
			$xpath = new DOMXPath($doc);
			$entries = $xpath->query('(//img[@src])|(//video/source[@src])');

			foreach ($entries as $entry) {

				if ($entry->hasAttribute('src') && strpos($entry->getAttribute('src'), "data:") !== 0) {

					$has_images = true;

					$src = rewrite_relative_url($site_url, $entry->getAttribute('src'));

					if ($this->cache_url($article_id, $src)) {
						$success = true;
					}
				}
			}
		}

		$esth = $this->pdo->prepare("SELECT content_url FROM ttrss_enclosures WHERE post_id = ? AND
			(content_type LIKE '%image%' OR content_type LIKE '%video%')");

        if ($esth->execute([$article_id])) {
        	while ($enc = $esth->fetch()) {

        		$has_images = true;
        		$url = rewrite_relative_url($site_url, $enc["content_url"]);

				if ($this->cache_url($article_id, $url)) {
					$success = true;
				}
			}
		}

		return $success || !$has_images;
	}

	function api_version() {
		return 2;
	}
}

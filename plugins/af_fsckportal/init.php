<?php
class Af_Fsckportal extends Plugin {

	private $host;

	public function about() {
		return array(1.0,
			"Remove feedsportal spamlinks from article content",
			"fox");
	}

	public function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	public function hook_article_filter($article) {

			$doc = new DOMDocument();

			@$doc->loadHTML('<?xml encoding="UTF-8">' . $article["content"]);

			if ($doc) {
				$xpath = new DOMXPath($doc);
				$entries = $xpath->query('(//img[@src]|//a[@href])');

				foreach ($entries as $entry) {
					if (preg_match("/feedsportal.com/", $entry->getAttribute("src"))) {
						$entry->parentNode->removeChild($entry);
					} else if (preg_match("/feedsportal.com/", $entry->getAttribute("href"))) {
						$entry->parentNode->removeChild($entry);
					}
				}

				$article["content"] = $doc->saveHTML();

		}

		return $article;
	}

	public function api_version() {
		return 2;
	}

}

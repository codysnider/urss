<?php
class Af_Comics extends Plugin {

	private $host;
	private $filters = array();

	function about() {
		return array(2.0,
			"Fixes RSS feeds of assorted comic strips",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_FETCH_FEED, $this);
		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);

		require_once __DIR__ . "/filter_base.php";

		$filters = array_merge(glob(__DIR__ . "/filters.local/*.php"), glob(__DIR__ . "/filters/*.php"));
		$names = [];

		foreach ($filters as $file) {
			$filter_name = preg_replace("/\..*$/", "", basename($file));

			if (array_search($filter_name, $names) === FALSE) {
				if (!class_exists($filter_name)) {
					require_once $file;
				}

				array_push($names, $filter_name);

				$filter = new $filter_name();

				if (is_subclass_of($filter, "Af_ComicFilter")) {
					array_push($this->filters, $filter);
					array_push($names, $filter_name);
				}
			}
		}
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Feeds supported by af_comics')."\">";

		print "<p>" . __("The following comics are currently supported:") . "</p>";

		$comics = array("GoComics");

		foreach ($this->filters as $f) {
			foreach ($f->supported() as $comic) {
				array_push($comics, $comic);
			}
		}

		asort($comics);

		print "<ul class=\"browseFeedList\" style=\"border-width : 1px\">";
		foreach ($comics as $comic) {
			print "<li>$comic</li>";
		}
		print "</ul>";

		print "<p>".__('GoComics requires a specific URL to workaround their lack of feed support: <code>http://feeds.feedburner.com/uclick/<em>comic_name</em></code> (e.g. <code>http://www.gocomics.com/garfield</code> uses <code>http://feeds.feedburner.com/uclick/garfield</code>).')."</p>";

		print "<p>".__('Drop any updated filters into <code>filters.local</code> in plugin directory.')."</p>";

		print "</div>";
	}

	function hook_article_filter($article) {
		foreach ($this->filters as $f) {
			if ($f->process($article))
				break;
		}

		return $article;
	}

	// GoComics dropped feed support so it needs to be handled when fetching the feed.
	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed, $last_article_timestamp, $auth_login, $auth_pass) {
		if ($auth_login || $auth_pass)
			return $feed_data;

		if (preg_match('#^https?://feeds.feedburner.com/uclick/([-a-z]+)#', $fetch_url, $comic)) {
			$site_url = 'http://www.gocomics.com/' . $comic[1];

			$article_link = $site_url . date('/Y/m/d');

			$body = fetch_file_contents(array('url' => $article_link, 'type' => 'text/html', 'followlocation' => false));

			require_once 'lib/MiniTemplator.class.php';

			$feed_title = htmlspecialchars($comic[1]);
			$site_url = htmlspecialchars($site_url);
			$article_link = htmlspecialchars($article_link);

			$tpl = new MiniTemplator();

			$tpl->readTemplateFromFile('templates/generated_feed.txt');

			$tpl->setVariable('FEED_TITLE', $feed_title, true);
			$tpl->setVariable('VERSION', VERSION, true);
			$tpl->setVariable('FEED_URL', htmlspecialchars($fetch_url), true);
			$tpl->setVariable('SELF_URL', $site_url, true);

			$tpl->setVariable('ARTICLE_UPDATED_ATOM', date('c'), true);
			$tpl->setVariable('ARTICLE_UPDATED_RFC822', date(DATE_RFC822), true);

			if ($body) {
				$doc = new DOMDocument();

				if (@$doc->loadHTML($body)) {
					$xpath = new DOMXPath($doc);

					$node = $xpath->query('//picture[contains(@class, "item-comic-image")]/img')->item(0);

					if ($node) {
						$tpl->setVariable('ARTICLE_ID', $article_link, true);
						$tpl->setVariable('ARTICLE_LINK', $article_link, true);
						$tpl->setVariable('ARTICLE_TITLE', date('l, F d, Y'), true);
						$tpl->setVariable('ARTICLE_EXCERPT', '', true);
						$tpl->setVariable('ARTICLE_CONTENT', $doc->saveXML($node), true);

						$tpl->setVariable('ARTICLE_AUTHOR', '', true);
						$tpl->setVariable('ARTICLE_SOURCE_LINK', $site_url, true);
						$tpl->setVariable('ARTICLE_SOURCE_TITLE', $feed_title, true);

						$tpl->addBlock('entry');
					}
				}
			}

			$tpl->addBlock('feed');

			$tmp_data = '';

			if ($tpl->generateOutputToString($tmp_data))
				$feed_data = $tmp_data;
		}

		return $feed_data;
	}

	function api_version() {
		return 2;
	}

}
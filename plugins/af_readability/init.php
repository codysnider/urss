<?php
class Af_Readability extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Try to inline article content using Readability",
			"fox");
	}

	function flags() {
		return array("needs_curl" => true);
	}

	function save() {
		$enable_share_anything = checkbox_to_sql_bool($_POST["enable_share_anything"]) == "true";

		$this->host->set($this, "enable_share_anything", $enable_share_anything);

		echo __("Data saved.");
	}

	function init($host)
	{
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
		$host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);

		$host->add_filter_action($this, "action_inline", __("Inline content"));
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('af_readability settings')."\">";

		print_notice("Enable the plugin for specific feeds in the feed editor.");

		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				console.log(dojo.objectToQuery(this.getValues()));
				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						notify_info(transport.responseText);
					}
				});
				//this.reset();
			}
			</script>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"af_readability\">";

		$enable_share_anything = $this->host->get($this, "enable_share_anything");
		$enable_share_anything_checked = $enable_share_anything ? "checked" : "";

		print "<input dojoType=\"dijit.form.CheckBox\"
			$enable_share_anything_checked name=\"enable_share_anything\" id=\"enable_share_anything\">
			<label for=\"enable_share_anything\">" . __("Use Readability for pages shared via bookmarklet.") . "</label>";

		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
				__("Save")."</button>";

		print "</form>";

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();

		$enabled_feeds = $this->filter_unknown_feeds($enabled_feeds);
		$this->host->set($this, "enabled_feeds", $enabled_feeds);

		if (count($enabled_feeds) > 0) {
			print "<h3>" . __("Currently enabled for (click to edit):") . "</h3>";

			print "<ul class=\"browseFeedList\" style=\"border-width : 1px\">";
			foreach ($enabled_feeds as $f) {
				print "<li>" .
					"<img src='images/pub_set.png'
						style='vertical-align : middle'> <a href='#'
						onclick='editFeed($f)'>".
					getFeedTitle($f) . "</a></li>";
			}
			print "</ul>";
		}

		print "</div>";
	}

	function hook_prefs_edit_feed($feed_id) {
		print "<div class=\"dlgSec\">".__("Readability")."</div>";
		print "<div class=\"dlgSecCont\">";

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();

		$key = array_search($feed_id, $enabled_feeds);
		$checked = $key !== FALSE ? "checked" : "";

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"af_readability_enabled\"
			name=\"af_readability_enabled\"
			$checked>&nbsp;<label for=\"af_readability_enabled\">".__('Inline article content')."</label>";

		print "</div>";
	}

	function hook_prefs_save_feed($feed_id) {
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();

		$enable = checkbox_to_sql_bool($_POST["af_readability_enabled"]) == 'true';
		$key = array_search($feed_id, $enabled_feeds);

		if ($enable) {
			if ($key === FALSE) {
				array_push($enabled_feeds, $feed_id);
			}
		} else {
			if ($key !== FALSE) {
				unset($enabled_feeds[$key]);
			}
		}

		$this->host->set($this, "enabled_feeds", $enabled_feeds);
	}

	function hook_article_filter_action($article, $action) {
		return $this->process_article($article);
	}

	public function extract_content($url) {
		if (!class_exists("Readability")) require_once(dirname(dirname(__DIR__)). "/lib/readability/Readability.php");

		if (!defined('NO_CURL') && function_exists('curl_init') && !ini_get("open_basedir")) {

			$ch = curl_init($url);

			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_USERAGENT, SELF_USER_AGENT);

			@$result = curl_exec($ch);
			$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

			if (strpos($content_type, "text/html") === FALSE)
				return false;
		}

		$tmp = fetch_file_contents($url);

		if ($tmp && mb_strlen($tmp) < 65535 * 4) {
			$tmpdoc = new DOMDocument("1.0", "UTF-8");

			if (!$tmpdoc->loadHTML($tmp))
				return false;

			if (strtolower($tmpdoc->encoding) != 'utf-8') {
				$tmpxpath = new DOMXPath($tmpdoc);

				foreach ($tmpxpath->query("//meta") as $elem) {
					$elem->parentNode->removeChild($elem);
				}

				$tmp = $tmpdoc->saveHTML();
			}

			$r = new Readability($tmp, $url);

			if ($r->init()) {
				$tmpxpath = new DOMXPath($r->dom);

				$entries = $tmpxpath->query('(//a[@href]|//img[@src])');

				foreach ($entries as $entry) {
					if ($entry->hasAttribute("href")) {
						$entry->setAttribute("href",
								rewrite_relative_url($url, $entry->getAttribute("href")));

					}

					if ($entry->hasAttribute("src")) {
						$entry->setAttribute("src",
								rewrite_relative_url($url, $entry->getAttribute("src")));

					}

				}

				return $r->articleContent->innerHTML;
			}
		}

		return false;
	}

	function process_article($article) {

		$extracted_content = $this->extract_content($article["link"]);

		if ($extracted_content) {
			$article["content"] = $extracted_content;
		}

		return $article;
	}

	function hook_article_filter($article) {

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) return $article;

		$key = array_search($article["feed"]["id"], $enabled_feeds);
		if ($key === FALSE) return $article;

		return $this->process_article($article);

	}

	function api_version() {
		return 2;
	}

	private function filter_unknown_feeds($enabled_feeds) {
		$tmp = array();

		foreach ($enabled_feeds as $feed) {

			$result = db_query("SELECT id FROM ttrss_feeds WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) != 0) {
				array_push($tmp, $feed);
			}
		}

		return $tmp;
	}

}
?>

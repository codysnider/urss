<?php
class Embed_Original extends Plugin {

	/* @var PluginHost $host */
	private $host;

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

	function about() {
		return array(1.0,
			"Try to display original article content inside tt-rss",
			"fox");
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/init.js");
	}

	function get_css() {
		return file_get_contents(dirname(__FILE__) . "/init.css");
	}

	function hook_article_button($line) {
		$id = $line["id"];

		$rv = "<img src=\"plugins/embed_original/button.png\"
			class='tagsPic' style=\"cursor : pointer\"
			onclick=\"embedOriginalArticle($id)\"
			title='".__('Toggle embed original')."'>";

		return $rv;
	}

	function getUrl() {
		$id = $_REQUEST['id'];

		$sth = $this->pdo->prepare("SELECT link
				FROM ttrss_entries, ttrss_user_entries
				WHERE id = ? AND ref_id = id AND owner_uid = ?");
		$sth->execute([$id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			$url = $row['link'];
		} else {
			$url = "";
		}

		print json_encode(array("url" => $url, "id" => $id));
	}

	function api_version() {
		return 2;
	}

}
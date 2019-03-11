<?php
class Hotkeys_Noscroll extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"n/p hotkeys move between articles without scrolling",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_HOTKEY_MAP, $this);
	}

	function hook_hotkey_map($hotkeys) {

		$hotkeys["(40)|Down"] = "next_article_noscroll";
		$hotkeys["(38)|Up"] = "prev_article_noscroll";
		$hotkeys["n"] = "next_article_noscroll";
		$hotkeys["p"] = "prev_article_noscroll";

		return $hotkeys;
	}

	function api_version() {
		return 2;
	}

}

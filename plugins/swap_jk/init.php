<?php
class Swap_JK extends Plugin {

	private $host;

	public function about() {
		return array(1.0,
			"Swap j and k hotkeys (for vi brethren)",
			"fox");
	}

	public function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_HOTKEY_MAP, $this);
	}

	public function hook_hotkey_map($hotkeys) {

		$hotkeys["j"] = "next_feed";
		$hotkeys["k"] = "prev_feed";

		return $hotkeys;
	}

	public function api_version() {
		return 2;
	}

}

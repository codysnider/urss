<?php
class No_Title_Counters extends Plugin {
	private $host;

	public function about() {
		return array(1.0,
			"Remove counters from window title (prevents tab flashing on new articles)",
			"fox");
	}

	public function init($host) {
		$this->host = $host;

	}

	public function get_js() {
		return file_get_contents(__DIR__ . "/init.js");
	}

	public function api_version() {
		return 2;
	}

}

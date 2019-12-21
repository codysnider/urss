<?php
class No_URL_Hashes extends Plugin {
	private $host;

	public function about() {
		return array(1.0,
			"Disable URL hash usage (e.g. #f=10, etc)",
			"fox");
	}

	public function init($host) {
		$this->host = $host;

	}

	public function get_js() {
		return file_get_contents(__DIR__."/init.js");
	}

	public function api_version() {
		return 2;
	}

}

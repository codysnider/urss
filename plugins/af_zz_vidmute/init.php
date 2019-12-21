<?php
class Af_Zz_VidMute extends Plugin {
	private $host;

	public function about() {
		return array(1.0,
			"Mute audio in HTML5 videos",
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

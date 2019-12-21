<?php
class Af_Zz_NoAutoPlay extends Plugin {
    private $host;

    public function about() {
        return array(1.0,
            "Don't autoplay HTML5 videos",
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

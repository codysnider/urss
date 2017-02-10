<?php
class Af_Zz_ImgProxy extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Load insecure images via built-in proxy",
			"fox");
	}

	function flags() {
		return array("needs_curl" => true);
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_RENDER_ARTICLE, $this);
		$host->add_hook($host::HOOK_RENDER_ARTICLE_CDM, $this);
		$host->add_hook($host::HOOK_RENDER_ARTICLE_API, $this);
	}

	function hook_render_article($article) {
		return $this->hook_render_article_cdm($article);
	}

	function hook_render_article_api($headline) {
		return $this->hook_render_article_cdm($headline["headline"], true);
	}

	public function imgproxy() {
		$url = rewrite_relative_url(SELF_URL_PATH, $_REQUEST["url"]);
		$kind = (int) $_REQUEST["kind"]; // 1 = video

		$extension = $kind == 1 ? '.mp4' : '.png';
		$local_filename = CACHE_DIR . "/images/" . sha1($url) . $extension;

		//if ($_REQUEST["debug"] == "1") { print $local_filename; die; }

		header("Content-Disposition: inline; filename=\"".basename($local_filename)."\"");

		if (file_exists($local_filename)) {
			$mimetype = mime_content_type($local_filename);
			header("Content-type: $mimetype");

			$stamp = gmdate("D, d M Y H:i:s", filemtime($local_filename)). " GMT";
			header("Last-Modified: $stamp", true);

			readfile($local_filename);
		} else {
			$data = fetch_file_contents(array("url" => $url));
			if ($data) {
				if (file_put_contents($local_filename, $data)) {
					$mimetype = mime_content_type($local_filename);
					header("Content-type: $mimetype");
				}

				print $data;
			}
		}
	}

	function rewrite_url_if_needed($url, $kind = 0) {
		$scheme = parse_url($url, PHP_URL_SCHEME);

		if ($scheme != 'https' && $scheme != "" && strpos($url, "data:") !== 0) {
			$url = "backend.php?op=pluginhandler&plugin=af_zz_imgproxy&method=imgproxy&kind=$kind&url=" .
				htmlspecialchars($url);
		}

		return $url;
	}

	function hook_render_article_cdm($article, $api_mode = false) {

		$need_saving = false;

		$doc = new DOMDocument();
		if (@$doc->loadHTML($article["content"])) {
			$xpath = new DOMXPath($doc);
			$imgs = $xpath->query("//img[@src]");

			foreach ($imgs as $img) {
				$new_src = $this->rewrite_url_if_needed($img->getAttribute("src"));

				if ($new_src != $img->getAttribute("src")) {
					$img->setAttribute("src", $new_src);

					$need_saving = true;
				}
			}

			$vids = $xpath->query("//video");

			foreach ($vids as $vid) {
				if ($vid->hasAttribute("poster")) {
					$new_src = $this->rewrite_url_if_needed($vid->getAttribute("poster"));

					if ($new_src != $vid->getAttribute("poster")) {
						$vid->setAttribute("poster", $new_src);

						$need_saving = true;
					}
				}

				$vsrcs = $xpath->query("source", $vid);

				foreach ($vsrcs as $vsrc) {
					$new_src = $this->rewrite_url_if_needed($vsrc->getAttribute("src"), 1);

					if ($new_src != $vsrc->getAttribute("src")) {
						$vid->setAttribute("src", $new_src);

						$need_saving = true;
					}
				}
			}
		}

		if ($need_saving) $article["content"] = $doc->saveXML();

		return $article;
	}

	function api_version() {
		return 2;
	}
}
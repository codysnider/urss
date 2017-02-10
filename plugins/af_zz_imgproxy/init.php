<?php
class Af_Zz_ImgProxy extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Load insecure images via built-in proxy (no caching)",
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

	/*public function vidproxy() {
		$url = $_REQUEST["url"];

		if (preg_match("/\.(mp4|webm|gifv)/", $url, $matches)) {
			$type = $matches[1];
			$embed_url = $url;

			if ($type == "gifv") {
				$type = "mp4";
				$embed_url = str_replace(".gifv", ".mp4", $embed_url);
			}

			header("Content-type: text/html");

			$embed_url = htmlspecialchars("backend.php?op=pluginhandler&plugin=af_zz_imgproxy&method=imgproxy&url=" .
				urlencode($embed_url));

			print "<video class=\"\" autoplay=\"true\" controls=\"true\" loop=\"true\">";
			print "<source src=\"$embed_url\" type=\"video/$type\">";
			print "</video>";
		} else {
			header("Location: " . htmlspecialchars($url));
		}
	}*/

	public function imgproxy() {
		$url = rewrite_relative_url(SELF_URL_PATH, $_REQUEST["url"]);

		if (function_exists("getimagesize")) {
			$is = @getimagesize($url);
			header("Content-type: " . $is["mime"]);
		}

		print fetch_file_contents(array("url" => $url));
	}

	function rewrite_url_if_needed($url) {
		$scheme = parse_url($url, PHP_URL_SCHEME);

		if ($scheme != 'https' && $scheme != "") {
			$url = "backend.php?op=pluginhandler&plugin=af_zz_imgproxy&method=imgproxy&url=" .
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
					$new_src = $this->rewrite_url_if_needed($vsrc->getAttribute("src"));

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
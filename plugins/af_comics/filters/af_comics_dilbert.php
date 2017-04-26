<?php
class Af_Comics_Dilbert extends Af_ComicFilter {

	function supported() {
		return array("Dilbert");
	}

	function process(&$article) {
		if (strpos($article["link"], "dilbert.com") !== FALSE) {
				$res = fetch_file_contents($article["link"], false, false, false,
					 false, false, 0,
					 "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)");

				global $fetch_last_error_content;

				if (!$res && $fetch_last_error_content)
					$res = $fetch_last_error_content;

				$doc = new DOMDocument();
				@$doc->loadHTML($res);

				if ($doc) {
					$xpath = new DOMXPath($doc);

					$basenode = $xpath->query('//img[contains(@class, "img-comic")]')->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
					}
				}

			return true;
		}

		return false;
	}
}
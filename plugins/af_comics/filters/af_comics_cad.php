<?php
class Af_Comics_Cad extends Af_ComicFilter {

	function supported() {
		return array("Ctrl+Alt+Del");
	}

	function process(&$article) {
		if (strpos($article["link"], "cad-comic.com/cad/") !== FALSE) {
			if (strpos($article["title"], "News:") === FALSE) {

				$doc = new DOMDocument();

				if (@$doc->loadHTML(fetch_file_contents($article["link"]))) {
					$xpath = new DOMXPath($doc);
					$basenode = $xpath->query('(//img[contains(@src, "/comics/cad-")])')->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
					}
				}

			}

			return true;
		}

		return false;
	}
}
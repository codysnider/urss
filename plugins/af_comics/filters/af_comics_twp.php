<?php
class Af_Comics_Twp extends Af_ComicFilter {

	public function supported() {
		return array("Three Word Phrase");
	}

	public function process(&$article) {

		if (strpos($article["link"], "threewordphrase.com") !== false) {

				$doc = new DOMDocument();

				if (@$doc->loadHTML(fetch_file_contents($article["link"]))) {
					$xpath = new DOMXpath($doc);

					$basenode = $xpath->query("//td/center/img")->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveHTML($basenode);
					}
				}

			return true;
		}

		return false;
	}
}

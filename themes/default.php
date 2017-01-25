<?php
	header("Content-type: text/css");

	function import_tag($filename) {
		return "@import \"$filename?".filemtime($filename)."\";";
	}

	$styles = [ "tt-rss.css", "dijit.css", "cdm.css", "prefs.css" ];

	foreach ($styles as $style) {
		print import_tag("../css/$style") . "\n";
	}
?>


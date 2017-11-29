<?php
	header("Content-type: text/css");

	function import_tag($filename) {
		return "@import \"$filename?".filemtime($filename)."\";";
	}

	print import_tag("../css/default.css") . "\n";
?>


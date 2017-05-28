<?php
	require_once "functions.php";

	spl_autoload_register(function($class) {
		$class_file = str_replace("_", "/", strtolower(basename($class)));

		$file = dirname(__FILE__)."/../classes/$class_file.php";

		if (file_exists($file)) {
			require $file;
		}

	});

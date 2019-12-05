<?php

	function get_version(&$git_commit = false, &$git_timestamp = false) {
		$version = "UNKNOWN (Unsupported)";

		date_default_timezone_set('UTC');
		$root_dir = dirname(dirname(__FILE__));

		if (is_dir("$root_dir/.git")) {
			$rc = 0;
			$output = [];

			exec("git log --pretty='%ct %h' -n1 HEAD " . escapeshellarg($root_dir), $output, $rc);

			if ($rc == 0) {
				if (is_array($output) && count($output) > 0) {
					list ($timestamp, $commit) = explode(" ", $output[0], 2);

					$git_commit = $commit;
					$git_timestamp = $timestamp;

					$version = strftime("%y.%m", $timestamp) . "-$commit";
				}
			}
		}

		return $version;
	}

	define('VERSION', get_version());

<?php

	/* for package maintainers who don't use git: if version_static.txt exists in tt-rss root
		directory, its contents are displayed instead of git commit-based version, this could be generated
		based on source git tree commit used when creating the package */

	function get_version(&$git_commit = false, &$git_timestamp = false) {
		$version = "UNKNOWN (Unsupported)";

		date_default_timezone_set('UTC');
		$root_dir = dirname(dirname(__FILE__));

		if (file_exists("$root_dir/version_static.txt")) {
			$version = trim(file_get_contents("$root_dir/version_static.txt")) . " (Unsupported)";
		} else if (is_dir("$root_dir/.git")) {
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

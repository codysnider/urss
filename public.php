<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "autoload.php";
	require_once "sessions.php";
	require_once "functions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	startup_gettext();

	$script_started = microtime(true);

	if (!init_plugins()) return;

	if (ENABLE_GZIP_OUTPUT && function_exists("ob_gzhandler")) {
		ob_start("ob_gzhandler");
	}

	$method = $_REQUEST["op"];

	$override = PluginHost::getInstance()->lookup_handler("public", $method);

	if ($override) {
		$handler = $override;
	} else {
		$handler = new Handler_Public($_REQUEST);
	}

	if (implements_interface($handler, "IHandler") && $handler->before($method)) {
		if ($method && method_exists($handler, $method)) {
			$handler->$method();
		} else if (method_exists($handler, 'index')) {
			$handler->index();
		}
		$handler->after();
		return;
	}

	header("Content-Type: text/plain");
	print error_json(13);
?>

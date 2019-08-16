<?php
class PluginHandler extends Handler_Protected {
	function csrf_ignore($method) {
		return true;
	}

	function catchall($method) {
		$plugin_name = clean($_REQUEST["plugin"]);
		$plugin = PluginHost::getInstance()->get_plugin($plugin_name);

		if ($plugin) {
			if (method_exists($plugin, $method)) {
				$plugin->$method();
			} else {
				user_error("PluginHandler: Requested unknown method '$method' of plugin '$plugin_name'.", E_USER_WARNING);
				print error_json(13);
			}
		} else {
			user_error("PluginHandler: Requested method '$method' of unknown plugin '$plugin_name'.", E_USER_WARNING);
			print error_json(14);
		}
	}
}

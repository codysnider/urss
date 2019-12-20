<?php

abstract class Plugin {

	const API_VERSION_COMPAT = 1;

	/**
	 * @var PDO
	 */
	protected $pdo;

	abstract function init(PluginHost $host);

	/**
	 * @return array(version, name, description, author, false)
	 */
	abstract function about();

	public function __construct() {
		$this->pdo = Db::pdo();
	}

	public function flags() {
		/* associative array, possible keys:
			needs_curl = boolean
		*/
		return array();
	}

	public function is_public_method() {
		return false;
	}

	public function get_js() {
		return "";
	}

	public function get_prefs_js() {
		return "";
	}

	public function api_version() {
		return Plugin::API_VERSION_COMPAT;
	}

	/* gettext-related helpers */

	public function __($msgid) {
		return _dgettext(PluginHost::object_to_domain($this), $msgid);
	}

	public function _ngettext($singular, $plural, $number) {
		return _dngettext(PluginHost::object_to_domain($this), $singular, $plural, $number);
	}

	public function T_sprintf() {
		$args = func_get_args();
		$msgid = array_shift($args);

		return vsprintf($this->__($msgid), $args);
	}
}

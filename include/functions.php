<?php
	define('EXPECTED_CONFIG_VERSION', 26);
	define('SCHEMA_VERSION', 131);

	define('LABEL_BASE_INDEX', -1024);
	define('PLUGIN_FEED_BASE_INDEX', -128);

	define('COOKIE_LIFETIME_LONG', 86400*365);

	$fetch_last_error = false;
	$fetch_last_error_code = false;
	$fetch_last_content_type = false;
	$fetch_last_error_content = false; // curl only for the time being
	$fetch_curl_used = false;
	$suppress_debugging = false;

	libxml_disable_entity_loader(true);

	// separate test because this is included before sanity checks
	if (function_exists("mb_internal_encoding")) mb_internal_encoding("UTF-8");

	date_default_timezone_set('UTC');
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
	} else {
		error_reporting(E_ALL & ~E_NOTICE);
	}

	require_once 'config.php';

	/**
	 * Define a constant if not already defined
	 */
	function define_default($name, $value) {
		defined($name) or define($name, $value);
	}

	/* Some tunables you can override in config.php using define():	*/

	define_default('FEED_FETCH_TIMEOUT', 45);
	// How may seconds to wait for response when requesting feed from a site
	define_default('FEED_FETCH_NO_CACHE_TIMEOUT', 15);
	// How may seconds to wait for response when requesting feed from a
	// site when that feed wasn't cached before
	define_default('FILE_FETCH_TIMEOUT', 45);
	// Default timeout when fetching files from remote sites
	define_default('FILE_FETCH_CONNECT_TIMEOUT', 15);
	// How many seconds to wait for initial response from website when
	// fetching files from remote sites
	define_default('DAEMON_UPDATE_LOGIN_LIMIT', 30);
	// stop updating feeds if users haven't logged in for X days
	define_default('DAEMON_FEED_LIMIT', 500);
	// feed limit for one update batch
	define_default('DAEMON_SLEEP_INTERVAL', 120);
	// default sleep interval between feed updates (sec)
	define_default('MIN_CACHE_FILE_SIZE', 1024);
	// do not cache files smaller than that (bytes)
	define_default('CACHE_MAX_DAYS', 7);
	// max age in days for various automatically cached (temporary) files

	/* tunables end here */

	if (DB_TYPE == "pgsql") {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING_FOR_DATE');
	} else {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING');
	}

	/**
	 * Return available translations names.
	 *
	 * @access public
	 * @return array A array of available translations.
	 */
	function get_translations() {
		$tr = array(
					"auto"  => "Detect automatically",
					"ar_SA" => "العربيّة (Arabic)",
					"bg_BG" => "Bulgarian",
					"da_DA" => "Dansk",
					"ca_CA" => "Català",
					"cs_CZ" => "Česky",
					"en_US" => "English",
					"el_GR" => "Ελληνικά",
					"es_ES" => "Español (España)",
					"es_LA" => "Español",
					"de_DE" => "Deutsch",
					"fr_FR" => "Français",
					"hu_HU" => "Magyar (Hungarian)",
					"it_IT" => "Italiano",
					"ja_JP" => "日本語 (Japanese)",
					"lv_LV" => "Latviešu",
					"nb_NO" => "Norwegian bokmål",
					"nl_NL" => "Dutch",
					"pl_PL" => "Polski",
					"ru_RU" => "Русский",
					"pt_BR" => "Portuguese/Brazil",
					"pt_PT" => "Portuguese/Portugal",
					"zh_CN" => "Simplified Chinese",
					"zh_TW" => "Traditional Chinese",
					"sv_SE" => "Svenska",
					"fi_FI" => "Suomi",
					"tr_TR" => "Türkçe");

		return $tr;
	}

	require_once "lib/accept-to-gettext.php";
	require_once "lib/gettext/gettext.inc";

	function startup_gettext() {

		# Get locale from Accept-Language header
		$lang = al2gt(array_keys(get_translations()), "text/html");

		if (defined('_TRANSLATION_OVERRIDE_DEFAULT')) {
			$lang = _TRANSLATION_OVERRIDE_DEFAULT;
		}

		if ($_SESSION["uid"] && get_schema_version() >= 120) {
			$pref_lang = get_pref("USER_LANGUAGE", $_SESSION["uid"]);

			if ($pref_lang && $pref_lang != 'auto') {
				$lang = $pref_lang;
			}
		}

		if ($lang) {
			if (defined('LC_MESSAGES')) {
				_setlocale(LC_MESSAGES, $lang);
			} else if (defined('LC_ALL')) {
				_setlocale(LC_ALL, $lang);
			}

			_bindtextdomain("messages", "locale");

			_textdomain("messages");
			_bind_textdomain_codeset("messages", "UTF-8");
		}
	}

	require_once 'db-prefs.php';
	require_once 'version.php';
	require_once 'controls.php';

	define('SELF_USER_AGENT', 'Tiny Tiny RSS/' . VERSION . ' (http://tt-rss.org/)');
	ini_set('user_agent', SELF_USER_AGENT);

	$schema_version = false;

	function _debug_suppress($suppress) {
		global $suppress_debugging;

		$suppress_debugging = $suppress;
	}

	/**
	 * Print a timestamped debug message.
	 *
	 * @param string $msg The debug message.
	 * @return void
	 */
	function _debug($msg, $show = true) {
		global $suppress_debugging;

		//echo "[$suppress_debugging] $msg $show\n";

		if ($suppress_debugging) return false;

		$ts = strftime("%H:%M:%S", time());
		if (function_exists('posix_getpid')) {
			$ts = "$ts/" . posix_getpid();
		}

		if ($show && !(defined('QUIET') && QUIET)) {
			print "[$ts] $msg\n";
		}

		if (defined('LOGFILE'))  {
			$fp = fopen(LOGFILE, 'a+');

			if ($fp) {
				$locked = false;

				if (function_exists("flock")) {
					$tries = 0;

					// try to lock logfile for writing
					while ($tries < 5 && !$locked = flock($fp, LOCK_EX | LOCK_NB)) {
						sleep(1);
						++$tries;
					}

					if (!$locked) {
						fclose($fp);
						return;
					}
				}

				fputs($fp, "[$ts] $msg\n");

				if (function_exists("flock")) {
					flock($fp, LOCK_UN);
				}

				fclose($fp);
			}
		}

	} // function _debug

	/**
	 * Purge a feed old posts.
	 *
	 * @param mixed $link A database connection.
	 * @param mixed $feed_id The id of the purged feed.
	 * @param mixed $purge_interval Olderness of purged posts.
	 * @param boolean $debug Set to True to enable the debug. False by default.
	 * @access public
	 * @return void
	 */
	function purge_feed($feed_id, $purge_interval, $debug = false) {

		if (!$purge_interval) $purge_interval = feed_purge_interval($feed_id);

		$rows = -1;

		$result = db_query(
			"SELECT owner_uid FROM ttrss_feeds WHERE id = '$feed_id'");

		$owner_uid = false;

		if (db_num_rows($result) == 1) {
			$owner_uid = db_fetch_result($result, 0, "owner_uid");
		}

		if ($purge_interval == -1 || !$purge_interval) {
			if ($owner_uid) {
				CCache::update($feed_id, $owner_uid);
			}
			return;
		}

		if (!$owner_uid) return;

		if (FORCE_ARTICLE_PURGE == 0) {
			$purge_unread = get_pref("PURGE_UNREAD_ARTICLES",
				$owner_uid, false);
		} else {
			$purge_unread = true;
			$purge_interval = FORCE_ARTICLE_PURGE;
		}

		if (!$purge_unread) $query_limit = " unread = false AND ";

		if (DB_TYPE == "pgsql") {
			$result = db_query("DELETE FROM ttrss_user_entries
				USING ttrss_entries
				WHERE ttrss_entries.id = ref_id AND
				marked = false AND
				feed_id = '$feed_id' AND
				$query_limit
				ttrss_entries.date_updated < NOW() - INTERVAL '$purge_interval days'");

		} else {

/*			$result = db_query("DELETE FROM ttrss_user_entries WHERE
				marked = false AND feed_id = '$feed_id' AND
				(SELECT date_updated FROM ttrss_entries WHERE
					id = ref_id) < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)"); */

			$result = db_query("DELETE FROM ttrss_user_entries
				USING ttrss_user_entries, ttrss_entries
				WHERE ttrss_entries.id = ref_id AND
				marked = false AND
				feed_id = '$feed_id' AND
				$query_limit
				ttrss_entries.date_updated < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)");
		}

		$rows = db_affected_rows($result);

		CCache::update($feed_id, $owner_uid);

		if ($debug) {
			_debug("Purged feed $feed_id ($purge_interval): deleted $rows articles");
		}

		return $rows;
	} // function purge_feed

	function feed_purge_interval($feed_id) {

		$result = db_query("SELECT purge_interval, owner_uid FROM ttrss_feeds
			WHERE id = '$feed_id'");

		if (db_num_rows($result) == 1) {
			$purge_interval = db_fetch_result($result, 0, "purge_interval");
			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if ($purge_interval == 0) $purge_interval = get_pref(
				'PURGE_OLD_DAYS', $owner_uid);

			return $purge_interval;

		} else {
			return -1;
		}
	}

	/*function get_feed_update_interval($feed_id) {
		$result = db_query("SELECT owner_uid, update_interval FROM
			ttrss_feeds WHERE id = '$feed_id'");

		if (db_num_rows($result) == 1) {
			$update_interval = db_fetch_result($result, 0, "update_interval");
			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if ($update_interval != 0) {
				return $update_interval;
			} else {
				return get_pref('DEFAULT_UPDATE_INTERVAL', $owner_uid, false);
			}

		} else {
			return -1;
		}
	}*/

	// TODO: multiple-argument way is deprecated, first parameter is a hash now
	function fetch_file_contents($options /* previously: 0: $url , 1: $type = false, 2: $login = false, 3: $pass = false,
 				4: $post_query = false, 5: $timeout = false, 6: $timestamp = 0, 7: $useragent = false*/) {

		global $fetch_last_error;
		global $fetch_last_error_code;
		global $fetch_last_error_content;
		global $fetch_last_content_type;
		global $fetch_curl_used;

		$fetch_last_error = false;
		$fetch_last_error_code = -1;
		$fetch_last_error_content = "";
		$fetch_last_content_type = "";
		$fetch_curl_used = false;

		if (!is_array($options)) {

			// falling back on compatibility shim
			$option_names = [ "url", "type", "login", "pass", "post_query", "timeout", "timestamp", "useragent" ];
			$tmp = [];

			for ($i = 0; $i < func_num_args(); $i++) {
				$tmp[$option_names[$i]] = func_get_arg($i);
			}

			$options = $tmp;

			/*$options = array(
					"url" => func_get_arg(0),
					"type" => @func_get_arg(1),
					"login" => @func_get_arg(2),
					"pass" => @func_get_arg(3),
					"post_query" => @func_get_arg(4),
					"timeout" => @func_get_arg(5),
					"timestamp" => @func_get_arg(6),
					"useragent" => @func_get_arg(7)
			); */
		}

		$url = $options["url"];
		$type = isset($options["type"]) ? $options["type"] : false;
		$login = isset($options["login"]) ? $options["login"] : false;
		$pass = isset($options["pass"]) ? $options["pass"] : false;
		$post_query = isset($options["post_query"]) ? $options["post_query"] : false;
		$timeout = isset($options["timeout"]) ? $options["timeout"] : false;
		$timestamp = isset($options["timestamp"]) ? $options["timestamp"] : 0;
		$useragent = isset($options["useragent"]) ? $options["useragent"] : false;
		$followlocation = isset($options["followlocation"]) ? $options["followlocation"] : true;

		$url = ltrim($url, ' ');
		$url = str_replace(' ', '%20', $url);

		if (strpos($url, "//") === 0)
			$url = 'http:' . $url;

		if (!defined('NO_CURL') && function_exists('curl_init') && !ini_get("open_basedir")) {

			$fetch_curl_used = true;

			$ch = curl_init($url);

			if ($timestamp && !$post_query) {
				curl_setopt($ch, CURLOPT_HTTPHEADER,
					array("If-Modified-Since: ".gmdate('D, d M Y H:i:s \G\M\T', $timestamp)));
			}

			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout ? $timeout : FILE_FETCH_CONNECT_TIMEOUT);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout ? $timeout : FILE_FETCH_TIMEOUT);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, !ini_get("open_basedir") && $followlocation);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 20);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERAGENT, $useragent ? $useragent :
				SELF_USER_AGENT);
			curl_setopt($ch, CURLOPT_ENCODING, "");
			//curl_setopt($ch, CURLOPT_REFERER, $url);

			if (!ini_get("open_basedir")) {
				curl_setopt($ch, CURLOPT_COOKIEJAR, "/dev/null");
			}

			if (defined('_CURL_HTTP_PROXY')) {
				curl_setopt($ch, CURLOPT_PROXY, _CURL_HTTP_PROXY);
			}

			if ($post_query) {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_query);
			}

			if ($login && $pass)
				curl_setopt($ch, CURLOPT_USERPWD, "$login:$pass");

			$contents = @curl_exec($ch);

			if (curl_errno($ch) === 23 || curl_errno($ch) === 61) {
				curl_setopt($ch, CURLOPT_ENCODING, 'none');
				$contents = @curl_exec($ch);
			}

			if ($contents === false) {
				$fetch_last_error = curl_errno($ch) . " " . curl_error($ch);
				curl_close($ch);
				return false;
			}

			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$fetch_last_content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

			$fetch_last_error_code = $http_code;

			if ($http_code != 200 || $type && strpos($fetch_last_content_type, "$type") === false) {
				if (curl_errno($ch) != 0) {
					$fetch_last_error = curl_errno($ch) . " " . curl_error($ch);
				} else {
					$fetch_last_error = "HTTP Code: $http_code";
				}
				$fetch_last_error_content = $contents;
				curl_close($ch);
				return false;
			}

			curl_close($ch);

			return $contents;
		} else {

			$fetch_curl_used = false;

			if ($login && $pass){
				$url_parts = array();

				preg_match("/(^[^:]*):\/\/(.*)/", $url, $url_parts);

				$pass = urlencode($pass);

				if ($url_parts[1] && $url_parts[2]) {
					$url = $url_parts[1] . "://$login:$pass@" . $url_parts[2];
				}
			}

			// TODO: should this support POST requests or not? idk

			if (!$post_query && $timestamp) {
				 $context = stream_context_create(array(
					  'http' => array(
							'method' => 'GET',
						    'ignore_errors' => true,
						    'timeout' => $timeout ? $timeout : FILE_FETCH_TIMEOUT,
							'protocol_version'=> 1.1,
							'header' => "If-Modified-Since: ".gmdate("D, d M Y H:i:s \\G\\M\\T\r\n", $timestamp)
					  )));
			} else {
				 $context = stream_context_create(array(
					  'http' => array(
							'method' => 'GET',
						    'ignore_errors' => true,
						    'timeout' => $timeout ? $timeout : FILE_FETCH_TIMEOUT,
							'protocol_version'=> 1.1
					  )));
			}

			$old_error = error_get_last();

			$data = @file_get_contents($url, false, $context);

			if (isset($http_response_header) && is_array($http_response_header)) {
				foreach ($http_response_header as $h) {
					if (substr(strtolower($h), 0, 13) == 'content-type:') {
						$fetch_last_content_type = substr($h, 14);
						// don't abort here b/c there might be more than one
						// e.g. if we were being redirected -- last one is the right one
					}

					if (substr(strtolower($h), 0, 7) == 'http/1.') {
						$fetch_last_error_code = (int) substr($h, 9, 3);
					}
				}
			}

			if ($fetch_last_error_code != 200) {
				$error = error_get_last();

				if ($error['message'] != $old_error['message']) {
					$fetch_last_error = $error["message"];
				} else {
					$fetch_last_error = "HTTP Code: $fetch_last_error_code";
				}

				$fetch_last_error_content = $data;

				return false;
			}
			return $data;
		}

	}

	/**
	 * Try to determine the favicon URL for a feed.
	 * adapted from wordpress favicon plugin by Jeff Minard (http://thecodepro.com/)
	 * http://dev.wp-plugins.org/file/favatars/trunk/favatars.php
	 *
	 * @param string $url A feed or page URL
	 * @access public
	 * @return mixed The favicon URL, or false if none was found.
	 */
	function get_favicon_url($url) {

		$favicon_url = false;

		if ($html = @fetch_file_contents($url)) {

			libxml_use_internal_errors(true);

			$doc = new DOMDocument();
			$doc->loadHTML($html);
			$xpath = new DOMXPath($doc);

			$base = $xpath->query('/html/head/base');
			foreach ($base as $b) {
				$url = $b->getAttribute("href");
				break;
			}

			$entries = $xpath->query('/html/head/link[@rel="shortcut icon" or @rel="icon"]');
			if (count($entries) > 0) {
				foreach ($entries as $entry) {
					$favicon_url = rewrite_relative_url($url, $entry->getAttribute("href"));
					break;
				}
			}
		}

		if (!$favicon_url)
			$favicon_url = rewrite_relative_url($url, "/favicon.ico");

		return $favicon_url;
	} // function get_favicon_url

	function initialize_user_prefs($uid, $profile = false) {

		$uid = db_escape_string($uid);

		if (!$profile) {
			$profile = "NULL";
			$profile_qpart = "AND profile IS NULL";
		} else {
			$profile_qpart = "AND profile = '$profile'";
		}

		if (get_schema_version() < 63) $profile_qpart = "";

		db_query("BEGIN");

		$result = db_query("SELECT pref_name,def_value FROM ttrss_prefs");

		$u_result = db_query("SELECT pref_name
			FROM ttrss_user_prefs WHERE owner_uid = '$uid' $profile_qpart");

		$active_prefs = array();

		while ($line = db_fetch_assoc($u_result)) {
			array_push($active_prefs, $line["pref_name"]);
		}

		while ($line = db_fetch_assoc($result)) {
			if (array_search($line["pref_name"], $active_prefs) === FALSE) {
//				print "adding " . $line["pref_name"] . "<br>";

				$line["def_value"] = db_escape_string($line["def_value"]);
				$line["pref_name"] = db_escape_string($line["pref_name"]);

				if (get_schema_version() < 63) {
					db_query("INSERT INTO ttrss_user_prefs
						(owner_uid,pref_name,value) VALUES
						('$uid', '".$line["pref_name"]."','".$line["def_value"]."')");

				} else {
					db_query("INSERT INTO ttrss_user_prefs
						(owner_uid,pref_name,value, profile) VALUES
						('$uid', '".$line["pref_name"]."','".$line["def_value"]."', $profile)");
				}

			}
		}

		db_query("COMMIT");

	}

	function get_ssl_certificate_id() {
		if ($_SERVER["REDIRECT_SSL_CLIENT_M_SERIAL"]) {
			return sha1($_SERVER["REDIRECT_SSL_CLIENT_M_SERIAL"] .
				$_SERVER["REDIRECT_SSL_CLIENT_V_START"] .
				$_SERVER["REDIRECT_SSL_CLIENT_V_END"] .
				$_SERVER["REDIRECT_SSL_CLIENT_S_DN"]);
		}
		if ($_SERVER["SSL_CLIENT_M_SERIAL"]) {
			return sha1($_SERVER["SSL_CLIENT_M_SERIAL"] .
				$_SERVER["SSL_CLIENT_V_START"] .
				$_SERVER["SSL_CLIENT_V_END"] .
				$_SERVER["SSL_CLIENT_S_DN"]);
		}
		return "";
	}

	function authenticate_user($login, $password, $check_only = false) {

		if (!SINGLE_USER_MODE) {
			$user_id = false;

			foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_AUTH_USER) as $plugin) {

				$user_id = (int) $plugin->authenticate($login, $password);

				if ($user_id) {
					$_SESSION["auth_module"] = strtolower(get_class($plugin));
					break;
				}
			}

			if ($user_id && !$check_only) {
				@session_start();

				$_SESSION["uid"] = $user_id;
				$_SESSION["version"] = VERSION_STATIC;

				$result = db_query("SELECT login,access_level,pwd_hash FROM ttrss_users
					WHERE id = '$user_id'");

				$_SESSION["name"] = db_fetch_result($result, 0, "login");
				$_SESSION["access_level"] = db_fetch_result($result, 0, "access_level");
				$_SESSION["csrf_token"] = uniqid_short();

				db_query("UPDATE ttrss_users SET last_login = NOW() WHERE id = " .
					$_SESSION["uid"]);

				$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
				$_SESSION["user_agent"] = sha1($_SERVER['HTTP_USER_AGENT']);
				$_SESSION["pwd_hash"] = db_fetch_result($result, 0, "pwd_hash");

				$_SESSION["last_version_check"] = time();

				initialize_user_prefs($_SESSION["uid"]);

				return true;
			}

			return false;

		} else {

			$_SESSION["uid"] = 1;
			$_SESSION["name"] = "admin";
			$_SESSION["access_level"] = 10;

			$_SESSION["hide_hello"] = true;
			$_SESSION["hide_logout"] = true;

			$_SESSION["auth_module"] = false;

			if (!$_SESSION["csrf_token"]) {
				$_SESSION["csrf_token"] = uniqid_short();
			}

			$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];

			initialize_user_prefs($_SESSION["uid"]);

			return true;
		}
	}

	function make_password($length = 8) {

		$password = "";
		$possible = "0123456789abcdfghjkmnpqrstvwxyzABCDFGHJKMNPQRSTVWXYZ";

   	$i = 0;

		while ($i < $length) {
			$char = substr($possible, mt_rand(0, strlen($possible)-1), 1);

			if (!strstr($password, $char)) {
				$password .= $char;
				$i++;
			}
		}
		return $password;
	}

	// this is called after user is created to initialize default feeds, labels
	// or whatever else

	// user preferences are checked on every login, not here

	function initialize_user($uid) {

		db_query("insert into ttrss_feeds (owner_uid,title,feed_url)
			values ('$uid', 'Tiny Tiny RSS: Forum',
				'http://tt-rss.org/forum/rss.php')");
	}

	function logout_user() {
		session_destroy();
		if (isset($_COOKIE[session_name()])) {
		   setcookie(session_name(), '', time()-42000, '/');
		}
	}

	function validate_csrf($csrf_token) {
		return $csrf_token == $_SESSION['csrf_token'];
	}

	function load_user_plugins($owner_uid, $pluginhost = false) {

		if (!$pluginhost) $pluginhost = PluginHost::getInstance();

		if ($owner_uid && SCHEMA_VERSION >= 100) {
			$plugins = get_pref("_ENABLED_PLUGINS", $owner_uid);

			$pluginhost->load($plugins, PluginHost::KIND_USER, $owner_uid);

			if (get_schema_version() > 100) {
				$pluginhost->load_data();
			}
		}
	}

	function login_sequence() {
		if (SINGLE_USER_MODE) {
			@session_start();
			authenticate_user("admin", null);
			startup_gettext();
			load_user_plugins($_SESSION["uid"]);
		} else {
			if (!validate_session()) $_SESSION["uid"] = false;

			if (!$_SESSION["uid"]) {

				if (AUTH_AUTO_LOGIN && authenticate_user(null, null)) {
				    $_SESSION["ref_schema_version"] = get_schema_version(true);
				} else {
					 authenticate_user(null, null, true);
				}

				if (!$_SESSION["uid"]) {
					@session_destroy();
					setcookie(session_name(), '', time()-42000, '/');

					render_login_form();
					exit;
				}

			} else {
				/* bump login timestamp */
				db_query("UPDATE ttrss_users SET last_login = NOW() WHERE id = " .
					$_SESSION["uid"]);
				$_SESSION["last_login_update"] = time();
			}

			if ($_SESSION["uid"]) {
				startup_gettext();
				load_user_plugins($_SESSION["uid"]);

				/* cleanup ccache */

				db_query("DELETE FROM ttrss_counters_cache WHERE owner_uid = ".
					$_SESSION["uid"] . " AND
						(SELECT COUNT(id) FROM ttrss_feeds WHERE
							ttrss_feeds.id = feed_id) = 0");

				db_query("DELETE FROM ttrss_cat_counters_cache WHERE owner_uid = ".
					$_SESSION["uid"] . " AND
						(SELECT COUNT(id) FROM ttrss_feed_categories WHERE
							ttrss_feed_categories.id = feed_id) = 0");

			}

		}
	}

	function truncate_string($str, $max_len, $suffix = '&hellip;') {
		if (mb_strlen($str, "utf-8") > $max_len) {
			return mb_substr($str, 0, $max_len, "utf-8") . $suffix;
		} else {
			return $str;
		}
	}

	// is not utf8 clean
	function truncate_middle($str, $max_len, $suffix = '&hellip;') {
		if (strlen($str) > $max_len) {
			return substr_replace($str, $suffix, $max_len / 2, mb_strlen($str) - $max_len);
		} else {
			return $str;
		}
	}

	function convert_timestamp($timestamp, $source_tz, $dest_tz) {

		try {
			$source_tz = new DateTimeZone($source_tz);
		} catch (Exception $e) {
			$source_tz = new DateTimeZone('UTC');
		}

		try {
			$dest_tz = new DateTimeZone($dest_tz);
		} catch (Exception $e) {
			$dest_tz = new DateTimeZone('UTC');
		}

		$dt = new DateTime(date('Y-m-d H:i:s', $timestamp), $source_tz);
		return $dt->format('U') + $dest_tz->getOffset($dt);
	}

	function make_local_datetime($timestamp, $long, $owner_uid = false,
					$no_smart_dt = false, $eta_min = false) {

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];
		if (!$timestamp) $timestamp = '1970-01-01 0:00';

		global $utc_tz;
		global $user_tz;

		if (!$utc_tz) $utc_tz = new DateTimeZone('UTC');

		$timestamp = substr($timestamp, 0, 19);

		# We store date in UTC internally
		$dt = new DateTime($timestamp, $utc_tz);

		$user_tz_string = get_pref('USER_TIMEZONE', $owner_uid);

		if ($user_tz_string != 'Automatic') {

			try {
				if (!$user_tz) $user_tz = new DateTimeZone($user_tz_string);
			} catch (Exception $e) {
				$user_tz = $utc_tz;
			}

			$tz_offset = $user_tz->getOffset($dt);
		} else {
			$tz_offset = (int) -$_SESSION["clientTzOffset"];
		}

		$user_timestamp = $dt->format('U') + $tz_offset;

		if (!$no_smart_dt) {
			return smart_date_time($user_timestamp,
				$tz_offset, $owner_uid, $eta_min);
		} else {
			if ($long)
				$format = get_pref('LONG_DATE_FORMAT', $owner_uid);
			else
				$format = get_pref('SHORT_DATE_FORMAT', $owner_uid);

			return date($format, $user_timestamp);
		}
	}

	function smart_date_time($timestamp, $tz_offset = 0, $owner_uid = false, $eta_min = false) {
		if (!$owner_uid) $owner_uid = $_SESSION['uid'];

		if ($eta_min && time() + $tz_offset - $timestamp < 3600) {
			return T_sprintf("%d min", date("i", time() + $tz_offset - $timestamp));
		} else if (date("Y.m.d", $timestamp) == date("Y.m.d", time() + $tz_offset)) {
			return date("G:i", $timestamp);
		} else if (date("Y", $timestamp) == date("Y", time() + $tz_offset)) {
			$format = get_pref('SHORT_DATE_FORMAT', $owner_uid);
			return date($format, $timestamp);
		} else {
			$format = get_pref('LONG_DATE_FORMAT', $owner_uid);
			return date($format, $timestamp);
		}
	}

	function sql_bool_to_bool($s) {
		if ($s == "t" || $s == "1" || strtolower($s) == "true") {
			return true;
		} else {
			return false;
		}
	}

	function bool_to_sql_bool($s) {
		if ($s) {
			return "true";
		} else {
			return "false";
		}
	}

	// Session caching removed due to causing wrong redirects to upgrade
	// script when get_schema_version() is called on an obsolete session
	// created on a previous schema version.
	function get_schema_version($nocache = false) {
		global $schema_version;

		if (!$schema_version && !$nocache) {
			$result = db_query("SELECT schema_version FROM ttrss_version");
			$version = db_fetch_result($result, 0, "schema_version");
			$schema_version = $version;
			return $version;
		} else {
			return $schema_version;
		}
	}

	function sanity_check() {
		require_once 'errors.php';
		global $ERRORS;

		$error_code = 0;
		$schema_version = get_schema_version(true);

		if ($schema_version != SCHEMA_VERSION) {
			$error_code = 5;
		}

		if (DB_TYPE == "mysql") {
			$result = db_query("SELECT true", false);
			if (db_num_rows($result) != 1) {
				$error_code = 10;
			}
		}

		if (db_escape_string("testTEST") != "testTEST") {
			$error_code = 12;
		}

		return array("code" => $error_code, "message" => $ERRORS[$error_code]);
	}

	function file_is_locked($filename) {
		if (file_exists(LOCK_DIRECTORY . "/$filename")) {
			if (function_exists('flock')) {
				$fp = @fopen(LOCK_DIRECTORY . "/$filename", "r");
				if ($fp) {
					if (flock($fp, LOCK_EX | LOCK_NB)) {
						flock($fp, LOCK_UN);
						fclose($fp);
						return false;
					}
					fclose($fp);
					return true;
				} else {
					return false;
				}
			}
			return true; // consider the file always locked and skip the test
		} else {
			return false;
		}
	}


	function make_lockfile($filename) {
		$fp = fopen(LOCK_DIRECTORY . "/$filename", "w");

		if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
			$stat_h = fstat($fp);
			$stat_f = stat(LOCK_DIRECTORY . "/$filename");

			if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
				if ($stat_h["ino"] != $stat_f["ino"] ||
						$stat_h["dev"] != $stat_f["dev"]) {

					return false;
				}
			}

			if (function_exists('posix_getpid')) {
				fwrite($fp, posix_getpid() . "\n");
			}
			return $fp;
		} else {
			return false;
		}
	}

	function make_stampfile($filename) {
		$fp = fopen(LOCK_DIRECTORY . "/$filename", "w");

		if (flock($fp, LOCK_EX | LOCK_NB)) {
			fwrite($fp, time() . "\n");
			flock($fp, LOCK_UN);
			fclose($fp);
			return true;
		} else {
			return false;
		}
	}

	function sql_random_function() {
		if (DB_TYPE == "mysql") {
			return "RAND()";
		} else {
			return "RANDOM()";
		}
	}

	function getFeedUnread($feed, $is_cat = false) {
		return Feeds::getFeedArticles($feed, $is_cat, true, $_SESSION["uid"]);
	}


	/*function get_pgsql_version() {
		$result = db_query("SELECT version() AS version");
		$version = explode(" ", db_fetch_result($result, 0, "version"));
		return $version[1];
	}*/

	function checkbox_to_sql_bool($val) {
		return ($val == "on") ? "true" : "false";
	}

	/*function getFeedCatTitle($id) {
		if ($id == -1) {
			return __("Special");
		} else if ($id < LABEL_BASE_INDEX) {
			return __("Labels");
		} else if ($id > 0) {
			$result = db_query("SELECT ttrss_feed_categories.title
				FROM ttrss_feeds, ttrss_feed_categories WHERE ttrss_feeds.id = '$id' AND
					cat_id = ttrss_feed_categories.id");
			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "title");
			} else {
				return __("Uncategorized");
			}
		} else {
			return "getFeedCatTitle($id) failed";
		}

	}*/

	function uniqid_short() {
		return uniqid(base_convert(rand(), 10, 36));
	}

	function make_init_params() {
		$params = array();

		foreach (array("ON_CATCHUP_SHOW_NEXT_FEED", "HIDE_READ_FEEDS",
					 "ENABLE_FEED_CATS", "FEEDS_SORT_BY_UNREAD", "CONFIRM_FEED_CATCHUP",
					 "CDM_AUTO_CATCHUP", "FRESH_ARTICLE_MAX_AGE",
					 "HIDE_READ_SHOWS_SPECIAL", "COMBINED_DISPLAY_MODE") as $param) {

			$params[strtolower($param)] = (int) get_pref($param);
		}

		$params["icons_url"] = ICONS_URL;
		$params["cookie_lifetime"] = SESSION_COOKIE_LIFETIME;
		$params["default_view_mode"] = get_pref("_DEFAULT_VIEW_MODE");
		$params["default_view_limit"] = (int) get_pref("_DEFAULT_VIEW_LIMIT");
		$params["default_view_order_by"] = get_pref("_DEFAULT_VIEW_ORDER_BY");
		$params["bw_limit"] = (int) $_SESSION["bw_limit"];
		$params["label_base_index"] = (int) LABEL_BASE_INDEX;

		$theme = get_pref( "USER_CSS_THEME", false, false);
		$params["theme"] = theme_valid("$theme") ? $theme : "";

		$params["plugins"] = implode(", ", PluginHost::getInstance()->get_plugin_names());

		$params["php_platform"] = PHP_OS;
		$params["php_version"] = PHP_VERSION;

		$params["sanity_checksum"] = sha1(file_get_contents("include/sanity_check.php"));

		$result = db_query("SELECT MAX(id) AS mid, COUNT(*) AS nf FROM
				ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$max_feed_id = db_fetch_result($result, 0, "mid");
		$num_feeds = db_fetch_result($result, 0, "nf");

		$params["max_feed_id"] = (int) $max_feed_id;
		$params["num_feeds"] = (int) $num_feeds;

		$params["hotkeys"] = get_hotkeys_map();

		$params["csrf_token"] = $_SESSION["csrf_token"];
		$params["widescreen"] = (int) $_COOKIE["ttrss_widescreen"];

		$params['simple_update'] = defined('SIMPLE_UPDATE_MODE') && SIMPLE_UPDATE_MODE;

		$params["icon_alert"] = base64_img("images/alert.png");
		$params["icon_information"] = base64_img("images/information.png");
		$params["icon_cross"] = base64_img("images/cross.png");
		$params["icon_indicator_white"] = base64_img("images/indicator_white.gif");

		return $params;
	}

	function get_hotkeys_info() {
		$hotkeys = array(
			__("Navigation") => array(
				"next_feed" => __("Open next feed"),
				"prev_feed" => __("Open previous feed"),
				"next_article" => __("Open next article"),
				"prev_article" => __("Open previous article"),
				"next_article_noscroll" => __("Open next article (don't scroll long articles)"),
				"prev_article_noscroll" => __("Open previous article (don't scroll long articles)"),
				"next_article_noexpand" => __("Move to next article (don't expand or mark read)"),
				"prev_article_noexpand" => __("Move to previous article (don't expand or mark read)"),
				"search_dialog" => __("Show search dialog")),
			__("Article") => array(
				"toggle_mark" => __("Toggle starred"),
				"toggle_publ" => __("Toggle published"),
				"toggle_unread" => __("Toggle unread"),
				"edit_tags" => __("Edit tags"),
				"open_in_new_window" => __("Open in new window"),
				"catchup_below" => __("Mark below as read"),
				"catchup_above" => __("Mark above as read"),
				"article_scroll_down" => __("Scroll down"),
				"article_scroll_up" => __("Scroll up"),
				"select_article_cursor" => __("Select article under cursor"),
				"email_article" => __("Email article"),
				"close_article" => __("Close/collapse article"),
				"toggle_expand" => __("Toggle article expansion (combined mode)"),
				"toggle_widescreen" => __("Toggle widescreen mode"),
				"toggle_embed_original" => __("Toggle embed original")),
			__("Article selection") => array(
				"select_all" => __("Select all articles"),
				"select_unread" => __("Select unread"),
				"select_marked" => __("Select starred"),
				"select_published" => __("Select published"),
				"select_invert" => __("Invert selection"),
				"select_none" => __("Deselect everything")),
			__("Feed") => array(
				"feed_refresh" => __("Refresh current feed"),
				"feed_unhide_read" => __("Un/hide read feeds"),
				"feed_subscribe" => __("Subscribe to feed"),
				"feed_edit" => __("Edit feed"),
				"feed_catchup" => __("Mark as read"),
				"feed_reverse" => __("Reverse headlines"),
				"feed_toggle_vgroup" => __("Toggle headline grouping"),
				"feed_debug_update" => __("Debug feed update"),
				"feed_debug_viewfeed" => __("Debug viewfeed()"),
				"catchup_all" => __("Mark all feeds as read"),
				"cat_toggle_collapse" => __("Un/collapse current category"),
				"toggle_combined_mode" => __("Toggle combined mode"),
				"toggle_cdm_expanded" => __("Toggle auto expand in combined mode")),
			__("Go to") => array(
				"goto_all" => __("All articles"),
				"goto_fresh" => __("Fresh"),
				"goto_marked" => __("Starred"),
				"goto_published" => __("Published"),
				"goto_tagcloud" => __("Tag cloud"),
				"goto_prefs" => __("Preferences")),
			__("Other") => array(
				"create_label" => __("Create label"),
				"create_filter" => __("Create filter"),
				"collapse_sidebar" => __("Un/collapse sidebar"),
				"help_dialog" => __("Show help dialog"))
		);

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_HOTKEY_INFO) as $plugin) {
			$hotkeys = $plugin->hook_hotkey_info($hotkeys);
		}

		return $hotkeys;
	}

	function get_hotkeys_map() {
		$hotkeys = array(
	//			"navigation" => array(
			"k" => "next_feed",
			"j" => "prev_feed",
			"n" => "next_article",
			"p" => "prev_article",
			"(38)|up" => "prev_article",
			"(40)|down" => "next_article",
	//				"^(38)|Ctrl-up" => "prev_article_noscroll",
	//				"^(40)|Ctrl-down" => "next_article_noscroll",
			"(191)|/" => "search_dialog",
	//			"article" => array(
			"s" => "toggle_mark",
			"*s" => "toggle_publ",
			"u" => "toggle_unread",
			"*t" => "edit_tags",
			"o" => "open_in_new_window",
			"c p" => "catchup_below",
			"c n" => "catchup_above",
			"*n" => "article_scroll_down",
			"*p" => "article_scroll_up",
			"*(38)|Shift+up" => "article_scroll_up",
			"*(40)|Shift+down" => "article_scroll_down",
			"a *w" => "toggle_widescreen",
			"a e" => "toggle_embed_original",
			"e" => "email_article",
			"a q" => "close_article",
	//			"article_selection" => array(
			"a a" => "select_all",
			"a u" => "select_unread",
			"a *u" => "select_marked",
			"a p" => "select_published",
			"a i" => "select_invert",
			"a n" => "select_none",
	//			"feed" => array(
			"f r" => "feed_refresh",
			"f a" => "feed_unhide_read",
			"f s" => "feed_subscribe",
			"f e" => "feed_edit",
			"f q" => "feed_catchup",
			"f x" => "feed_reverse",
			"f g" => "feed_toggle_vgroup",
			"f *d" => "feed_debug_update",
			"f *g" => "feed_debug_viewfeed",
			"f *c" => "toggle_combined_mode",
			"f c" => "toggle_cdm_expanded",
			"*q" => "catchup_all",
			"x" => "cat_toggle_collapse",
	//			"goto" => array(
			"g a" => "goto_all",
			"g f" => "goto_fresh",
			"g s" => "goto_marked",
			"g p" => "goto_published",
			"g t" => "goto_tagcloud",
			"g *p" => "goto_prefs",
	//			"other" => array(
			"(9)|Tab" => "select_article_cursor", // tab
			"c l" => "create_label",
			"c f" => "create_filter",
			"c s" => "collapse_sidebar",
			"^(191)|Ctrl+/" => "help_dialog",
		);

		if (get_pref('COMBINED_DISPLAY_MODE')) {
			$hotkeys["^(38)|Ctrl-up"] = "prev_article_noscroll";
			$hotkeys["^(40)|Ctrl-down"] = "next_article_noscroll";
		}

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_HOTKEY_MAP) as $plugin) {
			$hotkeys = $plugin->hook_hotkey_map($hotkeys);
		}

		$prefixes = array();

		foreach (array_keys($hotkeys) as $hotkey) {
			$pair = explode(" ", $hotkey, 2);

			if (count($pair) > 1 && !in_array($pair[0], $prefixes)) {
				array_push($prefixes, $pair[0]);
			}
		}

		return array($prefixes, $hotkeys);
	}

	function check_for_update() {
		if (defined("GIT_VERSION_TIMESTAMP")) {
			$content = @fetch_file_contents(array("url" => "http://tt-rss.org/version.json", "timeout" => 5));

			if ($content) {
				$content = json_decode($content, true);

				if ($content && isset($content["changeset"])) {
					if ((int)GIT_VERSION_TIMESTAMP < (int)$content["changeset"]["timestamp"] &&
						GIT_VERSION_HEAD != $content["changeset"]["id"]) {

						return $content["changeset"]["id"];
					}
				}
			}
		}

		return "";
	}

	function make_runtime_info($disable_update_check = false) {
		$data = array();

		$result = db_query("SELECT MAX(id) AS mid, COUNT(*) AS nf FROM
				ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$max_feed_id = db_fetch_result($result, 0, "mid");
		$num_feeds = db_fetch_result($result, 0, "nf");

		$data["max_feed_id"] = (int) $max_feed_id;
		$data["num_feeds"] = (int) $num_feeds;

		$data['last_article_id'] = Article::getLastArticleId();
		$data['cdm_expanded'] = get_pref('CDM_EXPANDED');

		$data['dep_ts'] = calculate_dep_timestamp();
		$data['reload_on_ts_change'] = !defined('_NO_RELOAD_ON_TS_CHANGE');


		if (CHECK_FOR_UPDATES && !$disable_update_check && $_SESSION["last_version_check"] + 86400 + rand(-1000, 1000) < time()) {
			$update_result = @check_for_update();

			$data["update_result"] = $update_result;

			$_SESSION["last_version_check"] = time();
		}

		if (file_exists(LOCK_DIRECTORY . "/update_daemon.lock")) {

			$data['daemon_is_running'] = (int) file_is_locked("update_daemon.lock");

			if (time() - $_SESSION["daemon_stamp_check"] > 30) {

				$stamp = (int) @file_get_contents(LOCK_DIRECTORY . "/update_daemon.stamp");

				if ($stamp) {
					$stamp_delta = time() - $stamp;

					if ($stamp_delta > 1800) {
						$stamp_check = 0;
					} else {
						$stamp_check = 1;
						$_SESSION["daemon_stamp_check"] = time();
					}

					$data['daemon_stamp_ok'] = $stamp_check;

					$stamp_fmt = date("Y.m.d, G:i", $stamp);

					$data['daemon_stamp'] = $stamp_fmt;
				}
			}
		}

		return $data;
	}

	function search_to_sql($search, $search_language) {

		$keywords = str_getcsv(trim($search), " ");
		$query_keywords = array();
		$search_words = array();
		$search_query_leftover = array();

		if ($search_language)
			$search_language = db_escape_string(mb_strtolower($search_language));
		else
			$search_language = "english";

		foreach ($keywords as $k) {
			if (strpos($k, "-") === 0) {
				$k = substr($k, 1);
				$not = "NOT";
			} else {
				$not = "";
			}

			$commandpair = explode(":", mb_strtolower($k), 2);

			switch ($commandpair[0]) {
				case "title":
					if ($commandpair[1]) {
						array_push($query_keywords, "($not (LOWER(ttrss_entries.title) LIKE '%".
							db_escape_string(mb_strtolower($commandpair[1]))."%'))");
					} else {
						array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
								OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
						array_push($search_words, $k);
					}
					break;
				case "author":
					if ($commandpair[1]) {
						array_push($query_keywords, "($not (LOWER(author) LIKE '%".
							db_escape_string(mb_strtolower($commandpair[1]))."%'))");
					} else {
						array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
								OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
						array_push($search_words, $k);
					}
					break;
				case "note":
					if ($commandpair[1]) {
						if ($commandpair[1] == "true")
							array_push($query_keywords, "($not (note IS NOT NULL AND note != ''))");
						else if ($commandpair[1] == "false")
							array_push($query_keywords, "($not (note IS NULL OR note = ''))");
						else
							array_push($query_keywords, "($not (LOWER(note) LIKE '%".
								db_escape_string(mb_strtolower($commandpair[1]))."%'))");
					} else {
						array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
								OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
						if (!$not) array_push($search_words, $k);
					}
					break;
				case "star":

					if ($commandpair[1]) {
						if ($commandpair[1] == "true")
							array_push($query_keywords, "($not (marked = true))");
						else
							array_push($query_keywords, "($not (marked = false))");
					} else {
						array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
								OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
						if (!$not) array_push($search_words, $k);
					}
					break;
				case "pub":
					if ($commandpair[1]) {
						if ($commandpair[1] == "true")
							array_push($query_keywords, "($not (published = true))");
						else
							array_push($query_keywords, "($not (published = false))");

					} else {
						array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
								OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
						if (!$not) array_push($search_words, $k);
					}
					break;
				case "unread":
					if ($commandpair[1]) {
						if ($commandpair[1] == "true")
							array_push($query_keywords, "($not (unread = true))");
						else
							array_push($query_keywords, "($not (unread = false))");

					} else {
						array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
								OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
						if (!$not) array_push($search_words, $k);
					}
					break;
				default:
					if (strpos($k, "@") === 0) {

						$user_tz_string = get_pref('USER_TIMEZONE', $_SESSION['uid']);
						$orig_ts = strtotime(substr($k, 1));
						$k = date("Y-m-d", convert_timestamp($orig_ts, $user_tz_string, 'UTC'));

						//$k = date("Y-m-d", strtotime(substr($k, 1)));

						array_push($query_keywords, "(".SUBSTRING_FOR_DATE."(updated,1,LENGTH('$k')) $not = '$k')");
					} else {

						if (DB_TYPE == "pgsql") {
							$k = mb_strtolower($k);
							array_push($search_query_leftover, $not ? "!$k" : $k);
						} else {
							array_push($query_keywords, "(UPPER(ttrss_entries.title) $not LIKE UPPER('%$k%')
								OR UPPER(ttrss_entries.content) $not LIKE UPPER('%$k%'))");
						}

						if (!$not) array_push($search_words, $k);
					}
			}
		}

		if (count($search_query_leftover) > 0) {
			$search_query_leftover = db_escape_string(implode(" & ", $search_query_leftover));

			if (DB_TYPE == "pgsql") {
				array_push($query_keywords,
					"(tsvector_combined @@ to_tsquery('$search_language', '$search_query_leftover'))");
			}

		}

		$search_query_part = implode("AND", $query_keywords);

		return array($search_query_part, $search_words);
	}

	function iframe_whitelisted($entry) {
		$whitelist = array("youtube.com", "youtu.be", "vimeo.com", "player.vimeo.com");

		@$src = parse_url($entry->getAttribute("src"), PHP_URL_HOST);

		if ($src) {
			foreach ($whitelist as $w) {
				if ($src == $w || $src == "www.$w")
					return true;
			}
		}

		return false;
	}

	function sanitize($str, $force_remove_images = false, $owner = false, $site_url = false, $highlight_words = false, $article_id = false) {
		if (!$owner) $owner = $_SESSION["uid"];

		$res = trim($str); if (!$res) return '';

		$charset_hack = '<head>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			</head>';

		$res = trim($res); if (!$res) return '';

		libxml_use_internal_errors(true);

		$doc = new DOMDocument();
		$doc->loadHTML($charset_hack . $res);
		$xpath = new DOMXPath($doc);

		$ttrss_uses_https = parse_url(get_self_url_prefix(), PHP_URL_SCHEME) === 'https';
		$rewrite_base_url = $site_url ? $site_url : SELF_URL_PATH;

		$entries = $xpath->query('(//a[@href]|//img[@src]|//video/source[@src]|//audio/source[@src])');

		foreach ($entries as $entry) {

			if ($entry->hasAttribute('href')) {
				$entry->setAttribute('href',
					rewrite_relative_url($rewrite_base_url, $entry->getAttribute('href')));

				$entry->setAttribute('rel', 'noopener noreferrer');
			}

			if ($entry->hasAttribute('src')) {
				$src = rewrite_relative_url($rewrite_base_url, $entry->getAttribute('src'));
				$cached_filename = CACHE_DIR . '/images/' . sha1($src);

				if (file_exists($cached_filename)) {

					// this is strictly cosmetic
					if ($entry->tagName == 'img') {
						$suffix = ".png";
					} else if ($entry->parentNode && $entry->parentNode->tagName == "video") {
						$suffix = ".mp4";
					} else if ($entry->parentNode && $entry->parentNode->tagName == "audio") {
						$suffix = ".ogg";
					} else {
						$suffix = "";
					}

					$src = get_self_url_prefix() . '/public.php?op=cached_url&hash=' . sha1($src) . $suffix;

					if ($entry->hasAttribute('srcset')) {
						$entry->removeAttribute('srcset');
					}

					if ($entry->hasAttribute('sizes')) {
						$entry->removeAttribute('sizes');
					}
				}

				$entry->setAttribute('src', $src);
			}

			if ($entry->nodeName == 'img') {

				if ($entry->hasAttribute('src')) {
					$is_https_url = parse_url($entry->getAttribute('src'), PHP_URL_SCHEME) === 'https';

					if ($ttrss_uses_https && !$is_https_url) {

						if ($entry->hasAttribute('srcset')) {
							$entry->removeAttribute('srcset');
						}

						if ($entry->hasAttribute('sizes')) {
							$entry->removeAttribute('sizes');
						}
					}
				}

				if (($owner && get_pref("STRIP_IMAGES", $owner)) ||
					$force_remove_images || $_SESSION["bw_limit"]) {

					$p = $doc->createElement('p');

					$a = $doc->createElement('a');
					$a->setAttribute('href', $entry->getAttribute('src'));

					$a->appendChild(new DOMText($entry->getAttribute('src')));
					$a->setAttribute('target', '_blank');
					$a->setAttribute('rel', 'noopener noreferrer');

					$p->appendChild($a);

					$entry->parentNode->replaceChild($p, $entry);
				}
			}

			if (strtolower($entry->nodeName) == "a") {
				$entry->setAttribute("target", "_blank");
				$entry->setAttribute("rel", "noopener noreferrer");
			}
		}

		$entries = $xpath->query('//iframe');
		foreach ($entries as $entry) {
			if (!iframe_whitelisted($entry)) {
				$entry->setAttribute('sandbox', 'allow-scripts');
			} else {
				if ($_SERVER['HTTPS'] == "on") {
					$entry->setAttribute("src",
						str_replace("http://", "https://",
							$entry->getAttribute("src")));
				}
			}
		}

		$allowed_elements = array('a', 'address', 'acronym', 'audio', 'article', 'aside',
			'b', 'bdi', 'bdo', 'big', 'blockquote', 'body', 'br',
			'caption', 'cite', 'center', 'code', 'col', 'colgroup',
			'data', 'dd', 'del', 'details', 'description', 'dfn', 'div', 'dl', 'font',
			'dt', 'em', 'footer', 'figure', 'figcaption',
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'html', 'i',
			'img', 'ins', 'kbd', 'li', 'main', 'mark', 'nav', 'noscript',
			'ol', 'p', 'pre', 'q', 'ruby', 'rp', 'rt', 's', 'samp', 'section',
			'small', 'source', 'span', 'strike', 'strong', 'sub', 'summary',
			'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time',
			'tr', 'track', 'tt', 'u', 'ul', 'var', 'wbr', 'video', 'xml:namespace' );

		if ($_SESSION['hasSandbox']) $allowed_elements[] = 'iframe';

		$disallowed_attributes = array('id', 'style', 'class');

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SANITIZE) as $plugin) {
			$retval = $plugin->hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id);
			if (is_array($retval)) {
				$doc = $retval[0];
				$allowed_elements = $retval[1];
				$disallowed_attributes = $retval[2];
			} else {
				$doc = $retval;
			}
		}

		$doc->removeChild($doc->firstChild); //remove doctype
		$doc = strip_harmful_tags($doc, $allowed_elements, $disallowed_attributes);

		if ($highlight_words) {
			foreach ($highlight_words as $word) {

				// http://stackoverflow.com/questions/4081372/highlight-keywords-in-a-paragraph

				$elements = $xpath->query("//*/text()");

				foreach ($elements as $child) {

					$fragment = $doc->createDocumentFragment();
					$text = $child->textContent;

					while (($pos = mb_stripos($text, $word)) !== false) {
						$fragment->appendChild(new DomText(mb_substr($text, 0, $pos)));
						$word = mb_substr($text, $pos, mb_strlen($word));
						$highlight = $doc->createElement('span');
						$highlight->appendChild(new DomText($word));
						$highlight->setAttribute('class', 'highlight');
						$fragment->appendChild($highlight);
						$text = mb_substr($text, $pos + mb_strlen($word));
					}

					if (!empty($text)) $fragment->appendChild(new DomText($text));

					$child->parentNode->replaceChild($fragment, $child);
				}
			}
		}

		$res = $doc->saveHTML();

		/* strip everything outside of <body>...</body> */

		$res_frag = array();
		if (preg_match('/<body>(.*)<\/body>/is', $res, $res_frag)) {
			return $res_frag[1];
		} else {
			return $res;
		}
	}

	function strip_harmful_tags($doc, $allowed_elements, $disallowed_attributes) {
		$xpath = new DOMXPath($doc);
		$entries = $xpath->query('//*');

		foreach ($entries as $entry) {
			if (!in_array($entry->nodeName, $allowed_elements)) {
				$entry->parentNode->removeChild($entry);
			}

			if ($entry->hasAttributes()) {
				$attrs_to_remove = array();

				foreach ($entry->attributes as $attr) {

					if (strpos($attr->nodeName, 'on') === 0) {
						array_push($attrs_to_remove, $attr);
					}

					if ($attr->nodeName == 'href' && stripos($attr->value, 'javascript:') === 0) {
						array_push($attrs_to_remove, $attr);
					}

					if (in_array($attr->nodeName, $disallowed_attributes)) {
						array_push($attrs_to_remove, $attr);
					}
				}

				foreach ($attrs_to_remove as $attr) {
					$entry->removeAttributeNode($attr);
				}
			}
		}

		return $doc;
	}

	function trim_array($array) {
		$tmp = $array;
		array_walk($tmp, 'trim');
		return $tmp;
	}

	function tag_is_valid($tag) {
		if ($tag == '') return false;
		if (is_numeric($tag)) return false;
		if (mb_strlen($tag) > 250) return false;

		if (!$tag) return false;

		return true;
	}

	function render_login_form() {
		header('Cache-Control: public');

		require_once "login_form.php";
		exit;
	}

	function T_sprintf() {
		$args = func_get_args();
		return vsprintf(__(array_shift($args)), $args);
	}

	function print_checkpoint($n, $s) {
		$ts = microtime(true);
		echo sprintf("<!-- CP[$n] %.4f seconds -->\n", $ts - $s);
		return $ts;
	}

	function sanitize_tag($tag) {
		$tag = trim($tag);

		$tag = mb_strtolower($tag, 'utf-8');

		$tag = preg_replace('/[,\'\"\+\>\<]/', "", $tag);

		if (DB_TYPE == "mysql") {
			$tag = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $tag);
		}

		return $tag;
	}

	function get_self_url_prefix() {
		if (strrpos(SELF_URL_PATH, "/") === strlen(SELF_URL_PATH)-1) {
			return substr(SELF_URL_PATH, 0, strlen(SELF_URL_PATH)-1);
		} else {
			return SELF_URL_PATH;
		}
	}

	function encrypt_password($pass, $salt = '', $mode2 = false) {
		if ($salt && $mode2) {
			return "MODE2:" . hash('sha256', $salt . $pass);
		} else if ($salt) {
			return "SHA1X:" . sha1("$salt:$pass");
		} else {
			return "SHA1:" . sha1($pass);
		}
	} // function encrypt_password

	function load_filters($feed_id, $owner_uid) {
		$filters = array();

		$cat_id = (int)Feeds::getFeedCategory($feed_id);

		if ($cat_id == 0)
			$null_cat_qpart = "cat_id IS NULL OR";
		else
			$null_cat_qpart = "";

		$result = db_query("SELECT * FROM ttrss_filters2 WHERE
				owner_uid = $owner_uid AND enabled = true ORDER BY order_id, title");

		$check_cats = array_merge(
			Feeds::getParentCategories($cat_id, $owner_uid),
			[$cat_id]);

		$check_cats_str = join(",", $check_cats);
		$check_cats_fullids = array_map(function($a) { return "CAT:$a"; }, $check_cats);

		while ($line = db_fetch_assoc($result)) {
			$filter_id = $line["id"];

            $match_any_rule = sql_bool_to_bool($line["match_any_rule"]);

			$result2 = db_query("SELECT
					r.reg_exp, r.inverse, r.feed_id, r.cat_id, r.cat_filter, r.match_on, t.name AS type_name
					FROM ttrss_filters2_rules AS r,
					ttrss_filter_types AS t
					WHERE
					    (match_on IS NOT NULL OR 
						  (($null_cat_qpart (cat_id IS NULL AND cat_filter = false) OR cat_id IN ($check_cats_str)) AND
						  (feed_id IS NULL OR feed_id = '$feed_id'))) AND
						filter_type = t.id AND filter_id = '$filter_id'");

			$rules = array();
			$actions = array();

			while ($rule_line = db_fetch_assoc($result2)) {
	#				print_r($rule_line);

                if ($rule_line["match_on"]) {
                    $match_on = json_decode($rule_line["match_on"], true);

                    if (in_array("0", $match_on) || in_array($feed_id, $match_on) || count(array_intersect($check_cats_fullids, $match_on)) > 0) {

                        $rule = array();
                        $rule["reg_exp"] = $rule_line["reg_exp"];
                        $rule["type"] = $rule_line["type_name"];
                        $rule["inverse"] = sql_bool_to_bool($rule_line["inverse"]);

                        array_push($rules, $rule);
                    } else if (!$match_any_rule) {
                        // this filter contains a rule that doesn't match to this feed/category combination
                        // thus filter has to be rejected

                        $rules = [];
                        break;
                    }

                } else {

                    $rule = array();
                    $rule["reg_exp"] = $rule_line["reg_exp"];
                    $rule["type"] = $rule_line["type_name"];
                    $rule["inverse"] = sql_bool_to_bool($rule_line["inverse"]);

                    array_push($rules, $rule);
                }
			}

			if (count($rules) > 0) {
                $result2 = db_query("SELECT a.action_param,t.name AS type_name
                        FROM ttrss_filters2_actions AS a,
                        ttrss_filter_actions AS t
                        WHERE
                            action_id = t.id AND filter_id = '$filter_id'");

                while ($action_line = db_fetch_assoc($result2)) {
                    #				print_r($action_line);

                    $action = array();
                    $action["type"] = $action_line["type_name"];
                    $action["param"] = $action_line["action_param"];

                    array_push($actions, $action);
                }
            }

			$filter = array();
			$filter["match_any_rule"] = sql_bool_to_bool($line["match_any_rule"]);
			$filter["inverse"] = sql_bool_to_bool($line["inverse"]);
			$filter["rules"] = $rules;
			$filter["actions"] = $actions;

			if (count($rules) > 0 && count($actions) > 0) {
				array_push($filters, $filter);
			}
		}

		return $filters;
	}

	function get_score_pic($score) {
		if ($score > 100) {
			return "score_high.png";
		} else if ($score > 0) {
			return "score_half_high.png";
		} else if ($score < -100) {
			return "score_low.png";
		} else if ($score < 0) {
			return "score_half_low.png";
		} else {
			return "score_neutral.png";
		}
	}

	function feed_has_icon($id) {
		return is_file(ICONS_DIR . "/$id.ico") && filesize(ICONS_DIR . "/$id.ico") > 0;
	}

	function init_plugins() {
		PluginHost::getInstance()->load(PLUGINS, PluginHost::KIND_ALL);

		return true;
	}

	function add_feed_category($feed_cat, $parent_cat_id = false) {

		if (!$feed_cat) return false;

		db_query("BEGIN");

		if ($parent_cat_id) {
			$parent_qpart = "parent_cat = '$parent_cat_id'";
			$parent_insert = "'$parent_cat_id'";
		} else {
			$parent_qpart = "parent_cat IS NULL";
			$parent_insert = "NULL";
		}

		$feed_cat = mb_substr($feed_cat, 0, 250);

		$result = db_query(
			"SELECT id FROM ttrss_feed_categories
				WHERE $parent_qpart AND title = '$feed_cat' AND owner_uid = ".$_SESSION["uid"]);

		if (db_num_rows($result) == 0) {

			$result = db_query(
				"INSERT INTO ttrss_feed_categories (owner_uid,title,parent_cat)
					VALUES ('".$_SESSION["uid"]."', '$feed_cat', $parent_insert)");

			db_query("COMMIT");

			return true;
		}

		return false;
	}

	/**
	 * Fixes incomplete URLs by prepending "http://".
	 * Also replaces feed:// with http://, and
	 * prepends a trailing slash if the url is a domain name only.
	 *
	 * @param string $url Possibly incomplete URL
	 *
	 * @return string Fixed URL.
	 */
	function fix_url($url) {

		// support schema-less urls
		if (strpos($url, '//') === 0) {
			$url = 'https:' . $url;
		}

		if (strpos($url, '://') === false) {
			$url = 'http://' . $url;
		} else if (substr($url, 0, 5) == 'feed:') {
			$url = 'http:' . substr($url, 5);
		}

		//prepend slash if the URL has no slash in it
		// "http://www.example" -> "http://www.example/"
		if (strpos($url, '/', strpos($url, ':') + 3) === false) {
			$url .= '/';
		}

		//convert IDNA hostname to punycode if possible
		if (function_exists("idn_to_ascii")) {
			$parts = parse_url($url);
			if (mb_detect_encoding($parts['host']) != 'ASCII')
			{
				$parts['host'] = idn_to_ascii($parts['host']);
				$url = build_url($parts);
			}
		}

		if ($url != "http:///")
			return $url;
		else
			return '';
	}

	function validate_feed_url($url) {
		$parts = parse_url($url);

		return ($parts['scheme'] == 'http' || $parts['scheme'] == 'feed' || $parts['scheme'] == 'https');

	}

	/* function save_email_address($email) {
		// FIXME: implement persistent storage of emails

		if (!$_SESSION['stored_emails'])
			$_SESSION['stored_emails'] = array();

		if (!in_array($email, $_SESSION['stored_emails']))
			array_push($_SESSION['stored_emails'], $email);
	} */


	function get_feed_access_key($feed_id, $is_cat, $owner_uid = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$sql_is_cat = bool_to_sql_bool($is_cat);

		$result = db_query("SELECT access_key FROM ttrss_access_keys
				WHERE feed_id = '$feed_id'	AND is_cat = $sql_is_cat
				AND owner_uid = " . $owner_uid);

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "access_key");
		} else {
			$key = db_escape_string(uniqid_short());

			$result = db_query("INSERT INTO ttrss_access_keys
					(access_key, feed_id, is_cat, owner_uid)
					VALUES ('$key', '$feed_id', $sql_is_cat, '$owner_uid')");

			return $key;
		}
		return false;
	}

	function get_feeds_from_html($url, $content)
	{
		$url     = fix_url($url);
		$baseUrl = substr($url, 0, strrpos($url, '/') + 1);

		libxml_use_internal_errors(true);

		$doc = new DOMDocument();
		$doc->loadHTML($content);
		$xpath = new DOMXPath($doc);
		$entries = $xpath->query('/html/head/link[@rel="alternate" and '.
			'(contains(@type,"rss") or contains(@type,"atom"))]|/html/head/link[@rel="feed"]');
		$feedUrls = array();
		foreach ($entries as $entry) {
			if ($entry->hasAttribute('href')) {
				$title = $entry->getAttribute('title');
				if ($title == '') {
					$title = $entry->getAttribute('type');
				}
				$feedUrl = rewrite_relative_url(
					$baseUrl, $entry->getAttribute('href')
				);
				$feedUrls[$feedUrl] = $title;
			}
		}
		return $feedUrls;
	}

	function is_html($content) {
		return preg_match("/<html|DOCTYPE html/i", substr($content, 0, 100)) !== 0;
	}

	function url_is_html($url, $login = false, $pass = false) {
		return is_html(fetch_file_contents($url, false, $login, $pass));
	}

	function build_url($parts) {
		return $parts['scheme'] . "://" . $parts['host'] . $parts['path'];
	}

	function cleanup_url_path($path) {
		$path = str_replace("/./", "/", $path);
		$path = str_replace("//", "/", $path);

		return $path;
	}

	/**
	 * Converts a (possibly) relative URL to a absolute one.
	 *
	 * @param string $url     Base URL (i.e. from where the document is)
	 * @param string $rel_url Possibly relative URL in the document
	 *
	 * @return string Absolute URL
	 */
	function rewrite_relative_url($url, $rel_url) {
		if (strpos($rel_url, "://") !== false) {
			return $rel_url;
		} else if (strpos($rel_url, "//") === 0) {
			# protocol-relative URL (rare but they exist)
			return $rel_url;
		} else if (preg_match("/^[a-z]+:/i", $rel_url)) {
			# magnet:, feed:, etc
			return $rel_url;
		} else if (strpos($rel_url, "/") === 0) {
			$parts = parse_url($url);
			$parts['path'] = $rel_url;
			$parts['path'] = cleanup_url_path($parts['path']);

			return build_url($parts);

		} else {
			$parts = parse_url($url);
			if (!isset($parts['path'])) {
				$parts['path'] = '/';
			}
			$dir = $parts['path'];
			if (substr($dir, -1) !== '/') {
				$dir = dirname($parts['path']);
				$dir !== '/' && $dir .= '/';
			}
			$parts['path'] = $dir . $rel_url;
			$parts['path'] = cleanup_url_path($parts['path']);

			return build_url($parts);
		}
	}

	function cleanup_tags($days = 14, $limit = 1000) {

		if (DB_TYPE == "pgsql") {
			$interval_query = "date_updated < NOW() - INTERVAL '$days days'";
		} else if (DB_TYPE == "mysql") {
			$interval_query = "date_updated < DATE_SUB(NOW(), INTERVAL $days DAY)";
		}

		$tags_deleted = 0;

		while ($limit > 0) {
			$limit_part = 500;

			$query = "SELECT ttrss_tags.id AS id
					FROM ttrss_tags, ttrss_user_entries, ttrss_entries
					WHERE post_int_id = int_id AND $interval_query AND
					ref_id = ttrss_entries.id AND tag_cache != '' LIMIT $limit_part";

			$result = db_query($query);

			$ids = array();

			while ($line = db_fetch_assoc($result)) {
				array_push($ids, $line['id']);
			}

			if (count($ids) > 0) {
				$ids = join(",", $ids);

				$tmp_result = db_query("DELETE FROM ttrss_tags WHERE id IN ($ids)");
				$tags_deleted += db_affected_rows($tmp_result);
			} else {
				break;
			}

			$limit -= $limit_part;
		}

		return $tags_deleted;
	}

	function print_user_stylesheet() {
		$value = get_pref('USER_STYLESHEET');

		if ($value) {
			print "<style type=\"text/css\">";
			print str_replace("<br/>", "\n", $value);
			print "</style>";
		}

	}

	function filter_to_sql($filter, $owner_uid) {
		$query = array();

		if (DB_TYPE == "pgsql")
			$reg_qpart = "~";
		else
			$reg_qpart = "REGEXP";

		foreach ($filter["rules"] AS $rule) {
			$rule['reg_exp'] = str_replace('/', '\/', $rule["reg_exp"]);
			$regexp_valid = preg_match('/' . $rule['reg_exp'] . '/',
					$rule['reg_exp']) !== FALSE;

			if ($regexp_valid) {

				$rule['reg_exp'] = db_escape_string($rule['reg_exp']);

				switch ($rule["type"]) {
					case "title":
						$qpart = "LOWER(ttrss_entries.title) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
					case "content":
						$qpart = "LOWER(ttrss_entries.content) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
					case "both":
						$qpart = "LOWER(ttrss_entries.title) $reg_qpart LOWER('".
							$rule['reg_exp'] . "') OR LOWER(" .
							"ttrss_entries.content) $reg_qpart LOWER('" . $rule['reg_exp'] . "')";
						break;
					case "tag":
						$qpart = "LOWER(ttrss_user_entries.tag_cache) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
					case "link":
						$qpart = "LOWER(ttrss_entries.link) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
					case "author":
						$qpart = "LOWER(ttrss_entries.author) $reg_qpart LOWER('".
							$rule['reg_exp'] . "')";
						break;
				}

				if (isset($rule['inverse'])) $qpart = "NOT ($qpart)";

				if (isset($rule["feed_id"]) && $rule["feed_id"] > 0) {
					$qpart .= " AND feed_id = " . db_escape_string($rule["feed_id"]);
				}

				if (isset($rule["cat_id"])) {

					if ($rule["cat_id"] > 0) {
						$children = Feeds::getChildCategories($rule["cat_id"], $owner_uid);
						array_push($children, $rule["cat_id"]);

						$children = join(",", $children);

						$cat_qpart = "cat_id IN ($children)";
					} else {
						$cat_qpart = "cat_id IS NULL";
					}

					$qpart .= " AND $cat_qpart";
				}

				$qpart .= " AND feed_id IS NOT NULL";

				array_push($query, "($qpart)");

			}
		}

		if (count($query) > 0) {
			$fullquery = "(" . join($filter["match_any_rule"] ? "OR" : "AND", $query) . ")";
		} else {
			$fullquery = "(false)";
		}

		if ($filter['inverse']) $fullquery = "(NOT $fullquery)";

		return $fullquery;
	}

	if (!function_exists('gzdecode')) {
		function gzdecode($string) { // no support for 2nd argument
			return file_get_contents('compress.zlib://data:who/cares;base64,'.
				base64_encode($string));
		}
	}

	function get_random_bytes($length) {
		if (function_exists('openssl_random_pseudo_bytes')) {
			return openssl_random_pseudo_bytes($length);
		} else {
			$output = "";

			for ($i = 0; $i < $length; $i++)
				$output .= chr(mt_rand(0, 255));

			return $output;
		}
	}

	function read_stdin() {
		$fp = fopen("php://stdin", "r");

		if ($fp) {
			$line = trim(fgets($fp));
			fclose($fp);
			return $line;
		}

		return null;
	}

	function implements_interface($class, $interface) {
		return in_array($interface, class_implements($class));
	}

	function get_minified_js($files) {
		require_once 'lib/jshrink/Minifier.php';

		$rv = '';

		foreach ($files as $js) {
			if (!isset($_GET['debug'])) {
				$cached_file = CACHE_DIR . "/js/".basename($js).".js";

				if (file_exists($cached_file) && is_readable($cached_file) && filemtime($cached_file) >= filemtime("js/$js.js")) {

					list($header, $contents) = explode("\n", file_get_contents($cached_file), 2);

					if ($header && $contents) {
						list($htag, $hversion) = explode(":", $header);

						if ($htag == "tt-rss" && $hversion == VERSION) {
							$rv .= $contents;
							continue;
						}
					}
				}

				$minified = JShrink\Minifier::minify(file_get_contents("js/$js.js"));
				file_put_contents($cached_file, "tt-rss:" . VERSION . "\n" . $minified);
				$rv .= $minified;

			} else {
				$rv .= file_get_contents("js/$js.js"); // no cache in debug mode
			}
		}

		return $rv;
	}

	function calculate_dep_timestamp() {
		$files = array_merge(glob("js/*.js"), glob("css/*.css"));

		$max_ts = -1;

		foreach ($files as $file) {
			if (filemtime($file) > $max_ts) $max_ts = filemtime($file);
		}

		return $max_ts;
	}

	function T_js_decl($s1, $s2) {
		if ($s1 && $s2) {
			$s1 = preg_replace("/\n/", "", $s1);
			$s2 = preg_replace("/\n/", "", $s2);

			$s1 = preg_replace("/\"/", "\\\"", $s1);
			$s2 = preg_replace("/\"/", "\\\"", $s2);

			return "T_messages[\"$s1\"] = \"$s2\";\n";
		}
	}

	function init_js_translations() {

		print 'var T_messages = new Object();
	
			function __(msg) {
				if (T_messages[msg]) {
					return T_messages[msg];
				} else {
					return msg;
				}
			}
	
			function ngettext(msg1, msg2, n) {
				return __((parseInt(n) > 1) ? msg2 : msg1);
			}';

		$l10n = _get_reader();

		for ($i = 0; $i < $l10n->total; $i++) {
			$orig = $l10n->get_original_string($i);
			if(strpos($orig, "\000") !== FALSE) { // Plural forms
				$key = explode(chr(0), $orig);
				print T_js_decl($key[0], _ngettext($key[0], $key[1], 1)); // Singular
				print T_js_decl($key[1], _ngettext($key[0], $key[1], 2)); // Plural
			} else {
				$translation = __($orig);
				print T_js_decl($orig, $translation);
			}
		}
	}

	function get_theme_path($theme) {
		$check = "themes/$theme";
		if (file_exists($check)) return $check;

		$check = "themes.local/$theme";
		if (file_exists($check)) return $check;
	}

	function theme_valid($theme) {
		$bundled_themes = [ "default.php", "night.css", "compact.css" ];

		if (in_array($theme, $bundled_themes)) return true;

		$file = "themes/" . basename($theme);

		if (!file_exists($file)) $file = "themes.local/" . basename($theme);

		if (file_exists($file) && is_readable($file)) {
			$fh = fopen($file, "r");

			if ($fh) {
				$header = fgets($fh);
				fclose($fh);

				return strpos($header, "supports-version:" . VERSION_STATIC) !== FALSE;
			}
		}

		return false;
	}

	/**
	 * @SuppressWarnings(unused)
	 */
	function error_json($code) {
		require_once "errors.php";

		@$message = $ERRORS[$code];

		return json_encode(array("error" =>
			array("code" => $code, "message" => $message)));

	}

	/*function abs_to_rel_path($dir) {
		$tmp = str_replace(dirname(__DIR__), "", $dir);

		if (strlen($tmp) > 0 && substr($tmp, 0, 1) == "/") $tmp = substr($tmp, 1);

		return $tmp;
	}*/

	function get_upload_error_message($code) {

		$errors = array(
			0 => __('There is no error, the file uploaded with success'),
			1 => __('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			2 => __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			3 => __('The uploaded file was only partially uploaded'),
			4 => __('No file was uploaded'),
			6 => __('Missing a temporary folder'),
			7 => __('Failed to write file to disk.'),
			8 => __('A PHP extension stopped the file upload.'),
		);

		return $errors[$code];
	}

	function base64_img($filename) {
		if (file_exists($filename)) {
			$ext = pathinfo($filename, PATHINFO_EXTENSION);

			return "data:image/$ext;base64," . base64_encode(file_get_contents($filename));
		} else {
			return "";
		}
	}


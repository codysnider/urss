<?php
	define('EXPECTED_CONFIG_VERSION', 26);
	define('SCHEMA_VERSION', 138);

	define('LABEL_BASE_INDEX', -1024);
	define('PLUGIN_FEED_BASE_INDEX', -128);

	define('COOKIE_LIFETIME_LONG', 86400*365);

	$fetch_last_error = false;
	$fetch_last_error_code = false;
	$fetch_last_content_type = false;
	$fetch_last_error_content = false; // curl only for the time being
	$fetch_effective_url = false;
	$fetch_curl_used = false;

	libxml_disable_entity_loader(true);
	libxml_use_internal_errors(true);

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
	define_default('MAX_CACHE_FILE_SIZE', 64*1024*1024);
	// do not cache files larger than that (bytes)
	define_default('MAX_DOWNLOAD_FILE_SIZE', 16*1024*1024);
	// do not download general files larger than that (bytes)
	define_default('CACHE_MAX_DAYS', 7);
	// max age in days for various automatically cached (temporary) files
	define_default('MAX_CONDITIONAL_INTERVAL', 3600*12);
	// max interval between forced unconditional updates for servers
	// not complying with http if-modified-since (seconds)

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
					"auto"  => __("Detect automatically"),
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
					"uk_UA" => "Українська",
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

	// TODO: compat wrapper, remove at some point
	function _debug($msg) {
	    Debug::log($msg);
	}

	// TODO: max_size currently only works for CURL transfers
	// TODO: multiple-argument way is deprecated, first parameter is a hash now
	function fetch_file_contents($options /* previously: 0: $url , 1: $type = false, 2: $login = false, 3: $pass = false,
				4: $post_query = false, 5: $timeout = false, 6: $timestamp = 0, 7: $useragent = false*/) {

		global $fetch_last_error;
		global $fetch_last_error_code;
		global $fetch_last_error_content;
		global $fetch_last_content_type;
		global $fetch_last_modified;
		global $fetch_effective_url;
		global $fetch_curl_used;

		$fetch_last_error = false;
		$fetch_last_error_code = -1;
		$fetch_last_error_content = "";
		$fetch_last_content_type = "";
		$fetch_curl_used = false;
		$fetch_last_modified = "";
		$fetch_effective_url = "";

		if (!is_array($options)) {

			// falling back on compatibility shim
			$option_names = [ "url", "type", "login", "pass", "post_query", "timeout", "last_modified", "useragent" ];
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
		$last_modified = isset($options["last_modified"]) ? $options["last_modified"] : "";
		$useragent = isset($options["useragent"]) ? $options["useragent"] : false;
		$followlocation = isset($options["followlocation"]) ? $options["followlocation"] : true;
		$max_size = isset($options["max_size"]) ? $options["max_size"] : MAX_DOWNLOAD_FILE_SIZE; // in bytes
		$http_accept = isset($options["http_accept"]) ? $options["http_accept"] : false;

		$url = ltrim($url, ' ');
		$url = str_replace(' ', '%20', $url);

		if (strpos($url, "//") === 0)
			$url = 'http:' . $url;

		if (!defined('NO_CURL') && function_exists('curl_init') && !ini_get("open_basedir")) {

			$fetch_curl_used = true;

			$ch = curl_init($url);

			$curl_http_headers = [];

			if ($last_modified && !$post_query)
				array_push($curl_http_headers, "If-Modified-Since: $last_modified");

			if ($http_accept)
				array_push($curl_http_headers, "Accept: " . $http_accept);

			if (count($curl_http_headers) > 0)
				curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_http_headers);

			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout ? $timeout : FILE_FETCH_CONNECT_TIMEOUT);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout ? $timeout : FILE_FETCH_TIMEOUT);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, !ini_get("open_basedir") && $followlocation);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 20);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERAGENT, $useragent ? $useragent :
				SELF_USER_AGENT);
			curl_setopt($ch, CURLOPT_ENCODING, "");
			//curl_setopt($ch, CURLOPT_REFERER, $url);

			if ($max_size) {
				curl_setopt($ch, CURLOPT_NOPROGRESS, false);
				curl_setopt($ch, CURLOPT_BUFFERSIZE, 16384); // needed to get 5 arguments in progress function?

				// holy shit closures in php
				// download & upload are *expected* sizes respectively, could be zero
				curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($curl_handle, $download_size, $downloaded, $upload_size, $uploaded) use( &$max_size) {
					Debug::log("[curl progressfunction] $downloaded $max_size", Debug::$LOG_EXTENDED);

					return ($downloaded > $max_size) ? 1 : 0; // if max size is set, abort when exceeding it
				});

			}

			if (!ini_get("open_basedir")) {
				curl_setopt($ch, CURLOPT_COOKIEJAR, "/dev/null");
			}

			if (defined('_HTTP_PROXY')) {
				curl_setopt($ch, CURLOPT_PROXY, _HTTP_PROXY);
			}

			if ($post_query) {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_query);
			}

			if ($login && $pass)
				curl_setopt($ch, CURLOPT_USERPWD, "$login:$pass");

			$ret = @curl_exec($ch);

			$headers_length = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$headers = explode("\r\n", substr($ret, 0, $headers_length));
			$contents = substr($ret, $headers_length);

			foreach ($headers as $header) {
				if (strstr($header, ": ") !== FALSE) {
					list ($key, $value) = explode(": ", $header);

					if (strtolower($key) == "last-modified") {
						$fetch_last_modified = $value;
					}
				}

				if (substr(strtolower($header), 0, 7) == 'http/1.') {
					$fetch_last_error_code = (int) substr($header, 9, 3);
					$fetch_last_error = $header;
				}
			}

			if (curl_errno($ch) === 23 || curl_errno($ch) === 61) {
				curl_setopt($ch, CURLOPT_ENCODING, 'none');
				$contents = @curl_exec($ch);
			}

			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$fetch_last_content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

			$fetch_effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

			$fetch_last_error_code = $http_code;

			if ($http_code != 200 || $type && strpos($fetch_last_content_type, "$type") === false) {

				if (curl_errno($ch) != 0) {
					$fetch_last_error .=  "; " . curl_errno($ch) . " " . curl_error($ch);
				}

				$fetch_last_error_content = $contents;
				curl_close($ch);
				return false;
			}

			if (!$contents) {
				$fetch_last_error = curl_errno($ch) . " " . curl_error($ch);
				curl_close($ch);
				return false;
			}

			curl_close($ch);

			$is_gzipped = RSSUtils::is_gzipped($contents);

			if ($is_gzipped) {
				$tmp = @gzdecode($contents);

				if ($tmp) $contents = $tmp;
			}

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

			 $context_options = array(
				  'http' => array(
						'header' => array(
							'Connection: close'
						),
						'method' => 'GET',
						'ignore_errors' => true,
						'timeout' => $timeout ? $timeout : FILE_FETCH_TIMEOUT,
						'protocol_version'=> 1.1)
				  );

			if (!$post_query && $last_modified)
				array_push($context_options['http']['header'], "If-Modified-Since: $last_modified");

			if ($http_accept)
				array_push($context_options['http']['header'], "Accept: $http_accept");

			if (defined('_HTTP_PROXY')) {
				$context_options['http']['request_fulluri'] = true;
				$context_options['http']['proxy'] = _HTTP_PROXY;
			}

			$context = stream_context_create($context_options);

			$old_error = error_get_last();

			$fetch_effective_url = $url;

			$data = @file_get_contents($url, false, $context);

			if (isset($http_response_header) && is_array($http_response_header)) {
				foreach ($http_response_header as $header) {
					if (strstr($header, ": ") !== FALSE) {
						list ($key, $value) = explode(": ", $header);

						$key = strtolower($key);

						if ($key == 'content-type') {
							$fetch_last_content_type = $value;
							// don't abort here b/c there might be more than one
							// e.g. if we were being redirected -- last one is the right one
						} else if ($key == 'last-modified') {
							$fetch_last_modified = $value;
						} else if ($key == 'location') {
							$fetch_effective_url = $value;
						}
					}

					if (substr(strtolower($header), 0, 7) == 'http/1.') {
						$fetch_last_error_code = (int) substr($header, 9, 3);
						$fetch_last_error = $header;
					}
				}
			}

			if ($fetch_last_error_code != 200) {
				$error = error_get_last();

				if ($error['message'] != $old_error['message']) {
					$fetch_last_error .= "; " . $error["message"];
				}

				$fetch_last_error_content = $data;

				return false;
			}

			$is_gzipped = RSSUtils::is_gzipped($data);

			if ($is_gzipped) {
				$tmp = @gzdecode($data);

				if ($tmp) $data = $tmp;
			}

			return $data;
		}

	}

	function initialize_user_prefs($uid, $profile = false) {

		if (get_schema_version() < 63) $profile_qpart = "";

		$pdo = DB::pdo();
		$in_nested_tr = false;

		try {
			$pdo->beginTransaction();
		} catch (Exception $e) {
			$in_nested_tr = true;
		}

		$sth = $pdo->query("SELECT pref_name,def_value FROM ttrss_prefs");

		if (!is_numeric($profile) || !$profile || get_schema_version() < 63) $profile = null;

		$u_sth = $pdo->prepare("SELECT pref_name
			FROM ttrss_user_prefs WHERE owner_uid = :uid AND
				(profile = :profile OR (:profile IS NULL AND profile IS NULL))");
		$u_sth->execute([':uid' => $uid, ':profile' => $profile]);

		$active_prefs = array();

		while ($line = $u_sth->fetch()) {
			array_push($active_prefs, $line["pref_name"]);
		}

		while ($line = $sth->fetch()) {
			if (array_search($line["pref_name"], $active_prefs) === FALSE) {
//				print "adding " . $line["pref_name"] . "<br>";

				if (get_schema_version() < 63) {
					$i_sth = $pdo->prepare("INSERT INTO ttrss_user_prefs
						(owner_uid,pref_name,value) VALUES
						(?, ?, ?)");
					$i_sth->execute([$uid, $line["pref_name"], $line["def_value"]]);

				} else {
					$i_sth = $pdo->prepare("INSERT INTO ttrss_user_prefs
						(owner_uid,pref_name,value, profile) VALUES
						(?, ?, ?, ?)");
					$i_sth->execute([$uid, $line["pref_name"], $line["def_value"], $profile]);
				}

			}
		}

		if (!$in_nested_tr) $pdo->commit();

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
			$auth_module = false;

			foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_AUTH_USER) as $plugin) {

				$user_id = (int) $plugin->authenticate($login, $password);

				if ($user_id) {
					$auth_module = strtolower(get_class($plugin));
					break;
				}
			}

			if ($user_id && !$check_only) {

				session_start();
				session_regenerate_id(true);

				$_SESSION["uid"] = $user_id;
				$_SESSION["version"] = VERSION_STATIC;
				$_SESSION["auth_module"] = $auth_module;

				$pdo = DB::pdo();
				$sth = $pdo->prepare("SELECT login,access_level,pwd_hash FROM ttrss_users
					WHERE id = ?");
				$sth->execute([$user_id]);
				$row = $sth->fetch();

				$_SESSION["name"] = $row["login"];
				$_SESSION["access_level"] = $row["access_level"];
				$_SESSION["csrf_token"] = uniqid_short();

				$usth = $pdo->prepare("UPDATE ttrss_users SET last_login = NOW() WHERE id = ?");
				$usth->execute([$user_id]);

				$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
				$_SESSION["user_agent"] = sha1($_SERVER['HTTP_USER_AGENT']);
				$_SESSION["pwd_hash"] = $row["pwd_hash"];

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

	// this is used for user http parameters unless HTML code is actually needed
	function clean($param) {
		if (is_array($param)) {
			return array_map("strip_tags", $param);
		} else if (is_string($param)) {
			return strip_tags($param);
		} else {
			return $param;
		}
	}

	function clean_filename($filename) {
		return basename(preg_replace("/\.\.|[\/\\\]/", "", clean($filename)));
	}

	function make_password($length = 12) {
		$password = "";
		$possible = "0123456789abcdfghjkmnpqrstvwxyzABCDFGHJKMNPQRSTVWXYZ*%+^";

		$i = 0;

		while ($i < $length) {

			try {
				$idx = function_exists("random_int") ? random_int(0, strlen($possible) - 1) : mt_rand(0, strlen($possible) - 1);
			} catch (Exception $e) {
				$idx = mt_rand(0, strlen($possible) - 1);
			}

			$char = substr($possible, $idx, 1);

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

		$pdo = DB::pdo();

		$sth = $pdo->prepare("insert into ttrss_feeds (owner_uid,title,feed_url)
			values (?, 'Tiny Tiny RSS: Forum',
				'http://tt-rss.org/forum/rss.php')");
		$sth->execute([$uid]);
	}

	function logout_user() {
		@session_destroy();
		if (isset($_COOKIE[session_name()])) {
		   setcookie(session_name(), '', time()-42000, '/');
		}
		session_commit();
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
		$pdo = Db::pdo();

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
					logout_user();

					render_login_form();
					exit;
				}

			} else {
				/* bump login timestamp */
				$sth = $pdo->prepare("UPDATE ttrss_users SET last_login = NOW() WHERE id = ?");
				$sth->execute([$_SESSION['uid']]);

				$_SESSION["last_login_update"] = time();
			}

			if ($_SESSION["uid"]) {
				startup_gettext();
				load_user_plugins($_SESSION["uid"]);

				/* cleanup ccache */

				$sth = $pdo->prepare("DELETE FROM ttrss_counters_cache WHERE owner_uid = ?
					AND
						(SELECT COUNT(id) FROM ttrss_feeds WHERE
							ttrss_feeds.id = feed_id) = 0");

				$sth->execute([$_SESSION['uid']]);

				$sth = $pdo->prepare("DELETE FROM ttrss_cat_counters_cache WHERE owner_uid = ?
					AND
						(SELECT COUNT(id) FROM ttrss_feed_categories WHERE
							ttrss_feed_categories.id = feed_id) = 0");

				$sth->execute([$_SESSION['uid']]);
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

	function mb_substr_replace($original, $replacement, $position, $length) {
		$startString = mb_substr($original, 0, $position, "UTF-8");
		$endString = mb_substr($original, $position + $length, mb_strlen($original), "UTF-8");

		$out = $startString . $replacement . $endString;

		return $out;
	}

	function truncate_middle($str, $max_len, $suffix = '&hellip;') {
		if (mb_strlen($str) > $max_len) {
			return mb_substr_replace($str, $suffix, $max_len / 2, mb_strlen($str) - $max_len);
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
			$format = get_pref('SHORT_DATE_FORMAT', $owner_uid);
			if (strpos((strtolower($format)), "a") === false)
				return date("G:i", $timestamp);
			else
				return date("g:i a", $timestamp);
		} else if (date("Y", $timestamp) == date("Y", time() + $tz_offset)) {
			$format = get_pref('SHORT_DATE_FORMAT', $owner_uid);
			return date($format, $timestamp);
		} else {
			$format = get_pref('LONG_DATE_FORMAT', $owner_uid);
			return date($format, $timestamp);
		}
	}

	function sql_bool_to_bool($s) {
		return $s && ($s !== "f" && $s !== "false"); //no-op for PDO, backwards compat for legacy layer
	}

	function bool_to_sql_bool($s) {
		return $s ? 1 : 0;
	}

	// Session caching removed due to causing wrong redirects to upgrade
	// script when get_schema_version() is called on an obsolete session
	// created on a previous schema version.
	function get_schema_version($nocache = false) {
		global $schema_version;

		$pdo = DB::pdo();

		if (!$schema_version && !$nocache) {
			$row = $pdo->query("SELECT schema_version FROM ttrss_version")->fetch();
			$version = $row["schema_version"];
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

	function checkbox_to_sql_bool($val) {
		return ($val == "on") ? 1 : 0;
	}

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

		$params["check_for_updates"] = CHECK_FOR_UPDATES;
		$params["icons_url"] = ICONS_URL;
		$params["cookie_lifetime"] = SESSION_COOKIE_LIFETIME;
		$params["default_view_mode"] = get_pref("_DEFAULT_VIEW_MODE");
		$params["default_view_limit"] = (int) get_pref("_DEFAULT_VIEW_LIMIT");
		$params["default_view_order_by"] = get_pref("_DEFAULT_VIEW_ORDER_BY");
		$params["bw_limit"] = (int) $_SESSION["bw_limit"];
		$params["is_default_pw"] = Pref_Prefs::isdefaultpassword();
		$params["label_base_index"] = (int) LABEL_BASE_INDEX;

		$theme = get_pref( "USER_CSS_THEME", false, false);
		$params["theme"] = theme_exists($theme) ? $theme : "";

		$params["plugins"] = implode(", ", PluginHost::getInstance()->get_plugin_names());

		$params["php_platform"] = PHP_OS;
		$params["php_version"] = PHP_VERSION;

		$params["sanity_checksum"] = sha1(file_get_contents("include/sanity_check.php"));

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT MAX(id) AS mid, COUNT(*) AS nf FROM
				ttrss_feeds WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
		$row = $sth->fetch();

		$max_feed_id = $row["mid"];
		$num_feeds = $row["nf"];

		$params["max_feed_id"] = (int) $max_feed_id;
		$params["num_feeds"] = (int) $num_feeds;

		$params["hotkeys"] = get_hotkeys_map();

		$params["csrf_token"] = $_SESSION["csrf_token"];
		$params["widescreen"] = (int) $_COOKIE["ttrss_widescreen"];

		$params['simple_update'] = defined('SIMPLE_UPDATE_MODE') && SIMPLE_UPDATE_MODE;

		$params["icon_indicator_white"] = base64_img("images/indicator_white.gif");

		$params["labels"] = Labels::get_all_labels($_SESSION["uid"]);

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
				"toggle_cdm_expanded" => __("Toggle auto expand in combined mode"),
				"toggle_combined_mode" => __("Toggle combined mode")),
			__("Go to") => array(
				"goto_all" => __("All articles"),
				"goto_fresh" => __("Fresh"),
				"goto_marked" => __("Starred"),
				"goto_published" => __("Published"),
				"goto_read" => __("Recently read"),
				"goto_tagcloud" => __("Tag cloud"),
				"goto_prefs" => __("Preferences")),
			__("Other") => array(
				"create_label" => __("Create label"),
				"create_filter" => __("Create filter"),
				"collapse_sidebar" => __("Un/collapse sidebar"),
				"toggle_night_mode" => __("Toggle night mode"),
				"help_dialog" => __("Show help dialog"))
		);

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_HOTKEY_INFO) as $plugin) {
			$hotkeys = $plugin->hook_hotkey_info($hotkeys);
		}

		return $hotkeys;
	}

	function get_hotkeys_map() {
		$hotkeys = array(
			"k" => "next_feed",
			"j" => "prev_feed",
			"n" => "next_article",
			"p" => "prev_article",
			"(38)|Up" => "prev_article",
			"(40)|Down" => "next_article",
			"*(38)|Shift+Up" => "article_scroll_up",
			"*(40)|Shift+Down" => "article_scroll_down",
			"^(38)|Ctrl+Up" => "prev_article_noscroll",
			"^(40)|Ctrl+Down" => "next_article_noscroll",
			"/" => "search_dialog",
			"s" => "toggle_mark",
			"S" => "toggle_publ",
			"u" => "toggle_unread",
			"T" => "edit_tags",
			"o" => "open_in_new_window",
			"c p" => "catchup_below",
			"c n" => "catchup_above",
			"N" => "article_scroll_down",
			"P" => "article_scroll_up",
			"a W" => "toggle_widescreen",
			"a e" => "toggle_embed_original",
			"e" => "email_article",
			"a q" => "close_article",
			"a a" => "select_all",
			"a u" => "select_unread",
			"a U" => "select_marked",
			"a p" => "select_published",
			"a i" => "select_invert",
			"a n" => "select_none",
			"f r" => "feed_refresh",
			"f a" => "feed_unhide_read",
			"f s" => "feed_subscribe",
			"f e" => "feed_edit",
			"f q" => "feed_catchup",
			"f x" => "feed_reverse",
			"f g" => "feed_toggle_vgroup",
			"f D" => "feed_debug_update",
			"f G" => "feed_debug_viewfeed",
			"f C" => "toggle_combined_mode",
			"f c" => "toggle_cdm_expanded",
			"Q" => "catchup_all",
			"x" => "cat_toggle_collapse",
			"g a" => "goto_all",
			"g f" => "goto_fresh",
			"g s" => "goto_marked",
			"g p" => "goto_published",
			"g r" => "goto_read",
			"g t" => "goto_tagcloud",
			"g P" => "goto_prefs",
			"r" => "select_article_cursor",
			"c l" => "create_label",
			"c f" => "create_filter",
			"c s" => "collapse_sidebar",
			"a N" => "toggle_night_mode",
			"?" => "help_dialog",
		);

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

	function make_runtime_info() {
		$data = array();

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT MAX(id) AS mid, COUNT(*) AS nf FROM
				ttrss_feeds WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
		$row = $sth->fetch();

		$max_feed_id = $row['mid'];
		$num_feeds = $row['nf'];

		$data["max_feed_id"] = (int) $max_feed_id;
		$data["num_feeds"] = (int) $num_feeds;
		$data['cdm_expanded'] = get_pref('CDM_EXPANDED');
		$data["labels"] = Labels::get_all_labels($_SESSION["uid"]);

		if (LOG_DESTINATION == 'sql' && $_SESSION['access_level'] >= 10) {
			if (DB_TYPE == 'pgsql') {
				$log_interval = "created_at > NOW() - interval '1 hour'";
			} else {
				$log_interval = "created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
			}

			$sth = $pdo->prepare("SELECT COUNT(id) AS cid FROM ttrss_error_log WHERE $log_interval");
			$sth->execute();

			if ($row = $sth->fetch()) {
				$data['recent_log_events'] = $row['cid'];
			}
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

		$doc = new DOMDocument();
		$doc->loadHTML('<?xml encoding="UTF-8">' . $res);
		$xpath = new DOMXPath($doc);

		$rewrite_base_url = $site_url ? $site_url : get_self_url_prefix();

		$entries = $xpath->query('(//a[@href]|//img[@src]|//video/source[@src]|//audio/source[@src]|//picture/source[@src])');

		foreach ($entries as $entry) {

			if ($entry->hasAttribute('href')) {
				$entry->setAttribute('href',
					rewrite_relative_url($rewrite_base_url, $entry->getAttribute('href')));

				$entry->setAttribute('rel', 'noopener noreferrer');
			}

			if ($entry->hasAttribute('src')) {
				$src = rewrite_relative_url($rewrite_base_url, $entry->getAttribute('src'));
				$entry->setAttribute('src', $src);
			}

			if ($entry->nodeName == 'img') {
				$entry->setAttribute('referrerpolicy', 'no-referrer');

				$entry->removeAttribute('width');
				$entry->removeAttribute('height');

				if ($entry->hasAttribute('src')) {
					$is_https_url = parse_url($entry->getAttribute('src'), PHP_URL_SCHEME) === 'https';

					if (is_prefix_https() && !$is_https_url) {

						if ($entry->hasAttribute('srcset')) {
							$entry->removeAttribute('srcset');
						}

						if ($entry->hasAttribute('sizes')) {
							$entry->removeAttribute('sizes');
						}
					}
				}
			}

			if ($entry->hasAttribute('src') &&
					($owner && get_pref("STRIP_IMAGES", $owner)) || $force_remove_images || $_SESSION["bw_limit"]) {

				$p = $doc->createElement('p');

				$a = $doc->createElement('a');
				$a->setAttribute('href', $entry->getAttribute('src'));

				$a->appendChild(new DOMText($entry->getAttribute('src')));
				$a->setAttribute('target', '_blank');
				$a->setAttribute('rel', 'noopener noreferrer');

				$p->appendChild($a);

				if ($entry->nodeName == 'source') {

					if ($entry->parentNode && $entry->parentNode->parentNode)
						$entry->parentNode->parentNode->replaceChild($p, $entry->parentNode);

				} else if ($entry->nodeName == 'img') {

					if ($entry->parentNode)
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
				if (is_prefix_https()) {
					$entry->setAttribute("src",
						str_replace("http://", "https://",
							$entry->getAttribute("src")));
				}
			}
		}

		$allowed_elements = array('a', 'abbr', 'address', 'acronym', 'audio', 'article', 'aside',
			'b', 'bdi', 'bdo', 'big', 'blockquote', 'body', 'br',
			'caption', 'cite', 'center', 'code', 'col', 'colgroup',
			'data', 'dd', 'del', 'details', 'description', 'dfn', 'div', 'dl', 'font',
			'dt', 'em', 'footer', 'figure', 'figcaption',
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'html', 'i',
			'img', 'ins', 'kbd', 'li', 'main', 'mark', 'nav', 'noscript',
			'ol', 'p', 'picture', 'pre', 'q', 'ruby', 'rp', 'rt', 's', 'samp', 'section',
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

					if (strpos($attr->nodeName, "data-") === 0) {
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

	function is_server_https() {
		return (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] != 'off')) || $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';
	}

	function is_prefix_https() {
		return parse_url(SELF_URL_PATH, PHP_URL_SCHEME) == 'https';
	}

	// this returns SELF_URL_PATH sans ending slash
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

	function init_plugins() {
		PluginHost::getInstance()->load(PLUGINS, PluginHost::KIND_ALL);

		return true;
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

	function print_user_stylesheet() {
		$value = get_pref('USER_STYLESHEET');

		if ($value) {
			print "<style type=\"text/css\">";
			print str_replace("<br/>", "\n", $value);
			print "</style>";
		}

	}

	/* function filter_to_sql($filter, $owner_uid) {
		$query = array();

		$pdo = Db::pdo();

		if (DB_TYPE == "pgsql")
			$reg_qpart = "~";
		else
			$reg_qpart = "REGEXP";

		foreach ($filter["rules"] AS $rule) {
			$rule['reg_exp'] = str_replace('/', '\/', $rule["reg_exp"]);
			$regexp_valid = preg_match('/' . $rule['reg_exp'] . '/',
					$rule['reg_exp']) !== FALSE;

			if ($regexp_valid) {

				$rule['reg_exp'] = $pdo->quote($rule['reg_exp']);

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
					$qpart .= " AND feed_id = " . $pdo->quote($rule["feed_id"]);
				}

				if (isset($rule["cat_id"])) {

					if ($rule["cat_id"] > 0) {
						$children = Feeds::getChildCategories($rule["cat_id"], $owner_uid);
						array_push($children, $rule["cat_id"]);
						$children = array_map("intval", $children);

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
	} */

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

		global $text_domains;

		foreach (array_keys($text_domains) as $domain) {
			$l10n = _get_reader($domain);

			for ($i = 0; $i < $l10n->total; $i++) {
				$orig = $l10n->get_original_string($i);
				if(strpos($orig, "\000") !== FALSE) { // Plural forms
					$key = explode(chr(0), $orig);
					print T_js_decl($key[0], _ngettext($key[0], $key[1], 1)); // Singular
					print T_js_decl($key[1], _ngettext($key[0], $key[1], 2)); // Plural
				} else {
					$translation = _dgettext($domain,$orig);
					print T_js_decl($orig, $translation);
				}
			}

		}
	}

	function get_theme_path($theme) {
		if ($theme == "default.php")
			return "css/default.css";

		$check = "themes/$theme";
		if (file_exists($check)) return $check;

		$check = "themes.local/$theme";
		if (file_exists($check)) return $check;
	}

	function theme_exists($theme) {
		return file_exists("themes/$theme") || file_exists("themes.local/$theme");
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

	/*	this is essentially a wrapper for readfile() which allows plugins to hook
		output with httpd-specific "fast" implementation i.e. X-Sendfile or whatever else

		hook function should return true if request was handled (or at least attempted to)

		note that this can be called without user context so the plugin to handle this
		should be loaded systemwide in config.php */
	function send_local_file($filename) {
		if (file_exists($filename)) {

			if (is_writable($filename)) touch($filename);

			$tmppluginhost = new PluginHost();

			$tmppluginhost->load(PLUGINS, PluginHost::KIND_SYSTEM);
			$tmppluginhost->load_data();

			foreach ($tmppluginhost->get_hooks(PluginHost::HOOK_SEND_LOCAL_FILE) as $plugin) {
				if ($plugin->hook_send_local_file($filename)) return true;
			}

			$mimetype = mime_content_type($filename);

			// this is hardly ideal but 1) only media is cached in images/ and 2) seemingly only mp4
			// video files are detected as octet-stream by mime_content_type()

			if ($mimetype == "application/octet-stream")
				$mimetype = "video/mp4";

			header("Content-type: $mimetype");

			$stamp = gmdate("D, d M Y H:i:s", filemtime($filename)) . " GMT";
			header("Last-Modified: $stamp", true);

			return readfile($filename);
		} else {
			return false;
		}
	}

	function arr_qmarks($arr) {
		return str_repeat('?,', count($arr) - 1) . '?';
	}

	function get_scripts_timestamp() {
		$files = glob("js/*.js");
		$ts = 0;

		foreach ($files as $file) {
			$file_ts = filemtime($file);
			if ($file_ts > $ts) $ts = $file_ts;
		}

		return $ts;
	}

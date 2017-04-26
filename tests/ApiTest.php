<?php
use PHPUnit\Framework\TestCase;

set_include_path(dirname(__DIR__) ."/include" . PATH_SEPARATOR .
	dirname(__DIR__) . PATH_SEPARATOR .
	get_include_path());

require_once "autoload.php";

final class ApiTest extends TestCase {

	public function __construct() {
		init_plugins();
		initialize_user_prefs(1);
		set_pref('ENABLE_API_ACCESS', true, 1);

		parent::__construct();
	}

	public function apiCall($args, $method) {
		$_REQUEST = $args;

		$api = new API($args);
		ob_start();
		$api->$method();
		$rv = json_decode(ob_get_contents(), true);
		ob_end_clean();

		return $rv;
	}

	public function testBasicAuth() {
		$this->assertEquals(true,
			authenticate_user("admin", "password"));
	}

	public function testVersion() {

		$ret = $this->apiCall([], "getVersion");

		$this->assertStringStartsWith(
			VERSION_STATIC,
			$ret['content']['version']);
	}

	public function testLogin() {

		$ret = $this->apiCall(["op" => "login",
			"user" => "admin",
			"password" => "password"], "login");

		$this->assertNotEmpty($ret['content']['session_id']);
	}

	public function testGetUnread() {
		$this->testLogin();
		$ret = $this->apiCall([],"getUnread");

		$this->assertNotEmpty($ret['content']['unread']);
	}

	public function testGetFeeds() {
		$this->testLogin();
		$ret = $this->apiCall([], "getFeeds");

		$this->assertEquals("http://tt-rss.org/forum/rss.php",
			$ret['content'][0]['feed_url']);

	}
}

<?php
class BasicTest extends PHPUnit_Extensions_Selenium2TestCase {

	public function setUp() {
		$this->setHost('localhost');
		$this->setPort(4444);
		$this->setBrowserUrl('http://localhost/tt-rss/');
		$this->setBrowser('firefox');
	}

	public function setUpPage() {
		$this->timeouts()->implicitWait(5000);
	}

	public function testLogin() {
		$this->url('/index.php');

		$this->byName("login")->value('admin');
		$this->byName("password")->value('password');
		$this->byCssSelector('#dijit_form_Button_0_label')->click();

		$this->byCssSelector('#feedTree')->displayed();
	}

	public function testBasicDialogs() {
		$this->testLogin();

		$this->execute(["script" => "quickAddFilter()", "args" => []]);
		$this->byCssSelector("#filterEditDlg")->displayed();

		$this->execute(["script" => "dijit.byId('filterEditDlg').hide();", "args" => []]);

		$this->execute(["script" => "quickAddFeed()", "args" => []]);
		$this->byCssSelector("#feedAddDlg")->displayed();

		$this->execute(["script" => "dijit.byId('feedAddDlg').hide();", "args" => []]);
	}

	public function testOpenFeed() {
		$this->testLogin();

		$this->byCssSelector('#dijit__TreeNode_3')->click();

		$this->byCssSelector('#RROW-1 > .header')->displayed();
	}
}

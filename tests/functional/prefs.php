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
		$this->url('/prefs.php');

		$this->byName("login")->value('admin');
		$this->byName("password")->value('password');
		$this->byCssSelector('#dijit_form_Button_0_label')->click();

		$this->byCssSelector('#ttrssPrefs')->displayed();
	}

	public function testTabs() {

		$this->byCssSelector("#dijit_layout_AccordionPane_1_wrapper")->displayed();

		/* feeds */
		$this->execute(["script" => "selectTab('feedConfig');", "args" => []]);
		$this->byCssSelector("div.dijitTreeContainer > div.dijitTreeNode")->displayed();

		/* filters */
		$this->execute(["script" => "selectTab('filterConfig');", "args" => []]);
		$this->byCssSelector("div.dijitTreeContainer > div.dijitTreeNode")->displayed();

		/* filters */
		$this->execute(["script" => "selectTab('labelConfig');", "args" => []]);
		$this->byCssSelector("div.dijitTreeContainer > div.dijitTreeNode")->displayed();

	}
}

<?php
class Handler implements IHandler {
	protected $dbh;
	protected $pdo;
	protected $args;

	function __construct($args) {
		$this->dbh = Db::get();
		$this->pdo = Db::pdo();
		$this->args = $args;
	}

	function csrf_ignore($method) {
		return true;
	}

	function before($method) {
		return true;
	}

	function after() {
		return true;
	}

}
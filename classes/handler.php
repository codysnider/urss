<?php
class Handler implements IHandler {
	protected $pdo;
	protected $args;

	public function __construct($args) {
		$this->pdo = Db::pdo();
		$this->args = $args;
	}

	public function csrf_ignore($method) {
		return true;
	}

	public function before($method) {
		return true;
	}

	public function after() {
		return true;
	}

}

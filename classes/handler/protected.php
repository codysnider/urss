<?php
class Handler_Protected extends Handler {

	public function before($method) {
		return parent::before($method) && $_SESSION['uid'];
	}
}

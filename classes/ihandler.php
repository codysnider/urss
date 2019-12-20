<?php
interface IHandler {
	public function csrf_ignore($method);
	public function before($method);
	public function after();
}

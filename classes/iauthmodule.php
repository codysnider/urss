<?php
interface IAuthModule {
	public function authenticate($login, $password); // + optional third parameter: $service
}

<?php
interface IAuthModule {
	function authenticate($login, $password); // + optional third parameter: $service
}

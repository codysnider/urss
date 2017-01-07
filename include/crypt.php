<?php
	function decrypt_string($str) {
		$pair = explode(":", $str);

		if (count($pair) == 2) {
			@$iv = base64_decode($pair[0]);
			@$encstr = base64_decode($pair[1]);

			if ($iv && $encstr) {
				$key = hash('SHA256', FEED_CRYPT_KEY, true);

				$str = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $encstr,
					MCRYPT_MODE_CBC, $iv);

				if ($str) return rtrim($str);
			}
		}

		return false;
	}
?>

<?php
class Auth_Internal extends Plugin implements IAuthModule {

	private $host;

	function about() {
		return array(1.0,
			"Authenticates against internal tt-rss database",
			"fox",
			true);
	}

	/* @var PluginHost $host */
	function init($host) {
		$this->host = $host;
		$this->pdo = Db::pdo();

		$host->add_hook($host::HOOK_AUTH_USER, $this);
	}

	function authenticate($login, $password, $service = '') {

		$pwd_hash1 = encrypt_password($password);
		$pwd_hash2 = encrypt_password($password, $login);
		$otp = $_REQUEST["otp"];

		if (get_schema_version() > 96) {

			$sth = $this->pdo->prepare("SELECT otp_enabled,salt FROM ttrss_users WHERE
				login = ?");
			$sth->execute([$login]);

			if ($row = $sth->fetch()) {
				$otp_enabled = $row['otp_enabled'];

				if ($otp_enabled) {

					// only allow app password checking if OTP is enabled
					if ($service && get_schema_version() > 138) {
						return $this->check_app_password($login, $password, $service);
					}

					if ($otp) {
						$base32 = new \OTPHP\Base32();

						$secret = $base32->encode(mb_substr(sha1($row["salt"]), 0, 12), false);
						$secret_legacy = $base32->encode(sha1($row["salt"]));

						$totp = new \OTPHP\TOTP($secret);
						$otp_check = $totp->now();

						$totp_legacy = new \OTPHP\TOTP($secret_legacy);
						$otp_check_legacy = $totp_legacy->now();

						if ($otp != $otp_check && $otp != $otp_check_legacy) {
							return false;
						}
					} else {
						$return = urlencode($_REQUEST["return"]);
						?>
						<!DOCTYPE html>
						<html>
							<head>
								<title>Tiny Tiny RSS</title>
								<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
							</head>
							<?php echo stylesheet_tag("css/default.css") ?>
						<body class="ttrss_utility otp">
						<h1><?php echo __("Authentication") ?></h1>
						<div class="content">
						<form action="public.php?return=<?php echo $return ?>"
								method="POST" class="otpform">
							<input type="hidden" name="op" value="login">
							<input type="hidden" name="login" value="<?php echo htmlspecialchars($login) ?>">
							<input type="hidden" name="password" value="<?php echo htmlspecialchars($password) ?>">
							<input type="hidden" name="bw_limit" value="<?php echo htmlspecialchars($_POST["bw_limit"]) ?>">
							<input type="hidden" name="remember_me" value="<?php echo htmlspecialchars($_POST["remember_me"]) ?>">
							<input type="hidden" name="profile" value="<?php echo htmlspecialchars($_POST["profile"]) ?>">

							<fieldset>
								<label><?php echo __("Please enter your one time password:") ?></label>
								<input autocomplete="off" size="6" name="otp" value=""/>
								<input type="submit" value="Continue"/>
							</fieldset>
						</form></div>
						<script type="text/javascript">
							document.forms[0].otp.focus();
						</script>
						<?php
						exit;
					}
				}
			}
		}

		// check app passwords first but allow regular password as a fallback for the time being
		// if OTP is not enabled

		if ($service && get_schema_version() > 138) {
			$user_id = $this->check_app_password($login, $password, $service);

			if ($user_id)
				return $user_id;
		}

		if (get_schema_version() > 87) {

			$sth = $this->pdo->prepare("SELECT salt FROM ttrss_users WHERE login = ?");
			$sth->execute([$login]);

			if ($row = $sth->fetch()) {
				$salt = $row['salt'];

				if ($salt == "") {

					$sth = $this->pdo->prepare("SELECT id FROM ttrss_users WHERE
						login = ? AND (pwd_hash = ? OR pwd_hash = ?)");

					$sth->execute([$login, $pwd_hash1, $pwd_hash2]);

					// verify and upgrade password to new salt base

					if ($row = $sth->fetch()) {
						// upgrade password to MODE2

						$user_id = $row['id'];

						$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
						$pwd_hash = encrypt_password($password, $salt, true);

						$sth = $this->pdo->prepare("UPDATE ttrss_users SET
							pwd_hash = ?, salt = ? WHERE login = ?");

						$sth->execute([$pwd_hash, $salt, $login]);

						return $user_id;

					} else {
						return false;
					}

				} else {
					$pwd_hash = encrypt_password($password, $salt, true);

					$sth = $this->pdo->prepare("SELECT id
						  FROM ttrss_users WHERE
						  login = ? AND pwd_hash = ?");
					$sth->execute([$login, $pwd_hash]);

					if ($row = $sth->fetch()) {
						return $row['id'];
					}
				}

			} else {
				$sth = $this->pdo->prepare("SELECT id
					FROM ttrss_users WHERE
					  login = ? AND (pwd_hash = ? OR pwd_hash = ?)");

				$sth->execute([$login, $pwd_hash1, $pwd_hash2]);

				if ($row = $sth->fetch()) {
					return $row['id'];
				}
			}
		} else {
			$sth = $this->pdo->prepare("SELECT id
					FROM ttrss_users WHERE
					  login = ? AND (pwd_hash = ? OR pwd_hash = ?)");

			$sth->execute([$login, $pwd_hash1, $pwd_hash2]);

			if ($row = $sth->fetch()) {
				return $row['id'];
			}
		}

		return false;
	}

	function check_password($owner_uid, $password) {

		$sth = $this->pdo->prepare("SELECT salt,login,otp_enabled FROM ttrss_users WHERE
			id = ?");
		$sth->execute([$owner_uid]);

		if ($row = $sth->fetch()) {

			$salt = $row['salt'];
			$login = $row['login'];

			if (!$salt) {
				$password_hash1 = encrypt_password($password);
				$password_hash2 = encrypt_password($password, $login);

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_users WHERE
					id = ? AND (pwd_hash = ? OR pwd_hash = ?)");

				$sth->execute([$owner_uid, $password_hash1, $password_hash2]);

				return $sth->fetch();

			} else {
				$password_hash = encrypt_password($password, $salt, true);

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_users WHERE
					id = ? AND pwd_hash = ?");

				$sth->execute([$owner_uid, $password_hash]);

				return $sth->fetch();
			}
		}

		return false;
	}

	function change_password($owner_uid, $old_password, $new_password) {

		if ($this->check_password($owner_uid, $old_password)) {

			$new_salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
			$new_password_hash = encrypt_password($new_password, $new_salt, true);

			$sth = $this->pdo->prepare("UPDATE ttrss_users SET
				pwd_hash = ?, salt = ?, otp_enabled = false
					WHERE id = ?");
			$sth->execute([$new_password_hash, $new_salt, $owner_uid]);

			$_SESSION["pwd_hash"] = $new_password_hash;

			$sth = $this->pdo->prepare("SELECT email, login FROM ttrss_users WHERE id = ?");
			$sth->execute([$owner_uid]);

			if ($row = $sth->fetch()) {
				$mailer = new Mailer();

				require_once "lib/MiniTemplator.class.php";

				$tpl = new MiniTemplator;

				$tpl->readTemplateFromFile("templates/password_change_template.txt");

				$tpl->setVariable('LOGIN', $row["login"]);
				$tpl->setVariable('TTRSS_HOST', SELF_URL_PATH);

				$tpl->addBlock('message');

				$tpl->generateOutputToString($message);

				$mailer->mail(["to_name" => $row["login"],
					"to_address" => $row["email"],
					"subject" => "[tt-rss] Password change notification",
					"message" => $message]);

			}

			return __("Password has been changed.");
		} else {
			return "ERROR: ".__('Old password is incorrect.');
		}
	}

	private function check_app_password($login, $password, $service) {
		$sth = $this->pdo->prepare("SELECT p.id, p.pwd_hash, u.id AS uid 
			FROM ttrss_app_passwords p, ttrss_users u 
			WHERE p.owner_uid = u.id AND u.login = ? AND service = ?");
		$sth->execute([$login, $service]);

		while ($row = $sth->fetch()) {
			list ($algo, $hash, $salt) = explode(":", $row["pwd_hash"]);

			if ($algo == "SSHA-512") {
				$test_hash = hash('sha512', $salt . $password);

				if ($test_hash == $hash) {
					$usth = $this->pdo->prepare("UPDATE ttrss_app_passwords SET last_used = NOW() WHERE id = ?");
					$usth->execute([$row['id']]);

					return $row['uid'];
				}
			} else {
				user_error("Got unknown algo of app password for user $login: $algo");
			}
		}

		return false;
	}

	function api_version() {
		return 2;
	}

}

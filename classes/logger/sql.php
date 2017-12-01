<?php
class Logger_SQL {

	function log_error($errno, $errstr, $file, $line, $context) {
		
		$pdo = Db::pdo();
		
		if ($pdo && get_schema_version() > 117) {

			try {
				$pdo->rollBack();
			} catch (Exception $e) {
				//
			}

			$owner_uid = $_SESSION["uid"] ? $_SESSION["uid"] : null;

			$sth = $pdo->prepare("INSERT INTO ttrss_error_log
				(errno, errstr, filename, lineno, context, owner_uid, created_at) VALUES
				(?, ?, ?, ?, ?, ?, NOW())");
			$sth->execute([$errno, $errstr, $file, $line, $context, $owner_uid]);

			return $sth->rowCount();
		}

		return false;
	}

}
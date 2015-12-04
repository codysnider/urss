<?php
class Logger_SQL {

	function log_error($errno, $errstr, $file, $line, $context) {
		if (Db::get() && get_schema_version() > 117) {

			$errno = Db::get()->escape_string($errno);
			$errstr = Db::get()->escape_string($errstr);
			$file = Db::get()->escape_string($file);
			$line = Db::get()->escape_string($line);
			$context = DB::get()->escape_string($context);

			$owner_uid = $_SESSION["uid"] ? $_SESSION["uid"] : "NULL";

			$result = Db::get()->query(
				"INSERT INTO ttrss_error_log
				(errno, errstr, filename, lineno, context, owner_uid, created_at) VALUES
				($errno, '$errstr', '$file', '$line', '$context', $owner_uid, NOW())");

			return Db::get()->affected_rows($result) != 0;
		}

		return false;
	}

}
?>

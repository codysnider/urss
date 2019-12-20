<?php
class Db_Pgsql implements IDb {
	private $link;
	private $last_error;

	public function connect($host, $user, $pass, $db, $port) {
		$string = "dbname=$db user=$user";

		if ($pass) {
			$string .= " password=$pass";
		}

		if ($host) {
			$string .= " host=$host";
		}

		if (is_numeric($port) && $port > 0) {
			$string = "$string port=" . $port;
		}

		$this->link = pg_connect($string);

		if (!$this->link) {
			print("Unable to connect to database (as $user to $host, database $db):" . pg_last_error());
			exit(102);
		}

		$this->init();

		return $this->link;
	}

	public function escape_string($s, $strip_tags = true) {
		if ($strip_tags) $s = strip_tags($s);

		return pg_escape_string($s);
	}

	public function query($query, $die_on_error = true) {
		$result = @pg_query($this->link, $query);

		if (!$result) {
			$this->last_error = @pg_last_error($this->link);

			@pg_query($this->link, "ROLLBACK");
			$query = htmlspecialchars($query); // just in case
			user_error("Query $query failed: " . ($this->link ? $this->last_error : "No connection"),
				$die_on_error ? E_USER_ERROR : E_USER_WARNING);
		}
		return $result;
	}

	public function fetch_assoc($result) {
		return pg_fetch_assoc($result);
	}


	public function num_rows($result) {
		return pg_num_rows($result);
	}

	public function fetch_result($result, $row, $param) {
		return pg_fetch_result($result, $row, $param);
	}

	public function close() {
		return pg_close($this->link);
	}

	public function affected_rows($result) {
		return pg_affected_rows($result);
	}

	public function last_error() {
		return pg_last_error($this->link);
	}

	public function last_query_error() {
		return $this->last_error;
	}

	public function init() {
		$this->query("set client_encoding = 'UTF-8'");
		pg_set_client_encoding("UNICODE");
		$this->query("set datestyle = 'ISO, european'");
		$this->query("set TIME ZONE 0");
		$this->query("set cpu_tuple_cost = 0.5");

		return true;
	}
}

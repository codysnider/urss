<?php
class Db implements IDb {
	private static $instance;
	private $adapter;
	private $link;
	private $pdo;

	private function __construct() {

		$er = error_reporting(E_ALL);

		switch (DB_TYPE) {
			case "mysql":
				$this->adapter = new Db_Mysqli();
				break;
			case "pgsql":
				$this->adapter = new Db_Pgsql();
				break;
			default:
				die("Unknown DB_TYPE: " . DB_TYPE);
		}

		if (!$this->adapter) {
			print("Error initializing database adapter for " . DB_TYPE);
			exit(100);
		}

		$db_port = defined('DB_PORT') && DB_PORT ? ';port='.DB_PORT : '';

		$this->pdo = new PDO(DB_TYPE . ':dbname='.DB_NAME.';host='.DB_HOST.$db_port,
			DB_USER,
			DB_PASS);

		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );

		if (!$this->pdo) {
			print("Error connecting via PDO.");
			exit(101);
		}

		if (DB_TYPE == "pgsql") {

			$this->pdo->query("set client_encoding = 'UTF-8'");
			$this->pdo->query("set datestyle = 'ISO, european'");
			$this->pdo->query("set TIME ZONE 0");
			$this->pdo->query("set cpu_tuple_cost = 0.5");

		} else if (DB_TYPE == "mysql") {
			$this->pdo->query("SET time_zone = '+0:0'");

			if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
				$this->pdo->query("SET NAMES " . MYSQL_CHARSET);
			}
		}

		$this->link = $this->adapter->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, defined('DB_PORT') ? DB_PORT : "");

		if (!$this->link) {
			print("Error connecting through adapter: " . $this->adapter->last_error());
			exit(101);
		}

		error_reporting($er);
	}

	private function __clone() {
		//
	}

	public static function get() {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

    public static function pdo() {
        if (self::$instance == null)
            self::$instance = new self();

        return self::$instance->pdo;
    }

	static function quote($str){
		return("'$str'");
	}

	function reconnect() {
		$this->link = $this->adapter->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, defined('DB_PORT') ? DB_PORT : "");
	}

	function connect($host, $user, $pass, $db, $port) {
		//return $this->adapter->connect($host, $user, $pass, $db, $port);
		return ;
	}

	function escape_string($s, $strip_tags = true) {
		return $this->adapter->escape_string($s, $strip_tags);
	}

	function query($query, $die_on_error = true) {
		return $this->adapter->query($query, $die_on_error);
	}

	function fetch_assoc($result) {
		return $this->adapter->fetch_assoc($result);
	}

	function num_rows($result) {
		return $this->adapter->num_rows($result);
	}

	function fetch_result($result, $row, $param) {
		return $this->adapter->fetch_result($result, $row, $param);
	}

	function close() {
		return $this->adapter->close();
	}

	function affected_rows($result) {
		return $this->adapter->affected_rows($result);
	}

	function last_error() {
		return $this->adapter->last_error();
	}

	function last_query_error() {
		return $this->adapter->last_query_error();
	}
}
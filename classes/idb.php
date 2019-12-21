<?php
interface IDb {
    public function connect($host, $user, $pass, $db, $port);
    public function escape_string($s, $strip_tags = true);
    public function query($query, $die_on_error = true);
    public function fetch_assoc($result);
    public function num_rows($result);
    public function fetch_result($result, $row, $param);
    public function close();
    public function affected_rows($result);
    public function last_error();
    public function last_query_error();
}

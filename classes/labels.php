<?php
class Labels
{
	static function label_to_feed_id($label) {
		return LABEL_BASE_INDEX - 1 - abs($label);
	}

	static function feed_to_label_id($feed) {
		return LABEL_BASE_INDEX - 1 + abs($feed);
	}

	static function find_id($label, $owner_uid) {
		$result = db_query(
			"SELECT id FROM ttrss_labels2 WHERE caption = '$label'
				AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "id");
		} else {
			return 0;
		}
	}

	static function find_caption($label, $owner_uid) {
		$result = db_query(
			"SELECT caption FROM ttrss_labels2 WHERE id = '$label'
				AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) == 1) {
			return db_fetch_result($result, 0, "caption");
		} else {
			return "";
		}
	}

	static function get_all_labels($owner_uid)	{
		$rv = array();

		$result = db_query("SELECT fg_color, bg_color, caption FROM ttrss_labels2 WHERE owner_uid = " . $owner_uid);

		while ($line = db_fetch_assoc($result)) {
			array_push($rv, $line);
		}

		return $rv;
	}

	static function update_cache($owner_uid, $id, $labels = false, $force = false) {

		if ($force)
			Labels::clear_cache($id);

		if (!$labels)
			$labels = Article::get_article_labels($id);

		$labels = db_escape_string(json_encode($labels));

		db_query("UPDATE ttrss_user_entries SET
			label_cache = '$labels' WHERE ref_id = '$id' AND  owner_uid = '$owner_uid'");

	}

	static function clear_cache($id)	{

		db_query("UPDATE ttrss_user_entries SET
			label_cache = '' WHERE ref_id = '$id'");

	}

	static function remove_article($id, $label, $owner_uid) {

		$label_id = Labels::find_id($label, $owner_uid);

		if (!$label_id) return;

		db_query(
			"DELETE FROM ttrss_user_labels2
			WHERE
				label_id = '$label_id' AND
				article_id = '$id'");

		Labels::clear_cache($id);
	}

	static function add_article($id, $label, $owner_uid)	{

		$label_id = Labels::find_id($label, $owner_uid);

		if (!$label_id) return;

		$result = db_query(
			"SELECT
				article_id FROM ttrss_labels2, ttrss_user_labels2
			WHERE
				label_id = id AND
				label_id = '$label_id' AND
				article_id = '$id' AND owner_uid = '$owner_uid'
			LIMIT 1");

		if (db_num_rows($result) == 0) {
			db_query("INSERT INTO ttrss_user_labels2
				(label_id, article_id) VALUES ('$label_id', '$id')");
		}

		Labels::clear_cache($id);

	}

	static function remove($id, $owner_uid) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		db_query("BEGIN");

		$result = db_query("SELECT caption FROM ttrss_labels2
			WHERE id = '$id'");

		$caption = db_fetch_result($result, 0, "caption");

		$result = db_query("DELETE FROM ttrss_labels2 WHERE id = '$id'
			AND owner_uid = " . $owner_uid);

		if (db_affected_rows($result) != 0 && $caption) {

			/* Remove access key for the label */

			$ext_id = LABEL_BASE_INDEX - 1 - $id;

			db_query("DELETE FROM ttrss_access_keys WHERE
				feed_id = '$ext_id' AND owner_uid = $owner_uid");

			/* Remove cached data */

			db_query("UPDATE ttrss_user_entries SET label_cache = ''
				WHERE label_cache LIKE '%$caption%' AND owner_uid = " . $owner_uid);

		}

		db_query("COMMIT");
	}

	static function create($caption, $fg_color = '', $bg_color = '', $owner_uid = false)	{

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];

		db_query("BEGIN");

		$result = db_query("SELECT id FROM ttrss_labels2
			WHERE caption = '$caption' AND owner_uid = $owner_uid");

		if (db_num_rows($result) == 0) {
			$result = db_query(
				"INSERT INTO ttrss_labels2 (caption,owner_uid,fg_color,bg_color)
					VALUES ('$caption', '$owner_uid', '$fg_color', '$bg_color')");

			$result = db_affected_rows($result) != 0;
		}

		db_query("COMMIT");

		return $result;
	}
}
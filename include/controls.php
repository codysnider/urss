<?php

function print_select($id, $default, $values, $attributes = "", $name = "") {
	if (!$name) $name = $id;

	print "<select name=\"$name\" id=\"$id\" $attributes>";
	foreach ($values as $v) {
		if ($v == $default)
			$sel = "selected=\"1\"";
		else
			$sel = "";

		$v = trim($v);

		print "<option value=\"$v\" $sel>$v</option>";
	}
	print "</select>";
}

function print_select_hash($id, $default, $values, $attributes = "", $name = "") {
	if (!$name) $name = $id;

	print "<select name=\"$name\" id='$id' $attributes>";
	foreach (array_keys($values) as $v) {
		if ($v == $default)
			$sel = 'selected="selected"';
		else
			$sel = "";

		$v = trim($v);

		print "<option $sel value=\"$v\">".$values[$v]."</option>";
	}

	print "</select>";
}

function print_hidden($name, $value) {
	print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"$name\" value=\"$value\">";
}

function print_checkbox($id, $checked, $value = "", $attributes = "") {
	$checked_str = $checked ? "checked" : "";
	$value_str = $value ? "value=\"$value\"" : "";

	print "<input dojoType=\"dijit.form.CheckBox\" id=\"$id\" $value_str $checked_str $attributes name=\"$id\">";
}

function print_button($type, $value, $attributes = "") {
	print "<p><button dojoType=\"dijit.form.Button\" $attributes type=\"$type\">$value</button>";
}

function print_radio($id, $default, $true_is, $values, $attributes = "") {
	foreach ($values as $v) {

		if ($v == $default)
			$sel = "checked";
		else
			$sel = "";

		if ($v == $true_is) {
			$sel .= " value=\"1\"";
		} else {
			$sel .= " value=\"0\"";
		}

		print "<input class=\"noborder\" dojoType=\"dijit.form.RadioButton\"
				type=\"radio\" $sel $attributes name=\"$id\">&nbsp;$v&nbsp;";

	}
}

function print_feed_multi_select($id, $default_ids = [],
                           $attributes = "", $include_all_feeds = true,
                           $root_id = false, $nest_level = 0) {

    print_r(in_array("CAT:6",$default_ids));

    if (!$root_id) {
        print "<select multiple=\true\" id=\"$id\" name=\"$id\" $attributes>";
        if ($include_all_feeds) {
            $is_selected = (in_array("0", $default_ids)) ? "selected=\"1\"" : "";
            print "<option $is_selected value=\"0\">".__('All feeds')."</option>";
        }
    }

    if (get_pref('ENABLE_FEED_CATS')) {

        if ($root_id)
            $parent_qpart = "parent_cat = '$root_id'";
        else
            $parent_qpart = "parent_cat IS NULL";

        $result = db_query("SELECT id,title,
				(SELECT COUNT(id) FROM ttrss_feed_categories AS c2 WHERE
					c2.parent_cat = ttrss_feed_categories.id) AS num_children
				FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]." AND $parent_qpart ORDER BY title");

        while ($line = db_fetch_assoc($result)) {

            for ($i = 0; $i < $nest_level; $i++)
                $line["title"] = " - " . $line["title"];

            $is_selected = in_array("CAT:".$line["id"], $default_ids) ? "selected=\"1\"" : "";

            printf("<option $is_selected value='CAT:%d'>%s</option>",
                $line["id"], htmlspecialchars($line["title"]));

            if ($line["num_children"] > 0)
                print_feed_multi_select($id, $default_ids, $attributes,
                    $include_all_feeds, $line["id"], $nest_level+1);

            $feed_result = db_query("SELECT id,title FROM ttrss_feeds
					WHERE cat_id = '".$line["id"]."' AND owner_uid = ".$_SESSION["uid"] . " ORDER BY title");

            while ($fline = db_fetch_assoc($feed_result)) {
                $is_selected = (in_array($fline["id"], $default_ids)) ? "selected=\"1\"" : "";

                $fline["title"] = " + " . $fline["title"];

                for ($i = 0; $i < $nest_level; $i++)
                    $fline["title"] = " - " . $fline["title"];

                printf("<option $is_selected value='%d'>%s</option>",
                    $fline["id"], htmlspecialchars($fline["title"]));
            }
        }

        if (!$root_id) {
            $is_selected = in_array("CAT:0", $default_ids) ? "selected=\"1\"" : "";

            printf("<option $is_selected value='CAT:0'>%s</option>",
                __("Uncategorized"));

            $feed_result = db_query("SELECT id,title FROM ttrss_feeds
					WHERE cat_id IS NULL AND owner_uid = ".$_SESSION["uid"] . " ORDER BY title");

            while ($fline = db_fetch_assoc($feed_result)) {
                $is_selected = in_array($fline["id"], $default_ids) ? "selected=\"1\"" : "";

                $fline["title"] = " + " . $fline["title"];

                for ($i = 0; $i < $nest_level; $i++)
                    $fline["title"] = " - " . $fline["title"];

                printf("<option $is_selected value='%d'>%s</option>",
                    $fline["id"], htmlspecialchars($fline["title"]));
            }
        }

    } else {
        $result = db_query("SELECT id,title FROM ttrss_feeds
				WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

        while ($line = db_fetch_assoc($result)) {

            $is_selected = (in_array($line["id"], $default_ids)) ? "selected=\"1\"" : "";

            printf("<option $is_selected value='%d'>%s</option>",
                $line["id"], htmlspecialchars($line["title"]));
        }
    }

    if (!$root_id) {
        print "</select>";
    }
}


/*function print_feed_select($id, $default_id = "",
						   $attributes = "", $include_all_feeds = true,
						   $root_id = false, $nest_level = 0) {

	if (!$root_id) {
		print "<select id=\"$id\" name=\"$id\" $attributes>";
		if ($include_all_feeds) {
			$is_selected = ("0" == $default_id) ? "selected=\"1\"" : "";
			print "<option $is_selected value=\"0\">".__('All feeds')."</option>";
		}
	}

	if (get_pref('ENABLE_FEED_CATS')) {

		if ($root_id)
			$parent_qpart = "parent_cat = '$root_id'";
		else
			$parent_qpart = "parent_cat IS NULL";

		$result = db_query("SELECT id,title,
				(SELECT COUNT(id) FROM ttrss_feed_categories AS c2 WHERE
					c2.parent_cat = ttrss_feed_categories.id) AS num_children
				FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]." AND $parent_qpart ORDER BY title");

		while ($line = db_fetch_assoc($result)) {

			for ($i = 0; $i < $nest_level; $i++)
				$line["title"] = " - " . $line["title"];

			$is_selected = ("CAT:".$line["id"] == $default_id) ? "selected=\"1\"" : "";

			printf("<option $is_selected value='CAT:%d'>%s</option>",
				$line["id"], htmlspecialchars($line["title"]));

			if ($line["num_children"] > 0)
				print_feed_select($id, $default_id, $attributes,
					$include_all_feeds, $line["id"], $nest_level+1);

			$feed_result = db_query("SELECT id,title FROM ttrss_feeds
					WHERE cat_id = '".$line["id"]."' AND owner_uid = ".$_SESSION["uid"] . " ORDER BY title");

			while ($fline = db_fetch_assoc($feed_result)) {
				$is_selected = ($fline["id"] == $default_id) ? "selected=\"1\"" : "";

				$fline["title"] = " + " . $fline["title"];

				for ($i = 0; $i < $nest_level; $i++)
					$fline["title"] = " - " . $fline["title"];

				printf("<option $is_selected value='%d'>%s</option>",
					$fline["id"], htmlspecialchars($fline["title"]));
			}
		}

		if (!$root_id) {
			$default_is_cat = ($default_id == "CAT:0");
			$is_selected = $default_is_cat ? "selected=\"1\"" : "";

			printf("<option $is_selected value='CAT:0'>%s</option>",
				__("Uncategorized"));

			$feed_result = db_query("SELECT id,title FROM ttrss_feeds
					WHERE cat_id IS NULL AND owner_uid = ".$_SESSION["uid"] . " ORDER BY title");

			while ($fline = db_fetch_assoc($feed_result)) {
				$is_selected = ($fline["id"] == $default_id && !$default_is_cat) ? "selected=\"1\"" : "";

				$fline["title"] = " + " . $fline["title"];

				for ($i = 0; $i < $nest_level; $i++)
					$fline["title"] = " - " . $fline["title"];

				printf("<option $is_selected value='%d'>%s</option>",
					$fline["id"], htmlspecialchars($fline["title"]));
			}
		}

	} else {
		$result = db_query("SELECT id,title FROM ttrss_feeds
				WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

		while ($line = db_fetch_assoc($result)) {

			$is_selected = ($line["id"] == $default_id) ? "selected=\"1\"" : "";

			printf("<option $is_selected value='%d'>%s</option>",
				$line["id"], htmlspecialchars($line["title"]));
		}
	}

	if (!$root_id) {
		print "</select>";
	}
}*/

function print_feed_cat_select($id, $default_id,
							   $attributes, $include_all_cats = true, $root_id = false, $nest_level = 0) {

	if (!$root_id) {
		print "<select id=\"$id\" name=\"$id\" default=\"$default_id\" $attributes>";
	}

	if ($root_id)
		$parent_qpart = "parent_cat = '$root_id'";
	else
		$parent_qpart = "parent_cat IS NULL";

	$result = db_query("SELECT id,title,
				(SELECT COUNT(id) FROM ttrss_feed_categories AS c2 WHERE
					c2.parent_cat = ttrss_feed_categories.id) AS num_children
				FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]." AND $parent_qpart ORDER BY title");

	while ($line = db_fetch_assoc($result)) {
		if ($line["id"] == $default_id) {
			$is_selected = "selected=\"1\"";
		} else {
			$is_selected = "";
		}

		for ($i = 0; $i < $nest_level; $i++)
			$line["title"] = " - " . $line["title"];

		if ($line["title"])
			printf("<option $is_selected value='%d'>%s</option>",
				$line["id"], htmlspecialchars($line["title"]));

		if ($line["num_children"] > 0)
			print_feed_cat_select($id, $default_id, $attributes,
				$include_all_cats, $line["id"], $nest_level+1);
	}

	if (!$root_id) {
		if ($include_all_cats) {
			if (db_num_rows($result) > 0) {
				print "<option disabled=\"1\">--------</option>";
			}

			if ($default_id == 0) {
				$is_selected = "selected=\"1\"";
			} else {
				$is_selected = "";
			}

			print "<option $is_selected value=\"0\">".__('Uncategorized')."</option>";
		}
		print "</select>";
	}
}

function stylesheet_tag($filename) {
	$timestamp = filemtime($filename);

	return "<link rel=\"stylesheet\" type=\"text/css\" href=\"$filename?$timestamp\"/>\n";
}

function javascript_tag($filename) {
	$query = "";

	if (!(strpos($filename, "?") === FALSE)) {
		$query = substr($filename, strpos($filename, "?")+1);
		$filename = substr($filename, 0, strpos($filename, "?"));
	}

	$timestamp = filemtime($filename);

	if ($query) $timestamp .= "&$query";

	return "<script type=\"text/javascript\" charset=\"utf-8\" src=\"$filename?$timestamp\"></script>\n";
}

function format_warning($msg, $id = "") {
	return "<div class=\"alert\" id=\"$id\">$msg</div>";
}

function format_notice($msg, $id = "") {
	return "<div class=\"alert alert-info\" id=\"$id\">$msg</div>";
}

function format_error($msg, $id = "") {
	return "<div class=\"alert alert-danger\" id=\"$id\">$msg</div>";
}

function print_notice($msg) {
	return print format_notice($msg);
}

function print_warning($msg) {
	return print format_warning($msg);
}

function print_error($msg) {
	return print format_error($msg);
}

function format_inline_player($url, $ctype) {

	$entry = "";

	$url = htmlspecialchars($url);

	if (strpos($ctype, "audio/") === 0) {

		if ($_SESSION["hasAudio"] && (strpos($ctype, "ogg") !== false ||
				$_SESSION["hasMp3"])) {

			$entry .= "<audio preload=\"none\" controls>
					<source type=\"$ctype\" src=\"$url\"/>
					</audio>";

		} else {

			$entry .= "<object type=\"application/x-shockwave-flash\"
					data=\"lib/button/musicplayer.swf?song_url=$url\"
					width=\"17\" height=\"17\" style='float : left; margin-right : 5px;'>
					<param name=\"movie\"
						value=\"lib/button/musicplayer.swf?song_url=$url\" />
					</object>";
		}

		if ($entry) $entry .= "&nbsp; <a target=\"_blank\" rel=\"noopener noreferrer\"
				href=\"$url\">" . basename($url) . "</a>";

		return $entry;

	}

	return "";
}

function print_label_select($name, $value, $attributes = "") {

	$result = db_query("SELECT caption FROM ttrss_labels2
			WHERE owner_uid = '".$_SESSION["uid"]."' ORDER BY caption");

	print "<select default=\"$value\" name=\"" . htmlspecialchars($name) .
		"\" $attributes>";

	while ($line = db_fetch_assoc($result)) {

		$issel = ($line["caption"] == $value) ? "selected=\"1\"" : "";

		print "<option value=\"".htmlspecialchars($line["caption"])."\"
				$issel>" . htmlspecialchars($line["caption"]) . "</option>";

	}

#		print "<option value=\"ADD_LABEL\">" .__("Add label...") . "</option>";

	print "</select>";


}


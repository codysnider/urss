<?php
require_once "colors.php";

class Feeds extends Handler_Protected {

    private $params;

    function csrf_ignore($method) {
		$csrf_ignored = array("index", "feedbrowser", "quickaddfeed", "search");

		return array_search($method, $csrf_ignored) !== false;
	}

	private function format_headline_subtoolbar($feed_site_url, $feed_title,
			$feed_id, $is_cat, $search,
			$error, $feed_last_updated) {

		$catchup_sel_link = "catchupSelection()";

		$archive_sel_link = "archiveSelection()";
		$delete_sel_link = "deleteSelection()";

		$sel_all_link = "selectArticles('all')";
		$sel_unread_link = "selectArticles('unread')";
		$sel_none_link = "selectArticles('none')";
		$sel_inv_link = "selectArticles('invert')";

		$tog_unread_link = "selectionToggleUnread()";
		$tog_marked_link = "selectionToggleMarked()";
		$tog_published_link = "selectionTogglePublished()";

		$set_score_link = "setSelectionScore()";

		if ($is_cat) $cat_q = "&is_cat=$is_cat";

		if ($search) {
			$search_q = "&q=$search";
		} else {
			$search_q = "";
		}

		$reply = "<span class=\"holder\">";

		$rss_link = htmlspecialchars(get_self_url_prefix() .
			"/public.php?op=rss&id=$feed_id$cat_q$search_q");

		// right part

		$error_class = $error ? "error" : "";

		$reply .= "<span class='r'>
			<a href=\"#\"
				title=\"".__("View as RSS feed")."\"
				onclick=\"displayDlg('".__("View as RSS")."','generatedFeed', '$feed_id:$is_cat:$rss_link')\">
				<img class=\"noborder\" src=\"images/pub_set.png\"></a>";


#		$reply .= "<span>";
		$reply .= "<span id='feed_title' class='$error_class'>";

		if ($feed_site_url) {
			$last_updated = T_sprintf("Last updated: %s",
				$feed_last_updated);

			$target = "target=\"_blank\"";
			$reply .= "<a title=\"$last_updated\" $target href=\"$feed_site_url\">".
				truncate_string(strip_tags($feed_title), 30)."</a>";

			if ($error) {
				$error = htmlspecialchars($error);
				$reply .= "&nbsp;<img title=\"$error\" src='images/error.png' alt='error' class=\"noborder\">";
			}

		} else {
			$reply .= strip_tags($feed_title);
		}

		$reply .= "</span>";

		$reply .= "</span>";

#		$reply .= "</span>";

		// left part

		$reply .= "<span class=\"main\">";
		$reply .= "<span id='selected_prompt'></span>";

		/*$reply .= "<span class=\"sel_links\">
			<a href=\"#\" onclick=\"$sel_all_link\">".__('All')."</a>,
			<a href=\"#\" onclick=\"$sel_unread_link\">".__('Unread')."</a>,
			<a href=\"#\" onclick=\"$sel_inv_link\">".__('Invert')."</a>,
			<a href=\"#\" onclick=\"$sel_none_link\">".__('None')."</a></li>";

		$reply .= "</span> "; */

		$reply .= "<select dojoType=\"dijit.form.Select\"
			onchange=\"headlineActionsChange(this)\">";

		$reply .= "<option value=\"0\" disabled='1'>".__('Select...')."</option>";

		$reply .= "<option value=\"$sel_all_link\">".__('All')."</option>";
		$reply .= "<option value=\"$sel_unread_link\">".__('Unread')."</option>";
		$reply .= "<option value=\"$sel_inv_link\">".__('Invert')."</option>";
		$reply .= "<option value=\"$sel_none_link\">".__('None')."</option>";

		$reply .= "<option value=\"0\" disabled=\"1\">".__('Selection toggle:')."</option>";

		$reply .= "<option value=\"$tog_unread_link\">".__('Unread')."</option>
			<option value=\"$tog_marked_link\">".__('Starred')."</option>
			<option value=\"$tog_published_link\">".__('Published')."</option>";

		$reply .= "<option value=\"0\" disabled=\"1\">".__('Selection:')."</option>";

		$reply .= "<option value=\"$catchup_sel_link\">".__('Mark as read')."</option>";
		$reply .= "<option value=\"$set_score_link\">".__('Set score')."</option>";

		if ($feed_id != "0") {
			$reply .= "<option value=\"$archive_sel_link\">".__('Archive')."</option>";
		} else {
			$reply .= "<option value=\"$archive_sel_link\">".__('Move back')."</option>";
			$reply .= "<option value=\"$delete_sel_link\">".__('Delete')."</option>";

		}

		if (PluginHost::getInstance()->get_plugin("mail")) {
			$reply .= "<option value=\"emailArticle(false)\">".__('Forward by email').
				"</option>";
		}

		if (PluginHost::getInstance()->get_plugin("mailto")) {
			$reply .= "<option value=\"mailtoArticle(false)\">".__('Forward by email').
				"</option>";
		}

		$reply .= "<option value=\"0\" disabled=\"1\">".__('Feed:')."</option>";

		//$reply .= "<option value=\"catchupPage()\">".__('Mark as read')."</option>";

		$reply .= "<option value=\"displayDlg('".__("View as RSS")."','generatedFeed', '$feed_id:$is_cat:$rss_link')\">".__('View as RSS')."</option>";

		$reply .= "</select>";

		//$reply .= "</h2";

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_HEADLINE_TOOLBAR_BUTTON) as $p) {
			 $reply .= $p->hook_headline_toolbar_button($feed_id, $is_cat);
		}

		$reply .= "</span></span>";

		return $reply;
	}

	private function format_headlines_list($feed, $method, $view_mode, $limit, $cat_view,
					$offset, $vgr_last_feed = false,
					$override_order = false, $include_children = false, $check_first_id = false,
					$skip_first_id_check = false) {

		$disable_cache = false;

		$reply = array();

		$rgba_cache = array();

		$timing_info = microtime(true);

		$topmost_article_ids = array();

		if (!$offset) $offset = 0;
		if ($method == "undefined") $method = "";

		$method_split = explode(":", $method);

		if ($method == "ForceUpdate" && $feed > 0 && is_numeric($feed)) {
            $sth = $this->pdo->prepare("UPDATE ttrss_feeds 
                            SET last_updated = '1970-01-01', last_update_started = '1970-01-01'
                            WHERE id = ?");
            $sth->execute([$feed]);
		}

		if ($method_split[0] == "MarkAllReadGR")  {
			$this->catchup_feed($method_split[1], false);
		}

		// FIXME: might break tag display?

		if (is_numeric($feed) && $feed > 0 && !$cat_view) {
			$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE id = ? LIMIT 1");
			$sth->execute([$feed]);

			if (!$sth->fetch()) {
				$reply['content'] = "<div align='center'>".__('Feed not found.')."</div>";
			}
		}

		@$search = $_REQUEST["query"];
		@$search_language = $_REQUEST["search_language"]; // PGSQL only

		if ($search) {
			$disable_cache = true;
		}

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("H0", $timing_info);

		if (!$cat_view && is_numeric($feed) && $feed < PLUGIN_FEED_BASE_INDEX && $feed > LABEL_BASE_INDEX) {
			$handler = PluginHost::getInstance()->get_feed_handler(
				PluginHost::feed_to_pfeed_id($feed));

			if ($handler) {
				$options = array(
					"limit" => $limit,
					"view_mode" => $view_mode,
					"cat_view" => $cat_view,
					"search" => $search,
					"override_order" => $override_order,
					"offset" => $offset,
					"owner_uid" => $_SESSION["uid"],
					"filter" => false,
					"since_id" => 0,
					"include_children" => $include_children);

				$qfh_ret = $handler->get_headlines(PluginHost::feed_to_pfeed_id($feed),
					$options);
			}

		} else {

			$params = array(
				"feed" => $feed,
				"limit" => $limit,
				"view_mode" => $view_mode,
				"cat_view" => $cat_view,
				"search" => $search,
				"search_language" => $search_language,
				"override_order" => $override_order,
				"offset" => $offset,
				"include_children" => $include_children,
				"check_first_id" => $check_first_id,
				"skip_first_id_check" => $skip_first_id_check
			);

			$qfh_ret = $this->queryFeedHeadlines($params);
		}

		$vfeed_group_enabled = get_pref("VFEED_GROUP_BY_FEED") && $feed != -6;

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("H1", $timing_info);

		$result = $qfh_ret[0]; // this could be either a PDO query result or a -1 if first id changed
		$feed_title = $qfh_ret[1];
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];
		$last_updated = strpos($qfh_ret[4], '1970-') === FALSE ?
			make_local_datetime($qfh_ret[4], false) : __("Never");
		$highlight_words = $qfh_ret[5];
		$reply['first_id'] = $qfh_ret[6];
		$reply['search_query'] = [$search, $search_language];

		$vgroup_last_feed = $vgr_last_feed;

		$reply['toolbar'] = $this->format_headline_subtoolbar($feed_site_url,
			$feed_title,
			$feed, $cat_view, $search,
			$last_error, $last_updated);

		if ($offset == 0) {
			foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_HEADLINES_BEFORE) as $p) {
				 $reply['content'] .= $p->hook_headlines_before($feed, $cat_view, $qfh_ret);
			}
		}

		$reply['content'] = '';

		$headlines_count = 0;

        $lnum = $offset;
        $num_unread = 0;
        if ($_REQUEST["debug"]) $timing_info = print_checkpoint("PS", $timing_info);
        $expand_cdm = get_pref('CDM_EXPANDED');

        if (is_object($result)) {

			while ($line = $result->fetch()) {

				++$headlines_count;

				$line["content_preview"] =  "&mdash; " . truncate_string(strip_tags($line["content"]), 250);

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_QUERY_HEADLINES) as $p) {
					$line = $p->hook_query_headlines($line, 250, false);
				}

				if (get_pref('SHOW_CONTENT_PREVIEW')) {
					$content_preview =  $line["content_preview"];
				}

				$id = $line["id"];
				$feed_id = $line["feed_id"];
				$label_cache = $line["label_cache"];
				$labels = false;

				$mouseover_attrs = "onmouseover='postMouseIn(event, $id)' onmouseout='postMouseOut($id)'";

				if ($label_cache) {
					$label_cache = json_decode($label_cache, true);

					if ($label_cache) {
						if ($label_cache["no-labels"] == 1)
							$labels = array();
						else
							$labels = $label_cache;
					}
				}

				if (!is_array($labels)) $labels = Article::get_article_labels($id);

				$labels_str = "<span class=\"HLLCTR-$id\">";
				$labels_str .= Article::format_article_labels($labels);
				$labels_str .= "</span>";

				if (count($topmost_article_ids) < 3) {
					array_push($topmost_article_ids, $id);
				}

				$class = "";

				if ($line["unread"]) {
					$class .= " Unread";
					++$num_unread;
				}

				$marked_pic_src = $line["marked"] ? "mark_set.png" : "mark_unset.png";
				$class .= $line["marked"] ? " marked" : "";
				$marked_pic = "<img src=\"images/$marked_pic_src\" class=\"markedPic\" onclick='toggleMark($id)'>";

				$published_pic_src = $line["published"] ? "pub_set.png" : "pub_unset.png";
				$class .= $line["published"] ? " published" : "";
                $published_pic = "<img src=\"images/$published_pic_src\" class=\"pubPic\" onclick='togglePub($id)'>";

				$updated_fmt = make_local_datetime($line["updated"], false, false, false, true);
				$date_entered_fmt = T_sprintf("Imported at %s",
					make_local_datetime($line["date_entered"], false));

				$score = $line["score"];

				$score_pic = "images/" . get_score_pic($score);

				$score_pic = "<img class='hlScorePic' score='$score' onclick='changeScore($id, this)' src=\"$score_pic\"
                title=\"$score\">";

				if ($score > 500) {
					$hlc_suffix = "high";
				} else if ($score < -100) {
					$hlc_suffix = "low";
				} else {
					$hlc_suffix = "";
				}

				$entry_author = $line["author"];

				if ($entry_author) {
					$entry_author = " &mdash; $entry_author";
				}

				$has_feed_icon = feeds::feedHasIcon($feed_id);

				if ($has_feed_icon) {
					$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"".ICONS_URL."/$feed_id.ico\" alt=\"\">";
				} else {
					$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"images/pub_set.png\" alt=\"\">";
				}

				$entry_site_url = $line["site_url"];

				//setting feed headline background color, needs to change text color based on dark/light
				$fav_color = $line['favicon_avg_color'];

				require_once "colors.php";

				if ($fav_color && $fav_color != 'fail') {
					if (!isset($rgba_cache[$feed_id])) {
						$rgba_cache[$feed_id] = join(",", _color_unpack($fav_color));
					}
				}

				if (!get_pref('COMBINED_DISPLAY_MODE')) {

					if ($vfeed_group_enabled) {
						if ($feed_id != $vgroup_last_feed && $line["feed_title"]) {

							$vgroup_last_feed = $feed_id;

							$vf_catchup_link = "<a class='catchup' onclick='catchupFeedInGroup($feed_id);' href='#'>".__('mark feed as read')."</a>";

							$reply['content'] .= "<div data-feed-id='$feed_id' id='FTITLE-$feed_id' class='cdmFeedTitle'>".
								"<div style='float : right'>$feed_icon_img</div>".
								"<a class='title' href=\"#\" onclick=\"viewfeed({feed:$feed_id})\">".
								$line["feed_title"]."</a>
                            $vf_catchup_link</div>";


						}
					}

					$reply['content'] .= "<div class='hl hlMenuAttach $class' data-orig-feed-id='$feed_id' data-article-id='$id' id='RROW-$id' $mouseover_attrs>";

					$reply['content'] .= "<div class='hlLeft'>";

					$reply['content'] .= "<input dojoType=\"dijit.form.CheckBox\"
                        type=\"checkbox\" onclick=\"toggleSelectRow2(this)\"
                        class='rchk'>";

					$reply['content'] .= "$marked_pic";
					$reply['content'] .= "$published_pic";

					$reply['content'] .= "</div>";

					$reply['content'] .= "<div onclick='return hlClicked(event, $id)'
                    class=\"hlTitle\"><span class='hlContent $hlc_suffix'>";
					$reply['content'] .= "<a id=\"RTITLE-$id\" class=\"title $hlc_suffix\"
                    href=\"" . htmlspecialchars($line["link"]) . "\"
                    onclick=\"\">" .
						truncate_string($line["title"], 200);

					if (get_pref('SHOW_CONTENT_PREVIEW')) {
						$reply['content'] .= "<span class=\"contentPreview\">" . $line["content_preview"] . "</span>";
					}

					$reply['content'] .= "</a></span>";

					$reply['content'] .= $labels_str;

					$reply['content'] .= "</div>";

					if (!$vfeed_group_enabled) {
						if (@$line["feed_title"]) {
							$rgba = @$rgba_cache[$feed_id];

							$reply['content'] .= "<span class=\"hlFeed\"><a style=\"background : rgba($rgba, 0.3)\" href=\"#\" onclick=\"viewfeed({feed:$feed_id})\">".
								truncate_string($line["feed_title"],30)."</a></span>";
						}
					}


					$reply['content'] .= "<span class=\"hlUpdated\">";

					$reply['content'] .= "<div title='$date_entered_fmt'>$updated_fmt</div>
                    </span>";

					$reply['content'] .= "<div class=\"hlRight\">";

					$reply['content'] .= $score_pic;

					if ($line["feed_title"] && !$vfeed_group_enabled) {

						$reply['content'] .= "<span onclick=\"viewfeed({feed:$feed_id})\"
                        style=\"cursor : pointer\"
                        title=\"".htmlspecialchars($line['feed_title'])."\">
                        $feed_icon_img</span>";
					}

					$reply['content'] .= "</div>";
					$reply['content'] .= "</div>";

				} else {

					if ($line["tag_cache"])
						$tags = explode(",", $line["tag_cache"]);
					else
						$tags = false;

					$line["content"] = sanitize($line["content"],
						$line['hide_images'], false, $entry_site_url, $highlight_words, $line["id"]);

					foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_RENDER_ARTICLE_CDM) as $p) {
						$line = $p->hook_render_article_cdm($line);
					}

					$line['content'] = rewrite_cached_urls($line['content']);

					if ($vfeed_group_enabled && $line["feed_title"]) {
						if ($feed_id != $vgroup_last_feed) {

							$vgroup_last_feed = $feed_id;

							$vf_catchup_link = "<a class='catchup' onclick='catchupFeedInGroup($feed_id);' href='#'>".__('mark feed as read')."</a>";

							$feed_icon_src = Feeds::getFeedIcon($feed_id);
							$feed_icon_img = "<img class=\"tinyFeedIcon\" src=\"$feed_icon_src\">";

							$reply['content'] .= "<div data-feed-id='$feed_id' id='FTITLE-$feed_id' class='cdmFeedTitle'>".
								"<div style=\"float : right\">$feed_icon_img</div>".
								"<a href=\"#\" class='title' onclick=\"viewfeed({feed:$feed_id})\">".
								$line["feed_title"]."</a> $vf_catchup_link</div>";

						}
					}

					$expanded_class = $expand_cdm ? "expanded" : "expandable";

					$tmp_content = "<div class=\"cdm $hlc_suffix $expanded_class $class\"
                    id=\"RROW-$id\" data-article-id='$id' data-orig-feed-id='$feed_id' $mouseover_attrs>";

					$tmp_content .= "<div class=\"cdmHeader\">";
					$tmp_content .= "<div style=\"vertical-align : middle\">";

					$tmp_content .= "<input dojoType=\"dijit.form.CheckBox\"
                        type=\"checkbox\" onclick=\"toggleSelectRow2(this, false, true)\"
                        class='rchk'>";

					$tmp_content .= "$marked_pic";
					$tmp_content .= "$published_pic";

					$tmp_content .= "</div>";

					if ($highlight_words && count($highlight_words) > 0) {
						foreach ($highlight_words as $word) {
						    $word = preg_quote($word, "/");

							$line["title"] = preg_replace("/($word)/i",
								"<span class=\"highlight\">$1</span>", $line["title"]);
						}
					}

					// data-article-id included for context menu
					$tmp_content .= "<span id=\"RTITLE-$id\"
                    onclick=\"return cdmClicked(event, $id);\"
                    data-article-id=\"$id\"
                    class=\"titleWrap hlMenuAttach $hlc_suffix\">						
                    <a class=\"title $hlc_suffix\"
                    title=\"".htmlspecialchars($line["title"])."\"
                    target=\"_blank\" rel=\"noopener noreferrer\" href=\"".
						htmlspecialchars($line["link"])."\">".
						$line["title"] .
						"</a> <span class=\"author\">$entry_author</span>";

					$tmp_content .= $labels_str;

					$tmp_content .= "<span class='collapseBtn' style='display : none'>
                    <img src=\"images/collapse.png\" onclick=\"cdmCollapseArticle(event, $id)\"
                    title=\"".__("Collapse article")."\"/></span>";

					if (!$expand_cdm)
						$content_hidden = "style=\"display : none\"";
					else
						$excerpt_hidden = "style=\"display : none\"";

					$tmp_content .= "<span $excerpt_hidden id=\"CEXC-$id\" class=\"cdmExcerpt\">" . $content_preview . "</span>";

					$tmp_content .= "</span>";

					if (!$vfeed_group_enabled) {
						if (@$line["feed_title"]) {
							$rgba = @$rgba_cache[$feed_id];

							$tmp_content .= "<div class=\"hlFeed\">
                            <a href=\"#\" style=\"background-color: rgba($rgba,0.3)\"
                            onclick=\"viewfeed({feed:$feed_id})\">".
								truncate_string($line["feed_title"],30)."</a>
                        </div>";
						}
					}

					$tmp_content .= "<span class='updated' title='$date_entered_fmt'>$updated_fmt</span>";

					$tmp_content .= "<div class='scoreWrap' style=\"vertical-align : middle\">";
					$tmp_content .= "$score_pic";

					if (!get_pref("VFEED_GROUP_BY_FEED") && $line["feed_title"]) {
						$tmp_content .= "<span style=\"cursor : pointer\"
                        title=\"".htmlspecialchars($line["feed_title"])."\"
                        onclick=\"viewfeed({feed:$feed_id})\">$feed_icon_img</span>";
					}
					$tmp_content .= "</div>"; //scoreWrap

					$tmp_content .= "</div>"; //cdmHeader

					$tmp_content .= "<div class=\"cdmContent\" $content_hidden
                    onclick=\"return cdmClicked(event, $id, true);\"
                    id=\"CICD-$id\">";

					$tmp_content .= "<div id=\"POSTNOTE-$id\">";
					if ($line['note']) {
						$tmp_content .= Article::format_article_note($id, $line['note']);
					}
					$tmp_content .= "</div>"; //POSTNOTE

					if (!$line['lang']) $line['lang'] = 'en';

					$tmp_content .= "<div class=\"cdmContentInner\" lang=\"".$line['lang']."\">";

					if ($line["orig_feed_id"]) {

						$ofgh = $this->pdo->prepare("SELECT * FROM ttrss_archived_feeds
                        WHERE id = ? AND owner_uid = ?");
						$ofgh->execute([$line["orig_feed_id"], $_SESSION['uid']]);

						if ($tmp_line = $ofgh->fetch()) {

							$tmp_content .= "<div clear='both'>";
							$tmp_content .= __("Originally from:");

							$tmp_content .= "&nbsp;";

							$tmp_content .= "<a target='_blank' rel='noopener noreferrer'
                            href=' " . htmlspecialchars($tmp_line['site_url']) . "'>" .
								$tmp_line['title'] . "</a>";

							$tmp_content .= "&nbsp;";

							$tmp_content .= "<a target='_blank' rel='noopener noreferrer' href='" . htmlspecialchars($tmp_line['feed_url']) . "'>";
							$tmp_content .= "<img title='".__('Feed URL')."'class='tinyFeedIcon' src='images/pub_unset.png'></a>";

							$tmp_content .= "</div>";
						}
					}

					$tmp_content .= "<span id=\"CWRAP-$id\">";
					$tmp_content .= "<span id=\"CENCW-$id\" class=\"cencw\" style=\"display : none\">";
					$tmp_content .= htmlspecialchars($line["content"]);
					$tmp_content .= "</span>";
					$tmp_content .= "</span>";

					$tmp_content .= "</div>"; //cdmContentInner

					$tmp_content .= "<div class=\"cdmIntermediate\">";

					$always_display_enclosures = $line["always_display_enclosures"];
					$tmp_content .= Article::format_article_enclosures($id, $always_display_enclosures,
						$line["content"], $line["hide_images"]);

					$tmp_content .= "</div>"; // cdmIntermediate

					$tmp_content .= "<div class=\"cdmFooter\" onclick=\"cdmFooterClick(event)\">";

					foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_ARTICLE_LEFT_BUTTON) as $p) {
						$tmp_content .= $p->hook_article_left_button($line);
					}

					$tags_str = Article::format_tags_string($tags, $id);

					$tmp_content .= "<span class='left'>";

					$tmp_content .= "<img src='images/tag.png' alt='Tags' title='Tags'>
                    <span id=\"ATSTR-$id\">$tags_str</span>
                    <a title=\"".__('Edit tags for this article')."\"
                    href=\"#\" onclick=\"editArticleTags($id)\">(+)</a>";

					$num_comments = (int) $line["num_comments"];
					$entry_comments = "";

					if ($num_comments > 0) {
						if ($line["comments"]) {
							$comments_url = htmlspecialchars($line["comments"]);
						} else {
							$comments_url = htmlspecialchars($line["link"]);
						}
						$entry_comments = "<a class=\"postComments\"
                        target='_blank' rel='noopener noreferrer' href=\"$comments_url\">$num_comments ".
							_ngettext("comment", "comments", $num_comments)."</a>";

					} else {
						if ($line["comments"] && $line["link"] != $line["comments"]) {
							$entry_comments = "<a class=\"postComments\" target='_blank' rel='noopener noreferrer' href=\"".htmlspecialchars($line["comments"])."\">".__("comments")."</a>";
						}
					}

					if ($entry_comments) $tmp_content .= "&nbsp;($entry_comments)";

					$tmp_content .= "</span>";
					$tmp_content .= "<div>";

					foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_ARTICLE_BUTTON) as $p) {
						$tmp_content .= $p->hook_article_button($line);
					}

					$tmp_content .= "</div>"; // buttons

					$tmp_content .= "</div>"; // cdmFooter
					$tmp_content .= "</div>"; // cdmContent
					$tmp_content .= "</div>"; // RROW.cdm

					foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_FORMAT_ARTICLE_CDM) as $p) {
						$tmp_content = $p->hook_format_article_cdm($tmp_content, $line);
					}

					$reply['content'] .= $tmp_content;
				}

				++$lnum;
			}
        }

        if ($_REQUEST["debug"]) $timing_info = print_checkpoint("PE", $timing_info);

		if (!$headlines_count) {

			if (!is_numeric($result)) {

				switch ($view_mode) {
					case "unread":
						$message = __("No unread articles found to display.");
						break;
					case "updated":
						$message = __("No updated articles found to display.");
						break;
					case "marked":
						$message = __("No starred articles found to display.");
						break;
					default:
						if ($feed < LABEL_BASE_INDEX) {
							$message = __("No articles found to display. You can assign articles to labels manually from article header context menu (applies to all selected articles) or use a filter.");
						} else {
							$message = __("No articles found to display.");
						}
				}

				if (!$offset && $message) {
					$reply['content'] = "<div class='whiteBox'>$message";

					$reply['content'] .= "<p><span class=\"insensitive\">";

					$sth = $this->pdo->prepare("SELECT " . SUBSTRING_FOR_DATE . "(MAX(last_updated), 1, 19) AS last_updated FROM ttrss_feeds
                        WHERE owner_uid = ?");
					$sth->execute([$_SESSION['uid']]);
					$row = $sth->fetch();

					$last_updated = make_local_datetime($row["last_updated"], false);

					$reply['content'] .= sprintf(__("Feeds last updated at %s"), $last_updated);

					$sth = $this->pdo->prepare("SELECT COUNT(id) AS num_errors
                        FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ?");
					$sth->execute([$_SESSION['uid']]);
					$row = $sth->fetch();

					$num_errors = $row["num_errors"];

					if ($num_errors > 0) {
						$reply['content'] .= "<br/>";
						$reply['content'] .= "<a class=\"insensitive\" href=\"#\" onclick=\"showFeedsWithErrors()\">" .
							__('Some feeds have update errors (click for details)') . "</a>";
					}
					$reply['content'] .= "</span></p></div>";

				}
			} else if (is_numeric($result) && $result == -1) {
				$reply['first_id_changed'] = true;
			}
		}

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("H2", $timing_info);

		return array($topmost_article_ids, $headlines_count, $feed, $disable_cache,
			$vgroup_last_feed, $reply);
	}

	function catchupAll() {
		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
						last_read = NOW(), unread = false WHERE unread = true AND owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);

		CCache::zero_all($_SESSION["uid"]);
	}

	function view() {
		$timing_info = microtime(true);

		$reply = array();

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("0", $timing_info);

		$feed = $_REQUEST["feed"];
		$method = $_REQUEST["m"];
		$view_mode = $_REQUEST["view_mode"];
		$limit = 30;
		@$cat_view = $_REQUEST["cat"] == "true";
		@$next_unread_feed = $_REQUEST["nuf"];
		@$offset = $_REQUEST["skip"];
		@$vgroup_last_feed = $_REQUEST["vgrlf"];
		$order_by = $_REQUEST["order_by"];
		$check_first_id = $_REQUEST["fid"];

		if (is_numeric($feed)) $feed = (int) $feed;

		/* Feed -5 is a special case: it is used to display auxiliary information
		 * when there's nothing to load - e.g. no stuff in fresh feed */

		if ($feed == -5) {
			print json_encode($this->generate_dashboard_feed());
			return;
		}

		$sth = false;
		if ($feed < LABEL_BASE_INDEX) {

			$label_feed = Labels::feed_to_label_id($feed);

			$sth = $this->pdo->prepare("SELECT id FROM ttrss_labels2 WHERE
							id = ? AND owner_uid = ?");
			$sth->execute([$label_feed, $_SESSION['uid']]);

		} else if (!$cat_view && is_numeric($feed) && $feed > 0) {

			$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE
							id = ? AND owner_uid = ?");
			$sth->execute([$feed, $_SESSION['uid']]);

		} else if ($cat_view && is_numeric($feed) && $feed > 0) {

			$sth = $this->pdo->prepare("SELECT id FROM ttrss_feed_categories WHERE
							id = ? AND owner_uid = ?");

			$sth->execute([$feed, $_SESSION['uid']]);
		}

		if ($sth && !$sth->fetch()) {
			print json_encode($this->generate_error_feed(__("Feed not found.")));
			return;
		}

		/* Updating a label ccache means recalculating all of the caches
		 * so for performance reasons we don't do that here */

		if ($feed >= 0) {
			CCache::update($feed, $_SESSION["uid"], $cat_view);
		}

		set_pref("_DEFAULT_VIEW_MODE", $view_mode);
		set_pref("_DEFAULT_VIEW_ORDER_BY", $order_by);

		/* bump login timestamp if needed */
		if (time() - $_SESSION["last_login_update"] > 3600) {
			$sth = $this->pdo->prepare("UPDATE ttrss_users SET last_login = NOW() WHERE id = ?");
			$sth->execute([$_SESSION['uid']]);

			$_SESSION["last_login_update"] = time();
		}

		if (!$cat_view && is_numeric($feed) && $feed > 0) {
			$sth = $this->pdo->prepare("UPDATE ttrss_feeds SET last_viewed = NOW()
							WHERE id = ? AND owner_uid = ?");
			$sth->execute([$feed, $_SESSION['uid']]);
		}

		$reply['headlines'] = array();

		$override_order = false;
		$skip_first_id_check = false;

		switch ($order_by) {
		case "title":
			$override_order = "ttrss_entries.title, date_entered, updated";
			break;
		case "date_reverse":
			$override_order = "score DESC, date_entered, updated";
			$skip_first_id_check = true;
			break;
		case "feed_dates":
			$override_order = "updated DESC";
			break;
		}

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("04", $timing_info);

		$ret = $this->format_headlines_list($feed, $method,
			$view_mode, $limit, $cat_view, $offset,
			$vgroup_last_feed, $override_order, true, $check_first_id, $skip_first_id_check);

		//$topmost_article_ids = $ret[0];
		$headlines_count = $ret[1];
		/* $returned_feed = $ret[2]; */
		$disable_cache = $ret[3];
		$vgroup_last_feed = $ret[4];

		//$reply['headlines']['content'] =& $ret[5]['content'];
		//$reply['headlines']['toolbar'] =& $ret[5]['toolbar'];

		$reply['headlines'] = $ret[5];

		if (!$next_unread_feed)
			$reply['headlines']['id'] = $feed;
		else
			$reply['headlines']['id'] = $next_unread_feed;

		$reply['headlines']['is_cat'] = (bool) $cat_view;

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("05", $timing_info);

		$reply['headlines-info'] = array("count" => (int) $headlines_count,
						"vgroup_last_feed" => $vgroup_last_feed,
						"disable_cache" => (bool) $disable_cache);

		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("30", $timing_info);

		$reply['runtime-info'] = make_runtime_info();

		print json_encode($reply);

	}

	private function generate_dashboard_feed() {
		$reply = array();

		$reply['headlines']['id'] = -5;
		$reply['headlines']['is_cat'] = false;

		$reply['headlines']['toolbar'] = '';

		$reply['headlines']['content'] = "<div class='whiteBox'>".__('No feed selected.');

		$reply['headlines']['content'] .= "<p><span class=\"insensitive\">";

		$sth = $this->pdo->prepare("SELECT ".SUBSTRING_FOR_DATE."(MAX(last_updated), 1, 19) AS last_updated FROM ttrss_feeds
			WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
		$row = $sth->fetch();

		$last_updated = make_local_datetime($row["last_updated"], false);

		$reply['headlines']['content'] .= sprintf(__("Feeds last updated at %s"), $last_updated);

		$sth = $this->pdo->prepare("SELECT COUNT(id) AS num_errors
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
		$row = $sth->fetch();

		$num_errors = $row["num_errors"];

		if ($num_errors > 0) {
			$reply['headlines']['content'] .= "<br/>";
			$reply['headlines']['content'] .= "<a class=\"insensitive\" href=\"#\" onclick=\"showFeedsWithErrors()\">".
				__('Some feeds have update errors (click for details)')."</a>";
		}
		$reply['headlines']['content'] .= "</span></p>";

		$reply['headlines-info'] = array("count" => 0,
			"vgroup_last_feed" => '',
			"unread" => 0,
			"disable_cache" => true);

		return $reply;
	}

	private function generate_error_feed($error) {
		$reply = array();

		$reply['headlines']['id'] = -7;
		$reply['headlines']['is_cat'] = false;

		$reply['headlines']['toolbar'] = '';
		$reply['headlines']['content'] = "<div class='whiteBox'>". $error . "</div>";

		$reply['headlines-info'] = array("count" => 0,
			"vgroup_last_feed" => '',
			"unread" => 0,
			"disable_cache" => true);

		return $reply;
	}

	function quickAddFeed() {
		print "<form onsubmit='return false'>";

		print_hidden("op", "rpc");
		print_hidden("method", "addfeed");

		print "<div id='fadd_error_message' style='display : none' class='alert alert-danger'></div>";

		print "<div id='fadd_multiple_notify' style='display : none'>";
		print_notice("Provided URL is a HTML page referencing multiple feeds, please select required feed from the dropdown menu below.");
		print "<p></div>";

		print "<div class=\"dlgSec\">".__("Feed or site URL")."</div>";
		print "<div class=\"dlgSecCont\">";

		print "<div style='float : right'>
			<img style='display : none'
				id='feed_add_spinner' src='images/indicator_white.gif'></div>";

		print "<input style=\"font-size : 16px; width : 20em;\"
			placeHolder=\"".__("Feed or site URL")."\"
			dojoType=\"dijit.form.ValidationTextBox\" required=\"1\" name=\"feed\" id=\"feedDlg_feedUrl\">";

		print "<hr/>";

		if (get_pref('ENABLE_FEED_CATS')) {
			print __('Place in category:') . " ";
			print_feed_cat_select("cat", false, 'dojoType="dijit.form.Select"');
		}

		print "</div>";

		print '<div id="feedDlg_feedsContainer" style="display : none">

				<div class="dlgSec">' . __('Available feeds') . '</div>
				<div class="dlgSecCont">'.
				'<select id="feedDlg_feedContainerSelect"
					dojoType="dijit.form.Select" size="3">
					<script type="dojo/method" event="onChange" args="value">
						dijit.byId("feedDlg_feedUrl").attr("value", value);
					</script>
				</select>'.
				'</div></div>';

		print "<div id='feedDlg_loginContainer' style='display : none'>

				<div class=\"dlgSec\">".__("Authentication")."</div>
				<div class=\"dlgSecCont\">".

				" <input dojoType=\"dijit.form.TextBox\" name='login'\"
					placeHolder=\"".__("Login")."\"
					autocomplete=\"new-password\"
					style=\"width : 10em;\"> ".
				" <input
					placeHolder=\"".__("Password")."\"
					dojoType=\"dijit.form.TextBox\" type='password'
					autocomplete=\"new-password\"
					style=\"width : 10em;\" name='pass'\">
			</div></div>";


		print "<div style=\"clear : both\">
			<input type=\"checkbox\" name=\"need_auth\" dojoType=\"dijit.form.CheckBox\" id=\"feedDlg_loginCheck\"
					onclick='checkboxToggleElement(this, \"feedDlg_loginContainer\")'>
				<label for=\"feedDlg_loginCheck\">".
				__('This feed requires authentication.')."</div>";

		print "<div class=\"dlgButtons\">
			<button dojoType=\"dijit.form.Button\" class=\"btn-primary\" type=\"submit\" onclick=\"return dijit.byId('feedAddDlg').execute()\">".__('Subscribe')."</button>";

		if (!(defined('_DISABLE_FEED_BROWSER') && _DISABLE_FEED_BROWSER)) {
			print "<button dojoType=\"dijit.form.Button\" onclick=\"return feedBrowser()\">".__('More feeds')."</button>";
		}

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedAddDlg').hide()\">".__('Cancel')."</button>
			</div>";

		print "</form>";

		//return;
	}

	function feedBrowser() {
		if (defined('_DISABLE_FEED_BROWSER') && _DISABLE_FEED_BROWSER) return;

		$browser_search = $_REQUEST["search"];

		print_hidden("op", "rpc");
		print_hidden("method", "updateFeedBrowser");

		print "<div dojoType=\"dijit.Toolbar\">
			<div style='float : right'>
			<img style='display : none'
				id='feed_browser_spinner' src='images/indicator_white.gif'>
			<input name=\"search\" dojoType=\"dijit.form.TextBox\" size=\"20\" type=\"search\"
				onchange=\"dijit.byId('feedBrowserDlg').update()\" value=\"$browser_search\">
			<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').update()\">".__('Search')."</button>
		</div>";

		print " <select name=\"mode\" dojoType=\"dijit.form.Select\" onchange=\"dijit.byId('feedBrowserDlg').update()\">
			<option value='1'>" . __('Popular feeds') . "</option>
			<option value='2'>" . __('Feed archive') . "</option>
			</select> ";

		print __("limit:");

		print " <select dojoType=\"dijit.form.Select\" name=\"limit\" onchange=\"dijit.byId('feedBrowserDlg').update()\">";

		foreach (array(25, 50, 100, 200) as $l) {
			//$issel = ($l == $limit) ? "selected=\"1\"" : "";
			print "<option value=\"$l\">$l</option>";
		}

		print "</select> ";

		print "</div>";

		require_once "feedbrowser.php";

		print "<ul class='browseFeedList' id='browseFeedList'>";
		print make_feed_browser("", 25);
		print "</ul>";

		print "<div align='center'>
			<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').execute()\">".__('Subscribe')."</button>
			<button dojoType=\"dijit.form.Button\" style='display : none' id='feed_archive_remove' onclick=\"dijit.byId('feedBrowserDlg').removeFromArchive()\">".__('Remove')."</button>
			<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedBrowserDlg').hide()\" >".__('Cancel')."</button></div>";

	}

	function search() {
		$this->params = explode(":", $_REQUEST["param"], 2);

		$active_feed_id = sprintf("%d", $this->params[0]);
		$is_cat = $this->params[1] != "false";

		print "<form onsubmit='return false;'>";

		print "<div class=\"dlgSec\">".__('Look for')."</div>";

		print "<div class=\"dlgSecCont\">";

		print "<input dojoType=\"dijit.form.ValidationTextBox\"
			style=\"font-size : 16px; width : 20em;\"
			required=\"1\" name=\"query\" type=\"search\" value=''>";

		print "<hr/><span style='float : right'>".T_sprintf('in %s', $this->getFeedTitle($active_feed_id, $is_cat))."</span>";

		if (DB_TYPE == "pgsql") {
			print "<hr/>";
			print_select("search_language", "", Pref_Feeds::$feed_languages,
				"dojoType='dijit.form.Select' title=\"".__('Used for word stemming')."\"");
		}

		print "</div>";

		print "<div class=\"dlgButtons\">";

		if (count(PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SEARCH)) == 0) {
			print "<div style=\"float : left\">
				<a class=\"visibleLink\" target=\"_blank\" href=\"http://tt-rss.org/wiki/SearchSyntax\">".__("Search syntax")."</a>
				</div>";
		}

		print "<button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"btn-primary\" onclick=\"dijit.byId('searchDlg').execute()\">".__('Search')."</button>
		<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('searchDlg').hide()\">".__('Cancel')."</button>
		</div>";

		print "</form>";
	}

	function update_debugger() {
		header("Content-type: text/html");

		$feed_id = (int)$_REQUEST["feed_id"];
		@$do_update = $_REQUEST["action"] == "do_update";
		$csrf_token = $_REQUEST["csrf_token"];

		$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE id = ? AND owner_uid = ?");
		$sth->execute([$feed_id, $_SESSION['uid']]);

		if (!$sth->fetch()) {
		    print "Access denied.";
		    return;
        }

		$refetch_checked = isset($_REQUEST["force_refetch"]) ? "checked" : "";
		$rehash_checked = isset($_REQUEST["force_rehash"]) ? "checked" : "";

		?>
		<html>
		<head>
			<?php echo stylesheet_tag("css/default.css") ?>
			<title>Feed Debugger</title>
		</head>
		<body class="small_margins ttrss_utility claro">
		<h1>Feed Debugger: <?php echo "$feed_id: " . $this->getFeedTitle($feed_id) ?></h1>
		<form method="GET" action="">
			<input type="hidden" name="op" value="feeds">
			<input type="hidden" name="method" value="update_debugger">
			<input type="hidden" name="xdebug" value="1">
			<input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?>">
			<input type="hidden" name="action" value="do_update">
			<input type="hidden" name="feed_id" value="<?php echo $feed_id ?>">
			<input type="checkbox" name="force_refetch" value="1" <?php echo $refetch_checked ?>> Force refetch<br/>
			<input type="checkbox" name="force_rehash" value="1" <?php echo $rehash_checked ?>> Force rehash<br/>

			<p/><button type="submit">Continue</button>
		</form>

		<hr>

		<pre><?php

		if ($do_update) {
			RSSUtils::update_rss_feed($feed_id, true);
		}

		?></pre>

		</body>
		</html>
		<?php

	}

	static function catchup_feed($feed, $cat_view, $owner_uid = false, $mode = 'all', $search = false) {

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];

		$pdo = Db::pdo();

		// Todo: all this interval stuff needs some generic generator function

		$search_qpart = is_array($search) && $search[0] ? search_to_sql($search[0], $search[1])[0] : 'true';

		switch ($mode) {
			case "1day":
				if (DB_TYPE == "pgsql") {
					$date_qpart = "date_entered < NOW() - INTERVAL '1 day' ";
				} else {
					$date_qpart = "date_entered < DATE_SUB(NOW(), INTERVAL 1 DAY) ";
				}
				break;
			case "1week":
				if (DB_TYPE == "pgsql") {
					$date_qpart = "date_entered < NOW() - INTERVAL '1 week' ";
				} else {
					$date_qpart = "date_entered < DATE_SUB(NOW(), INTERVAL 1 WEEK) ";
				}
				break;
			case "2week":
				if (DB_TYPE == "pgsql") {
					$date_qpart = "date_entered < NOW() - INTERVAL '2 week' ";
				} else {
					$date_qpart = "date_entered < DATE_SUB(NOW(), INTERVAL 2 WEEK) ";
				}
				break;
			default:
				$date_qpart = "true";
		}

		if (is_numeric($feed)) {
			if ($cat_view) {

				if ($feed >= 0) {

					if ($feed > 0) {
						$children = Feeds::getChildCategories($feed, $owner_uid);
						array_push($children, $feed);
						$children = array_map("intval", $children);

						$children = join(",", $children);

						$cat_qpart = "cat_id IN ($children)";
					} else {
						$cat_qpart = "cat_id IS NULL";
					}

					$sth = $pdo->prepare("UPDATE ttrss_user_entries
						SET unread = false, last_read = NOW() WHERE ref_id IN
							(SELECT id FROM
								(SELECT DISTINCT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
									AND owner_uid = ? AND unread = true AND feed_id IN
										(SELECT id FROM ttrss_feeds WHERE $cat_qpart) AND $date_qpart AND $search_qpart) as tmp)");
					$sth->execute([$owner_uid]);

				} else if ($feed == -2) {

					$sth = $pdo->prepare("UPDATE ttrss_user_entries
						SET unread = false,last_read = NOW() WHERE (SELECT COUNT(*)
							FROM ttrss_user_labels2, ttrss_entries WHERE article_id = ref_id AND id = ref_id AND $date_qpart AND $search_qpart) > 0
							AND unread = true AND owner_uid = ?");
					$sth->execute([$owner_uid]);
				}

			} else if ($feed > 0) {

				$sth = $pdo->prepare("UPDATE ttrss_user_entries
					SET unread = false, last_read = NOW() WHERE ref_id IN
						(SELECT id FROM
							(SELECT DISTINCT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = ? AND unread = true AND feed_id = ? AND $date_qpart AND $search_qpart) as tmp)");
				$sth->execute([$owner_uid, $feed]);

			} else if ($feed < 0 && $feed > LABEL_BASE_INDEX) { // special, like starred

				if ($feed == -1) {
					$sth = $pdo->prepare("UPDATE ttrss_user_entries
						SET unread = false, last_read = NOW() WHERE ref_id IN
							(SELECT id FROM
								(SELECT DISTINCT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
									AND owner_uid = ? AND unread = true AND marked = true AND $date_qpart AND $search_qpart) as tmp)");
					$sth->execute([$owner_uid]);
				}

				if ($feed == -2) {
					$sth = $pdo->prepare("UPDATE ttrss_user_entries
						SET unread = false, last_read = NOW() WHERE ref_id IN
							(SELECT id FROM
								(SELECT DISTINCT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
									AND owner_uid = ? AND unread = true AND published = true AND $date_qpart AND $search_qpart) as tmp)");
					$sth->execute([$owner_uid]);
				}

				if ($feed == -3) {

					$intl = (int) get_pref("FRESH_ARTICLE_MAX_AGE");

					if (DB_TYPE == "pgsql") {
						$match_part = "date_entered > NOW() - INTERVAL '$intl hour' ";
					} else {
						$match_part = "date_entered > DATE_SUB(NOW(),
							INTERVAL $intl HOUR) ";
					}

					$sth = $pdo->prepare("UPDATE ttrss_user_entries
						SET unread = false, last_read = NOW() WHERE ref_id IN
							(SELECT id FROM
								(SELECT DISTINCT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
									AND owner_uid = ? AND score >= 0 AND unread = true AND $date_qpart AND $match_part AND $search_qpart) as tmp)");
					$sth->execute([$owner_uid]);
				}

				if ($feed == -4) {
					$sth = $pdo->prepare("UPDATE ttrss_user_entries
						SET unread = false, last_read = NOW() WHERE ref_id IN
							(SELECT id FROM
								(SELECT DISTINCT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
									AND owner_uid = ? AND unread = true AND $date_qpart AND $search_qpart) as tmp)");
					$sth->execute([$owner_uid]);
				}

			} else if ($feed < LABEL_BASE_INDEX) { // label

				$label_id = Labels::feed_to_label_id($feed);

				$sth = $pdo->prepare("UPDATE ttrss_user_entries
					SET unread = false, last_read = NOW() WHERE ref_id IN
						(SELECT id FROM
							(SELECT DISTINCT ttrss_entries.id FROM ttrss_entries, ttrss_user_entries, ttrss_user_labels2 WHERE ref_id = id
								AND label_id = ? AND ref_id = article_id
								AND owner_uid = ? AND unread = true AND $date_qpart AND $search_qpart) as tmp)");
				$sth->execute([$label_id, $owner_uid]);

			}

			CCache::update($feed, $owner_uid, $cat_view);

		} else { // tag
			$sth = $pdo->prepare("UPDATE ttrss_user_entries
				SET unread = false, last_read = NOW() WHERE ref_id IN
					(SELECT id FROM
						(SELECT DISTINCT ttrss_entries.id FROM ttrss_entries, ttrss_user_entries, ttrss_tags WHERE ref_id = ttrss_entries.id
							AND post_int_id = int_id AND tag_name = ?
							AND ttrss_user_entries.owner_uid = ? AND unread = true AND $date_qpart AND $search_qpart) as tmp)");
			$sth->execute([$feed, $owner_uid]);

		}
	}

	static function getFeedArticles($feed, $is_cat = false, $unread_only = false,
							 $owner_uid = false) {

		$n_feed = (int) $feed;
		$need_entries = false;

		$pdo = Db::pdo();

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		if ($unread_only) {
			$unread_qpart = "unread = true";
		} else {
			$unread_qpart = "true";
		}

		$match_part = "";

		if ($is_cat) {
			return Feeds::getCategoryUnread($n_feed, $owner_uid);
		} else if ($n_feed == -6) {
			return 0;
		} else if ($feed != "0" && $n_feed == 0) {

			$sth = $pdo->prepare("SELECT SUM((SELECT COUNT(int_id)
				FROM ttrss_user_entries,ttrss_entries WHERE int_id = post_int_id
					AND ref_id = id AND $unread_qpart)) AS count FROM ttrss_tags
				WHERE owner_uid = ? AND tag_name = ?");

			$sth->execute([$owner_uid, $feed]);
			$row = $sth->fetch();

			return $row["count"];

		} else if ($n_feed == -1) {
			$match_part = "marked = true";
		} else if ($n_feed == -2) {
			$match_part = "published = true";
		} else if ($n_feed == -3) {
			$match_part = "unread = true AND score >= 0";

			$intl = (int) get_pref("FRESH_ARTICLE_MAX_AGE", $owner_uid);

			if (DB_TYPE == "pgsql") {
				$match_part .= " AND date_entered > NOW() - INTERVAL '$intl hour' ";
			} else {
				$match_part .= " AND date_entered > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
			}

			$need_entries = true;

		} else if ($n_feed == -4) {
			$match_part = "true";
		} else if ($n_feed >= 0) {

			if ($n_feed != 0) {
				$match_part = "feed_id = " . (int)$n_feed;
			} else {
				$match_part = "feed_id IS NULL";
			}

		} else if ($feed < LABEL_BASE_INDEX) {

			$label_id = Labels::feed_to_label_id($feed);

			return Feeds::getLabelUnread($label_id, $owner_uid);
		}

		if ($match_part) {

			if ($need_entries) {
				$from_qpart = "ttrss_user_entries,ttrss_entries";
				$from_where = "ttrss_entries.id = ttrss_user_entries.ref_id AND";
			} else {
				$from_qpart = "ttrss_user_entries";
				$from_where = "";
			}

			$sth = $pdo->prepare("SELECT count(int_id) AS unread
				FROM $from_qpart WHERE
				$unread_qpart AND $from_where ($match_part) AND ttrss_user_entries.owner_uid = ?");
			$sth->execute([$owner_uid]);
			$row = $sth->fetch();

			return $row["unread"];

		} else {

			$sth = $pdo->prepare("SELECT COUNT(post_int_id) AS unread
				FROM ttrss_tags,ttrss_user_entries,ttrss_entries
				WHERE tag_name = ? AND post_int_id = int_id AND ref_id = ttrss_entries.id
				AND $unread_qpart AND ttrss_tags.owner_uid = ,");

			$sth->execute([$feed, $owner_uid]);
			$row = $sth->fetch();

			return $row["unread"];
		}
	}

	/**
	 * @return array (code => Status code, message => error message if available)
	 *
	 *                 0 - OK, Feed already exists
	 *                 1 - OK, Feed added
	 *                 2 - Invalid URL
	 *                 3 - URL content is HTML, no feeds available
	 *                 4 - URL content is HTML which contains multiple feeds.
	 *                     Here you should call extractfeedurls in rpc-backend
	 *                     to get all possible feeds.
	 *                 5 - Couldn't download the URL content.
	 *                 6 - Content is an invalid XML.
	 */
	static function subscribe_to_feed($url, $cat_id = 0,
							   $auth_login = '', $auth_pass = '') {

		global $fetch_last_error;
		global $fetch_last_error_content;

		$pdo = Db::pdo();

		$url = fix_url($url);

		if (!$url || !validate_feed_url($url)) return array("code" => 2);

		$contents = @fetch_file_contents($url, false, $auth_login, $auth_pass);

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SUBSCRIBE_FEED) as $plugin) {
			$contents = $plugin->hook_subscribe_feed($contents, $url, $auth_login, $auth_pass);
		}
		
		if (!$contents) {
			if (preg_match("/cloudflare\.com/", $fetch_last_error_content)) {
				$fetch_last_error .= " (feed behind Cloudflare)";
			}

			return array("code" => 5, "message" => $fetch_last_error);
		}

		if (is_html($contents)) {
			$feedUrls = get_feeds_from_html($url, $contents);

			if (count($feedUrls) == 0) {
				return array("code" => 3);
			} else if (count($feedUrls) > 1) {
				return array("code" => 4, "feeds" => $feedUrls);
			}
			//use feed url as new URL
			$url = key($feedUrls);
		}

		if (!$cat_id) $cat_id = null;

		$sth = $pdo->prepare("SELECT id FROM ttrss_feeds
			WHERE feed_url = ? AND owner_uid = ?");
		$sth->execute([$url, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			return array("code" => 0, "feed_id" => (int) $row["id"]);
		} else {
			$sth = $pdo->prepare(
				"INSERT INTO ttrss_feeds
					(owner_uid,feed_url,title,cat_id, auth_login,auth_pass,update_method,auth_pass_encrypted)
				VALUES (?, ?, ?, ?, ?, ?, 0, false)");

			$sth->execute([$_SESSION['uid'], $url, "[Unknown]", $cat_id, (string)$auth_login, (string)$auth_pass]);

			$sth = $pdo->prepare("SELECT id FROM ttrss_feeds WHERE feed_url = ?
					AND owner_uid = ?");
			$sth->execute([$url, $_SESSION['uid']]);
			$row = $sth->fetch();

			$feed_id = $row["id"];

			if ($feed_id) {
				RSSUtils::set_basic_feed_info($feed_id);
			}

			return array("code" => 1, "feed_id" => (int) $feed_id);

		}
	}

	static function getIconFile($feed_id) {
		return ICONS_DIR . "/$feed_id.ico";
	}

	static function feedHasIcon($id) {
		return is_file(ICONS_DIR . "/$id.ico") && filesize(ICONS_DIR . "/$id.ico") > 0;
	}

	static function getFeedIcon($id) {
		switch ($id) {
			case 0:
				return "images/archive.png";
				break;
			case -1:
				return "images/star.png";
				break;
			case -2:
				return "images/feed.png";
				break;
			case -3:
				return "images/fresh.png";
				break;
			case -4:
				return "images/folder.png";
				break;
			case -6:
				return "images/time.png";
				break;
			default:
				if ($id < LABEL_BASE_INDEX) {
					return "images/label.png";
				} else {
					$icon = self::getIconFile($id);

                    if ($icon && file_exists($icon)) {
						return ICONS_URL . "/" . basename($icon) . "?" . filemtime($icon);
					}
				}
				break;
		}

		return false;
	}

	static function getFeedTitle($id, $cat = false) {
	    $pdo = Db::pdo();

		if ($cat) {
			return Feeds::getCategoryTitle($id);
		} else if ($id == -1) {
			return __("Starred articles");
		} else if ($id == -2) {
			return __("Published articles");
		} else if ($id == -3) {
			return __("Fresh articles");
		} else if ($id == -4) {
			return __("All articles");
		} else if ($id === 0 || $id === "0") {
			return __("Archived articles");
		} else if ($id == -6) {
			return __("Recently read");
		} else if ($id < LABEL_BASE_INDEX) {

			$label_id = Labels::feed_to_label_id($id);

			$sth = $pdo->prepare("SELECT caption FROM ttrss_labels2 WHERE id = ?");
			$sth->execute([$label_id]);

			if ($row = $sth->fetch()) {
				return $row["caption"];
			} else {
				return "Unknown label ($label_id)";
			}

		} else if (is_numeric($id) && $id > 0) {

		    $sth = $pdo->prepare("SELECT title FROM ttrss_feeds WHERE id = ?");
		    $sth->execute([$id]);

		    if ($row = $sth->fetch()) {
				return $row["title"];
			} else {
				return "Unknown feed ($id)";
			}

		} else {
			return $id;
		}
	}

	static function getCategoryUnread($cat, $owner_uid = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();

		if ($cat >= 0) {

		    if (!$cat) $cat = null;

			$sth = $pdo->prepare("SELECT id FROM ttrss_feeds
                    WHERE (cat_id = :cat OR (:cat IS NULL AND cat_id IS NULL))
					AND owner_uid = :uid");

			$sth->execute([":cat" => $cat, ":uid" => $owner_uid]);

			$cat_feeds = array();
			while ($line = $sth->fetch()) {
				array_push($cat_feeds, "feed_id = " . (int)$line["id"]);
			}

			if (count($cat_feeds) == 0) return 0;

			$match_part = implode(" OR ", $cat_feeds);

			$sth = $pdo->prepare("SELECT COUNT(int_id) AS unread
				FROM ttrss_user_entries
				WHERE	unread = true AND ($match_part)
				AND owner_uid = ?");
			$sth->execute([$owner_uid]);

			$unread = 0;

			# this needs to be rewritten
			while ($line = $sth->fetch()) {
				$unread += $line["unread"];
			}

			return $unread;
		} else if ($cat == -1) {
			return getFeedUnread(-1) + getFeedUnread(-2) + getFeedUnread(-3) + getFeedUnread(0);
		} else if ($cat == -2) {

			$sth = $pdo->prepare("SELECT COUNT(unread) AS unread FROM
					ttrss_user_entries, ttrss_user_labels2
				WHERE article_id = ref_id AND unread = true
					AND ttrss_user_entries.owner_uid = ?");
			$sth->execute([$owner_uid]);
            $row = $sth->fetch();

			return $row["unread"];
		}
	}

	// only accepts real cats (>= 0)
	static function getCategoryChildrenUnread($cat, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT id FROM ttrss_feed_categories WHERE parent_cat = ?
				AND owner_uid = ?");
		$sth->execute([$cat, $owner_uid]);

		$unread = 0;

		while ($line = $sth->fetch()) {
			$unread += Feeds::getCategoryUnread($line["id"], $owner_uid);
			$unread += Feeds::getCategoryChildrenUnread($line["id"], $owner_uid);
		}

		return $unread;
	}

	static function getGlobalUnread($user_id = false) {

		if (!$user_id) $user_id = $_SESSION["uid"];

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT SUM(value) AS c_id FROM ttrss_counters_cache
			WHERE owner_uid = ? AND feed_id > 0");
		$sth->execute([$user_id]);
		$row = $sth->fetch();

		return $row["c_id"];
	}

	static function getCategoryTitle($cat_id) {

		if ($cat_id == -1) {
			return __("Special");
		} else if ($cat_id == -2) {
			return __("Labels");
		} else {

		    $pdo = Db::pdo();

			$sth = $pdo->prepare("SELECT title FROM ttrss_feed_categories WHERE
				id = ?");
			$sth->execute([$cat_id]);

			if ($row = $sth->fetch()) {
				return $row["title"];
			} else {
				return __("Uncategorized");
			}
		}
	}

	static function getLabelUnread($label_id, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT COUNT(ref_id) AS unread FROM ttrss_user_entries, ttrss_user_labels2
			WHERE owner_uid = ? AND unread = true AND label_id = ? AND article_id = ref_id");

		$sth->execute([$owner_uid, $label_id]);

		if ($row = $sth->fetch()) {
			return $row["unread"];
		} else {
			return 0;
		}
	}

	static function queryFeedHeadlines($params) {

		$pdo = Db::pdo();

		// WARNING: due to highly dynamic nature of this query its going to quote parameters
        // right before adding them to SQL part

		$feed = $params["feed"];
		$limit = isset($params["limit"]) ? $params["limit"] : 30;
		$view_mode = $params["view_mode"];
		$cat_view = isset($params["cat_view"]) ? $params["cat_view"] : false;
		$search = isset($params["search"]) ? $params["search"] : false;
		$search_language = isset($params["search_language"]) ? $params["search_language"] : "";
		$override_order = isset($params["override_order"]) ? $params["override_order"] : false;
		$offset = isset($params["offset"]) ? $params["offset"] : 0;
		$owner_uid = isset($params["owner_uid"]) ? $params["owner_uid"] : $_SESSION["uid"];
		$since_id = isset($params["since_id"]) ? $params["since_id"] : 0;
		$include_children = isset($params["include_children"]) ? $params["include_children"] : false;
		$ignore_vfeed_group = isset($params["ignore_vfeed_group"]) ? $params["ignore_vfeed_group"] : false;
		$override_strategy = isset($params["override_strategy"]) ? $params["override_strategy"] : false;
		$override_vfeed = isset($params["override_vfeed"]) ? $params["override_vfeed"] : false;
		$start_ts = isset($params["start_ts"]) ? $params["start_ts"] : false;
		$check_first_id = isset($params["check_first_id"]) ? $params["check_first_id"] : false;
		$skip_first_id_check = isset($params["skip_first_id_check"]) ? $params["skip_first_id_check"] : false;

		$ext_tables_part = "";
		$limit_query_part = "";

		$search_words = array();

		if ($search) {
			foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SEARCH) as $plugin) {
				list($search_query_part, $search_words) = $plugin->hook_search($search);
				break;
			}

			// fall back in case of no plugins
			if (!$search_query_part) {
				list($search_query_part, $search_words) = search_to_sql($search, $search_language);
			}
			$search_query_part .= " AND ";
		} else {
			$search_query_part = "";
		}

		if ($since_id) {
			$since_id_part = "ttrss_entries.id > ".$pdo->quote($since_id)." AND ";
		} else {
			$since_id_part = "";
		}

		$view_query_part = "";

		if ($view_mode == "adaptive") {
			if ($search) {
				$view_query_part = " ";
			} else if ($feed != -1) {

				$unread = getFeedUnread($feed, $cat_view);

				if ($cat_view && $feed > 0 && $include_children)
					$unread += Feeds::getCategoryChildrenUnread($feed);

				if ($unread > 0) {
					$view_query_part = " unread = true AND ";
				}
			}
		}

		if ($view_mode == "marked") {
			$view_query_part = " marked = true AND ";
		}

		if ($view_mode == "has_note") {
			$view_query_part = " (note IS NOT NULL AND note != '') AND ";
		}

		if ($view_mode == "published") {
			$view_query_part = " published = true AND ";
		}

		if ($view_mode == "unread" && $feed != -6) {
			$view_query_part = " unread = true AND ";
		}

		if ($limit > 0) {
			$limit_query_part = "LIMIT " . (int)$limit;
		}

		$allow_archived = false;

		$vfeed_query_part = "";

		/* tags */
		if (!is_numeric($feed)) {
			$query_strategy_part = "true";
			$vfeed_query_part = "(SELECT title FROM ttrss_feeds WHERE
					id = feed_id) as feed_title,";
		} else if ($feed > 0) {

			if ($cat_view) {

				if ($feed > 0) {
					if ($include_children) {
						# sub-cats
						$subcats = Feeds::getChildCategories($feed, $owner_uid);
						array_push($subcats, $feed);
						$subcats = array_map("intval", $subcats);

						$query_strategy_part = "cat_id IN (".
							implode(",", $subcats).")";

					} else {
						$query_strategy_part = "cat_id = " . $pdo->quote($feed);
					}

				} else {
					$query_strategy_part = "cat_id IS NULL";
				}

				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";

			} else {
				$query_strategy_part = "feed_id = " . $pdo->quote($feed);
			}
		} else if ($feed == 0 && !$cat_view) { // archive virtual feed
			$query_strategy_part = "feed_id IS NULL";
			$allow_archived = true;
		} else if ($feed == 0 && $cat_view) { // uncategorized
			$query_strategy_part = "cat_id IS NULL AND feed_id IS NOT NULL";
			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
		} else if ($feed == -1) { // starred virtual feed
			$query_strategy_part = "marked = true";
			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			$allow_archived = true;

			if (!$override_order) {
				$override_order = "last_marked DESC, date_entered DESC, updated DESC";
			}

		} else if ($feed == -2) { // published virtual feed OR labels category

			if (!$cat_view) {
				$query_strategy_part = "published = true";
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
				$allow_archived = true;

				if (!$override_order) {
					$override_order = "last_published DESC, date_entered DESC, updated DESC";
				}

			} else {
				$vfeed_query_part = "ttrss_feeds.title AS feed_title,";

				$ext_tables_part = "ttrss_labels2,ttrss_user_labels2,";

				$query_strategy_part = "ttrss_labels2.id = ttrss_user_labels2.label_id AND
						ttrss_user_labels2.article_id = ref_id";

			}
		} else if ($feed == -6) { // recently read
			$query_strategy_part = "unread = false AND last_read IS NOT NULL";

			if (DB_TYPE == "pgsql") {
				$query_strategy_part .= " AND last_read > NOW() - INTERVAL '1 DAY' ";
			} else {
				$query_strategy_part .= " AND last_read > DATE_SUB(NOW(), INTERVAL 1 DAY) ";
			}

			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			$allow_archived = true;
			$ignore_vfeed_group = true;

			if (!$override_order) $override_order = "last_read DESC";

		} else if ($feed == -3) { // fresh virtual feed
			$query_strategy_part = "unread = true AND score >= 0";

			$intl = (int) get_pref("FRESH_ARTICLE_MAX_AGE", $owner_uid);

			if (DB_TYPE == "pgsql") {
				$query_strategy_part .= " AND date_entered > NOW() - INTERVAL '$intl hour' ";
			} else {
				$query_strategy_part .= " AND date_entered > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
			}

			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
		} else if ($feed == -4) { // all articles virtual feed
			$allow_archived = true;
			$query_strategy_part = "true";
			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
		} else if ($feed <= LABEL_BASE_INDEX) { // labels
			$label_id = Labels::feed_to_label_id($feed);

			$query_strategy_part = "label_id = ".$pdo->quote($label_id)." AND
					ttrss_labels2.id = ttrss_user_labels2.label_id AND
					ttrss_user_labels2.article_id = ref_id";

			$vfeed_query_part = "ttrss_feeds.title AS feed_title,";
			$ext_tables_part = "ttrss_labels2,ttrss_user_labels2,";
			$allow_archived = true;

		} else {
			$query_strategy_part = "true";
		}

		$order_by = "score DESC, date_entered DESC, updated DESC";

		if ($override_order) {
			$order_by = $override_order;
		}

		if ($override_strategy) {
			$query_strategy_part = $override_strategy;
		}

		if ($override_vfeed) {
			$vfeed_query_part = $override_vfeed;
		}

		if ($search) {
			$feed_title = T_sprintf("Search results: %s", $search);
		} else {
			if ($cat_view) {
				$feed_title = Feeds::getCategoryTitle($feed);
			} else {
				if (is_numeric($feed) && $feed > 0) {
					$ssth = $pdo->prepare("SELECT title,site_url,last_error,last_updated
							FROM ttrss_feeds WHERE id = ? AND owner_uid = ?");
					$ssth->execute([$feed, $owner_uid]);
                    $row = $ssth->fetch();

					$feed_title = $row["title"];
					$feed_site_url = $row["site_url"];
					$last_error = $row["last_error"];
					$last_updated = $row["last_updated"];
				} else {
					$feed_title = Feeds::getFeedTitle($feed);
				}
			}
		}

		$content_query_part = "content, ";

		if ($limit_query_part) {
			$offset_query_part = "OFFSET " . (int)$offset;
		} else {
			$offset_query_part = "";
		}

		if (is_numeric($feed)) {
			// proper override_order applied above
			if ($vfeed_query_part && !$ignore_vfeed_group && get_pref('VFEED_GROUP_BY_FEED', $owner_uid)) {
				if (!$override_order) {
					$order_by = "ttrss_feeds.title, ".$order_by;
				} else {
					$order_by = "ttrss_feeds.title, ".$override_order;
				}
			}

			if (!$allow_archived) {
				$from_qpart = "${ext_tables_part}ttrss_entries LEFT JOIN ttrss_user_entries ON (ref_id = ttrss_entries.id),ttrss_feeds";
				$feed_check_qpart = "ttrss_user_entries.feed_id = ttrss_feeds.id AND";

			} else {
				$from_qpart = "${ext_tables_part}ttrss_entries LEFT JOIN ttrss_user_entries ON (ref_id = ttrss_entries.id)
						LEFT JOIN ttrss_feeds ON (feed_id = ttrss_feeds.id)";
			}

			if ($vfeed_query_part) $vfeed_query_part .= "favicon_avg_color,";

			if ($start_ts) {
				$start_ts_formatted = date("Y/m/d H:i:s", strtotime($start_ts));
				$start_ts_query_part = "date_entered >= '$start_ts_formatted' AND";
			} else {
				$start_ts_query_part = "";
			}

			$first_id = 0;
			$first_id_query_strategy_part = $query_strategy_part;

			if ($feed == -3)
				$first_id_query_strategy_part = "true";

			if (DB_TYPE == "pgsql") {
				$sanity_interval_qpart = "date_entered >= NOW() - INTERVAL '1 hour' AND";
			} else {
				$sanity_interval_qpart = "date_entered >= DATE_SUB(NOW(), INTERVAL 1 hour) AND";
			}

			if (!$search && !$skip_first_id_check) {
				// if previous topmost article id changed that means our current pagination is no longer valid
				$query = "SELECT DISTINCT
							ttrss_feeds.title,
							date_entered,
							guid,
							ttrss_entries.id,
							ttrss_entries.title,
							updated,
							score,
							marked,
							published,
							last_marked,
							last_published,
							last_read
						FROM
							$from_qpart
						WHERE
						$feed_check_qpart
						ttrss_user_entries.owner_uid = ".$pdo->quote($owner_uid)." AND
						$search_query_part
						$start_ts_query_part
						$since_id_part
						$sanity_interval_qpart
						$first_id_query_strategy_part ORDER BY $order_by LIMIT 1";

				/*if ($_REQUEST["debug"]) {
					print $query;
				}*/

				$res = $pdo->query($query);

				if ($row = $res->fetch()) {
					$first_id = (int)$row["id"];

					if ($offset > 0 && $first_id && $check_first_id && $first_id != $check_first_id) {
						return array(-1, $feed_title, $feed_site_url, $last_error, $last_updated, $search_words, $first_id);
					}
				}
			}

			$query = "SELECT DISTINCT
						date_entered,
						guid,
						ttrss_entries.id,ttrss_entries.title,
						updated,
						label_cache,
						tag_cache,
						always_display_enclosures,
						site_url,
						note,
						num_comments,
						comments,
						int_id,
						uuid,
						lang,
						hide_images,
						unread,feed_id,marked,published,link,last_read,orig_feed_id,
						last_marked, last_published,
						$vfeed_query_part
						$content_query_part
						author,score
					FROM
						$from_qpart
					WHERE
					$feed_check_qpart
					ttrss_user_entries.owner_uid = ".$pdo->quote($owner_uid)." AND
					$search_query_part
					$start_ts_query_part
					$view_query_part
					$since_id_part
					$query_strategy_part ORDER BY $order_by
					$limit_query_part $offset_query_part";

			//if ($_REQUEST["debug"]) print $query;

			$res = $pdo->query($query);

		} else {
			// browsing by tag

			$query = "SELECT DISTINCT
							date_entered,
							guid,
							note,
							ttrss_entries.id as id,
							title,
							updated,
							unread,
							feed_id,
							orig_feed_id,
							marked,
							num_comments,
							comments,
							int_id,
							tag_cache,
							label_cache,
							link,
							lang,
							uuid,
							last_read,
							(SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) AS hide_images,
							last_marked, last_published,
							$since_id_part
							$vfeed_query_part
							$content_query_part
							author, score
						FROM ttrss_entries, ttrss_user_entries, ttrss_tags
						WHERE
							ref_id = ttrss_entries.id AND
							ttrss_user_entries.owner_uid = ".$pdo->quote($owner_uid)." AND
							post_int_id = int_id AND
							tag_name = ".$pdo->quote($feed)." AND
							$view_query_part
							$search_query_part
							$query_strategy_part ORDER BY $order_by
							$limit_query_part $offset_query_part";

			if ($_REQUEST["debug"]) print $query;

			$res = $pdo->query($query);
		}

		return array($res, $feed_title, $feed_site_url, $last_error, $last_updated, $search_words, $first_id);

	}

	static function getParentCategories($cat, $owner_uid) {
		$rv = array();

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT parent_cat FROM ttrss_feed_categories
			WHERE id = ? AND parent_cat IS NOT NULL AND owner_uid = ?");
		$sth->execute([$cat, $owner_uid]);

		while ($line = $sth->fetch()) {
			array_push($rv, $line["parent_cat"]);
			$rv = array_merge($rv, Feeds::getParentCategories($line["parent_cat"], $owner_uid));
		}

		return $rv;
	}

	static function getChildCategories($cat, $owner_uid) {
		$rv = array();

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT id FROM ttrss_feed_categories
			WHERE parent_cat = ? AND owner_uid = ?");
		$sth->execute([$cat, $owner_uid]);

		while ($line = $sth->fetch()) {
			array_push($rv, $line["id"]);
			$rv = array_merge($rv, Feeds::getChildCategories($line["id"], $owner_uid));
		}

		return $rv;
	}

	static function getFeedCategory($feed) {
		$pdo = Db::pdo();

	    $sth = $pdo->prepare("SELECT cat_id FROM ttrss_feeds
				WHERE id = ?");
	    $sth->execute([$feed]);

		if ($row = $sth->fetch()) {
			return $row["cat_id"];
		} else {
			return false;
		}

	}


}


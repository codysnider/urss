<?php
class Handler_Public extends Handler {

	private function generate_syndicated_feed($owner_uid, $feed, $is_cat,
		$limit, $offset, $search,
		$view_mode = false, $format = 'atom', $order = false, $orig_guid = false, $start_ts = false) {

		require_once "lib/MiniTemplator.class.php";

		$note_style = 	"background-color : #fff7d5;
			border-width : 1px; ".
			"padding : 5px; border-style : dashed; border-color : #e7d796;".
			"margin-bottom : 1em; color : #9a8c59;";

		if (!$limit) $limit = 60;

		$date_sort_field = "date_entered DESC, updated DESC";

		if ($feed == -2 && !$is_cat) {
			$date_sort_field = "last_published DESC";
		} else if ($feed == -1 && !$is_cat) {
			$date_sort_field = "last_marked DESC";
		}

		switch ($order) {
		case "title":
			$date_sort_field = "ttrss_entries.title, date_entered, updated";
			break;
		case "date_reverse":
			$date_sort_field = "date_entered, updated";
			break;
		case "feed_dates":
			$date_sort_field = "updated DESC";
			break;
		}

		$params = array(
			"owner_uid" => $owner_uid,
			"feed" => $feed,
			"limit" => $limit,
			"view_mode" => $view_mode,
			"cat_view" => $is_cat,
			"search" => $search,
			"override_order" => $date_sort_field,
			"include_children" => true,
			"ignore_vfeed_group" => true,
			"offset" => $offset,
			"start_ts" => $start_ts
		);

		if (!$is_cat && is_numeric($feed) && $feed < PLUGIN_FEED_BASE_INDEX && $feed > LABEL_BASE_INDEX) {

			$user_plugins = get_pref("_ENABLED_PLUGINS", $owner_uid);

			$tmppluginhost = new PluginHost();
			$tmppluginhost->load(PLUGINS, PluginHost::KIND_ALL);
			$tmppluginhost->load($user_plugins, PluginHost::KIND_USER, $owner_uid);
			$tmppluginhost->load_data();

			$handler = $tmppluginhost->get_feed_handler(
				PluginHost::feed_to_pfeed_id($feed));

			if ($handler) {
				$qfh_ret = $handler->get_headlines(PluginHost::feed_to_pfeed_id($feed), $params);
			}

		} else {
			$qfh_ret = Feeds::queryFeedHeadlines($params);
		}

		$result = $qfh_ret[0];
		$feed_title = htmlspecialchars($qfh_ret[1]);
		$feed_site_url = $qfh_ret[2];
		/* $last_error = $qfh_ret[3]; */

		$feed_self_url = get_self_url_prefix() .
			"/public.php?op=rss&id=$feed&key=" .
			get_feed_access_key($feed, false, $owner_uid);

		if (!$feed_site_url) $feed_site_url = get_self_url_prefix();

		if ($format == 'atom') {
			$tpl = new MiniTemplator;

			$tpl->readTemplateFromFile("templates/generated_feed.txt");

			$tpl->setVariable('FEED_TITLE', $feed_title, true);
			$tpl->setVariable('VERSION', VERSION, true);
			$tpl->setVariable('FEED_URL', htmlspecialchars($feed_self_url), true);

			$tpl->setVariable('SELF_URL', htmlspecialchars(get_self_url_prefix()), true);
			while ($line = $result->fetch()) {

				$line["content_preview"] = sanitize(truncate_string(strip_tags($line["content"]), 100, '...'));

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_QUERY_HEADLINES) as $p) {
					$line = $p->hook_query_headlines($line);
				}

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_ARTICLE_EXPORT_FEED) as $p) {
					$line = $p->hook_article_export_feed($line, $feed, $is_cat);
				}

				$tpl->setVariable('ARTICLE_ID',
					htmlspecialchars($orig_guid ? $line['link'] :
							$this->make_article_tag_uri($line['id'], $line['date_entered'])), true);
				$tpl->setVariable('ARTICLE_LINK', htmlspecialchars($line['link']), true);
				$tpl->setVariable('ARTICLE_TITLE', htmlspecialchars($line['title']), true);
				$tpl->setVariable('ARTICLE_EXCERPT', $line["content_preview"], true);

				$content = sanitize($line["content"], false, $owner_uid,
					$feed_site_url, false, $line["id"]);

				if ($line['note']) {
					$content = "<div style=\"$note_style\">Article note: " . $line['note'] . "</div>" .
						$content;
					$tpl->setVariable('ARTICLE_NOTE', htmlspecialchars($line['note']), true);
				}

				$tpl->setVariable('ARTICLE_CONTENT', $content, true);

				$tpl->setVariable('ARTICLE_UPDATED_ATOM',
					date('c', strtotime($line["updated"])), true);
				$tpl->setVariable('ARTICLE_UPDATED_RFC822',
					date(DATE_RFC822, strtotime($line["updated"])), true);

				$tpl->setVariable('ARTICLE_AUTHOR', htmlspecialchars($line['author']), true);

				$tpl->setVariable('ARTICLE_SOURCE_LINK', htmlspecialchars($line['site_url'] ? $line["site_url"] : get_self_url_prefix()), true);
				$tpl->setVariable('ARTICLE_SOURCE_TITLE', htmlspecialchars($line['feed_title'] ? $line['feed_title'] : $feed_title), true);

				$tags = Article::get_article_tags($line["id"], $owner_uid);

				foreach ($tags as $tag) {
					$tpl->setVariable('ARTICLE_CATEGORY', htmlspecialchars($tag), true);
					$tpl->addBlock('category');
				}

				$enclosures = Article::get_article_enclosures($line["id"]);

				if (count($enclosures) > 0) {
					foreach ($enclosures as $e) {
						$type = htmlspecialchars($e['content_type']);
						$url = htmlspecialchars($e['content_url']);
						$length = $e['duration'] ? $e['duration'] : 1;

						$tpl->setVariable('ARTICLE_ENCLOSURE_URL', $url, true);
						$tpl->setVariable('ARTICLE_ENCLOSURE_TYPE', $type, true);
						$tpl->setVariable('ARTICLE_ENCLOSURE_LENGTH', $length, true);

						$tpl->addBlock('enclosure');
					}
				} else {
					$tpl->setVariable('ARTICLE_ENCLOSURE_URL', null, true);
					$tpl->setVariable('ARTICLE_ENCLOSURE_TYPE', null, true);
					$tpl->setVariable('ARTICLE_ENCLOSURE_LENGTH', null, true);
				}

				$tpl->setVariable('ARTICLE_OG_IMAGE',
                        $this->get_article_image($enclosures, $line['content'], $feed_site_url), true);

				$tpl->addBlock('entry');
			}

			$tmp = "";

			$tpl->addBlock('feed');
			$tpl->generateOutputToString($tmp);

			if (@!clean($_REQUEST["noxml"])) {
				header("Content-Type: text/xml; charset=utf-8");
			} else {
				header("Content-Type: text/plain; charset=utf-8");
			}

			print $tmp;
		} else if ($format == 'json') {

			$feed = array();

			$feed['title'] = $feed_title;
			$feed['version'] = VERSION;
			$feed['feed_url'] = $feed_self_url;

			$feed['self_url'] = get_self_url_prefix();

			$feed['articles'] = array();

			while ($line = $result->fetch()) {

				$line["content_preview"] = sanitize(truncate_string(strip_tags($line["content_preview"]), 100, '...'));

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_QUERY_HEADLINES) as $p) {
					$line = $p->hook_query_headlines($line, 100);
				}

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_ARTICLE_EXPORT_FEED) as $p) {
					$line = $p->hook_article_export_feed($line, $feed, $is_cat);
				}

				$article = array();

				$article['id'] = $line['link'];
				$article['link']	= $line['link'];
				$article['title'] = $line['title'];
				$article['excerpt'] = $line["content_preview"];
				$article['content'] = sanitize($line["content"], false, $owner_uid, $feed_site_url, false, $line["id"]);
				$article['updated'] = date('c', strtotime($line["updated"]));

				if ($line['note']) $article['note'] = $line['note'];
				if ($article['author']) $article['author'] = $line['author'];

				$tags = Article::get_article_tags($line["id"], $owner_uid);

				if (count($tags) > 0) {
					$article['tags'] = array();

					foreach ($tags as $tag) {
						array_push($article['tags'], $tag);
					}
				}

				$enclosures = Article::get_article_enclosures($line["id"]);

				if (count($enclosures) > 0) {
					$article['enclosures'] = array();

					foreach ($enclosures as $e) {
						$type = $e['content_type'];
						$url = $e['content_url'];
						$length = $e['duration'];

						array_push($article['enclosures'], array("url" => $url, "type" => $type, "length" => $length));
					}
				}

				array_push($feed['articles'], $article);
			}

			header("Content-Type: text/json; charset=utf-8");
			print json_encode($feed);

		} else {
			header("Content-Type: text/plain; charset=utf-8");
			print json_encode(array("error" => array("message" => "Unknown format")));
		}
	}

	function getUnread() {
		$login = clean($_REQUEST["login"]);
		$fresh = clean($_REQUEST["fresh"]) == "1";

		$sth = $this->pdo->prepare("SELECT id FROM ttrss_users WHERE login = ?");
		$sth->execute([$login]);

		if ($row = $sth->fetch()) {
			$uid = $row["id"];

			print Feeds::getGlobalUnread($uid);

			if ($fresh) {
				print ";";
				print Feeds::getFeedArticles(-3, false, true, $uid);
			}

		} else {
			print "-1;User not found";
		}
	}

	function getProfiles() {
		$login = clean($_REQUEST["login"]);
		$rv = [];

		if ($login) {
			$sth = $this->pdo->prepare("SELECT ttrss_settings_profiles.* FROM ttrss_settings_profiles,ttrss_users
			WHERE ttrss_users.id = ttrss_settings_profiles.owner_uid AND login = ? ORDER BY title");
			$sth->execute([$login]);

			$rv = [ [ "value" => 0, "label" => __("Default profile") ] ];

			while ($line = $sth->fetch()) {
				$id = $line["id"];
				$title = $line["title"];

				array_push($rv, [ "label" => $title, "value" => $id ]);
			}
	    }

		print json_encode($rv);
	}

	function logout() {
		logout_user();
		header("Location: index.php");
	}

	function share() {
		$uuid = clean($_REQUEST["key"]);

		$sth = $this->pdo->prepare("SELECT ref_id, owner_uid FROM ttrss_user_entries WHERE
			uuid = ?");
		$sth->execute([$uuid]);

		if ($row = $sth->fetch()) {
			header("Content-Type: text/html");

			$id = $row["ref_id"];
			$owner_uid = $row["owner_uid"];

			print $this->format_article($id, $owner_uid);

		} else {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			print "Article not found.";
		}

	}

	private function get_article_image($enclosures, $content, $site_url) {
	    $og_image = false;

		foreach ($enclosures as $enc) {
			if (strpos($enc["content_type"], "image/") !== FALSE) {
				return rewrite_relative_url($site_url, $enc["content_url"]);
			}
		}

		if (!$og_image) {
			$tmpdoc = new DOMDocument();

			if (@$tmpdoc->loadHTML('<?xml encoding="UTF-8">' . mb_substr($content, 0, 131070))) {
				$tmpxpath = new DOMXPath($tmpdoc);
				$imgs = $tmpxpath->query("//img");

				foreach ($imgs as $img) {
					$src = $img->getAttribute("src");

					if (mb_strpos($src, "data:") !== 0)
						return rewrite_relative_url($site_url, $src);
				}
			}
		}

		return false;
    }

	private function format_article($id, $owner_uid) {

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT id,title,link,content,feed_id,comments,int_id,lang,
			".SUBSTRING_FOR_DATE."(updated,1,16) as updated,
			(SELECT site_url FROM ttrss_feeds WHERE id = feed_id) as site_url,
			(SELECT title FROM ttrss_feeds WHERE id = feed_id) as feed_title,
			(SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) as hide_images,
			(SELECT always_display_enclosures FROM ttrss_feeds WHERE id = feed_id) as always_display_enclosures,
			num_comments,
			tag_cache,
			author,
			guid,
			orig_feed_id,
			note
			FROM ttrss_entries,ttrss_user_entries
			WHERE	id = ? AND ref_id = id AND owner_uid = ?");
		$sth->execute([$id, $owner_uid]);

		$rv = '';

		if ($line = $sth->fetch()) {

			$line["tags"] = Article::get_article_tags($id, $owner_uid, $line["tag_cache"]);
			unset($line["tag_cache"]);

			$line["content"] = sanitize($line["content"],
				$line['hide_images'],
				$owner_uid, $line["site_url"], false, $line["id"]);

			foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_RENDER_ARTICLE) as $p) {
				$line = $p->hook_render_article($line);
			}

			$line['content'] = rewrite_cached_urls($line['content']);

			$enclosures = Article::get_article_enclosures($line["id"]);

            header("Content-Type: text/html");

            $rv .= "<!DOCTYPE html>
                    <html><head>
                    <meta http-equiv='Content-Type' content='text/html; charset=utf-8'/>
                    <title>".$line["title"]."</title>".
                    stylesheet_tag("css/default.css")."
                    <link rel='shortcut icon' type='image/png' href='images/favicon.png'>
                    <link rel='icon' type='image/png' sizes='72x72' href='images/favicon-72px.png'>";

            $rv .= "<meta property='og:title' content=\"".htmlspecialchars(html_entity_decode($line["title"], ENT_NOQUOTES | ENT_HTML401))."\"/>\n";
            $rv .= "<meta property='og:description' content=\"".
                htmlspecialchars(
                	truncate_string(
                		preg_replace("/[\r\n\t]/", "",
							preg_replace("/ {1,}/", " ",
								strip_tags(html_entity_decode($line["content"], ENT_NOQUOTES | ENT_HTML401))
							)
					), 500, "...")
				)."\"/>\n";

            $rv .= "</head>";

            $og_image = $this->get_article_image($enclosures, $line['content'], $line["site_url"]);

            if ($og_image) {
                $rv .= "<meta property='og:image' content=\"" . htmlspecialchars($og_image) . "\"/>";
            }

            $rv .= "<body class='flat ttrss_utility ttrss_zoom'>";
            $rv .= "<div class='container'>";

			if ($line["link"]) {
				$rv .= "<h1><a target='_blank' rel='noopener noreferrer'
					title=\"".htmlspecialchars($line['title'])."\"
					href=\"" .htmlspecialchars($line["link"]) . "\">" .	$line["title"] . "</a></h1>";
			} else {
				$rv .= "<h1>" . $line["title"] . "</h1>";
			}

			$rv .= "<div class='content post'>";

			/* header */

			$rv .= "<div class='header'>";
			$rv .= "<div class='row'>"; # row

			//$entry_author = $line["author"] ? " - " . $line["author"] : "";
			$parsed_updated = make_local_datetime($line["updated"], true,
				$owner_uid, true);

			$rv .= "<div>".$line['author']."</div>";
            $rv .= "<div>$parsed_updated</div>";

			$rv .= "</div>"; # row

			$rv .= "</div>"; # header

			/* content */

			$lang = $line['lang'] ? $line['lang'] : "en";
			$rv .= "<div class='content' lang='$lang'>";

			/* content body */

			$rv .= $line["content"];

            $rv .= Article::format_article_enclosures($id,
                $line["always_display_enclosures"],
                $line["content"],
                $line["hide_images"]);

			$rv .= "</div>"; # content

			$rv .= "</div>"; # post

		}

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_FORMAT_ARTICLE) as $p) {
			$rv = $p->hook_format_article($rv, $line, true);
		}

		return $rv;

	}

	function rss() {
		$feed = clean($_REQUEST["id"]);
		$key = clean($_REQUEST["key"]);
		$is_cat = clean($_REQUEST["is_cat"]);
		$limit = (int)clean($_REQUEST["limit"]);
		$offset = (int)clean($_REQUEST["offset"]);

		$search = clean($_REQUEST["q"]);
		$view_mode = clean($_REQUEST["view-mode"]);
		$order = clean($_REQUEST["order"]);
		$start_ts = clean($_REQUEST["ts"]);

		$format = clean($_REQUEST['format']);
		$orig_guid = clean($_REQUEST["orig_guid"]);

		if (!$format) $format = 'atom';

		if (SINGLE_USER_MODE) {
			authenticate_user("admin", null);
		}

		$owner_id = false;

		if ($key) {
			$sth = $this->pdo->prepare("SELECT owner_uid FROM
				ttrss_access_keys WHERE access_key = ? AND feed_id = ?");
			$sth->execute([$key, $feed]);

			if ($row = $sth->fetch())
				$owner_id = $row["owner_uid"];
		}

		if ($owner_id) {
			$this->generate_syndicated_feed($owner_id, $feed, $is_cat, $limit,
				$offset, $search, $view_mode, $format, $order, $orig_guid, $start_ts);
		} else {
			header('HTTP/1.1 403 Forbidden');
		}
	}

	function updateTask() {
		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_UPDATE_TASK, "hook_update_task", false);
	}

	function housekeepingTask() {
		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_HOUSE_KEEPING, "hook_house_keeping", false);
	}

	function globalUpdateFeeds() {
		RPC::updaterandomfeed_real();

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_UPDATE_TASK, "hook_update_task", false);
	}

	function sharepopup() {
		if (SINGLE_USER_MODE) {
			login_sequence();
		}

		header('Content-Type: text/html; charset=utf-8');
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title><?php echo __("Share with Tiny Tiny RSS") ?> ?></title>
			<?php
			echo stylesheet_tag("css/default.css");
			echo javascript_tag("lib/prototype.js");
			echo javascript_tag("lib/dojo/dojo.js");
			echo javascript_tag("lib/dojo/tt-rss-layer.js");
			echo javascript_tag("lib/scriptaculous/scriptaculous.js?load=effects,controls")
			?>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<link rel="shortcut icon" type="image/png" href="images/favicon.png">
			<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png">
		</head>
		<body class='flat ttrss_utility share_popup'>
		<script type="text/javascript">
			require(['dojo/parser', "dojo/ready", 'dijit/form/Button','dijit/form/CheckBox', 'dijit/form/Form',
				'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'],function(parser, ready){
				ready(function() {
					parser.parse();

					new Ajax.Autocompleter('labels_value', 'labels_choices',
						"backend.php?op=rpc&method=completeLabels",
						{ tokens: ',', paramName: "search" });
				});
			});
		</script>
		<div class="content">

		<?php

		$action = clean($_REQUEST["action"]);

		if ($_SESSION["uid"]) {

			if ($action == 'share') {

				$title = strip_tags(clean($_REQUEST["title"]));
				$url = strip_tags(clean($_REQUEST["url"]));
				$content = strip_tags(clean($_REQUEST["content"]));
				$labels = strip_tags(clean($_REQUEST["labels"]));

				Article::create_published_article($title, $url, $content, $labels,
					$_SESSION["uid"]);

				print "<script type='text/javascript'>";
				print "window.close();";
				print "</script>";

			} else {
				$title = htmlspecialchars(clean($_REQUEST["title"]));
				$url = htmlspecialchars(clean($_REQUEST["url"]));

				?>
				<form id='share_form' name='share_form'>

					<input type="hidden" name="op" value="sharepopup">
					<input type="hidden" name="action" value="share">

					<fieldset>
						<label><?php echo __("Title:") ?></label>
						<input style='width : 270px' dojoType='dijit.form.TextBox' name='title' value="<?php echo $title ?>">
					</fieldset>

					<fieldset>
						<label><?php echo __("URL:") ?></label>
						<input style='width : 270px' name='url' dojoType='dijit.form.TextBox' value="<?php echo $url ?>">
					</fieldset>

					<fieldset>
						<label><?php echo __("Content:") ?></label>
						<input style='width : 270px' name='content' dojoType='dijit.form.TextBox' value="">
					</fieldset>

					<fieldset>
						<label><?php echo __("Labels:") ?></label>
						<input style='width : 270px' name='labels' dojoType='dijit.form.TextBox' id="labels_value"
						   placeholder='Alpha, Beta, Gamma' value="">
						<div class="autocomplete" id="labels_choices"
							 style="display : block"></div>
					</fieldset>

					<hr/>

					<fieldset>
						<button dojoType='dijit.form.Button' class="alt-primary" type="submit"><?php echo __('Share') ?></button>
						<button dojoType='dijit.form.Button' onclick="return window.close()"><?php echo __('Cancel') ?></button>
						<span class="text-muted small"><?php echo __("Shared article will appear in the Published feed.") ?></span>
					</fieldset>

				</form>
				<?php

			}

		} else {

			$return = urlencode(make_self_url());

			?>

			<?php print_error("Not logged in"); ?>

			<form action="public.php?return=<?php echo $return ?>" method="post">

				<input type="hidden" name="op" value="login">

				<fieldset>
					<label><?php echo __("Login:") ?></label>
					<input name="login" id="login" dojoType="dijit.form.TextBox" type="text"
						   onchange="fetchProfiles()" onfocus="fetchProfiles()" onblur="fetchProfiles()"
						   required="1" value="<?php echo $_SESSION["fake_login"] ?>" />
				</fieldset>

				<fieldset>
					<label><?php echo __("Password:") ?></label>

					<input type="password" name="password" required="1"
						   dojoType="dijit.form.TextBox"
						   class="input input-text"
						   value="<?php echo $_SESSION["fake_password"] ?>"/>
				</fieldset>

				<hr/>

				<fieldset>
					<label> </label>

					<button dojoType="dijit.form.Button" type="submit" class="alt-primary"><?php echo __('Log in') ?></button>
				</fieldset>

			</form>
			<?php
		}

		print "</div></body></html>";
	}

	function login() {
		if (!SINGLE_USER_MODE) {

			$login = clean($_POST["login"]);
			$password = clean($_POST["password"]);
			$remember_me = clean($_POST["remember_me"]);

			if ($remember_me) {
				session_set_cookie_params(SESSION_COOKIE_LIFETIME);
			} else {
				session_set_cookie_params(0);
			}

			if (authenticate_user($login, $password)) {
				$_POST["password"] = "";

				if (get_schema_version() >= 120) {
					$_SESSION["language"] = get_pref("USER_LANGUAGE", $_SESSION["uid"]);
				}

				$_SESSION["ref_schema_version"] = get_schema_version(true);
				$_SESSION["bw_limit"] = !!clean($_POST["bw_limit"]);

				if (clean($_POST["profile"])) {

					$profile = (int) clean($_POST["profile"]);

					$sth = $this->pdo->prepare("SELECT id FROM ttrss_settings_profiles
						WHERE id = ? AND owner_uid = ?");
					$sth->execute([$profile, $_SESSION['uid']]);

					if ($sth->fetch()) {
						$_SESSION["profile"] = $profile;
 					} else {
					    $_SESSION["profile"] = null;
                    }
				}
			} else {

				// start an empty session to deliver login error message
				@session_start();

				if (!isset($_SESSION["login_error_msg"]))
					$_SESSION["login_error_msg"] = __("Incorrect username or password");

				user_error("Failed login attempt for $login from {$_SERVER['REMOTE_ADDR']}", E_USER_WARNING);
			}

			$return = clean($_REQUEST['return']);

			if ($_REQUEST['return'] && mb_strpos($return, SELF_URL_PATH) === 0) {
				header("Location: " . clean($_REQUEST['return']));
			} else {
				header("Location: " . get_self_url_prefix());
			}
		}
	}

	/* function subtest() {
		header("Content-type: text/plain; charset=utf-8");

		$url = clean($_REQUEST["url"]);

		print "$url\n\n";


		print_r(get_feeds_from_html($url, fetch_file_contents($url)));

	} */

	function subscribe() {
		if (SINGLE_USER_MODE) {
			login_sequence();
		}

		if ($_SESSION["uid"]) {

			$feed_url = trim(clean($_REQUEST["feed_url"]));

			header('Content-Type: text/html; charset=utf-8');
			?>
			<!DOCTYPE html>
			<html>
			<head>
				<title>Tiny Tiny RSS</title>
				<?php
					echo stylesheet_tag("css/default.css");
					echo javascript_tag("lib/prototype.js");
					echo javascript_tag("lib/dojo/dojo.js");
					echo javascript_tag("lib/dojo/tt-rss-layer.js");
				?>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
				<link rel="shortcut icon" type="image/png" href="images/favicon.png">
				<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png">
			</head>
			<body class='flat ttrss_utility'>
			<script type="text/javascript">
				require(['dojo/parser', "dojo/ready", 'dijit/form/Button','dijit/form/CheckBox', 'dijit/form/Form',
					'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'],function(parser, ready){
					ready(function() {
						parser.parse();
					});
				});
			</script>
			<div class="container">
			<h1><?php echo __("Subscribe to feed...") ?></h1>
			<div class='content'>
			<?php

			if (!$feed_url) {
				?>
				<form method="post">
					<input type="hidden" name="op" value="subscribe">
					<fieldset>
						<label>Feed or site URL:</label>
						<input style="width: 300px" dojoType="dijit.form.ValidationTextBox" required="1" name="feed_url">
					</fieldset>

					<button class="alt-primary" dojoType="dijit.form.Button" type="submit">
						<?php echo __("Subscribe") ?>
					</button>

					<a href="index.php"><?php echo __("Return to Tiny Tiny RSS") ?></a>
				</form>
				<?php
			} else {

				$rc = Feeds::subscribe_to_feed($feed_url);
				$feed_urls = false;

				switch ($rc['code']) {
					case 0:
						print_warning(T_sprintf("Already subscribed to <b>%s</b>.", $feed_url));
						break;
					case 1:
						print_notice(T_sprintf("Subscribed to <b>%s</b>.", $feed_url));
						break;
					case 2:
						print_error(T_sprintf("Could not subscribe to <b>%s</b>.", $feed_url));
						break;
					case 3:
						print_error(T_sprintf("No feeds found in <b>%s</b>.", $feed_url));
						break;
					case 4:
						$feed_urls = $rc["feeds"];
						break;
					case 5:
						print_error(T_sprintf("Could not subscribe to <b>%s</b>.<br>Can't download the Feed URL.", $feed_url));
						break;
				}

				if ($feed_urls) {

					print "<form action='public.php'>";
					print "<input type='hidden' name='op' value='subscribe'>";

					print "<fieldset>";
					print "<label style='display : inline'>" . __("Multiple feed URLs found:") . "</label>";
					print "<select name='feed_url' dojoType='dijit.form.Select'>";

					foreach ($feed_urls as $url => $name) {
						$url = htmlspecialchars($url);
						$name = htmlspecialchars($name);

						print "<option value=\"$url\">$name</option>";
					}

					print "</select>";
					print "</fieldset>";

					print "<button class='alt-primary' dojoType='dijit.form.Button' type='submit'>".__("Subscribe to selected feed")."</button>";
					print "<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>";

					print "</form>";
				}

				$tp_uri = get_self_url_prefix() . "/prefs.php";

				if ($rc['code'] <= 2){
					$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE
					feed_url = ? AND owner_uid = ?");
					$sth->execute([$feed_url, $_SESSION['uid']]);
					$row = $sth->fetch();

					$feed_id = $row["id"];
				} else {
					$feed_id = 0;
				}

				if ($feed_id) {
					print "<form method='GET' action=\"$tp_uri\">
					<input type='hidden' name='tab' value='feedConfig'>
					<input type='hidden' name='method' value='editfeed'>
					<input type='hidden' name='methodparam' value='$feed_id'>
					<button dojoType='dijit.form.Button' class='alt-info' type='submit'>".__("Edit subscription options")."</button>
					<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>
					</form>";
				}
			}

			print "</div></div></body></html>";

		} else {
			render_login_form();
		}
	}

	function index() {
		header("Content-Type: text/plain");
		print error_json(13);
	}

	function forgotpass() {
		startup_gettext();
		session_start();

		@$hash = clean($_REQUEST["hash"]);

		header('Content-Type: text/html; charset=utf-8');
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title>Tiny Tiny RSS</title>
			<link rel="shortcut icon" type="image/png" href="images/favicon.png">
			<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png">
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<?php
				echo stylesheet_tag("css/default.css");
				echo javascript_tag("lib/prototype.js");
				echo javascript_tag("lib/dojo/dojo.js");
				echo javascript_tag("lib/dojo/tt-rss-layer.js");
			?>
		</head>
		<body class='flat ttrss_utility'>
		<div class='container'>

		<script type="text/javascript">
		require(['dojo/parser', "dojo/ready", 'dijit/form/Button','dijit/form/CheckBox', 'dijit/form/Form',
    		'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'],function(parser, ready){
			ready(function() {
				parser.parse();
			});
		});
		</script>
		<?php

		print "<h1>".__("Password recovery")."</h1>";
		print "<div class='content'>";

		@$method = clean($_POST['method']);

		if ($hash) {
			$login = clean($_REQUEST["login"]);

			if ($login) {
				$sth = $this->pdo->prepare("SELECT id, resetpass_token FROM ttrss_users
					WHERE login = ?");
				$sth->execute([$login]);

				if ($row = $sth->fetch()) {
					$id = $row["id"];
					$resetpass_token_full = $row["resetpass_token"];
					list($timestamp, $resetpass_token) = explode(":", $resetpass_token_full);

					if ($timestamp && $resetpass_token &&
						$timestamp >= time() - 15*60*60 &&
						$resetpass_token == $hash) {

							$sth = $this->pdo->prepare("UPDATE ttrss_users SET resetpass_token = NULL
								WHERE id = ?");
							$sth->execute([$id]);

							Pref_Users::resetUserPassword($id, true);

							print "<p>"."Completed."."</p>";

					} else {
						print_error("Some of the information provided is missing or incorrect.");
					}
				} else {
					print_error("Some of the information provided is missing or incorrect.");
				}
			} else {
				print_error("Some of the information provided is missing or incorrect.");
			}

			print "<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>";

		} else if (!$method) {
			print_notice(__("You will need to provide valid account name and email. Password reset link will be sent to your email address."));

			print "<form method='POST' action='public.php'>
				<input type='hidden' name='method' value='do'>
				<input type='hidden' name='op' value='forgotpass'>

				<fieldset>
				<label>".__("Login:")."</label>
				<input dojoType='dijit.form.TextBox' type='text' name='login' value='' required>
				</fieldset>

				<fieldset>
				<label>".__("Email:")."</label>
				<input dojoType='dijit.form.TextBox' type='email' name='email' value='' required>
				</fieldset>";

			$_SESSION["pwdreset:testvalue1"] = rand(1,10);
			$_SESSION["pwdreset:testvalue2"] = rand(1,10);

			print "<fieldset>
				<label>".T_sprintf("How much is %d + %d:", $_SESSION["pwdreset:testvalue1"], $_SESSION["pwdreset:testvalue2"])."</label>
				<input dojoType='dijit.form.TextBox' type='text' name='test' value='' required>
				</fieldset>

				<hr/>
				<fieldset>
				<button dojoType='dijit.form.Button' type='submit' class='alt-danger'>".__("Reset password")."</button>
				<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>
				</fieldset>

				</form>";
		} else if ($method == 'do') {

			$login = clean($_POST["login"]);
			$email = clean($_POST["email"]);
			$test = clean($_POST["test"]);

			if ($test != ($_SESSION["pwdreset:testvalue1"] + $_SESSION["pwdreset:testvalue2"]) || !$email || !$login) {
				print_error(__('Some of the required form parameters are missing or incorrect.'));

				print "<form method='GET' action='public.php'>
					<input type='hidden' name='op' value='forgotpass'>
					<button dojoType='dijit.form.Button' type='submit' class='alt-primary'>".__("Go back")."</button>
					</form>";

			} else {

				// prevent submitting this form multiple times
				$_SESSION["pwdreset:testvalue1"] = rand(1, 1000);
				$_SESSION["pwdreset:testvalue2"] = rand(1, 1000);

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_users
					WHERE login = ? AND email = ?");
				$sth->execute([$login, $email]);

				if ($row = $sth->fetch()) {
					print_notice("Password reset instructions are being sent to your email address.");

					$id = $row["id"];

					if ($id) {
						$resetpass_token = sha1(get_random_bytes(128));
						$resetpass_link = get_self_url_prefix() . "/public.php?op=forgotpass&hash=" . $resetpass_token .
							"&login=" . urlencode($login);

						require_once "lib/MiniTemplator.class.php";

						$tpl = new MiniTemplator;

						$tpl->readTemplateFromFile("templates/resetpass_link_template.txt");

						$tpl->setVariable('LOGIN', $login);
						$tpl->setVariable('RESETPASS_LINK', $resetpass_link);

						$tpl->addBlock('message');

						$message = "";

						$tpl->generateOutputToString($message);

						$mailer = new Mailer();

						$rc = $mailer->mail(["to_name" => $login,
							"to_address" => $email,
							"subject" => __("[tt-rss] Password reset request"),
							"message" => $message]);

						if (!$rc) print_error($mailer->error());

						$resetpass_token_full = time() . ":" . $resetpass_token;

						$sth = $this->pdo->prepare("UPDATE ttrss_users
							SET resetpass_token = ?
							WHERE login = ? AND email = ?");

						$sth->execute([$resetpass_token_full, $login, $email]);

					} else {
						print_error("User ID not found.");
					}

					print "<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>";

				} else {
					print_error(__("Sorry, login and email combination not found."));

					print "<form method='GET' action='public.php'>
						<input type='hidden' name='op' value='forgotpass'>
						<button dojoType='dijit.form.Button' type='submit'>".__("Go back")."</button>
						</form>";

				}
			}

		}

		print "</div>";
		print "</div>";
		print "</body>";
		print "</html>";

	}

	function dbupdate() {
		startup_gettext();

		if (!SINGLE_USER_MODE && $_SESSION["access_level"] < 10) {
			$_SESSION["login_error_msg"] = __("Your access level is insufficient to run this script.");
			render_login_form();
			exit;
		}

		?>
		<!DOCTYPE html>
		<html>
			<head>
			<title>Database Updater</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<?php echo stylesheet_tag("css/default.css") ?>
			<link rel="shortcut icon" type="image/png" href="images/favicon.png">
			<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png">
			<?php
				echo stylesheet_tag("css/default.css");
				echo javascript_tag("lib/prototype.js");
				echo javascript_tag("lib/dojo/dojo.js");
				echo javascript_tag("lib/dojo/tt-rss-layer.js");
			?>
			<style type="text/css">
				span.ok { color : #009000; font-weight : bold; }
				span.err { color : #ff0000; font-weight : bold; }
			</style>
		</head>
		<body class="flat ttrss_utility">

			<script type="text/javascript">
				require(['dojo/parser', "dojo/ready", 'dijit/form/Button','dijit/form/CheckBox', 'dijit/form/Form',
					'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'],function(parser, ready){
					ready(function() {
						parser.parse();
					});
				});

				function confirmOP() {
					return confirm("Update the database?");
				}
			</script>

			<div class="container">
			<h1><?php echo __("Database Updater") ?></h1>

			<div class="content">

			<?php
				@$op = clean($_REQUEST["subop"]);
				$updater = new DbUpdater(Db::pdo(), DB_TYPE, SCHEMA_VERSION);

				if ($op == "performupdate") {
					if ($updater->isUpdateRequired()) {

						print "<h2>" . T_sprintf("Performing updates to version %d", SCHEMA_VERSION) . "</h2>";

						for ($i = $updater->getSchemaVersion() + 1; $i <= SCHEMA_VERSION; $i++) {
							print "<ul>";

							print "<li class='text-info'>" . T_sprintf("Updating to version %d", $i) . "</li>";

							print "<li>";
							$result = $updater->performUpdateTo($i, true);
							print "</li>";

							if (!$result) {
								print "</ul>";

								print_error("One of the updates failed. Either retry the process or perform updates manually.");

								print "<form method='POST'>
									<input type='hidden' name='subop' value='performupdate'>
									<button type='submit' dojoType='dijit.form.Button' class='alt-danger' onclick='return confirmOP()'>".__("Try again")."</button>
									<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>
								</form>";

								return;
							} else {
								print "<li class='text-success'>" . __("Completed.") . "</li>";
								print "</ul>";
							}
						}

						print_notice("Your Tiny Tiny RSS database is now updated to the latest version.");

						print "<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>";

					} else {
						print_notice("Tiny Tiny RSS database is up to date.");

						print "<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>";
					}
				} else {
					if ($updater->isUpdateRequired()) {

						print "<h2>".T_sprintf("Tiny Tiny RSS database needs update to the latest version (%d to %d).",
							$updater->getSchemaVersion(), SCHEMA_VERSION)."</h2>";

						if (DB_TYPE == "mysql") {
							print_error("<strong>READ THIS:</strong> Due to MySQL limitations, your database is not completely protected while updating. ".
								"Errors may put it in an inconsistent state requiring manual rollback. <strong>BACKUP YOUR DATABASE BEFORE CONTINUING.</strong>");
						} else {
							print_warning("Please backup your database before proceeding.");
						}

						print "<form method='POST'>
							<input type='hidden' name='subop' value='performupdate'>
							<button type='submit' dojoType='dijit.form.Button' class='alt-danger' onclick='return confirmOP()'>".__("Perform updates")."</button>
						</form>";

					} else {

						print_notice("Tiny Tiny RSS database is up to date.");

						print "<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>";
					}
				}
			?>

			</div>
			</div>
			</body>
			</html>
		<?php
	}

	function cached_url() {
		@$req_filename = basename($_GET['hash']);

		// we don't need an extension to find the file, hash is a complete URL
		$hash = preg_replace("/\.[^\.]*$/", "", $req_filename);

		if ($hash) {

			$filename = CACHE_DIR . '/images/' . $hash;

			if (file_exists($filename)) {
				header("Content-Disposition: inline; filename=\"$req_filename\"");

				send_local_file($filename);

			} else {
				header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
				echo "File not found.";
			}
		}
	}

	private function make_article_tag_uri($id, $timestamp) {

		$timestamp = date("Y-m-d", strtotime($timestamp));

		return "tag:" . parse_url(get_self_url_prefix(), PHP_URL_HOST) . ",$timestamp:/$id";
	}

	// this should be used very carefully because this endpoint is exposed to unauthenticated users
	// plugin data is not loaded because there's no user context and owner_uid/session may or may not be available
	// in general, don't do anything user-related in here and do not modify $_SESSION
	public function pluginhandler() {
		$host = new PluginHost();

		$plugin = basename(clean($_REQUEST["plugin"]));
		$method = clean($_REQUEST["pmethod"]);

		$host->load($plugin, PluginHost::KIND_USER, 0);
		$host->load_data();

		$pclass = $host->get_plugin($plugin);

		if ($pclass) {
			if (method_exists($pclass, $method)) {
				if ($pclass->is_public_method($method)) {
					$pclass->$method();
				} else {
					header("Content-Type: text/json");
					print error_json(6);
				}
			} else {
				header("Content-Type: text/json");
				print error_json(13);
			}
		} else {
			header("Content-Type: text/json");
			print error_json(14);
		}
	}
}
?>

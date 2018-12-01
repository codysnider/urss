/* global dijit, __ */

let global_unread = -1;
let _widescreen_mode = false;
let _rpc_seq = 0;
let _active_feed_id = 0;
let _active_feed_is_cat = false;
let hotkey_actions = {};
let _headlines_scroll_timeout = false;

function next_seq() {
	_rpc_seq += 1;
	return _rpc_seq;
}

function get_seq() {
	return _rpc_seq;
}

function activeFeedIsCat() {
	return !!_active_feed_is_cat;
}

function getActiveFeedId() {
	return _active_feed_id;
}

function setActiveFeedId(id, is_cat) {
	hash_set('f', id);
	hash_set('c', is_cat ? 1 : 0);

	_active_feed_id = id;
	_active_feed_is_cat = is_cat;

	$("headlines-frame").setAttribute("feed-id", id);
	$("headlines-frame").setAttribute("is-cat", is_cat ? 1 : 0);

	selectFeed(id, is_cat);

	PluginHost.run(PluginHost.HOOK_FEED_SET_ACTIVE, _active_article_id);
}


function updateFeedList() {
	try {
		Element.show("feedlistLoading");

		resetCounterCache();

		if (dijit.byId("feedTree")) {
			dijit.byId("feedTree").destroyRecursive();
		}

		const store = new dojo.data.ItemFileWriteStore({
			url: "backend.php?op=pref_feeds&method=getfeedtree&mode=2"
		});

		const treeModel = new fox.FeedStoreModel({
			store: store,
			query: {
				"type": getInitParam('enable_feed_cats') == 1 ? "category" : "feed"
			},
			rootId: "root",
			rootLabel: "Feeds",
			childrenAttrs: ["items"]
		});

		const tree = new fox.FeedTree({
			model: treeModel,
			onClick: function (item, node) {
				const id = String(item.id);
				const is_cat = id.match("^CAT:");
				const feed = id.substr(id.indexOf(":") + 1);
				viewfeed({feed: feed, is_cat: is_cat});
				return false;
			},
			openOnClick: false,
			showRoot: false,
			persist: true,
			id: "feedTree",
		}, "feedTree");

		var tmph = dojo.connect(dijit.byId('feedMenu'), '_openMyself', function (event) {
			console.log(dijit.getEnclosingWidget(event.target));
			dojo.disconnect(tmph);
		});

		$("feeds-holder").appendChild(tree.domNode);

		var tmph = dojo.connect(tree, 'onLoad', function () {
			dojo.disconnect(tmph);
			Element.hide("feedlistLoading");

			try {
				feedlist_init();

				setLoadingProgress(25);
			} catch (e) {
				exception_error(e);
			}
		});

		tree.startup();
	} catch (e) {
		exception_error(e);
	}
}

function catchupAllFeeds() {

	const str = __("Mark all articles as read?");

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {

		notify_progress("Marking all feeds as read...");

		xhrPost("backend.php", {op: "feeds", method: "catchupAll"}, () => {
			request_counters(true);
			viewCurrentFeed();
		});

		global_unread = 0;
		updateTitle("");
	}
}

function viewCurrentFeed(method) {
	console.log("viewCurrentFeed: " + method);

	if (getActiveFeedId() != undefined) {
		viewfeed({feed: getActiveFeedId(), is_cat: activeFeedIsCat(), method: method});
	}
	return false; // block unneeded form submits
}

function timeout() {
	if (getInitParam("bw_limit") != "1") {
		request_counters(true);
		setTimeout(timeout, 60*1000);
	}
}

function search() {
	const query = "backend.php?op=feeds&method=search&param=" +
		param_escape(getActiveFeedId() + ":" + activeFeedIsCat());

	if (dijit.byId("searchDlg"))
		dijit.byId("searchDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "searchDlg",
		title: __("Search"),
		style: "width: 600px",
		execute: function() {
			if (this.validate()) {
				_search_query = this.attr('value');
				this.hide();
				viewCurrentFeed();
			}
		},
		href: query});

	dialog.show();
}

function updateTitle() {
	let tmp = "Tiny Tiny RSS";

	if (global_unread > 0) {
		tmp = "(" + global_unread + ") " + tmp;
	}

	document.title = tmp;
}

function genericSanityCheck() {
	setCookie("ttrss_test", "TEST");

	if (getCookie("ttrss_test") != "TEST") {
		return fatalError(2);
	}

	return true;
}


function init() {

	window.onerror = function(message, filename, lineno, colno, error) {
		report_error(message, filename, lineno, colno, error);
	};

	require(["dojo/_base/kernel",
			"dojo/ready",
			"dojo/parser",
			"dojo/_base/loader",
			"dojo/_base/html",
			"dojo/query",
			"dijit/ProgressBar",
			"dijit/ColorPalette",
			"dijit/Dialog",
			"dijit/form/Button",
			"dijit/form/ComboButton",
			"dijit/form/CheckBox",
			"dijit/form/DropDownButton",
			"dijit/form/FilteringSelect",
			"dijit/form/Form",
			"dijit/form/RadioButton",
			"dijit/form/Select",
			"dijit/form/MultiSelect",
			"dijit/form/SimpleTextarea",
			"dijit/form/TextBox",
			"dijit/form/ComboBox",
			"dijit/form/ValidationTextBox",
			"dijit/InlineEditBox",
			"dijit/layout/AccordionContainer",
			"dijit/layout/BorderContainer",
			"dijit/layout/ContentPane",
			"dijit/layout/TabContainer",
			"dijit/PopupMenuItem",
			"dijit/Menu",
			"dijit/Toolbar",
			"dijit/Tree",
			"dijit/tree/dndSource",
			"dijit/tree/ForestStoreModel",
			"dojo/data/ItemFileWriteStore",
			"fox/FeedStoreModel",
			"fox/FeedTree" ], function (dojo, ready, parser) {

			ready(function() {

				try {
					parser.parse();

					if (!genericSanityCheck())
						return false;

					setLoadingProgress(30);
					init_hotkey_actions();

					const a = document.createElement('audio');
					const hasAudio = !!a.canPlayType;
					const hasSandbox = "sandbox" in document.createElement("iframe");
					const hasMp3 = !!(a.canPlayType && a.canPlayType('audio/mpeg;').replace(/no/, ''));
					const clientTzOffset = new Date().getTimezoneOffset() * 60;

					const params = {
							op: "rpc", method: "sanityCheck", hasAudio: hasAudio,
							hasMp3: hasMp3,
							clientTzOffset: clientTzOffset,
							hasSandbox: hasSandbox
						};

					xhrPost("backend.php", params, (transport) => {
						try {
							backend_sanity_check_callback(transport);
						} catch (e) {
							console.error(e);
						}
					});

				} catch (e) {
					exception_error(e);
				}

			});


	});
}

function init_hotkey_actions() {
	hotkey_actions["next_feed"] = function() {
		const rv = dijit.byId("feedTree").getNextFeed(
			getActiveFeedId(), activeFeedIsCat());

		if (rv) viewfeed({feed: rv[0], is_cat: rv[1], delayed: true})
	};
	hotkey_actions["prev_feed"] = function() {
		const rv = dijit.byId("feedTree").getPreviousFeed(
			getActiveFeedId(), activeFeedIsCat());

		if (rv) viewfeed({feed: rv[0], is_cat: rv[1], delayed: true})
	};
	hotkey_actions["next_article"] = function() {
		moveToPost('next');
	};
	hotkey_actions["prev_article"] = function() {
		moveToPost('prev');
	};
	hotkey_actions["next_article_noscroll"] = function() {
		moveToPost('next', true);
	};
	hotkey_actions["prev_article_noscroll"] = function() {
		moveToPost('prev', true);
	};
	hotkey_actions["next_article_noexpand"] = function() {
		moveToPost('next', true, true);
	};
	hotkey_actions["prev_article_noexpand"] = function() {
		moveToPost('prev', true, true);
	};
	hotkey_actions["search_dialog"] = function() {
		search();
	};
	hotkey_actions["toggle_mark"] = function() {
		selectionToggleMarked();
	};
	hotkey_actions["toggle_publ"] = function() {
		selectionTogglePublished();
	};
	hotkey_actions["toggle_unread"] = function() {
		selectionToggleUnread({no_error: 1});
	};
	hotkey_actions["edit_tags"] = function() {
		const id = getActiveArticleId();
		if (id) {
			editArticleTags(id);
		}
	}
	hotkey_actions["open_in_new_window"] = function() {
		if (getActiveArticleId()) {
			openArticleInNewWindow(getActiveArticleId());
		}
	};
	hotkey_actions["catchup_below"] = function() {
		catchupRelativeToArticle(1);
	};
	hotkey_actions["catchup_above"] = function() {
		catchupRelativeToArticle(0);
	};
	hotkey_actions["article_scroll_down"] = function() {
		scrollArticle(40);
	};
	hotkey_actions["article_scroll_up"] = function() {
		scrollArticle(-40);
	};
	hotkey_actions["close_article"] = function() {
		if (isCombinedMode()) {
			cdmCollapseActive();
		} else {
			closeArticlePanel();
		}
	};
	hotkey_actions["email_article"] = function() {
		if (typeof emailArticle != "undefined") {
			emailArticle();
		} else if (typeof mailtoArticle != "undefined") {
			mailtoArticle();
		} else {
			alert(__("Please enable mail plugin first."));
		}
	};
	hotkey_actions["select_all"] = function() {
		selectArticles('all');
	};
	hotkey_actions["select_unread"] = function() {
		selectArticles('unread');
	};
	hotkey_actions["select_marked"] = function() {
		selectArticles('marked');
	};
	hotkey_actions["select_published"] = function() {
		selectArticles('published');
	};
	hotkey_actions["select_invert"] = function() {
		selectArticles('invert');
	};
	hotkey_actions["select_none"] = function() {
		selectArticles('none');
	};
	hotkey_actions["feed_refresh"] = function() {
		if (getActiveFeedId() != undefined) {
			viewfeed({feed: getActiveFeedId(), is_cat: activeFeedIsCat()});
			return;
		}
	};
	hotkey_actions["feed_unhide_read"] = function() {
		toggleDispRead();
	};
	hotkey_actions["feed_subscribe"] = function() {
		quickAddFeed();
	};
	hotkey_actions["feed_debug_update"] = function() {
		if (!activeFeedIsCat() && parseInt(getActiveFeedId()) > 0) {
			window.open("backend.php?op=feeds&method=update_debugger&feed_id=" + getActiveFeedId() +
				"&csrf_token=" + getInitParam("csrf_token"));
		} else {
			alert("You can't debug this kind of feed.");
		}
	};

	hotkey_actions["feed_debug_viewfeed"] = function() {
		viewfeed({feed: getActiveFeedId(), is_cat: activeFeedIsCat(), viewfeed_debug: true});
	};

	hotkey_actions["feed_edit"] = function() {
		if (activeFeedIsCat())
			alert(__("You can't edit this kind of feed."));
		else
			editFeed(getActiveFeedId());
	};
	hotkey_actions["feed_catchup"] = function() {
		if (getActiveFeedId() != undefined) {
			catchupCurrentFeed();
			return;
		}
	};
	hotkey_actions["feed_reverse"] = function() {
		reverseHeadlineOrder();
	};
	hotkey_actions["feed_toggle_vgroup"] = function() {
		xhrPost("backend.php", {op: "rpc", method: "togglepref", key: "VFEED_GROUP_BY_FEED"}, () => {
			viewCurrentFeed();
		})
	};
	hotkey_actions["catchup_all"] = function() {
		catchupAllFeeds();
	};
	hotkey_actions["cat_toggle_collapse"] = function() {
		if (activeFeedIsCat()) {
			dijit.byId("feedTree").collapseCat(getActiveFeedId());
			return;
		}
	};
	hotkey_actions["goto_all"] = function() {
		viewfeed({feed: -4});
	};
	hotkey_actions["goto_fresh"] = function() {
		viewfeed({feed: -3});
	};
	hotkey_actions["goto_marked"] = function() {
		viewfeed({feed: -1});
	};
	hotkey_actions["goto_published"] = function() {
		viewfeed({feed: -2});
	};
	hotkey_actions["goto_tagcloud"] = function() {
		displayDlg(__("Tag cloud"), "printTagCloud");
	};
	hotkey_actions["goto_prefs"] = function() {
		gotoPreferences();
	};
	hotkey_actions["select_article_cursor"] = function() {
		const id = getArticleUnderPointer();
		if (id) {
			const row = $("RROW-" + id);

			if (row) {
				const cb = dijit.getEnclosingWidget(
					row.select(".rchk")[0]);

				if (cb) {
					if (!row.hasClassName("active"))
						cb.attr("checked", !cb.attr("checked"));

					toggleSelectRowById(cb, "RROW-" + id);
					return false;
				}
			}
		}
	};
	hotkey_actions["create_label"] = function() {
		addLabel();
	};
	hotkey_actions["create_filter"] = function() {
		quickAddFilter();
	};
	hotkey_actions["collapse_sidebar"] = function() {
		collapse_feedlist();
	};
	hotkey_actions["toggle_embed_original"] = function() {
		if (typeof embedOriginalArticle != "undefined") {
			if (getActiveArticleId())
				embedOriginalArticle(getActiveArticleId());
		} else {
			alert(__("Please enable embed_original plugin first."));
		}
	};
	hotkey_actions["toggle_widescreen"] = function() {
		if (!isCombinedMode()) {
			_widescreen_mode = !_widescreen_mode;

			// reset stored sizes because geometry changed
			setCookie("ttrss_ci_width", 0);
			setCookie("ttrss_ci_height", 0);

			switchPanelMode(_widescreen_mode);
		} else {
			alert(__("Widescreen is not available in combined mode."));
		}
	};
	hotkey_actions["help_dialog"] = function() {
		helpDialog("main");
	};
	hotkey_actions["toggle_combined_mode"] = function() {
		notify_progress("Loading, please wait...");

		const value = isCombinedMode() ? "false" : "true";

		xhrPost("backend.php", {op: "rpc", method: "setpref", key: "COMBINED_DISPLAY_MODE", value: value}, () => {
			setInitParam("combined_display_mode",
				!getInitParam("combined_display_mode"));

			closeArticlePanel();
			viewCurrentFeed();
		})
	};
	hotkey_actions["toggle_cdm_expanded"] = function() {
		notify_progress("Loading, please wait...");

		const value = getInitParam("cdm_expanded") ? "false" : "true";

		xhrPost("backend.php", { op: "rpc", method: "setpref", key: "CDM_EXPANDED", value: value }, () => {
			setInitParam("cdm_expanded", !getInitParam("cdm_expanded"));
			viewCurrentFeed();
		});
	};

}

function init_second_stage() {
	updateFeedList();
	closeArticlePanel();

	if (parseInt(getCookie("ttrss_fh_width")) > 0) {
		dijit.byId("feeds-holder").domNode.setStyle(
			{width: getCookie("ttrss_fh_width") + "px" });
	}

	dijit.byId("main").resize();

	var tmph = dojo.connect(dijit.byId('feeds-holder'), 'resize',
		function (args) {
			if (args && args.w >= 0) {
				setCookie("ttrss_fh_width", args.w, getInitParam("cookie_lifetime"));
			}
	});

	var tmph = dojo.connect(dijit.byId('content-insert'), 'resize',
		function (args) {
			if (args && args.w >= 0 && args.h >= 0) {
				setCookie("ttrss_ci_width", args.w, getInitParam("cookie_lifetime"));
				setCookie("ttrss_ci_height", args.h, getInitParam("cookie_lifetime"));
			}
	});

	delCookie("ttrss_test");

	const toolbar = document.forms["main_toolbar_form"];

	dijit.getEnclosingWidget(toolbar.view_mode).attr('value',
		getInitParam("default_view_mode"));

	dijit.getEnclosingWidget(toolbar.order_by).attr('value',
		getInitParam("default_view_order_by"));

	const hash_feed_id = hash_get('f');
	const hash_feed_is_cat = hash_get('c') == "1";

	if (hash_feed_id != undefined) {
		setActiveFeedId(hash_feed_id, hash_feed_is_cat);
	}

	setLoadingProgress(50);

	// can't use cache_clear() here because viewfeed might not have initialized yet
	if ('sessionStorage' in window && window['sessionStorage'] !== null)
		sessionStorage.clear();

	_widescreen_mode = getInitParam("widescreen");
	switchPanelMode(_widescreen_mode);

	$("headlines-frame").onscroll = (event) => {
		clearTimeout(_headlines_scroll_timeout);
		_headlines_scroll_timeout = window.setTimeout(function() {
			//console.log('done scrolling', event);
			headlinesScrollHandler(event);
		}, 50);
	}

	console.log("second stage ok");

	if (getInitParam("simple_update")) {
		console.log("scheduling simple feed updater...");
		window.setTimeout(update_random_feed, 30*1000);
	}
}

function quickMenuGo(opid) {
	switch (opid) {
	case "qmcPrefs":
		gotoPreferences();
		break;
	case "qmcLogout":
		document.location.href = "backend.php?op=logout";
		break;
	case "qmcTagCloud":
		displayDlg(__("Tag cloud"), "printTagCloud");
		break;
	case "qmcSearch":
		search();
		break;
	case "qmcAddFeed":
		quickAddFeed();
		break;
	case "qmcDigest":
		window.location.href = "backend.php?op=digest";
		break;
	case "qmcEditFeed":
		if (activeFeedIsCat())
			alert(__("You can't edit this kind of feed."));
		else
			editFeed(getActiveFeedId());
		break;
	case "qmcRemoveFeed":
		var actid = getActiveFeedId();

		if (activeFeedIsCat()) {
			alert(__("You can't unsubscribe from the category."));
			return;
		}

		if (!actid) {
			alert(__("Please select some feed first."));
			return;
		}

		var fn = getFeedName(actid);

		var pr = __("Unsubscribe from %s?").replace("%s", fn);

		if (confirm(pr)) {
			unsubscribeFeed(actid);
		}
		break;
	case "qmcCatchupAll":
		catchupAllFeeds();
		break;
	case "qmcShowOnlyUnread":
		toggleDispRead();
		break;
	case "qmcToggleWidescreen":
		if (!isCombinedMode()) {
			_widescreen_mode = !_widescreen_mode;

			// reset stored sizes because geometry changed
			setCookie("ttrss_ci_width", 0);
			setCookie("ttrss_ci_height", 0);

			switchPanelMode(_widescreen_mode);
		} else {
			alert(__("Widescreen is not available in combined mode."));
		}
		break;
	case "qmcHKhelp":
		helpDialog("main");
		break;
	default:
		console.log("quickMenuGo: unknown action: " + opid);
	}
}

function toggleDispRead() {

	const hide = !(getInitParam("hide_read_feeds") == "1");

	xhrPost("backend.php", {op: "rpc", method: "setpref", key: "HIDE_READ_FEEDS", value: hide}, () => {
		hideOrShowFeeds(hide);
		setInitParam("hide_read_feeds", hide);
	});
}

function parse_runtime_info(data) {

	//console.log("parsing runtime info...");

	for (const k in data) {
		const v = data[k];

//		console.log("RI: " + k + " => " + v);

		if (k == "dep_ts" && parseInt(getInitParam("dep_ts")) > 0) {
			if (parseInt(getInitParam("dep_ts")) < parseInt(v) && getInitParam("reload_on_ts_change")) {
				window.location.reload();
			}
		}

		if (k == "daemon_is_running" && v != 1) {
			notify_error("<span onclick=\"explainError(1)\">Update daemon is not running.</span>", true);
			return;
		}

		if (k == "update_result") {
			const updatesIcon = dijit.byId("updatesIcon").domNode;

			if (v) {
				Element.show(updatesIcon);
			} else {
				Element.hide(updatesIcon);
			}
		}

		if (k == "daemon_stamp_ok" && v != 1) {
			notify_error("<span onclick=\"explainError(3)\">Update daemon is not updating feeds.</span>", true);
			return;
		}

		if (k == "max_feed_id" || k == "num_feeds") {
			if (init_params[k] != v) {
				console.log("feed count changed, need to reload feedlist.");
				updateFeedList();
			}
		}

		init_params[k] = v;
		notify('');
	}

	PluginHost.run(PluginHost.HOOK_RUNTIME_INFO_LOADED, data);
}

function collapse_feedlist() {
	Element.toggle("feeds-holder");

	const splitter = $("feeds-holder_splitter");

	Element.visible("feeds-holder") ? splitter.show() : splitter.hide();

	dijit.byId("main").resize();
}

function viewModeChanged() {
	cache_clear();
	return viewCurrentFeed('');
}

function hotkey_handler(e) {
	if (e.target.nodeName == "INPUT" || e.target.nodeName == "TEXTAREA") return;

	const action_name = keyeventToAction(e);

	if (action_name) {
		const action_func = hotkey_actions[action_name];

		if (action_func != null) {
			action_func();
			e.stopPropagation();
			return false;
		}
	}
}

function inPreferences() {
	return false;
}

function reverseHeadlineOrder() {

	const toolbar = document.forms["main_toolbar_form"];
	const order_by = dijit.getEnclosingWidget(toolbar.order_by);

	let value = order_by.attr('value');

	if (value == "date_reverse")
		value = "default";
	else
		value = "date_reverse";

	order_by.attr('value', value);

	viewCurrentFeed();

}

function handle_rpc_json(transport, scheduled_call) {

	const netalert_dijit = dijit.byId("net-alert");
	let netalert = false;

	if (netalert_dijit) netalert = netalert_dijit.domNode;

	try {
		const reply = JSON.parse(transport.responseText);

		if (reply) {

			const error = reply['error'];

			if (error) {
				const code = error['code'];
				const msg = error['msg'];

				console.warn("[handle_rpc_json] received fatal error " + code + "/" + msg);

				if (code != 0) {
					fatalError(code, msg);
					return false;
				}
			}

			const seq = reply['seq'];

			if (seq && get_seq() != seq) {
				console.log("[handle_rpc_json] sequence mismatch: " + seq +
					" (want: " + get_seq() + ")");
				return true;
			}

			const message = reply['message'];

			if (message == "UPDATE_COUNTERS") {
				console.log("need to refresh counters...");
				setInitParam("last_article_id", -1);
				request_counters(true);
			}

			const counters = reply['counters'];

			if (counters)
				parse_counters(counters, scheduled_call);

			const runtime_info = reply['runtime-info'];

			if (runtime_info)
				parse_runtime_info(runtime_info);

			if (netalert) netalert.hide();

			return reply;

		} else {
			if (netalert)
				netalert.show();
			else
				notify_error("Communication problem with server.");
		}

	} catch (e) {
		if (netalert)
			netalert.show();
		else
			notify_error("Communication problem with server.");

		console.error(e);
	}

	return false;
}

function switchPanelMode(wide) {
	if (isCombinedMode()) return;

	const article_id = getActiveArticleId();

	if (wide) {
		dijit.byId("headlines-wrap-inner").attr("design", 'sidebar');
		dijit.byId("content-insert").attr("region", "trailing");

  		dijit.byId("content-insert").domNode.setStyle({width: '50%',
			height: 'auto',
			borderTopWidth: '0px' });

		if (parseInt(getCookie("ttrss_ci_width")) > 0) {
			dijit.byId("content-insert").domNode.setStyle(
				{width: getCookie("ttrss_ci_width") + "px" });
		}

		$("headlines-frame").setStyle({ borderBottomWidth: '0px' });
		$("headlines-frame").addClassName("wide");

	} else {

		dijit.byId("content-insert").attr("region", "bottom");

  		dijit.byId("content-insert").domNode.setStyle({width: 'auto',
			height: '50%',
			borderTopWidth: '0px'});

		if (parseInt(getCookie("ttrss_ci_height")) > 0) {
			dijit.byId("content-insert").domNode.setStyle(
				{height: getCookie("ttrss_ci_height") + "px" });
		}

		$("headlines-frame").setStyle({ borderBottomWidth: '1px' });
		$("headlines-frame").removeClassName("wide");

	}

	closeArticlePanel();

	if (article_id) view(article_id);

	xhrPost("backend.php", {op: "rpc", method: "setpanelmode", wide: wide ? 1 : 0});
}

function update_random_feed() {
	console.log("in update_random_feed");

	xhrPost("backend.php", { op: "rpc", method: "updateRandomFeed" }, (transport) => {
		handle_rpc_json(transport, true);
		window.setTimeout(update_random_feed, 30*1000);
	});
}

function hash_get(key) {
	const kv = window.location.hash.substring(1).toQueryParams();
	return kv[key];
}

function hash_set(key, value) {
	const kv = window.location.hash.substring(1).toQueryParams();
	kv[key] = value;
	window.location.hash = $H(kv).toQueryString();
}

function gotoPreferences() {
	document.location.href = "prefs.php";
}

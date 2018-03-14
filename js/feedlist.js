var _infscroll_disable = 0;
var _infscroll_request_sent = 0;

var _search_query = false;
var _viewfeed_last = 0;
var _viewfeed_timeout = false;

var counters_last_request = 0;
var _counters_prev = [];

function resetCounterCache() {
	_counters_prev = [];
}

function loadMoreHeadlines() {
	console.log("loadMoreHeadlines");

	var offset = 0;

	var view_mode = document.forms["main_toolbar_form"].view_mode.value;
	var unread_in_buffer = $$("#headlines-frame > div[id*=RROW][class*=Unread]").length;
	var num_all = $$("#headlines-frame > div[id*=RROW]").length;
	var num_unread = getFeedUnread(getActiveFeedId(), activeFeedIsCat());

	// TODO implement marked & published

	if (view_mode == "marked") {
		console.warn("loadMoreHeadlines: marked is not implemented, falling back.");
		offset = num_all;
	} else if (view_mode == "published") {
		console.warn("loadMoreHeadlines: published is not implemented, falling back.");
		offset = num_all;
	} else if (view_mode == "unread") {
		offset = unread_in_buffer;
	} else if (_search_query) {
		offset = num_all;
	} else if (view_mode == "adaptive" && !(getActiveFeedId() == -1 && !activeFeedIsCat())) {
		// ^ starred feed shows both unread & read articles in adaptive mode
		offset = num_unread > 0 ? unread_in_buffer : num_all;
	} else {
		offset = num_all;
	}

	console.log("offset: " + offset);

	viewfeed({feed: getActiveFeedId(), is_cat: activeFeedIsCat(), offset: offset, infscroll_req: true});

}

function cleanup_memory(root) {
	var dijits = dojo.query("[widgetid]", dijit.byId(root).domNode).map(dijit.byNode);

	dijits.each(function (d) {
		dojo.destroy(d.domNode);
	});

	$$("#" + root + " *").each(function (i) {
		i.parentNode ? i.parentNode.removeChild(i) : true;
	});
}

function viewfeed(params) {
	var feed = params.feed;
	var is_cat = params.is_cat;
	var offset = params.offset;
	var background = params.background;
	var infscroll_req = params.infscroll_req;
	var can_wait = params.can_wait;
	var viewfeed_debug = params.viewfeed_debug;
	var method = params.method;

	if (is_cat == undefined)
		is_cat = false;
	else
		is_cat = !!is_cat;

	if (offset == undefined) offset = 0;
	if (background == undefined) background = false;
	if (infscroll_req == undefined) infscroll_req = false;

	last_requested_article = 0;

	if (feed != getActiveFeedId() || activeFeedIsCat() != is_cat) {
		if (!background && _search_query) _search_query = false;
	}

	if (!background) {
		_viewfeed_last = get_timestamp();

		if (getActiveFeedId() != feed || !infscroll_req) {
			setActiveArticleId(0);
			_infscroll_disable = 0;

			cleanup_memory("headlines-frame");
			_headlines_scroll_offset = 0;
		}

		if (infscroll_req) {
			var timestamp = get_timestamp();

			if (_infscroll_request_sent && _infscroll_request_sent + 30 > timestamp) {
				//console.log("infscroll request in progress, aborting");
				return;
			}

			_infscroll_request_sent = timestamp;
		}
	}

	Form.enable("main_toolbar_form");

	var toolbar_query = Form.serialize("main_toolbar_form");

	var query = "?op=feeds&method=view&feed=" + param_escape(feed) + "&" +
		toolbar_query;

	if (method) query += "&m=" + param_escape(method);

	if (offset > 0) {
		if (current_first_id) {
			query = query + "&fid=" + param_escape(current_first_id);
		}
	}

	if (!background) {
		if (_search_query) {
			query = query + "&" + _search_query;
			//_search_query = false;
		}

		if (offset != 0) {
			query = query + "&skip=" + offset;

			// to prevent duplicate feed titles when showing grouped vfeeds
			if (vgroup_last_feed) {
				query = query + "&vgrlf=" + param_escape(vgroup_last_feed);
			}
		} else {
			if (!is_cat && feed == getActiveFeedId() && !params.method) {
				query = query + "&m=ForceUpdate";
			}
		}

		Form.enable("main_toolbar_form");

		if (!setFeedExpandoIcon(feed, is_cat,
			(is_cat) ? 'images/indicator_tiny.gif' : 'images/indicator_white.gif'))
				notify_progress("Loading, please wait...", true);
	}

	query += "&cat=" + is_cat;

	console.log(query);

	if (can_wait && _viewfeed_timeout) {
		setFeedExpandoIcon(getActiveFeedId(), activeFeedIsCat(), 'images/blank_icon.gif');
		clearTimeout(_viewfeed_timeout);
	}

	setActiveFeedId(feed, is_cat);

	if (viewfeed_debug) {
		window.open("backend.php" + query + "&debug=1&csrf_token=" + getInitParam("csrf_token"));
	}

	var timeout_ms = can_wait ? 250 : 0;
	_viewfeed_timeout = setTimeout(function() {

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				try {
					setFeedExpandoIcon(feed, is_cat, 'images/blank_icon.gif');
					headlines_callback2(transport, offset, background, infscroll_req);
					PluginHost.run(PluginHost.HOOK_FEED_LOADED, [feed, is_cat]);
				} catch (e) {
					exception_error(e);
				}
			} });
	}, timeout_ms); // Wait 250ms

}

function feedlist_init() {
	console.log("in feedlist init");

	loading_set_progress(50);

	document.onkeydown = hotkey_handler;
	setTimeout(hotkey_prefix_timeout, 5*1000);

	if (!getActiveFeedId()) {
		viewfeed({feed: -3});
	} else {
		viewfeed({feed: getActiveFeedId(), is_cat: activeFeedIsCat()});
	}

	hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

	if (getInitParam("is_default_pw")) {
		console.warn("user password is at default value");

		var dialog = new dijit.Dialog({
			title: __("Your password is at default value"),
			href: "backend.php?op=dlg&method=defaultpasswordwarning",
			id: 'infoBox',
			style: "width: 600px",
			onCancel: function() {
				return true;
			},
			onExecute: function() {
				return true;
			},
			onClose: function() {
				return true;
			}
		});

		dialog.show();
	}

	// bw_limit disables timeout() so we request initial counters separately
    if (getInitParam("bw_limit") == "1") {
		request_counters(true);
    } else {
    	setTimeout(timeout, 250);
    }
}


function request_counters(force) {
	var date = new Date();
	var timestamp = Math.round(date.getTime() / 1000);

	if (force || timestamp - counters_last_request > 5) {
		console.log("scheduling request of counters...");

		counters_last_request = timestamp;

		var query = "?op=rpc&method=getAllCounters&seq=" + next_seq();

		if (!force)
			query = query + "&last_article_id=" + getInitParam("last_article_id");

		console.log(query);

		new Ajax.Request("backend.php", {
			parameters: query,
			onComplete: function(transport) {
				handle_rpc_json(transport);
			} });

	} else {
		console.log("request_counters: rate limit reached: " + (timestamp - counters_last_request));
	}
}

// NOTE: this implementation is incomplete
// for general objects but good enough for counters
// http://adripofjavascript.com/blog/drips/object-equality-in-javascript.html
function counter_is_equal(a, b) {
	// Create arrays of property names
	var aProps = Object.getOwnPropertyNames(a);
	var bProps = Object.getOwnPropertyNames(b);

	// If number of properties is different,
	// objects are not equivalent
	if (aProps.length != bProps.length) {
		return false;
	}

	for (var i = 0; i < aProps.length; i++) {
		var propName = aProps[i];

		// If values of same property are not equal,
		// objects are not equivalent
		if (a[propName] !== b[propName]) {
			return false;
		}
	}

	// If we made it this far, objects
	// are considered equivalent
	return true;
}


function parse_counters(elems) {
	for (var l = 0; l < elems.length; l++) {

		if (_counters_prev[l] && counter_is_equal(elems[l], _counters_prev[l])) {
			continue;
		}

		var id = elems[l].id;
		var kind = elems[l].kind;
		var ctr = parseInt(elems[l].counter);
		var error = elems[l].error;
		var has_img = elems[l].has_img;
		var updated = elems[l].updated;
		var auxctr = parseInt(elems[l].auxcounter);

		if (id == "global-unread") {
			global_unread = ctr;
			updateTitle();
			continue;
		}

		if (id == "subscribed-feeds") {
			/* feeds_found = ctr; */
			continue;
		}

		/*if (getFeedUnread(id, (kind == "cat")) != ctr ||
				(kind == "cat")) {
		}*/

		setFeedUnread(id, (kind == "cat"), ctr);
		setFeedValue(id, (kind == "cat"), 'auxcounter', auxctr);

		if (kind != "cat") {
			setFeedValue(id, false, 'error', error);
			setFeedValue(id, false, 'updated', updated);

			if (id > 0) {
				if (has_img) {
					setFeedIcon(id, false,
						getInitParam("icons_url") + "/" + id + ".ico?" + has_img);
				} else {
					setFeedIcon(id, false, 'images/blank_icon.gif');
				}
			}
		}
	}

	hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

	_counters_prev = elems;
}

function getFeedUnread(feed, is_cat) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.getFeedUnread(feed, is_cat);

	} catch (e) {
		//
	}

	return -1;
}

function getFeedCategory(feed) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.getFeedCategory(feed);

	} catch (e) {
		//
	}

	return false;
}

function hideOrShowFeeds(hide) {
	var tree = dijit.byId("feedTree");

	if (tree)
		return tree.hideRead(hide, getInitParam("hide_read_shows_special"));
}

function getFeedName(feed, is_cat) {

	if (isNaN(feed)) return feed; // it's a tag

	var tree = dijit.byId("feedTree");

	if (tree && tree.model)
		return tree.model.getFeedValue(feed, is_cat, 'name');
}

function getFeedValue(feed, is_cat, key) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.getFeedValue(feed, is_cat, key);

	} catch (e) {
		//
	}
	return '';
}

function setFeedUnread(feed, is_cat, unread) {
	var tree = dijit.byId("feedTree");

	if (tree && tree.model)
		return tree.model.setFeedUnread(feed, is_cat, unread);
}

function setFeedValue(feed, is_cat, key, value) {
	try {
		var tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.setFeedValue(feed, is_cat, key, value);

	} catch (e) {
		//
	}
}

function selectFeed(feed, is_cat) {
	var tree = dijit.byId("feedTree");

	if (tree) return tree.selectFeed(feed, is_cat);
}

function setFeedIcon(feed, is_cat, src) {
	var tree = dijit.byId("feedTree");

	if (tree) return tree.setFeedIcon(feed, is_cat, src);
}

function setFeedExpandoIcon(feed, is_cat, src) {
	var tree = dijit.byId("feedTree");

	if (tree) return tree.setFeedExpandoIcon(feed, is_cat, src);

	return false;
}

function getNextUnreadFeed(feed, is_cat) {
	var tree = dijit.byId("feedTree");
	var nuf = tree.model.getNextUnreadFeed(feed, is_cat);

	if (nuf)
		return tree.model.store.getValue(nuf, 'bare_id');
}

function catchupCurrentFeed(mode) {
	catchupFeed(getActiveFeedId(), activeFeedIsCat(), mode);
}

function catchupFeedInGroup(id) {
	var title = getFeedName(id);

	var str = __("Mark all articles in %s as read?").replace("%s", title);

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {

		var rows = $$("#headlines-frame > div[id*=RROW][data-orig-feed-id='"+id+"']");

		if (rows.length > 0) {

			rows.each(function (row) {
				row.removeClassName("Unread");

				if (row.getAttribute("data-article-id") != getActiveArticleId()) {
					new Effect.Fade(row, {duration: 0.5});
				}

			});

			var feedTitles = $$("#headlines-frame > div[class='cdmFeedTitle']");

			for (var i = 0; i < feedTitles.length; i++) {
				if (feedTitles[i].getAttribute("data-feed-id") == id) {

					if (i < feedTitles.length - 1) {
						new Effect.Fade(feedTitles[i], {duration: 0.5});
					}

					break;
				}
			}

			updateFloatingTitle(true);
		}

		var catchup_query = "?op=rpc&method=catchupFeed&feed_id=" +
				id + "&is_cat=false";

		console.log(catchup_query);

		notify_progress("Loading, please wait...", true);

		new Ajax.Request("backend.php", {
			parameters: catchup_query,
			onComplete: function (transport) {
				handle_rpc_json(transport);
			}
		} );

		//return viewCurrentFeed('MarkAllReadGR:' + id);
	}
}

function catchupFeed(feed, is_cat, mode) {
	if (is_cat == undefined) is_cat = false;

	var str = false;

	switch (mode) {
	case "1day":
		str = __("Mark %w in %s older than 1 day as read?");
		break;
	case "1week":
		str = __("Mark %w in %s older than 1 week as read?");
		break;
	case "2week":
		str = __("Mark %w in %s older than 2 weeks as read?");
		break;
	default:
		str = __("Mark %w in %s as read?");
	}

	var mark_what = last_search_query && last_search_query[0] ? __("search results") : __("all articles");
	var fn = getFeedName(feed, is_cat);

	str = str.replace("%s", fn)
		.replace("%w", mark_what);

	if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
		return;
	}

	var catchup_query = {op: 'rpc', method: 'catchupFeed', feed_id: feed,
		is_cat: is_cat, mode: mode, search_query: last_search_query[0],
		search_lang: last_search_query[1]};

	console.log(catchup_query);

	notify_progress("Loading, please wait...", true);

	new Ajax.Request("backend.php",	{
		parameters: catchup_query,
		onComplete: function(transport) {
				handle_rpc_json(transport);

				var show_next_feed = getInitParam("on_catchup_show_next_feed") == "1";

				if (show_next_feed) {
					var nuf = getNextUnreadFeed(feed, is_cat);

					if (nuf) {
						viewfeed({feed: nuf, is_cat: is_cat});
					}
				} else {
					if (feed == getActiveFeedId() && is_cat == activeFeedIsCat()) {
						viewCurrentFeed();
					}
				}

				notify("");
			} });

}

function decrementFeedCounter(feed, is_cat) {
	var ctr = getFeedUnread(feed, is_cat);

	if (ctr > 0) {
		setFeedUnread(feed, is_cat, ctr - 1);
		global_unread = global_unread - 1;
		updateTitle();

		if (!is_cat) {
			var cat = parseInt(getFeedCategory(feed));

			if (!isNaN(cat)) {
				ctr = getFeedUnread(cat, true);

				if (ctr > 0) {
					setFeedUnread(cat, true, ctr - 1);
				}
			}
		}
	}

}



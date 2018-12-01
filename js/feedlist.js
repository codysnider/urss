let infscroll_in_progress = 0;
let infscroll_disabled = 0;

let _infscroll_timeout = false;
let _search_query = false;
let _viewfeed_wait_timeout = false;

let counters_last_request = 0;
let _counters_prev = [];

function resetCounterCache() {
	_counters_prev = [];
}

function loadMoreHeadlines() {
	const view_mode = document.forms["main_toolbar_form"].view_mode.value;
	const unread_in_buffer = $$("#headlines-frame > div[id*=RROW][class*=Unread]").length;
	const num_all = $$("#headlines-frame > div[id*=RROW]").length;
	const num_unread = getFeedUnread(getActiveFeedId(), activeFeedIsCat());

	// TODO implement marked & published

	let offset = num_all;

	switch (view_mode) {
		case "marked":
		case "published":
			console.warn("loadMoreHeadlines: ", view_mode, "not implemented");
			break;
		case "unread":
			offset = unread_in_buffer;
			break;
		case "adaptive":
			if (!(getActiveFeedId() == -1 && !activeFeedIsCat()))
				offset = num_unread > 0 ? unread_in_buffer : num_all;
			break;
	}

	console.log("loadMoreHeadlines, offset=", offset);

	viewfeed({feed: getActiveFeedId(), is_cat: activeFeedIsCat(), offset: offset, infscroll_req: true});
}

function cleanup_memory(root) {
	const dijits = dojo.query("[widgetid]", dijit.byId(root).domNode).map(dijit.byNode);

	dijits.each(function (d) {
		dojo.destroy(d.domNode);
	});

	$$("#" + root + " *").each(function (i) {
		i.parentNode ? i.parentNode.removeChild(i) : true;
	});
}

function viewfeed(params) {
	const feed = params.feed;
	const is_cat = !!params.is_cat || false;
	const offset = params.offset || 0;
	const viewfeed_debug = params.viewfeed_debug;
	const method = params.method;
	// this is used to quickly switch between feeds, sets active but xhr is on a timeout
	const delayed = params.delayed || false;

	if (feed != getActiveFeedId() || activeFeedIsCat() != is_cat) {
		_search_query = false;
		setActiveArticleId(0);
	}

	if (offset != 0) {
		if (infscroll_in_progress)
			return;

		infscroll_in_progress = 1;

		window.clearTimeout(_infscroll_timeout);
		_infscroll_timeout = window.setTimeout(() => {
			console.log('infscroll request timed out, aborting');
			infscroll_in_progress = 0;

			// call scroll handler to maybe repeat infscroll request
			headlinesScrollHandler();
		}, 10 * 1000);
	}

	Form.enable("main_toolbar_form");

	let query = Object.assign({op: "feeds", method: "view", feed: feed},
		dojo.formToObject("main_toolbar_form"));

	if (method) query.m = method;

	if (offset > 0) {
		if (current_first_id) {
			query.fid = current_first_id;
		}
	}

	if (_search_query) {
		query = Object.assign(query, _search_query);
	}

	if (offset != 0) {
		query.skip = offset;

		// to prevent duplicate feed titles when showing grouped vfeeds
		if (vgroup_last_feed) {
			query.vgrlf = vgroup_last_feed;
		}
	} else if (!is_cat && feed == getActiveFeedId() && !params.method) {
			query.m = "ForceUpdate";
		}

	Form.enable("main_toolbar_form");

	if (!delayed)
		if (!setFeedExpandoIcon(feed, is_cat,
			(is_cat) ? 'images/indicator_tiny.gif' : 'images/indicator_white.gif'))
				notify_progress("Loading, please wait...", true);

	query.cat = is_cat;

	setActiveFeedId(feed, is_cat);

	if (viewfeed_debug) {
		window.open("backend.php?" +
			dojo.objectToQuery(
				Object.assign({debug: 1, csrf_token: getInitParam("csrf_token")}, query)
			));
	}

	window.clearTimeout(_viewfeed_wait_timeout);
	_viewfeed_wait_timeout = window.setTimeout(() => {
		catchupBatchedArticles(() => {
			xhrPost("backend.php", query, (transport) => {
				try {
					setFeedExpandoIcon(feed, is_cat, 'images/blank_icon.gif');
					headlines_callback2(transport, offset);
					PluginHost.run(PluginHost.HOOK_FEED_LOADED, [feed, is_cat]);
				} catch (e) {
					exception_error(e);
				}
			});
		});
	}, delayed ? 250 : 0);
}

function feedlist_init() {
	console.log("in feedlist init");

	setLoadingProgress(50);

	document.onkeydown = hotkey_handler;
	setInterval(hotkeyPrefixTimeout, 3*1000);
	setInterval(catchupBatchedArticles, 10*1000);

	if (!getActiveFeedId()) {
		viewfeed({feed: -3});
	} else {
		viewfeed({feed: getActiveFeedId(), is_cat: activeFeedIsCat()});
	}

	hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

	if (getInitParam("is_default_pw")) {
		console.warn("user password is at default value");

		const dialog = new dijit.Dialog({
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
	const date = new Date();
	const timestamp = Math.round(date.getTime() / 1000);

	if (force || timestamp - counters_last_request > 5) {
		console.log("scheduling request of counters...");

		counters_last_request = timestamp;

		let query = {op: "rpc", method: "getAllCounters", seq: next_seq()};

		if (!force)
			query.last_article_id = getInitParam("last_article_id");

		xhrPost("backend.php", query, (transport) => {
			handle_rpc_json(transport);
		});

	} else {
		console.log("request_counters: rate limit reached: " + (timestamp - counters_last_request));
	}
}

// NOTE: this implementation is incomplete
// for general objects but good enough for counters
// http://adripofjavascript.com/blog/drips/object-equality-in-javascript.html
function counter_is_equal(a, b) {
	// Create arrays of property names
	const aProps = Object.getOwnPropertyNames(a);
	const bProps = Object.getOwnPropertyNames(b);

	// If number of properties is different,
	// objects are not equivalent
	if (aProps.length != bProps.length) {
		return false;
	}

	for (let i = 0; i < aProps.length; i++) {
		const propName = aProps[i];

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
	for (let l = 0; l < elems.length; l++) {

		if (_counters_prev[l] && counter_is_equal(elems[l], _counters_prev[l])) {
			continue;
		}

		const id = elems[l].id;
		const kind = elems[l].kind;
		const ctr = parseInt(elems[l].counter);
		const error = elems[l].error;
		const has_img = elems[l].has_img;
		const updated = elems[l].updated;
		const auxctr = parseInt(elems[l].auxcounter);

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
		const tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.getFeedUnread(feed, is_cat);

	} catch (e) {
		//
	}

	return -1;
}

function getFeedCategory(feed) {
	try {
		const tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.getFeedCategory(feed);

	} catch (e) {
		//
	}

	return false;
}

function hideOrShowFeeds(hide) {
	const tree = dijit.byId("feedTree");

	if (tree)
		return tree.hideRead(hide, getInitParam("hide_read_shows_special"));
}

function getFeedName(feed, is_cat) {

	if (isNaN(feed)) return feed; // it's a tag

	const tree = dijit.byId("feedTree");

	if (tree && tree.model)
		return tree.model.getFeedValue(feed, is_cat, 'name');
}

/* function getFeedValue(feed, is_cat, key) {
	try {
		const tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.getFeedValue(feed, is_cat, key);

	} catch (e) {
		//
	}
	return '';
} */

function setFeedUnread(feed, is_cat, unread) {
	const tree = dijit.byId("feedTree");

	if (tree && tree.model)
		return tree.model.setFeedUnread(feed, is_cat, unread);
}

function setFeedValue(feed, is_cat, key, value) {
	try {
		const tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.setFeedValue(feed, is_cat, key, value);

	} catch (e) {
		//
	}
}

function selectFeed(feed, is_cat) {
	const tree = dijit.byId("feedTree");

	if (tree) return tree.selectFeed(feed, is_cat);
}

function setFeedIcon(feed, is_cat, src) {
	const tree = dijit.byId("feedTree");

	if (tree) return tree.setFeedIcon(feed, is_cat, src);
}

function setFeedExpandoIcon(feed, is_cat, src) {
	const tree = dijit.byId("feedTree");

	if (tree) return tree.setFeedExpandoIcon(feed, is_cat, src);

	return false;
}

function getNextUnreadFeed(feed, is_cat) {
	const tree = dijit.byId("feedTree");
	const nuf = tree.model.getNextUnreadFeed(feed, is_cat);

	if (nuf)
		return tree.model.store.getValue(nuf, 'bare_id');
}

function catchupCurrentFeed(mode) {
	catchupFeed(getActiveFeedId(), activeFeedIsCat(), mode);
}

function catchupFeedInGroup(id) {
	const title = getFeedName(id);

	const str = __("Mark all articles in %s as read?").replace("%s", title);

	if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {

		const rows = $$("#headlines-frame > div[id*=RROW][data-orig-feed-id='"+id+"']");

		if (rows.length > 0) {

			rows.each(function (row) {
				row.removeClassName("Unread");

				if (row.getAttribute("data-article-id") != getActiveArticleId()) {
					new Effect.Fade(row, {duration: 0.5});
				}

			});

			const feedTitles = $$("#headlines-frame > div[class='feed-title']");

			for (let i = 0; i < feedTitles.length; i++) {
				if (feedTitles[i].getAttribute("data-feed-id") == id) {

					if (i < feedTitles.length - 1) {
						new Effect.Fade(feedTitles[i], {duration: 0.5});
					}

					break;
				}
			}

			updateFloatingTitle(true);
		}

		notify_progress("Loading, please wait...", true);

		xhrPost("backend.php", { op: "rpc", method: "catchupFeed", feed_id: id, is_cat: false}, (transport) => {
			handle_rpc_json(transport);
		});
	}
}

function catchupFeed(feed, is_cat, mode) {
	if (is_cat == undefined) is_cat = false;

	let str = false;

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

	const mark_what = last_search_query && last_search_query[0] ? __("search results") : __("all articles");
	const fn = getFeedName(feed, is_cat);

	str = str.replace("%s", fn)
		.replace("%w", mark_what);

	if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
		return;
	}

	const catchup_query = {op: 'rpc', method: 'catchupFeed', feed_id: feed,
		is_cat: is_cat, mode: mode, search_query: last_search_query[0],
		search_lang: last_search_query[1]};

	notify_progress("Loading, please wait...", true);

	xhrPost("backend.php", catchup_query, (transport) => {
		handle_rpc_json(transport);

		const show_next_feed = getInitParam("on_catchup_show_next_feed") == "1";

		if (show_next_feed) {
			const nuf = getNextUnreadFeed(feed, is_cat);

			if (nuf) {
				viewfeed({feed: nuf, is_cat: is_cat});
			}
		} else if (feed == getActiveFeedId() && is_cat == activeFeedIsCat()) {
			viewCurrentFeed();
		}

		notify("");
	});
}

function decrementFeedCounter(feed, is_cat) {
	let ctr = getFeedUnread(feed, is_cat);

	if (ctr > 0) {
		setFeedUnread(feed, is_cat, ctr - 1);
		global_unread = global_unread - 1;
		updateTitle();

		if (!is_cat) {
			const cat = parseInt(getFeedCategory(feed));

			if (!isNaN(cat)) {
				ctr = getFeedUnread(cat, true);

				if (ctr > 0) {
					setFeedUnread(cat, true, ctr - 1);
				}
			}
		}
	}

}



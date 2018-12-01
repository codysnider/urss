const Feeds = {
	counters_last_request: 0,
	_active_feed_id: 0,
	_active_feed_is_cat: false,
	infscroll_in_progress: 0,
	infscroll_disabled: 0,
	_infscroll_timeout: false,
	_search_query: false,
	last_search_query: [],
	_viewfeed_wait_timeout: false,
	_counters_prev: [],
	// NOTE: this implementation is incomplete
	// for general objects but good enough for counters
	// http://adripofjavascript.com/blog/drips/object-equality-in-javascript.html
	counterEquals: function(a, b) {
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
	},
	resetCounters: function () {
		this._counters_prev = [];
	},
	parseCounters: function (elems) {
		for (let l = 0; l < elems.length; l++) {

			if (Feeds._counters_prev[l] && this.counterEquals(elems[l], this._counters_prev[l])) {
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
				App.global_unread = ctr;
				App.updateTitle();
				continue;
			}

			if (id == "subscribed-feeds") {
				/* feeds_found = ctr; */
				continue;
			}

			/*if (this.getFeedUnread(id, (kind == "cat")) != ctr ||
					(kind == "cat")) {
			}*/

			this.setFeedUnread(id, (kind == "cat"), ctr);
			this.setFeedValue(id, (kind == "cat"), 'auxcounter', auxctr);

			if (kind != "cat") {
				this.setFeedValue(id, false, 'error', error);
				this.setFeedValue(id, false, 'updated', updated);

				if (id > 0) {
					if (has_img) {
						this.setFeedIcon(id, false,
							getInitParam("icons_url") + "/" + id + ".ico?" + has_img);
					} else {
						this.setFeedIcon(id, false, 'images/blank_icon.gif');
					}
				}
			}
		}

		this.hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);
		this._counters_prev = elems;
	},
	viewCurrentFeed: function(method) {
		console.log("viewCurrentFeed: " + method);

		if (this.getActiveFeedId() != undefined) {
			this.viewfeed({feed: this.getActiveFeedId(), is_cat: this.activeFeedIsCat(), method: method});
		}
		return false; // block unneeded form submits
	},
	openNextUnreadFeed: function() {
		const is_cat = this.activeFeedIsCat();
		const nuf = this.getNextUnreadFeed(this.getActiveFeedId(), is_cat);
		if (nuf) this.viewfeed({feed: nuf, is_cat: is_cat});
	},
	collapseFeedlist: function() {
		Element.toggle("feeds-holder");

		const splitter = $("feeds-holder_splitter");

		Element.visible("feeds-holder") ? splitter.show() : splitter.hide();

		dijit.byId("main").resize();
	},
	cancelSearch: function() {
		this._search_query = "";
		this.viewCurrentFeed();
	},
	requestCounters: function(force) {
		const date = new Date();
		const timestamp = Math.round(date.getTime() / 1000);

		if (force || timestamp - this.counters_last_request > 5) {
			console.log("scheduling request of counters...");

			this.counters_last_request = timestamp;

			let query = {op: "rpc", method: "getAllCounters", seq: Utils.next_seq()};

			if (!force)
				query.last_article_id = getInitParam("last_article_id");

			xhrPost("backend.php", query, (transport) => {
				Utils.handleRpcJson(transport);
			});

		} else {
			console.log("request_counters: rate limit reached: " + (timestamp - this.counters_last_request));
		}
	},
	reload: function() {
		try {
			Element.show("feedlistLoading");

			this.resetCounters();

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
					Feeds.viewfeed({feed: feed, is_cat: is_cat});
					return false;
				},
				openOnClick: false,
				showRoot: false,
				persist: true,
				id: "feedTree",
			}, "feedTree");

			const tmph = dojo.connect(dijit.byId('feedMenu'), '_openMyself', function (event) {
				console.log(dijit.getEnclosingWidget(event.target));
				dojo.disconnect(tmph);
			});

			$("feeds-holder").appendChild(tree.domNode);

			const tmph2 = dojo.connect(tree, 'onLoad', function () {
				dojo.disconnect(tmph2);
				Element.hide("feedlistLoading");

				try {
					Feeds.init();
					Utils.setLoadingProgress(25);
				} catch (e) {
					exception_error(e);
				}
			});

			tree.startup();
		} catch (e) {
			exception_error(e);
		}
	},
	init: function() {
		console.log("in feedlist init");

		Utils.setLoadingProgress(50);

		document.onkeydown = () => { App.hotkeyHandler(event) };
		window.setInterval(() => { hotkeyPrefixTimeout() }, 3 * 1000);
		window.setInterval(() => { Headlines.catchupBatchedArticles() }, 10 * 1000);

		if (!this.getActiveFeedId()) {
			this.viewfeed({feed: -3});
		} else {
			this.viewfeed({feed: this.getActiveFeedId(), is_cat: this.activeFeedIsCat()});
		}

		this.hideOrShowFeeds(getInitParam("hide_read_feeds") == 1);

		if (getInitParam("is_default_pw")) {
			console.warn("user password is at default value");

			const dialog = new dijit.Dialog({
				title: __("Your password is at default value"),
				href: "backend.php?op=dlg&method=defaultpasswordwarning",
				id: 'infoBox',
				style: "width: 600px",
				onCancel: function () {
					return true;
				},
				onExecute: function () {
					return true;
				},
				onClose: function () {
					return true;
				}
			});

			dialog.show();
		}

		// bw_limit disables timeout() so we request initial counters separately
		if (getInitParam("bw_limit") == "1") {
			this.requestCounters(true);
		} else {
			setTimeout(() => {
				this.requestCounters(true);
				setInterval(() => { this.requestCounters(); }, 60 * 1000)
			}, 250);
		}
	},
	activeFeedIsCat: function() {
		return !!this._active_feed_is_cat;
	},
	getActiveFeedId: function() {
		return this._active_feed_id;
	},
	setActiveFeedId: function(id, is_cat) {
		hash_set('f', id);
		hash_set('c', is_cat ? 1 : 0);

		this._active_feed_id = id;
		this._active_feed_is_cat = is_cat;

		$("headlines-frame").setAttribute("feed-id", id);
		$("headlines-frame").setAttribute("is-cat", is_cat ? 1 : 0);

		this.selectFeed(id, is_cat);

		PluginHost.run(PluginHost.HOOK_FEED_SET_ACTIVE, [this._active_feed_id, this._active_feed_is_cat]);
	},
	selectFeed: function(feed, is_cat) {
		const tree = dijit.byId("feedTree");

		if (tree) return tree.selectFeed(feed, is_cat);
	},
	toggleDispRead: function() {
		const hide = !(getInitParam("hide_read_feeds") == "1");

		xhrPost("backend.php", {op: "rpc", method: "setpref", key: "HIDE_READ_FEEDS", value: hide}, () => {
			this.hideOrShowFeeds(hide);
			setInitParam("hide_read_feeds", hide);
		});
	},
	hideOrShowFeeds: function(hide) {
		const tree = dijit.byId("feedTree");

		if (tree)
			return tree.hideRead(hide, getInitParam("hide_read_shows_special"));
	},
	viewfeed: function(params) {
		const feed = params.feed;
		const is_cat = !!params.is_cat || false;
		const offset = params.offset || 0;
		const viewfeed_debug = params.viewfeed_debug;
		const method = params.method;
		// this is used to quickly switch between feeds, sets active but xhr is on a timeout
		const delayed = params.delayed || false;

		if (feed != this.getActiveFeedId() || this.activeFeedIsCat() != is_cat) {
			this._search_query = false;
			Article.setActiveArticleId(0);
		}

		if (offset != 0) {
			if (this.infscroll_in_progress)
				return;

			this.infscroll_in_progress = 1;

			window.clearTimeout(this._infscroll_timeout);
			this._infscroll_timeout = window.setTimeout(() => {
				console.log('infscroll request timed out, aborting');
				this.infscroll_in_progress = 0;

				// call scroll handler to maybe repeat infscroll request
				Headlines.scrollHandler();
			}, 10 * 1000);
		}

		Form.enable("main_toolbar_form");

		let query = Object.assign({op: "feeds", method: "view", feed: feed},
			dojo.formToObject("main_toolbar_form"));

		if (method) query.m = method;

		if (offset > 0) {
			if (Headlines.current_first_id) {
				query.fid = Headlines.current_first_id;
			}
		}

		if (this._search_query) {
			query = Object.assign(query, this._search_query);
		}

		if (offset != 0) {
			query.skip = offset;

			// to prevent duplicate feed titles when showing grouped vfeeds
			if (Headlines.vgroup_last_feed != undefined) {
				query.vgrlf = Headlines.vgroup_last_feed;
			}
		} else if (!is_cat && feed == this.getActiveFeedId() && !params.method) {
			query.m = "ForceUpdate";
		}

		Form.enable("main_toolbar_form");

		if (!delayed)
			if (!this.setFeedExpandoIcon(feed, is_cat,
				(is_cat) ? 'images/indicator_tiny.gif' : 'images/indicator_white.gif'))
				notify_progress("Loading, please wait...", true);

		query.cat = is_cat;

		this.setActiveFeedId(feed, is_cat);

		if (viewfeed_debug) {
			window.open("backend.php?" +
				dojo.objectToQuery(
					Object.assign({debug: 1, csrf_token: getInitParam("csrf_token")}, query)
				));
		}

		window.clearTimeout(this._viewfeed_wait_timeout);
		this._viewfeed_wait_timeout = window.setTimeout(() => {
			Headlines.catchupBatchedArticles(() => {
				xhrPost("backend.php", query, (transport) => {
					try {
						this.setFeedExpandoIcon(feed, is_cat, 'images/blank_icon.gif');
						Headlines.onLoaded(transport, offset);
						PluginHost.run(PluginHost.HOOK_FEED_LOADED, [feed, is_cat]);
					} catch (e) {
						exception_error(e);
					}
				});
			});
		}, delayed ? 250 : 0);
	},
	catchupAllFeeds: function() {
		const str = __("Mark all articles as read?");

		if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {

			notify_progress("Marking all feeds as read...");

			xhrPost("backend.php", {op: "feeds", method: "catchupAll"}, () => {
				this.requestCounters(true);
				this.viewCurrentFeed();
			});

			App.global_unread = 0;
			App.updateTitle();
		}
	},
	decrementFeedCounter: function(feed, is_cat) {
		let ctr = this.getFeedUnread(feed, is_cat);

		if (ctr > 0) {
			this.setFeedUnread(feed, is_cat, ctr - 1);
			App.global_unread -= 1;
			App.updateTitle();

			if (!is_cat) {
				const cat = parseInt(this.getFeedCategory(feed));

				if (!isNaN(cat)) {
					ctr = this.getFeedUnread(cat, true);

					if (ctr > 0) {
						this.setFeedUnread(cat, true, ctr - 1);
					}
				}
			}
		}
	},
	catchupFeed: function(feed, is_cat, mode) {
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

		const mark_what = this.last_search_query && this.last_search_query[0] ? __("search results") : __("all articles");
		const fn = this.getFeedName(feed, is_cat);

		str = str.replace("%s", fn)
			.replace("%w", mark_what);

		if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
			return;
		}

		const catchup_query = {
			op: 'rpc', method: 'catchupFeed', feed_id: feed,
			is_cat: is_cat, mode: mode, search_query: this.last_search_query[0],
			search_lang: this.last_search_query[1]
		};

		notify_progress("Loading, please wait...", true);

		xhrPost("backend.php", catchup_query, (transport) => {
			Utils.handleRpcJson(transport);

			const show_next_feed = getInitParam("on_catchup_show_next_feed") == "1";

			if (show_next_feed) {
				const nuf = this.getNextUnreadFeed(feed, is_cat);

				if (nuf) {
					this.viewfeed({feed: nuf, is_cat: is_cat});
				}
			} else if (feed == this.getActiveFeedId() && is_cat == this.activeFeedIsCat()) {
				this.viewCurrentFeed();
			}

			notify("");
		});
	},
	catchupCurrentFeed: function(mode) {
		this.catchupFeed(this.getActiveFeedId(), this.activeFeedIsCat(), mode);
	},
	catchupFeedInGroup: function(id) {
		const title = this.getFeedName(id);

		const str = __("Mark all articles in %s as read?").replace("%s", title);

		if (getInitParam("confirm_feed_catchup") != 1 || confirm(str)) {

			const rows = $$("#headlines-frame > div[id*=RROW][data-orig-feed-id='" + id + "']");

			if (rows.length > 0) {

				rows.each(function (row) {
					row.removeClassName("Unread");

					if (row.getAttribute("data-article-id") != Article.getActiveArticleId()) {
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

				Headlines.updateFloatingTitle(true);
			}

			notify_progress("Loading, please wait...", true);

			xhrPost("backend.php", {op: "rpc", method: "catchupFeed", feed_id: id, is_cat: false}, (transport) => {
				Utils.handleRpcJson(transport);
			});
		}
	},
	getFeedUnread: function(feed, is_cat) {
		try {
			const tree = dijit.byId("feedTree");

			if (tree && tree.model)
				return tree.model.getFeedUnread(feed, is_cat);

		} catch (e) {
			//
		}

		return -1;
	},
	getFeedCategory: function(feed) {
		try {
			const tree = dijit.byId("feedTree");

			if (tree && tree.model)
				return tree.getFeedCategory(feed);

		} catch (e) {
			//
		}

		return false;
	},
	getFeedName: function(feed, is_cat) {
		if (isNaN(feed)) return feed; // it's a tag

		const tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.getFeedValue(feed, is_cat, 'name');
	},
	setFeedUnread: function(feed, is_cat, unread) {
		const tree = dijit.byId("feedTree");

		if (tree && tree.model)
			return tree.model.setFeedUnread(feed, is_cat, unread);
	},
	setFeedValue: function(feed, is_cat, key, value) {
		try {
			const tree = dijit.byId("feedTree");

			if (tree && tree.model)
				return tree.model.setFeedValue(feed, is_cat, key, value);

		} catch (e) {
			//
		}
	},
	getFeedValue: function(feed, is_cat, key) {
		try {
			const tree = dijit.byId("feedTree");

			if (tree && tree.model)
				return tree.model.getFeedValue(feed, is_cat, key);

		} catch (e) {
			//
		}
		return '';
	},
	setFeedIcon: function(feed, is_cat, src) {
		const tree = dijit.byId("feedTree");

		if (tree) return tree.setFeedIcon(feed, is_cat, src);
	},
	setFeedExpandoIcon: function(feed, is_cat, src) {
		const tree = dijit.byId("feedTree");

		if (tree) return tree.setFeedExpandoIcon(feed, is_cat, src);

		return false;
	},
	getNextUnreadFeed: function(feed, is_cat) {
		const tree = dijit.byId("feedTree");
		const nuf = tree.model.getNextUnreadFeed(feed, is_cat);

		if (nuf)
			return tree.model.store.getValue(nuf, 'bare_id');
	},
	search: function() {
		const query = "backend.php?op=feeds&method=search&param=" +
			param_escape(Feeds.getActiveFeedId() + ":" + Feeds.activeFeedIsCat());

		if (dijit.byId("searchDlg"))
			dijit.byId("searchDlg").destroyRecursive();

		const dialog = new dijit.Dialog({
			id: "searchDlg",
			title: __("Search"),
			style: "width: 600px",
			execute: function () {
				if (this.validate()) {
					Feeds._search_query = this.attr('value');
					this.hide();
					Feeds.viewCurrentFeed();
				}
			},
			href: query
		});

		dialog.show();
	},
	updateRandomFeed: function() {
		console.log("in update_random_feed");

		xhrPost("backend.php", {op: "rpc", method: "updateRandomFeed"}, (transport) => {
			Utils.handleRpcJson(transport, true);
		});
	},
};

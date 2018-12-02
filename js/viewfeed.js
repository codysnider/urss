/* global dijit, __, ngettext, notify */

const ArticleCache = {
	has_storage: 'sessionStorage' in window && window['sessionStorage'] !== null,
	set: function(id, obj) {
		if (this.has_storage)
			try {
				sessionStorage["article:" + id] = obj;
			} catch (e) {
				sessionStorage.clear();
			}
	},
	get: function(id) {
		if (this.has_storage)
			return sessionStorage["article:" + id];
	},
	clear: function() {
		if (this.has_storage)
			sessionStorage.clear();
	},
	del: function(id) {
		if (this.has_storage)
			sessionStorage.removeItem("article:" + id);
	},
};

const Article = {
	_active_article_id: 0,
	selectionSetScore: function() {
		const ids = Headlines.getSelected();

		if (ids.length > 0) {
			console.log(ids);

			const score = prompt(__("Please enter new score for selected articles:"));

			if (score != undefined) {
				const query = {
					op: "article", method: "setScore", id: ids.toString(),
					score: score
				};

				xhrJson("backend.php", query, (reply) => {
					if (reply) {
						reply.id.each((id) => {
							const row = $("RROW-" + id);

							if (row) {
								const pic = row.getElementsByClassName("score-pic")[0];

								if (pic) {
									pic.src = pic.src.replace(/score_.*?\.png/,
										reply["score_pic"]);
									pic.setAttribute("score", reply["score"]);
								}
							}
						});
					}
				});
			}

		} else {
			alert(__("No articles are selected."));
		}
	},
	setScore: function(id, pic) {
		const score = pic.getAttribute("score");

		const new_score = prompt(__("Please enter new score for this article:"), score);

		if (new_score != undefined) {
			const query = {op: "article", method: "setScore", id: id, score: new_score};

			xhrJson("backend.php", query, (reply) => {
				if (reply) {
					pic.src = pic.src.replace(/score_.*?\.png/, reply["score_pic"]);
					pic.setAttribute("score", new_score);
					pic.setAttribute("title", new_score);
				}
			});
		}
	},
	cdmUnsetActive: function(event) {
		const row = $("RROW-" + Article.getActive());

		if (row) {
			row.removeClassName("active");
			const cb = dijit.getEnclosingWidget(row.select(".rchk")[0]);

			if (cb && !row.hasClassName("Selected"))
				cb.attr("checked", false);

			Article.setActive(0);

			if (event)
				event.stopPropagation();

			return false;
		}
	},
	close: function () {
		if (dijit.byId("content-insert"))
			dijit.byId("headlines-wrap-inner").removeChild(
				dijit.byId("content-insert"));
	},
	displayUrl: function (id) {
		const query = {op: "rpc", method: "getlinktitlebyid", id: id};

		xhrJson("backend.php", query, (reply) => {
			if (reply && reply.link) {
				prompt(__("Article URL:"), reply.link);
			}
		});
	},
	openInNewWindow: function (id) {
		const w = window.open("");
		w.opener = null;
		w.location = "backend.php?op=article&method=redirect&id=" + id;

		Article.setActive(id);
	},
	render: function (article) {
		Utils.cleanupMemory("content-insert");

		dijit.byId("headlines-wrap-inner").addChild(
			dijit.byId("content-insert"));

		const c = dijit.byId("content-insert");

		try {
			c.domNode.scrollTop = 0;
		} catch (e) {
		}

		c.attr('content', article);
		PluginHost.run(PluginHost.HOOK_ARTICLE_RENDERED, c.domNode);

		Headlines.correctHeadlinesOffset(Article.getActive());

		try {
			c.focus();
		} catch (e) {
		}
	},
	view: function(id, noexpand) {
		this.setActive(id);

		if (!noexpand) {
			console.log("loading article", id);

			const cids = [];

			/* only request uncached articles */

			this.getRelativeIds(id).each((n) => {
				if (!ArticleCache.get(n))
					cids.push(n);
			});

			const cached_article = ArticleCache.get(id);

			if (cached_article) {
				console.log('rendering cached', id);
				this.render(cached_article);
				return false;
			}

			xhrPost("backend.php", {op: "article", method: "view", id: id, cids: cids.toString()}, (transport) => {
				try {
					const reply = Utils.handleRpcJson(transport);

					if (reply) {

						reply.each(function (article) {
							if (Article.getActive() == article['id']) {
								Article.render(article['content']);
							}
							ArticleCache.set(article['id'], article['content']);
						});

					} else {
						console.error("Invalid object received: " + transport.responseText);

						Article.render("<div class='whiteBox'>" +
							__('Could not display article (invalid object received - see error console for details)') + "</div>");
					}

					//const unread_in_buffer = $$("#headlines-frame > div[id*=RROW][class*=Unread]").length;
					//request_counters(unread_in_buffer == 0);

					notify("");

				} catch (e) {
					exception_error(e);
				}
			})
		}

		return false;
	},
	editTags: function(id) {
		const query = "backend.php?op=article&method=editArticleTags&param=" + param_escape(id);

		if (dijit.byId("editTagsDlg"))
			dijit.byId("editTagsDlg").destroyRecursive();

		const dialog = new dijit.Dialog({
			id: "editTagsDlg",
			title: __("Edit article Tags"),
			style: "width: 600px",
			execute: function () {
				if (this.validate()) {
					notify_progress("Saving article tags...", true);

					xhrPost("backend.php", this.attr('value'), (transport) => {
						try {
							notify('');
							dialog.hide();

							const data = JSON.parse(transport.responseText);

							if (data) {
								const id = data.id;

								const tags = $("ATSTR-" + id);
								const tooltip = dijit.byId("ATSTRTIP-" + id);

								if (tags) tags.innerHTML = data.content;
								if (tooltip) tooltip.attr('label', data.content_full);
							}
						} catch (e) {
							exception_error(e);
						}
					});
				}
			},
			href: query
		});

		const tmph = dojo.connect(dialog, 'onLoad', function () {
			dojo.disconnect(tmph);

			new Ajax.Autocompleter('tags_str', 'tags_choices',
				"backend.php?op=article&method=completeTags",
				{tokens: ',', paramName: "search"});
		});

		dialog.show();
	},
	cdmScrollToId: function(id, force) {
		const ctr = $("headlines-frame");
		const e = $("RROW-" + id);

		if (!e || !ctr) return;

		if (force || e.offsetTop + e.offsetHeight > (ctr.scrollTop + ctr.offsetHeight) ||
			e.offsetTop < ctr.scrollTop) {

			// expanded cdm has a 4px margin now
			ctr.scrollTop = parseInt(e.offsetTop) - 4;

			Element.hide("floatingTitle");
		}
	},
	setActive: function(id) {
		console.log("setActive", id);

		$$("div[id*=RROW][class*=active]").each((e) => {
			e.removeClassName("active");

			if (!e.hasClassName("Selected")) {
				const cb = dijit.getEnclosingWidget(e.select(".rchk")[0]);
				if (cb) cb.attr("checked", false);
			}
		});

		this._active_article_id = id;

		const row = $("RROW-" + id);

		if (row) {
			if (row.hasAttribute("data-content")) {
				console.log("unpacking: " + row.id);

				row.select(".content-inner")[0].innerHTML = row.getAttribute("data-content");
				row.removeAttribute("data-content");

				PluginHost.run(PluginHost.HOOK_ARTICLE_RENDERED_CDM, row);
			}

			if (row.hasClassName("Unread")) {

				Headlines.catchupBatched(() => {
					Feeds.decrementFeedCounter(Feeds.getActiveFeedId(), Feeds.activeFeedIsCat());
					Headlines.toggleUnread(id, 0);
					Headlines.updateFloatingTitle(true);
				});

			}

			row.addClassName("active");

			if (!row.hasClassName("Selected")) {
				const cb = dijit.getEnclosingWidget(row.select(".rchk")[0]);
				if (cb) cb.attr("checked", true);
			}

			PluginHost.run(PluginHost.HOOK_ARTICLE_SET_ACTIVE, this._active_article_id);
		}

		Headlines.updateSelectedPrompt();
	},
	getActive: function() {
		return this._active_article_id;
	},
	scroll: function(offset) {
		if (!App.isCombinedMode()) {
			const ci = $("content-insert");
			if (ci) {
				ci.scrollTop += offset;
			}
		} else {
			const hi = $("headlines-frame");
			if (hi) {
				hi.scrollTop += offset;
			}

		}
	},
	getRelativeIds: function(id, limit) {

		const tmp = [];

		if (!limit) limit = 6; //3

		const ids = Headlines.getLoaded();

		for (let i = 0; i < ids.length; i++) {
			if (ids[i] == id) {
				for (let k = 1; k <= limit; k++) {
					//if (i > k-1) tmp.push(ids[i-k]);
					if (i < ids.length - k) tmp.push(ids[i + k]);
				}
				break;
			}
		}

		return tmp;
	},
	mouseIn: function(id) {
		this.post_under_pointer = id;
	},
	mouseOut: function(id) {
		this.post_under_pointer = false;
	},
	getUnderPointer: function() {
		return this.post_under_pointer;
	}
};

const Headlines = {
	vgroup_last_feed: undefined,
	_headlines_scroll_timeout: 0,
	loaded_article_ids: [],
	current_first_id: 0,
	catchup_id_batch: [],
	click: function(event, id, in_body) {
		in_body = in_body || false;

		if (App.isCombinedMode()) {

			if (!in_body && (event.ctrlKey || id == Article.getActive() || getInitParam("cdm_expanded"))) {
				Article.openInNewWindow(id);
			}

			Article.setActive(id);

			if (!getInitParam("cdm_expanded"))
				Article.cdmScrollToId(id);

			return in_body;

		} else {
			if (event.ctrlKey) {
				Article.openInNewWindow(id);
				Article.setActive(id);
			} else {
				Article.view(id);
			}

			return false;
		}
	},
	initScrollHandler: function() {
		$("headlines-frame").onscroll = (event) => {
			clearTimeout(this._headlines_scroll_timeout);
			this._headlines_scroll_timeout = window.setTimeout(function() {
				//console.log('done scrolling', event);
				Headlines.scrollHandler();
			}, 50);
		}
	},
	loadMore: function() {
		const view_mode = document.forms["main_toolbar_form"].view_mode.value;
		const unread_in_buffer = $$("#headlines-frame > div[id*=RROW][class*=Unread]").length;
		const num_all = $$("#headlines-frame > div[id*=RROW]").length;
		const num_unread = Feeds.getFeedUnread(Feeds.getActiveFeedId(), Feeds.activeFeedIsCat());

		// TODO implement marked & published

		let offset = num_all;

		switch (view_mode) {
			case "marked":
			case "published":
				console.warn("loadMore: ", view_mode, "not implemented");
				break;
			case "unread":
				offset = unread_in_buffer;
				break;
			case "adaptive":
				if (!(Feeds.getActiveFeedId() == -1 && !Feeds.activeFeedIsCat()))
					offset = num_unread > 0 ? unread_in_buffer : num_all;
				break;
		}

		console.log("loadMore, offset=", offset);

		Feeds.viewfeed({feed: Feeds.getActiveFeedId(), is_cat: Feeds.activeFeedIsCat(), offset: offset});
	},
	scrollHandler: function() {
		try {
			Headlines.unpackVisible();

			if (App.isCombinedMode()) {
				Headlines.updateFloatingTitle();

				// set topmost child in the buffer as active, but not if we're at the beginning (to prevent auto marking
				// first article as read all the time)
				if ($("headlines-frame").scrollTop != 0 &&
					getInitParam("cdm_expanded") && getInitParam("cdm_auto_catchup") == 1) {

					const rows = $$("#headlines-frame > div[id*=RROW]");

					for (let i = 0; i < rows.length; i++) {
						const row = rows[i];

						if ($("headlines-frame").scrollTop <= row.offsetTop &&
							row.offsetTop - $("headlines-frame").scrollTop < 100 &&
							row.getAttribute("data-article-id") != Article.getActive()) {

							Article.setActive(row.getAttribute("data-article-id"));
							break;
						}
					}
				}
			}

			if (!Feeds.infscroll_disabled) {
				const hsp = $("headlines-spacer");
				const container = $("headlines-frame");

				if (hsp && hsp.offsetTop - 250 <= container.scrollTop + container.offsetHeight) {

					hsp.innerHTML = "<span class='loading'><img src='images/indicator_tiny.gif'> " +
						__("Loading, please wait...") + "</span>";

					Headlines.loadMore();
					return;
				}
			}

			if (getInitParam("cdm_auto_catchup") == 1) {

				let rows = $$("#headlines-frame > div[id*=RROW][class*=Unread]");

				for (let i = 0; i < rows.length; i++) {
					const row = rows[i];

					if ($("headlines-frame").scrollTop > (row.offsetTop + row.offsetHeight / 2)) {
						const id = row.getAttribute("data-article-id")

						if (this.catchup_id_batch.indexOf(id) == -1)
							this.catchup_id_batch.push(id);

					} else {
						break;
					}
				}

				if (Feeds.infscroll_disabled) {
					const row = $$("#headlines-frame div[id*=RROW]").last();

					if (row && $("headlines-frame").scrollTop >
						(row.offsetTop + row.offsetHeight - 50)) {

						console.log("we seem to be at an end");

						if (getInitParam("on_catchup_show_next_feed") == "1") {
							Feeds.openNextUnreadFeed();
						}
					}
				}
			}
		} catch (e) {
			console.warn("scrollHandler", e);
		}
	},
	updateFloatingTitle: function(unread_only) {
		if (!App.isCombinedMode()/* || !getInitParam("cdm_expanded")*/) return;

		const hf = $("headlines-frame");
		const elems = $$("#headlines-frame > div[id*=RROW]");
		const ft = $("floatingTitle");

		for (let i = 0; i < elems.length; i++) {
			const row = elems[i];

			if (row && row.offsetTop + row.offsetHeight > hf.scrollTop) {

				const header = row.select(".header")[0];
				const id = row.getAttribute("data-article-id");

				if (unread_only || id != ft.getAttribute("data-article-id")) {
					if (id != ft.getAttribute("data-article-id")) {

						ft.setAttribute("data-article-id", id);
						ft.innerHTML = header.innerHTML;
						ft.firstChild.innerHTML = "<img class='anchor marked-pic' src='images/page_white_go.png' " +
							"onclick=\"Article.cdmScrollToId(" + id + ", true)\">" + ft.firstChild.innerHTML;

						this.initFloatingMenu();

						const cb = ft.select(".rchk")[0];

						if (cb)
							cb.parentNode.removeChild(cb);
					}

					if (row.hasClassName("Unread"))
						ft.addClassName("Unread");
					else
						ft.removeClassName("Unread");

					PluginHost.run(PluginHost.HOOK_FLOATING_TITLE, row);
				}

				ft.style.marginRight = hf.offsetWidth - row.offsetWidth + "px";

				if (header.offsetTop + header.offsetHeight < hf.scrollTop + ft.offsetHeight - 5 &&
					row.offsetTop + row.offsetHeight >= hf.scrollTop + ft.offsetHeight - 5)
					new Effect.Appear(ft, {duration: 0.3});
				else
					Element.hide(ft);

				return;
			}
		}
	},
	unpackVisible: function() {
		if (!App.isCombinedMode() || !getInitParam("cdm_expanded")) return;

		const rows = $$("#headlines-frame div[id*=RROW][data-content]");
		const threshold = $("headlines-frame").scrollTop + $("headlines-frame").offsetHeight + 600;

		for (let i = 0; i < rows.length; i++) {
			const row = rows[i];

			if (row.offsetTop <= threshold) {
				console.log("unpacking: " + row.id);

				row.select(".content-inner")[0].innerHTML = row.getAttribute("data-content");
				row.removeAttribute("data-content");

				PluginHost.run(PluginHost.HOOK_ARTICLE_RENDERED_CDM, row);
			} else {
				break;
			}
		}
	},
	onLoaded: function(transport, offset) {
		const reply = Utils.handleRpcJson(transport);

		console.log("Headlines.onLoaded: offset=", offset);

		let is_cat = false;
		let feed_id = false;

		if (reply) {

			is_cat = reply['headlines']['is_cat'];
			feed_id = reply['headlines']['id'];
			Feeds.last_search_query = reply['headlines']['search_query'];

			if (feed_id != -7 && (feed_id != Feeds.getActiveFeedId() || is_cat != Feeds.activeFeedIsCat()))
				return;

			try {
				if (offset == 0) {
					$("headlines-frame").scrollTop = 0;

					Element.hide("floatingTitle");
					$("floatingTitle").setAttribute("data-article-id", 0);
					$("floatingTitle").innerHTML = "";
				}
			} catch (e) {
			}

			$("headlines-frame").removeClassName("cdm");
			$("headlines-frame").removeClassName("normal");

			$("headlines-frame").addClassName(App.isCombinedMode() ? "cdm" : "normal");

			const headlines_count = reply['headlines-info']['count'];
			Feeds.infscroll_disabled = parseInt(headlines_count) != 30;

			console.log('received', headlines_count, 'headlines, infscroll disabled=', Feeds.infscroll_disabled);

			this.vgroup_last_feed = reply['headlines-info']['vgroup_last_feed'];
			this.current_first_id = reply['headlines']['first_id'];

			if (offset == 0) {
				this.loaded_article_ids = [];

				dojo.html.set($("headlines-toolbar"),
					reply['headlines']['toolbar'],
					{parseContent: true});

				$("headlines-frame").innerHTML = '';

				let tmp = document.createElement("div");
				tmp.innerHTML = reply['headlines']['content'];
				dojo.parser.parse(tmp);

				while (tmp.hasChildNodes()) {
					const row = tmp.removeChild(tmp.firstChild);

					if (this.loaded_article_ids.indexOf(row.id) == -1 || row.hasClassName("feed-title")) {
						dijit.byId("headlines-frame").domNode.appendChild(row);

						this.loaded_article_ids.push(row.id);
					}
				}

				let hsp = $("headlines-spacer");

				if (!hsp) {
					hsp = document.createElement("div");
					hsp.id = "headlines-spacer";
				}

				dijit.byId('headlines-frame').domNode.appendChild(hsp);

				this.initHeadlinesMenu();

				if (Feeds.infscroll_disabled)
					hsp.innerHTML = "<a href='#' onclick='Feeds.openNextUnreadFeed()'>" +
						__("Click to open next unread feed.") + "</a>";

				if (Feeds._search_query) {
					$("feed_title").innerHTML += "<span id='cancel_search'>" +
						" (<a href='#' onclick='Feeds.cancelSearch()'>" + __("Cancel search") + "</a>)" +
						"</span>";
				}

			} else if (headlines_count > 0 && feed_id == Feeds.getActiveFeedId() && is_cat == Feeds.activeFeedIsCat()) {
				const c = dijit.byId("headlines-frame");
				//const ids = Headlines.getSelected();

				let hsp = $("headlines-spacer");

				if (hsp)
					c.domNode.removeChild(hsp);

				let tmp = document.createElement("div");
				tmp.innerHTML = reply['headlines']['content'];
				dojo.parser.parse(tmp);

				while (tmp.hasChildNodes()) {
					let row = tmp.removeChild(tmp.firstChild);

					if (this.loaded_article_ids.indexOf(row.id) == -1 || row.hasClassName("feed-title")) {
						dijit.byId("headlines-frame").domNode.appendChild(row);

						this.loaded_article_ids.push(row.id);
					}
				}

				if (!hsp) {
					hsp = document.createElement("div");
					hsp.id = "headlines-spacer";
				}

				c.domNode.appendChild(hsp);

				/* console.log("restore selected ids: " + ids);

				for (let i = 0; i < ids.length; i++) {
					markHeadline(ids[i]);
				} */

				this.initHeadlinesMenu();

				if (Feeds.infscroll_disabled) {
					hsp.innerHTML = "<a href='#' onclick='Feeds.openNextUnreadFeed()'>" +
						__("Click to open next unread feed.") + "</a>";
				}

			} else {
				console.log("no new headlines received");

				const first_id_changed = reply['headlines']['first_id_changed'];
				console.log("first id changed:" + first_id_changed);

				let hsp = $("headlines-spacer");

				if (hsp) {
					if (first_id_changed) {
						hsp.innerHTML = "<a href='#' onclick='Feeds.viewCurrentFeed()'>" +
							__("New articles found, reload feed to continue.") + "</a>";
					} else {
						hsp.innerHTML = "<a href='#' onclick='Feeds.openNextUnreadFeed()'>" +
							__("Click to open next unread feed.") + "</a>";
					}
				}
			}

		} else {
			console.error("Invalid object received: " + transport.responseText);
			dijit.byId("headlines-frame").attr('content', "<div class='whiteBox'>" +
				__('Could not update headlines (invalid object received - see error console for details)') +
				"</div>");
		}

		Feeds.infscroll_in_progress = 0;

		// this is used to auto-catchup articles if needed after infscroll request has finished,
		// unpack visible articles, fill buffer more, etc
		this.scrollHandler();

		notify("");
	},
	reverse: function() {
		const toolbar = document.forms["main_toolbar_form"];
		const order_by = dijit.getEnclosingWidget(toolbar.order_by);

		let value = order_by.attr('value');

		if (value == "date_reverse")
			value = "default";
		else
			value = "date_reverse";

		order_by.attr('value', value);

		Feeds.viewCurrentFeed();
	},
	selectionToggleUnread: function(params) {
		params = params || {};

		const cmode = params.cmode || 2;
		const callback = params.callback;
		const no_error = params.no_error || false;
		const ids = params.ids || Headlines.getSelected();

		if (ids.length == 0) {
			if (!no_error)
				alert(__("No articles are selected."));

			return;
		}

		ids.each((id) => {
			const row = $("RROW-" + id);

			if (row) {
				switch (cmode) {
					case 0:
						row.removeClassName("Unread");
						break;
					case 1:
						row.addClassName("Unread");
						break;
					case 2:
						row.toggleClassName("Unread");
				}
			}
		});

		const query = {
			op: "rpc", method: "catchupSelected",
			cmode: cmode, ids: ids.toString()
		};

		notify_progress("Loading, please wait...");

		xhrPost("backend.php", query, (transport) => {
			Utils.handleRpcJson(transport);
			if (callback) callback(transport);
		});
	},
	selectionToggleMarked: function(ids) {
		const rows = ids || Headlines.getSelected();

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		for (let i = 0; i < rows.length; i++) {
			this.toggleMark(rows[i], true, true);
		}

		const query = {
			op: "rpc", method: "markSelected",
			ids: rows.toString(), cmode: 2
		};

		xhrPost("backend.php", query, (transport) => {
			Utils.handleRpcJson(transport);
		});
	},
	selectionTogglePublished: function(ids) {
		const rows = ids || Headlines.getSelected();

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		for (let i = 0; i < rows.length; i++) {
			this.togglePub(rows[i], true);
		}

		if (rows.length > 0) {
			const query = {
				op: "rpc", method: "publishSelected",
				ids: rows.toString(), cmode: 2
			};

			xhrPost("backend.php", query, (transport) => {
				Utils.handleRpcJson(transport);
			});
		}
	},
	toggleMark: function(id, client_only) {
		const query = {op: "rpc", id: id, method: "mark"};
		const row = $("RROW-" + id);

		if (row) {
			const imgs = $$("img[class*=marked-pic][class*=marked-" + id + "]");

			imgs.each((img) => {
				if (!row.hasClassName("marked")) {
					img.src = img.src.replace("mark_unset", "mark_set");
					query.mark = 1;
				} else {
					img.src = img.src.replace("mark_set", "mark_unset");
					query.mark = 0;
				}
			});

			row.toggleClassName("marked");

			if (!client_only)
				xhrPost("backend.php", query, (transport) => {
					Utils.handleRpcJson(transport);
				});
		}
	},
	togglePub: function(id, client_only) {
		const row = $("RROW-" + id);

		if (row) {
			const query = {op: "rpc", id: id, method: "publ"};

			const imgs = $$("img[class*=pub-pic][class*=pub-" + id + "]");

			imgs.each((img) => {
				if (!row.hasClassName("published")) {
					img.src = img.src.replace("pub_unset", "pub_set");
					query.pub = 1;
				} else {
					img.src = img.src.replace("pub_set", "pub_unset");
					query.pub = 0;
				}
			});

			row.toggleClassName("published");

			if (!client_only)
				xhrPost("backend.php", query, (transport) => {
					Utils.handleRpcJson(transport);
				});

		}
	},
	move: function(mode, noscroll, noexpand) {
		const rows = Headlines.getLoaded();

		let prev_id = false;
		let next_id = false;

		if (!$('RROW-' + Article.getActive())) {
			Article.setActive(0);
		}

		if (!Article.getActive()) {
			next_id = rows[0];
			prev_id = rows[rows.length - 1]
		} else {
			for (let i = 0; i < rows.length; i++) {
				if (rows[i] == Article.getActive()) {

					// Account for adjacent identical article ids.
					if (i > 0) prev_id = rows[i - 1];

					for (let j = i + 1; j < rows.length; j++) {
						if (rows[j] != Article.getActive()) {
							next_id = rows[j];
							break;
						}
					}
					break;
				}
			}
		}

		console.log("cur: " + Article.getActive() + " next: " + next_id);

		if (mode == "next") {
			if (next_id || Article.getActive()) {
				if (App.isCombinedMode()) {

					const article = $("RROW-" + Article.getActive());
					const ctr = $("headlines-frame");

					if (!noscroll && article && article.offsetTop + article.offsetHeight >
						ctr.scrollTop + ctr.offsetHeight) {

						Article.scroll(ctr.offsetHeight / 4);

					} else if (next_id) {
						Article.setActive(next_id);
						Article.cdmScrollToId(next_id, true);
					}

				} else if (next_id) {
					Headlines.correctHeadlinesOffset(next_id);
					Article.view(next_id, noexpand);
				}
			}
		}

		if (mode == "prev") {
			if (prev_id || Article.getActive()) {
				if (App.isCombinedMode()) {

					const article = $("RROW-" + Article.getActive());
					const prev_article = $("RROW-" + prev_id);
					const ctr = $("headlines-frame");

					if (!noscroll && article && article.offsetTop < ctr.scrollTop) {
						Article.scroll(-ctr.offsetHeight / 3);
					} else if (!noscroll && prev_article &&
						prev_article.offsetTop < ctr.scrollTop) {
						Article.scroll(-ctr.offsetHeight / 4);
					} else if (prev_id) {
						Article.setActive(prev_id);
						Article.cdmScrollToId(prev_id, noscroll);
					}

				} else if (prev_id) {
					Headlines.correctHeadlinesOffset(prev_id);
					Article.view(prev_id, noexpand);
				}
			}
		}
	},
	updateSelectedPrompt: function() {
		const count = Headlines.getSelected().length;
		const elem = $("selected_prompt");

		if (elem) {
			elem.innerHTML = ngettext("%d article selected",
				"%d articles selected", count).replace("%d", count);

			count > 0 ? Element.show(elem) : Element.hide(elem);
		}
	},
	toggleUnread: function(id, cmode) {
		const row = $("RROW-" + id);

		if (row) {
			const origClassName = row.className;

			if (cmode == undefined) cmode = 2;

			switch (cmode) {
				case 0:
					row.removeClassName("Unread");
					break;
				case 1:
					row.addClassName("Unread");
					break;
				case 2:
					row.toggleClassName("Unread");
					break;
			}

			if (row.className != origClassName)
				xhrPost("backend.php",
					{op: "rpc", method: "catchupSelected", cmode: cmode, ids: id}, (transport) => {
						Utils.handleRpcJson(transport);
					});
		}
	},
	selectionRemoveLabel: function(id, ids) {
		if (!ids) ids = Headlines.getSelected();

		if (ids.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		const query = {
			op: "article", method: "removeFromLabel",
			ids: ids.toString(), lid: id
		};

		xhrPost("backend.php", query, (transport) => {
			Utils.handleRpcJson(transport);
			this.onLabelsUpdated(transport);
		});
	},
	selectionAssignLabel: function(id, ids) {
		if (!ids) ids = Headlines.getSelected();

		if (ids.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		const query = {
			op: "article", method: "assignToLabel",
			ids: ids.toString(), lid: id
		};

		xhrPost("backend.php", query, (transport) => {
			Utils.handleRpcJson(transport);
			this.onLabelsUpdated(transport);
		});
	},
	deleteSelection: function() {
		const rows = Headlines.getSelected();

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		const fn = Feeds.getFeedName(Feeds.getActiveFeedId(), Feeds.activeFeedIsCat());
		let str;

		if (Feeds.getActiveFeedId() != 0) {
			str = ngettext("Delete %d selected article in %s?", "Delete %d selected articles in %s?", rows.length);
		} else {
			str = ngettext("Delete %d selected article?", "Delete %d selected articles?", rows.length);
		}

		str = str.replace("%d", rows.length);
		str = str.replace("%s", fn);

		if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
			return;
		}

		const query = {op: "rpc", method: "delete", ids: rows.toString()};

		xhrPost("backend.php", query, (transport) => {
			Utils.handleRpcJson(transport);
			Feeds.viewCurrentFeed();
		});
	},
	getSelected: function() {
		const rv = [];

		$$("#headlines-frame > div[id*=RROW][class*=Selected]").each(
			function (child) {
				rv.push(child.getAttribute("data-article-id"));
			});

		// consider active article a honorary member of selected articles
		if (Article.getActive())
			rv.push(Article.getActive());

		return rv.uniq();
	},
	getLoaded: function() {
		const rv = [];

		const children = $$("#headlines-frame > div[id*=RROW-]");

		children.each(function (child) {
			if (Element.visible(child)) {
				rv.push(child.getAttribute("data-article-id"));
			}
		});

		return rv;
	},
	selectArticles: function(mode) {
		// mode = all,none,unread,invert,marked,published
		let query = "#headlines-frame > div[id*=RROW]";

		switch (mode) {
			case "none":
			case "all":
			case "invert":
				break;
			case "marked":
				query += "[class*=marked]";
				break;
			case "published":
				query += "[class*=published]";
				break;
			case "unread":
				query += "[class*=Unread]";
				break;
			default:
				console.warn("selectArticles: unknown mode", mode);
		}

		const rows = $$(query);

		for (let i = 0; i < rows.length; i++) {
			const row = rows[i];
			const cb = dijit.getEnclosingWidget(row.select(".rchk")[0]);

			switch (mode) {
				case "none":
					row.removeClassName("Selected");

					if (!row.hasClassName("active"))
						cb.attr("checked", false);
					break;
				case "invert":
					if (row.hasClassName("Selected")) {
						row.removeClassName("Selected");

						if (!row.hasClassName("active"))
							cb.attr("checked", false);
					} else {
						row.addClassName("Selected");
						cb.attr("checked", true);
					}
					break;
				default:
					row.addClassName("Selected");
					cb.attr("checked", true);
			}

			Headlines.updateSelectedPrompt();
		}
	},
	archiveSelection: function() {
		const rows = Headlines.getSelected();

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		const fn = Feeds.getFeedName(Feeds.getActiveFeedId(), Feeds.activeFeedIsCat());
		let str;
		let op;

		if (Feeds.getActiveFeedId() != 0) {
			str = ngettext("Archive %d selected article in %s?", "Archive %d selected articles in %s?", rows.length);
			op = "archive";
		} else {
			str = ngettext("Move %d archived article back?", "Move %d archived articles back?", rows.length);
			str += " " + __("Please note that unstarred articles might get purged on next feed update.");

			op = "unarchive";
		}

		str = str.replace("%d", rows.length);
		str = str.replace("%s", fn);

		if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
			return;
		}

		for (let i = 0; i < rows.length; i++) {
			ArticleCache.del(rows[i]);
		}

		const query = {op: "rpc", method: op, ids: rows.toString()};

		xhrPost("backend.php", query, (transport) => {
			Utils.handleRpcJson(transport);
			Feeds.viewCurrentFeed();
		});
	},
	catchupSelection: function() {
		const rows = Headlines.getSelected();

		if (rows.length == 0) {
			alert(__("No articles are selected."));
			return;
		}

		const fn = Feeds.getFeedName(Feeds.getActiveFeedId(), Feeds.activeFeedIsCat());

		let str = ngettext("Mark %d selected article in %s as read?", "Mark %d selected articles in %s as read?", rows.length);

		str = str.replace("%d", rows.length);
		str = str.replace("%s", fn);

		if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
			return;
		}

		Headlines.selectionToggleUnread({callback: Feeds.viewCurrentFeed, no_error: 1});
	},
	catchupBatched: function(callback) {
		console.log("catchupBatched, size=", this.catchup_id_batch.length);

		if (this.catchup_id_batch.length > 0) {

			// make a copy of the array
			const batch = this.catchup_id_batch.slice();
			const query = {
				op: "rpc", method: "catchupSelected",
				cmode: 0, ids: batch.toString()
			};

			xhrPost("backend.php", query, (transport) => {
				const reply = Utils.handleRpcJson(transport);

				if (reply) {
					const batch = reply.ids;

					batch.each(function (id) {
						const elem = $("RROW-" + id);
						if (elem) elem.removeClassName("Unread");
						Headlines.catchup_id_batch.remove(id);
					});
				}

				Headlines.updateFloatingTitle(true);

				if (callback) callback();
			});
		} else {
			if (callback) callback();
		}
	},
	catchupRelativeTo: function(below, id) {

		if (!id) id = Article.getActive();

		if (!id) {
			alert(__("No article is selected."));
			return;
		}

		const visible_ids = this.getLoaded();

		const ids_to_mark = [];

		if (!below) {
			for (let i = 0; i < visible_ids.length; i++) {
				if (visible_ids[i] != id) {
					const e = $("RROW-" + visible_ids[i]);

					if (e && e.hasClassName("Unread")) {
						ids_to_mark.push(visible_ids[i]);
					}
				} else {
					break;
				}
			}
		} else {
			for (let i = visible_ids.length - 1; i >= 0; i--) {
				if (visible_ids[i] != id) {
					const e = $("RROW-" + visible_ids[i]);

					if (e && e.hasClassName("Unread")) {
						ids_to_mark.push(visible_ids[i]);
					}
				} else {
					break;
				}
			}
		}

		if (ids_to_mark.length == 0) {
			alert(__("No articles found to mark"));
		} else {
			const msg = ngettext("Mark %d article as read?", "Mark %d articles as read?", ids_to_mark.length).replace("%d", ids_to_mark.length);

			if (getInitParam("confirm_feed_catchup") != 1 || confirm(msg)) {

				for (var i = 0; i < ids_to_mark.length; i++) {
					var e = $("RROW-" + ids_to_mark[i]);
					e.removeClassName("Unread");
				}

				const query = {
					op: "rpc", method: "catchupSelected",
					cmode: 0, ids: ids_to_mark.toString()
				};

				xhrPost("backend.php", query, (transport) => {
					Utils.handleRpcJson(transport);
				});
			}
		}
	},
	onLabelsUpdated: function(transport) {
		const data = JSON.parse(transport.responseText);

		if (data) {
			data['info-for-headlines'].each(function (elem) {
				$$(".HLLCTR-" + elem.id).each(function (ctr) {
					ctr.innerHTML = elem.labels;
				});
			});
		}
	},
	onActionChanged: function(elem) {
		eval(elem.value);
		elem.attr('value', 'false');
	},
	correctHeadlinesOffset: function(id) {
		const container = $("headlines-frame");
		const row = $("RROW-" + id);

		if (!container || !row) return;

		const viewport = container.offsetHeight;

		const rel_offset_top = row.offsetTop - container.scrollTop;
		const rel_offset_bottom = row.offsetTop + row.offsetHeight - container.scrollTop;

		//console.log("Rtop: " + rel_offset_top + " Rbtm: " + rel_offset_bottom);
		//console.log("Vport: " + viewport);

		if (rel_offset_top <= 0 || rel_offset_top > viewport) {
			container.scrollTop = row.offsetTop;
		} else if (rel_offset_bottom > viewport) {
			container.scrollTop = row.offsetTop + row.offsetHeight - viewport;
		}
	},
	initFloatingMenu: function() {
		if (!dijit.byId("floatingMenu")) {

			const menu = new dijit.Menu({
				id: "floatingMenu",
				targetNodeIds: ["floatingTitle"]
			});

			this.headlinesMenuCommon(menu);

			menu.startup();
		}
	},
	headlinesMenuCommon: function(menu) {

		menu.addChild(new dijit.MenuItem({
			label: __("Open original article"),
			onClick: function (event) {
				Article.openInNewWindow(this.getParent().currentTarget.getAttribute("data-article-id"));
			}
		}));

		menu.addChild(new dijit.MenuItem({
			label: __("Display article URL"),
			onClick: function (event) {
				Article.displayUrl(this.getParent().currentTarget.getAttribute("data-article-id"));
			}
		}));

		menu.addChild(new dijit.MenuSeparator());

		menu.addChild(new dijit.MenuItem({
			label: __("Toggle unread"),
			onClick: function () {

				let ids = Headlines.getSelected();
				// cast to string
				const id = (this.getParent().currentTarget.getAttribute("data-article-id")) + "";
				ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

				Headlines.selectionToggleUnread({ids: ids, no_error: 1});
			}
		}));

		menu.addChild(new dijit.MenuItem({
			label: __("Toggle starred"),
			onClick: function () {
				let ids = Headlines.getSelected();
				// cast to string
				const id = (this.getParent().currentTarget.getAttribute("data-article-id")) + "";
				ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

				Headlines.selectionToggleMarked(ids);
			}
		}));

		menu.addChild(new dijit.MenuItem({
			label: __("Toggle published"),
			onClick: function () {
				let ids = Headlines.getSelected();
				// cast to string
				const id = (this.getParent().currentTarget.getAttribute("data-article-id")) + "";
				ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

				Headlines.selectionTogglePublished(ids);
			}
		}));

		menu.addChild(new dijit.MenuSeparator());

		menu.addChild(new dijit.MenuItem({
			label: __("Mark above as read"),
			onClick: function () {
				Headlines.catchupRelativeTo(0, this.getParent().currentTarget.getAttribute("data-article-id"));
			}
		}));

		menu.addChild(new dijit.MenuItem({
			label: __("Mark below as read"),
			onClick: function () {
				Headlines.catchupRelativeTo(1, this.getParent().currentTarget.getAttribute("data-article-id"));
			}
		}));


		const labels = getInitParam("labels");

		if (labels && labels.length) {

			menu.addChild(new dijit.MenuSeparator());

			const labelAddMenu = new dijit.Menu({ownerMenu: menu});
			const labelDelMenu = new dijit.Menu({ownerMenu: menu});

			labels.each(function (label) {
				const bare_id = label.id;
				const name = label.caption;

				labelAddMenu.addChild(new dijit.MenuItem({
					label: name,
					labelId: bare_id,
					onClick: function () {

						let ids = Headlines.getSelected();
						// cast to string
						const id = (this.getParent().ownerMenu.currentTarget.getAttribute("data-article-id")) + "";

						ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

						Headlines.selectionAssignLabel(this.labelId, ids);
					}
				}));

				labelDelMenu.addChild(new dijit.MenuItem({
					label: name,
					labelId: bare_id,
					onClick: function () {
						let ids = Headlines.getSelected();
						// cast to string
						const id = (this.getParent().ownerMenu.currentTarget.getAttribute("data-article-id")) + "";

						ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

						Headlines.selectionRemoveLabel(this.labelId, ids);
					}
				}));

			});

			menu.addChild(new dijit.PopupMenuItem({
				label: __("Assign label"),
				popup: labelAddMenu
			}));

			menu.addChild(new dijit.PopupMenuItem({
				label: __("Remove label"),
				popup: labelDelMenu
			}));

		}
	},
	initHeadlinesMenu: function() {
		if (!dijit.byId("headlinesMenu")) {

			const menu = new dijit.Menu({
				id: "headlinesMenu",
				targetNodeIds: ["headlines-frame"],
				selector: ".hlMenuAttach"
			});

			this.headlinesMenuCommon(menu);

			menu.startup();
		}

		/* vgroup feed title menu */

		if (!dijit.byId("headlinesFeedTitleMenu")) {

			const menu = new dijit.Menu({
				id: "headlinesFeedTitleMenu",
				targetNodeIds: ["headlines-frame"],
				selector: "div.cdmFeedTitle"
			});

			menu.addChild(new dijit.MenuItem({
				label: __("Select articles in group"),
				onClick: function (event) {
					Headlines.selectArticles("all",
						"#headlines-frame > div[id*=RROW]" +
						"[data-orig-feed-id='" + this.getParent().currentTarget.getAttribute("data-feed-id") + "']");

				}
			}));

			menu.addChild(new dijit.MenuItem({
				label: __("Mark group as read"),
				onClick: function () {
					Headlines.selectArticles("none");
					Headlines.selectArticles("all",
						"#headlines-frame > div[id*=RROW]" +
						"[data-orig-feed-id='" + this.getParent().currentTarget.getAttribute("data-feed-id") + "']");

					Headlines.catchupSelection();
				}
			}));

			menu.addChild(new dijit.MenuItem({
				label: __("Mark feed as read"),
				onClick: function () {
					Feeds.catchupFeedInGroup(this.getParent().currentTarget.getAttribute("data-feed-id"));
				}
			}));

			menu.addChild(new dijit.MenuItem({
				label: __("Edit feed"),
				onClick: function () {
					CommonDialogs.editFeed(this.getParent().currentTarget.getAttribute("data-feed-id"));
				}
			}));

			menu.startup();
		}
	}
};

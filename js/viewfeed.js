/* global dijit, __, ngettext */

let _active_article_id = 0;

let vgroup_last_feed = false;
let post_under_pointer = false;

let catchup_id_batch = [];
//let catchup_timeout_id = false;

//let cids_requested = [];
let loaded_article_ids = [];
let current_first_id = 0;
let last_search_query;

let has_storage = 'sessionStorage' in window && window['sessionStorage'] !== null;

function headlines_callback2(transport, offset) {
	const reply = handle_rpc_json(transport);

	console.log("headlines_callback2, offset=", offset);

	let is_cat = false;
	let feed_id = false;

	if (reply) {

		is_cat = reply['headlines']['is_cat'];
		feed_id = reply['headlines']['id'];
		last_search_query = reply['headlines']['search_query'];

		if (feed_id != -7 && (feed_id != getActiveFeedId() || is_cat != activeFeedIsCat()))
			return;

		try {
			if (offset == 0) {
				$("headlines-frame").scrollTop = 0;

				Element.hide("floatingTitle");
				$("floatingTitle").setAttribute("data-article-id", 0);
				$("floatingTitle").innerHTML = "";
			}
		} catch (e) { }

		$("headlines-frame").removeClassName("cdm");
		$("headlines-frame").removeClassName("normal");

		$("headlines-frame").addClassName(isCombinedMode() ? "cdm" : "normal");

		const headlines_count = reply['headlines-info']['count'];
		infscroll_disabled = parseInt(headlines_count) != 30;

		console.log('received', headlines_count, 'headlines, infscroll disabled=', infscroll_disabled);

		vgroup_last_feed = reply['headlines-info']['vgroup_last_feed'];
		current_first_id = reply['headlines']['first_id'];

		if (offset == 0) {
			loaded_article_ids = [];

			dojo.html.set($("headlines-toolbar"),
					reply['headlines']['toolbar'],
					{parseContent: true});

			$("headlines-frame").innerHTML = '';

			let tmp = document.createElement("div");
			tmp.innerHTML = reply['headlines']['content'];
			dojo.parser.parse(tmp);

			while (tmp.hasChildNodes()) {
				const row = tmp.removeChild(tmp.firstChild);

				if (loaded_article_ids.indexOf(row.id) == -1 || row.hasClassName("feed-title")) {
					dijit.byId("headlines-frame").domNode.appendChild(row);

					loaded_article_ids.push(row.id);
				}
			}

			let hsp = $("headlines-spacer");
			if (!hsp) hsp = new Element("DIV", {"id": "headlines-spacer"});
			dijit.byId('headlines-frame').domNode.appendChild(hsp);

			initHeadlinesMenu();

			if (infscroll_disabled)
				hsp.innerHTML = "<a href='#' onclick='openNextUnreadFeed()'>" +
					__("Click to open next unread feed.") + "</a>";

			if (_search_query) {
				$("feed_title").innerHTML += "<span id='cancel_search'>" +
					" (<a href='#' onclick='cancelSearch()'>" + __("Cancel search") + "</a>)" +
					"</span>";
			}

		} else if (headlines_count > 0 && feed_id == getActiveFeedId() && is_cat == activeFeedIsCat()) {
			const c = dijit.byId("headlines-frame");
			//const ids = getSelectedArticleIds2();

			let hsp = $("headlines-spacer");

			if (hsp)
				c.domNode.removeChild(hsp);

			let tmp = document.createElement("div");
			tmp.innerHTML = reply['headlines']['content'];
			dojo.parser.parse(tmp);

			while (tmp.hasChildNodes()) {
				let row = tmp.removeChild(tmp.firstChild);

				if (loaded_article_ids.indexOf(row.id) == -1 || row.hasClassName("feed-title")) {
					dijit.byId("headlines-frame").domNode.appendChild(row);

					loaded_article_ids.push(row.id);
				}
			}

			if (!hsp) hsp = new Element("DIV", {"id": "headlines-spacer"});
			c.domNode.appendChild(hsp);

			if (headlines_count < 30) infscroll_disabled = true;

			/* console.log("restore selected ids: " + ids);

			for (let i = 0; i < ids.length; i++) {
				markHeadline(ids[i]);
			} */

			initHeadlinesMenu();

			if (infscroll_disabled) {
				hsp.innerHTML = "<a href='#' onclick='openNextUnreadFeed()'>" +
				__("Click to open next unread feed.") + "</a>";
			}

		} else {
			console.log("no new headlines received");

			const first_id_changed = reply['headlines']['first_id_changed'];
			console.log("first id changed:" + first_id_changed);

			let hsp = $("headlines-spacer");

			if (hsp) {
				if (first_id_changed) {
					hsp.innerHTML = "<a href='#' onclick='viewCurrentFeed()'>" +
					__("New articles found, reload feed to continue.") + "</a>";
				} else {
					hsp.innerHTML = "<a href='#' onclick='openNextUnreadFeed()'>" +
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

	infscroll_in_progress = 0;

	// this is used to auto-catchup articles if needed after infscroll request has finished,
	// unpack visible articles, etc
	headlinesScrollHandler();

	// if we have some more space in the buffer, why not try to fill it
	if (!infscroll_disabled && $("headlines-spacer") &&
			$("headlines-spacer").offsetTop < $("headlines-frame").offsetHeight) {

		window.setTimeout(function() {
			loadMoreHeadlines();
		}, 500);
	}

	notify("");
}

function render_article(article) {
	cleanup_memory("content-insert");

	dijit.byId("headlines-wrap-inner").addChild(
			dijit.byId("content-insert"));

	const c = dijit.byId("content-insert");

	try {
		c.domNode.scrollTop = 0;
	} catch (e) { }

	c.attr('content', article);
	PluginHost.run(PluginHost.HOOK_ARTICLE_RENDERED, c.domNode);

	correctHeadlinesOffset(getActiveArticleId());

	try {
		c.focus();
	} catch (e) { }
}

function view(id, noexpand) {
	setActiveArticleId(id);

	if (!noexpand) {
		console.log("loading article", id);

		const cids = [];

		/* only request uncached articles */

		getRelativePostIds(id).each((n) => {
			if (!cache_get("article:" + n))
				cids.push(n);
		});

		const cached_article = cache_get("article:" + id);

		if (cached_article) {
			console.log('rendering cached', id);
			render_article(cached_article);
			return false;
		}

		xhrPost("backend.php", {op: "article", method: "view", id: id, cids: cids.toString()}, (transport) => {
			try {
				const reply = handle_rpc_json(transport);

				if (reply) {

					reply.each(function(article) {
						if (getActiveArticleId() == article['id']) {
							render_article(article['content']);
						}
						//cids_requested.remove(article['id']);

						cache_set("article:" + article['id'], article['content']);
					});

				} else {
					console.error("Invalid object received: " + transport.responseText);

					render_article("<div class='whiteBox'>" +
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
}

function toggleMark(id, client_only) {
	const query = { op: "rpc", id: id, method: "mark" };
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
				handle_rpc_json(transport);
			});
	}
}

function togglePub(id, client_only) {
	const row = $("RROW-" + id);

	if (row) {
		const query = { op: "rpc", id: id, method: "publ" };

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
				handle_rpc_json(transport);
			});

	}
}

function moveToPost(mode, noscroll, noexpand) {
	const rows = getLoadedArticleIds();

	let prev_id = false;
	let next_id = false;

	if (!$('RROW-' + getActiveArticleId())) {
		setActiveArticleId(0);
	}

	if (!getActiveArticleId()) {
		next_id = rows[0];
		prev_id = rows[rows.length-1]
	} else {
		for (let i = 0; i < rows.length; i++) {
			if (rows[i] == getActiveArticleId()) {

				// Account for adjacent identical article ids.
				if (i > 0) prev_id = rows[i-1];

				for (let j = i+1; j < rows.length; j++) {
					if (rows[j] != getActiveArticleId()) {
						next_id = rows[j];
						break;
					}
				}
				break;
			}
		}
	}

	console.log("cur: " + getActiveArticleId() + " next: " + next_id);

	if (mode == "next") {
		if (next_id || getActiveArticleId()) {
			if (isCombinedMode()) {

				const article = $("RROW-" + getActiveArticleId());
				const ctr = $("headlines-frame");

				if (!noscroll && article && article.offsetTop + article.offsetHeight >
						ctr.scrollTop + ctr.offsetHeight) {

					scrollArticle(ctr.offsetHeight/4);

				} else if (next_id) {
					setActiveArticleId(next_id);
					cdmScrollToArticleId(next_id, true);
				}

			} else if (next_id) {
				correctHeadlinesOffset(next_id);
				view(next_id, noexpand);
			}
		}
	}

	if (mode == "prev") {
		if (prev_id || getActiveArticleId()) {
			if (isCombinedMode()) {

				const article = $("RROW-" + getActiveArticleId());
				const prev_article = $("RROW-" + prev_id);
				const ctr = $("headlines-frame");

				if (!noscroll && article && article.offsetTop < ctr.scrollTop) {
					scrollArticle(-ctr.offsetHeight/3);
				} else if (!noscroll && prev_article &&
						prev_article.offsetTop < ctr.scrollTop) {
					scrollArticle(-ctr.offsetHeight/4);
				} else if (prev_id) {
					setActiveArticleId(prev_id);
					cdmScrollToArticleId(prev_id, noscroll);
				}

			} else if (prev_id) {
				correctHeadlinesOffset(prev_id);
				view(prev_id, noexpand);
			}
		}
	}
}

function updateSelectedPrompt() {
	const count = getSelectedArticleIds2().length;
	const elem = $("selected_prompt");

	if (elem) {
		elem.innerHTML = ngettext("%d article selected",
				"%d articles selected", count).replace("%d", count);

		count > 0 ? Element.show(elem) : Element.hide(elem);
	}
}

function toggleUnread(id, cmode) {
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
				{op: "rpc", method: "catchupSelected", cmode: cmode, ids: id},(transport) => {
					handle_rpc_json(transport);
			});
	}
}

function selectionRemoveLabel(id, ids) {
	if (!ids) ids = getSelectedArticleIds2();

	if (ids.length == 0) {
		alert(__("No articles are selected."));
		return;
	}

	const query = { op: "article", method: "removeFromLabel",
		ids: ids.toString(), lid: id };

	xhrPost("backend.php", query, (transport) => {
		handle_rpc_json(transport);
		updateHeadlineLabels(transport);
	});
}

function selectionAssignLabel(id, ids) {
	if (!ids) ids = getSelectedArticleIds2();

	if (ids.length == 0) {
		alert(__("No articles are selected."));
		return;
	}

	const query = { op: "article", method: "assignToLabel",
		ids: ids.toString(), lid: id };

	xhrPost("backend.php", query, (transport) => {
		handle_rpc_json(transport);
		updateHeadlineLabels(transport);
	});
}

function selectionToggleUnread(params) {
	params = params || {};

	const cmode = params.cmode || 2;
	const callback = params.callback;
	const no_error = params.no_error || false;
	const ids = params.ids || getSelectedArticleIds2();

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

	const query = {op: "rpc", method: "catchupSelected",
		cmode: cmode, ids: ids.toString() };

	notify_progress("Loading, please wait...");

	xhrPost("backend.php", query, (transport) => {
		handle_rpc_json(transport);
		if (callback) callback(transport);
	});
}

function selectionToggleMarked(ids) {
	const rows = ids || getSelectedArticleIds2();

	if (rows.length == 0) {
		alert(__("No articles are selected."));
		return;
	}

	for (let i = 0; i < rows.length; i++) {
		toggleMark(rows[i], true, true);
	}

	const query = { op: "rpc", method: "markSelected",
		ids:  rows.toString(), cmode: 2 };

	xhrPost("backend.php", query, (transport) => {
		handle_rpc_json(transport);
	});
}

// sel_state ignored
function selectionTogglePublished(ids) {
	const rows = ids || getSelectedArticleIds2();

	if (rows.length == 0) {
		alert(__("No articles are selected."));
		return;
	}

	for (let i = 0; i < rows.length; i++) {
		togglePub(rows[i], true);
	}

	if (rows.length > 0) {
		const query = { op: "rpc", method: "publishSelected",
			ids:  rows.toString(), cmode: 2 };

		xhrPost("backend.php", query, (transport) => {
			handle_rpc_json(transport);
		});
	}
}

function getSelectedArticleIds2() {

	const rv = [];

	$$("#headlines-frame > div[id*=RROW][class*=Selected]").each(
		function(child) {
			rv.push(child.getAttribute("data-article-id"));
		});

	// consider active article a honorary member of selected articles
	if (getActiveArticleId())
		rv.push(getActiveArticleId());

	return rv.uniq();
}

function getLoadedArticleIds() {
	const rv = [];

	const children = $$("#headlines-frame > div[id*=RROW-]");

	children.each(function(child) {
		if (Element.visible(child)) {
			rv.push(child.getAttribute("data-article-id"));
		}
	});

	return rv;
}

// mode = all,none,unread,invert,marked,published
function selectArticles(mode) {
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

		updateSelectedPrompt();
	}
}

// noinspection JSUnusedGlobalSymbols
function deleteSelection() {

	const rows = getSelectedArticleIds2();

	if (rows.length == 0) {
		alert(__("No articles are selected."));
		return;
	}

	const fn = getFeedName(getActiveFeedId(), activeFeedIsCat());
	let str;

	if (getActiveFeedId() != 0) {
		str = ngettext("Delete %d selected article in %s?", "Delete %d selected articles in %s?", rows.length);
	} else {
		str = ngettext("Delete %d selected article?", "Delete %d selected articles?", rows.length);
	}

	str = str.replace("%d", rows.length);
	str = str.replace("%s", fn);

	if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
		return;
	}

	const query = { op: "rpc", method: "delete", ids: rows.toString() };

	xhrPost("backend.php", query, (transport) => {
		handle_rpc_json(transport);
		viewCurrentFeed();
	});
}

// noinspection JSUnusedGlobalSymbols
function archiveSelection() {

	const rows = getSelectedArticleIds2();

	if (rows.length == 0) {
		alert(__("No articles are selected."));
		return;
	}

	const fn = getFeedName(getActiveFeedId(), activeFeedIsCat());
	let str;
	let op;

	if (getActiveFeedId() != 0) {
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
		cache_delete("article:" + rows[i]);
	}

	const query = {op: "rpc", method: op, ids: rows.toString()};

	xhrPost("backend.php", query, (transport) => {
		handle_rpc_json(transport);
		viewCurrentFeed();
	});
}

function catchupSelection() {

	const rows = getSelectedArticleIds2();

	if (rows.length == 0) {
		alert(__("No articles are selected."));
		return;
	}

	const fn = getFeedName(getActiveFeedId(), activeFeedIsCat());

	let str = ngettext("Mark %d selected article in %s as read?", "Mark %d selected articles in %s as read?", rows.length);

	str = str.replace("%d", rows.length);
	str = str.replace("%s", fn);

	if (getInitParam("confirm_feed_catchup") == 1 && !confirm(str)) {
		return;
	}

	selectionToggleUnread({callback: viewCurrentFeed, no_error: 1});
}

function editArticleTags(id) {
	const query = "backend.php?op=article&method=editArticleTags&param=" + param_escape(id);

	if (dijit.byId("editTagsDlg"))
		dijit.byId("editTagsDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "editTagsDlg",
		title: __("Edit article Tags"),
		style: "width: 600px",
		execute: function() {
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

	const tmph = dojo.connect(dialog, 'onLoad', function() {
		dojo.disconnect(tmph);

		new Ajax.Autocompleter('tags_str', 'tags_choices',
		   "backend.php?op=article&method=completeTags",
		   { tokens: ',', paramName: "search" });
	});

	dialog.show();

}

function cdmScrollToArticleId(id, force) {
	const ctr = $("headlines-frame");
	const e = $("RROW-" + id);

	if (!e || !ctr) return;

	if (force || e.offsetTop+e.offsetHeight > (ctr.scrollTop+ctr.offsetHeight) ||
			e.offsetTop < ctr.scrollTop) {

		// expanded cdm has a 4px margin now
		ctr.scrollTop = parseInt(e.offsetTop) - 4;

		Element.hide("floatingTitle");
	}
}

function setActiveArticleId(id) {
	console.log("setActiveArticleId", id);

	$$("div[id*=RROW][class*=active]").each((e) => {
		e.removeClassName("active");

		if (!e.hasClassName("Selected")) {
			const cb = dijit.getEnclosingWidget(e.select(".rchk")[0]);
			if (cb) cb.attr("checked", false);
		}
	});

	_active_article_id = id;

	const row = $("RROW-" + id);

	if (row) {
		if (row.hasAttribute("data-content")) {
			console.log("unpacking: " + row.id);

			row.select(".content-inner")[0].innerHTML = row.getAttribute("data-content");
			row.removeAttribute("data-content");

			PluginHost.run(PluginHost.HOOK_ARTICLE_RENDERED_CDM, row);
		}

		if (row.hasClassName("Unread")) {

			catchupBatchedArticles(() => {
				decrementFeedCounter(getActiveFeedId(), activeFeedIsCat());
				toggleUnread(id, 0);
				updateFloatingTitle(true);
			});

		}

		row.addClassName("active");

		if (!row.hasClassName("Selected")) {
			const cb = dijit.getEnclosingWidget(row.select(".rchk")[0]);
			if (cb) cb.attr("checked", true);
		}

		PluginHost.run(PluginHost.HOOK_ARTICLE_SET_ACTIVE, _active_article_id);
	}

	updateSelectedPrompt();
}

function getActiveArticleId() {
	return _active_article_id;
}

function postMouseIn(e, id) {
	post_under_pointer = id;
}

function postMouseOut(id) {
	post_under_pointer = false;
}

function unpackVisibleArticles() {
	if (!isCombinedMode() || !getInitParam("cdm_expanded")) return;

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
}

function headlinesScrollHandler(/* event */) {
	try {
		unpackVisibleArticles();

		if (isCombinedMode()) {
			updateFloatingTitle();

			// set topmost child in the buffer as active
			if (getInitParam("cdm_expanded") && getInitParam("cdm_auto_catchup") == 1) {

				const rows = $$("#headlines-frame > div[id*=RROW]");

				for (let i = 0; i < rows.length; i++) {
					const row = rows[i];

					if ($("headlines-frame").scrollTop <= row.offsetTop &&
						row.offsetTop - $("headlines-frame").scrollTop < 100 &&
						row.getAttribute("data-article-id") != getActiveArticleId()) {

						setActiveArticleId(row.getAttribute("data-article-id"));
						break;
					}
				}
			}
		}

		if (!infscroll_disabled) {
			const hsp = $("headlines-spacer");
			const container = $("headlines-frame");

			if (hsp && hsp.offsetTop - 250 <= container.scrollTop + container.offsetHeight) {

				hsp.innerHTML = "<span class='loading'><img src='images/indicator_tiny.gif'> " +
					__("Loading, please wait...") + "</span>";

				loadMoreHeadlines();
				return;
			}
		}

		if (getInitParam("cdm_auto_catchup") == 1) {

			let rows = $$("#headlines-frame > div[id*=RROW][class*=Unread]");

			for (let i = 0; i < rows.length; i++) {
				const row = rows[i];

				if ($("headlines-frame").scrollTop > (row.offsetTop + row.offsetHeight/2)) {
					const id = row.getAttribute("data-article-id")

					if (catchup_id_batch.indexOf(id) == -1)
						catchup_id_batch.push(id);

				} else {
					break;
				}
			}

			if (infscroll_disabled) {
				const row = $$("#headlines-frame div[id*=RROW]").last();

				if (row && $("headlines-frame").scrollTop >
					(row.offsetTop + row.offsetHeight - 50)) {

					console.log("we seem to be at an end");

					if (getInitParam("on_catchup_show_next_feed") == "1") {
						openNextUnreadFeed();
					}
				}
			}
		}
	} catch (e) {
		console.warn("headlinesScrollHandler", e);
	}
}

function openNextUnreadFeed() {
	const is_cat = activeFeedIsCat();
	const nuf = getNextUnreadFeed(getActiveFeedId(), is_cat);
	if (nuf) viewfeed({feed: nuf, is_cat: is_cat});
}

function catchupBatchedArticles(callback) {
	console.log("catchupBatchedArticles, size=", catchup_id_batch.length);

	if (catchup_id_batch.length > 0) {

		// make a copy of the array
		const batch = catchup_id_batch.slice();
		const query = { op: "rpc", method: "catchupSelected",
			cmode: 0, ids: batch.toString() };

		xhrPost("backend.php", query, (transport) => {
			const reply = handle_rpc_json(transport);

			if (reply) {
				const batch = reply.ids;

				batch.each(function (id) {
					const elem = $("RROW-" + id);
					if (elem) elem.removeClassName("Unread");
					catchup_id_batch.remove(id);
				});
			}

			updateFloatingTitle(true);

			if (callback) callback();
		});
	} else {
		if (callback) callback();
	}
}

function catchupRelativeToArticle(below, id) {

	if (!id) id = getActiveArticleId();

	if (!id) {
		alert(__("No article is selected."));
		return;
	}

	const visible_ids = getLoadedArticleIds();

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

			const query = { op: "rpc", method: "catchupSelected",
				cmode: 0, ids: ids_to_mark.toString() };

			xhrPost("backend.php", query, (transport) => {
				handle_rpc_json(transport);
			});
		}
	}
}

function getArticleUnderPointer() {
	return post_under_pointer;
}

function scrollArticle(offset) {
	if (!isCombinedMode()) {
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
}

function updateHeadlineLabels(transport) {
	const data = JSON.parse(transport.responseText);

	if (data) {
		data['info-for-headlines'].each(function (elem) {
			$$(".HLLCTR-" + elem.id).each(function (ctr) {
				ctr.innerHTML = elem.labels;
			});
		});
	}
}

function cdmClicked(event, id, in_body) {
	in_body = in_body || false;

	if (!in_body && (event.ctrlKey || id == getActiveArticleId() || getInitParam("cdm_expanded"))) {
		openArticleInNewWindow(id);
	}

	setActiveArticleId(id);

	if (!getInitParam("cdm_expanded"))
		cdmScrollToArticleId(id);

	//var shift_key = event.shiftKey;

	/* if (!event.ctrlKey && !event.metaKey) {

		let elem = $("RROW-" + getActiveArticleId());

		if (elem) elem.removeClassName("active");

		selectArticles("none");
		toggleSelected(id);

		elem = $("RROW-" + id);
		const article_is_unread = elem.hasClassName("Unread");

		elem.removeClassName("Unread");
		elem.addClassName("active");

		setActiveArticleId(id);

		if (article_is_unread) {
			decrementFeedCounter(getActiveFeedId(), activeFeedIsCat());
			updateFloatingTitle(true);

			const query = {
				op: "rpc", method: "catchupSelected",
				cmode: 0, ids: id
			};

			xhrPost("backend.php", query, (transport) => {
				handle_rpc_json(transport);
			});
		}

		return !event.shiftKey;

	} else if (!in_body) {

		toggleSelected(id, true);

		let elem = $("RROW-" + id);
		const article_is_unread = elem.hasClassName("Unread");

		if (article_is_unread) {
			decrementFeedCounter(getActiveFeedId(), activeFeedIsCat());
		}

		toggleUnread(id, 0, false);

		openArticleInNewWindow(id);
	} else {
		return true;
	}

	const unread_in_buffer = $$("#headlines-frame > div[id*=RROW][class*=Unread]").length
	request_counters(unread_in_buffer == 0); */

	return in_body;
}

function hlClicked(event, id) {
	if (event.ctrlKey) {
		openArticleInNewWindow(id);
		setActiveArticleId(id);
	} else {
		view(id);
	}

	return false;

	/* if (event.which == 2) {
		view(id);
		return true;
	} else if (event.ctrlKey || event.metaKey) {
		openArticleInNewWindow(id);
		return false;
	} else {
		view(id);
		return false;
	} */
}

function openArticleInNewWindow(id) {
	const w = window.open("");
	w.opener = null;
	w.location = "backend.php?op=article&method=redirect&id=" + id;
}

function isCombinedMode() {
	return getInitParam("combined_display_mode");
}

/* function markHeadline(id, marked) {
	if (marked == undefined) marked = true;

	const row = $("RROW-" + id);
	if (row) {
		const check = dijit.getEnclosingWidget(
				row.getElementsByClassName("rchk")[0]);

		if (check) {
			check.attr("checked", marked);
		}

		if (marked)
			row.addClassName("Selected");
		else
			row.removeClassName("Selected");
	}
} */

function getRelativePostIds(id, limit) {

	const tmp = [];

	if (!limit) limit = 6; //3

	const ids = getLoadedArticleIds();

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
}

function correctHeadlinesOffset(id) {
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
}

function headlineActionsChange(elem) {
	eval(elem.value);
	elem.attr('value', 'false');
}

function cdmCollapseActive(event) {
	const row = $("RROW-" + getActiveArticleId());

	if (row) {
		row.removeClassName("active");
		const cb = dijit.getEnclosingWidget(row.select(".rchk")[0]);

		if (cb && !row.hasClassName("Selected"))
			cb.attr("checked", false);

		setActiveArticleId(0);

		if (event)
			event.stopPropagation();

		return false;
	}
}

function closeArticlePanel() {
	if (dijit.byId("content-insert"))
		dijit.byId("headlines-wrap-inner").removeChild(
			dijit.byId("content-insert"));
}

function initFloatingMenu() {
	if (!dijit.byId("floatingMenu")) {

		const menu = new dijit.Menu({
			id: "floatingMenu",
			targetNodeIds: ["floatingTitle"]
		});

		headlinesMenuCommon(menu);

		menu.startup();
	}
}

function headlinesMenuCommon(menu) {

	menu.addChild(new dijit.MenuItem({
		label: __("Open original article"),
		onClick: function (event) {
			openArticleInNewWindow(this.getParent().currentTarget.getAttribute("data-article-id"));
		}
	}));

	menu.addChild(new dijit.MenuItem({
		label: __("Display article URL"),
		onClick: function (event) {
			displayArticleUrl(this.getParent().currentTarget.getAttribute("data-article-id"));
		}
	}));

	menu.addChild(new dijit.MenuSeparator());

	menu.addChild(new dijit.MenuItem({
		label: __("Toggle unread"),
		onClick: function () {

			let ids = getSelectedArticleIds2();
			// cast to string
			const id = (this.getParent().currentTarget.getAttribute("data-article-id")) + "";
			ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

			selectionToggleUnread({ids: ids, no_error: 1});
		}
	}));

	menu.addChild(new dijit.MenuItem({
		label: __("Toggle starred"),
		onClick: function () {
			let ids = getSelectedArticleIds2();
			// cast to string
			const id = (this.getParent().currentTarget.getAttribute("data-article-id")) + "";
			ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

			selectionToggleMarked(ids);
		}
	}));

	menu.addChild(new dijit.MenuItem({
		label: __("Toggle published"),
		onClick: function () {
			let ids = getSelectedArticleIds2();
			// cast to string
			const id = (this.getParent().currentTarget.getAttribute("data-article-id")) + "";
			ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

			selectionTogglePublished(ids);
		}
	}));

	menu.addChild(new dijit.MenuSeparator());

	menu.addChild(new dijit.MenuItem({
		label: __("Mark above as read"),
		onClick: function () {
			catchupRelativeToArticle(0, this.getParent().currentTarget.getAttribute("data-article-id"));
		}
	}));

	menu.addChild(new dijit.MenuItem({
		label: __("Mark below as read"),
		onClick: function () {
			catchupRelativeToArticle(1, this.getParent().currentTarget.getAttribute("data-article-id"));
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

					let ids = getSelectedArticleIds2();
					// cast to string
					const id = (this.getParent().ownerMenu.currentTarget.getAttribute("data-article-id")) + "";

					ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

					selectionAssignLabel(this.labelId, ids);
				}
			}));

			labelDelMenu.addChild(new dijit.MenuItem({
				label: name,
				labelId: bare_id,
				onClick: function () {
					let ids = getSelectedArticleIds2();
					// cast to string
					const id = (this.getParent().ownerMenu.currentTarget.getAttribute("data-article-id")) + "";

					ids = ids.length != 0 && ids.indexOf(id) != -1 ? ids : [id];

					selectionRemoveLabel(this.labelId, ids);
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
}

function initHeadlinesMenu() {
	if (!dijit.byId("headlinesMenu")) {

		const menu = new dijit.Menu({
			id: "headlinesMenu",
			targetNodeIds: ["headlines-frame"],
			selector: ".hlMenuAttach"
		});

		headlinesMenuCommon(menu);

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
				selectArticles("all",
					"#headlines-frame > div[id*=RROW]" +
					"[data-orig-feed-id='" + this.getParent().currentTarget.getAttribute("data-feed-id") + "']");

			}
		}));

		menu.addChild(new dijit.MenuItem({
			label: __("Mark group as read"),
			onClick: function () {
				selectArticles("none");
				selectArticles("all",
					"#headlines-frame > div[id*=RROW]" +
					"[data-orig-feed-id='" + this.getParent().currentTarget.getAttribute("data-feed-id") + "']");

				catchupSelection();
			}
		}));

		menu.addChild(new dijit.MenuItem({
			label: __("Mark feed as read"),
			onClick: function () {
				catchupFeedInGroup(this.getParent().currentTarget.getAttribute("data-feed-id"));
			}
		}));

		menu.addChild(new dijit.MenuItem({
			label: __("Edit feed"),
			onClick: function () {
				editFeed(this.getParent().currentTarget.getAttribute("data-feed-id"));
			}
		}));

		menu.startup();
	}
}

function cache_set(id, obj) {
	//console.log("cache_set: " + id);
	if (has_storage)
		try {
			sessionStorage[id] = obj;
		} catch (e) {
			sessionStorage.clear();
		}
}

function cache_get(id) {
	if (has_storage)
		return sessionStorage[id];
}

function cache_clear() {
	if (has_storage)
		sessionStorage.clear();
}

function cache_delete(id) {
	if (has_storage)
		sessionStorage.removeItem(id);
}

function cancelSearch() {
	_search_query = "";
	viewCurrentFeed();
}

function setSelectionScore() {
	const ids = getSelectedArticleIds2();

	if (ids.length > 0) {
		console.log(ids);

		const score = prompt(__("Please enter new score for selected articles:"));

		if (score != undefined) {
			const query = { op: "article", method: "setScore", id: ids.toString(),
				score: score };

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
}

function changeScore(id, pic) {
	const score = pic.getAttribute("score");

	const new_score = prompt(__("Please enter new score for this article:"), score);

	if (new_score != undefined) {
		const query = { op: "article", method: "setScore", id: id, score: new_score };

		xhrJson("backend.php", query, (reply) => {
			if (reply) {
				pic.src = pic.src.replace(/score_.*?\.png/, reply["score_pic"]);
				pic.setAttribute("score", new_score);
				pic.setAttribute("title", new_score);
			}
		});
	}
}

function displayArticleUrl(id) {
	const query = { op: "rpc", method: "getlinktitlebyid", id: id };

	xhrJson("backend.php", query, (reply) => {
		if (reply && reply.link) {
			prompt(__("Article URL:"), reply.link);
		}
	});

}

// floatingTitle goto button uses this
/* function scrollToRowId(id) {
	const row = $(id);

	if (row)
		$("headlines-frame").scrollTop = row.offsetTop - 4;
} */

function updateFloatingTitle(unread_only) {
	if (!isCombinedMode()/* || !getInitParam("cdm_expanded")*/) return;

	const hf = $("headlines-frame");
	const elems = $$("#headlines-frame > div[id*=RROW]");
	const ft = $("floatingTitle");

	for (let i = 0; i < elems.length; i++) {
		const row = elems[i];

		if (row && row.offsetTop + row.offsetHeight > hf.scrollTop) {

			const header = row.select(".header")[0];
			var id = row.getAttribute("data-article-id");

			if (unread_only || id != ft.getAttribute("data-article-id")) {
				if (id != ft.getAttribute("data-article-id")) {

					ft.setAttribute("data-article-id", id);
					ft.innerHTML = header.innerHTML;
					ft.firstChild.innerHTML = "<img class='anchor marked-pic' src='images/page_white_go.png' " +
						"onclick=\"cdmScrollToArticleId("+id + ", true)\">" + ft.firstChild.innerHTML;

					initFloatingMenu();

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
}

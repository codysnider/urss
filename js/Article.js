'use strict'
/* global __, ngettext */
define(["dojo/_base/declare"], function (declare) {
	Article = {
		_active_article_id: 0,
		selectionSetScore: function () {
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
				alert(__("No articles selected."));
			}
		},
		setScore: function (id, pic) {
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
		cdmUnsetActive: function (event) {
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
			App.cleanupMemory("content-insert");

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
		view: function (id, noexpand) {
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
						const reply = App.handleRpcJson(transport);

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

						Notify.close();

					} catch (e) {
						App.Error.report(e);
					}
				})
			}

			return false;
		},
		editTags: function (id) {
			const query = "backend.php?op=article&method=editArticleTags&param=" + encodeURIComponent(id);

			if (dijit.byId("editTagsDlg"))
				dijit.byId("editTagsDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "editTagsDlg",
				title: __("Edit article Tags"),
				style: "width: 600px",
				execute: function () {
					if (this.validate()) {
						Notify.progress("Saving article tags...", true);

						xhrPost("backend.php", this.attr('value'), (transport) => {
							try {
								Notify.close();
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
								App.Error.report(e);
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
		cdmScrollToId: function (id, force) {
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
		setActive: function (id) {
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
						Feeds.decrementFeedCounter(Feeds.getActive(), Feeds.activeIsCat());
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
		getActive: function () {
			return this._active_article_id;
		},
		scroll: function (offset) {
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
		getRelativeIds: function (id, limit) {

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
		mouseIn: function (id) {
			this.post_under_pointer = id;
		},
		mouseOut: function (id) {
			this.post_under_pointer = false;
		},
		getUnderPointer: function () {
			return this.post_under_pointer;
		}
	}

	return Article;
});
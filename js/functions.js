/* global dijit, __ */

let init_params = {};
let _label_base_index = -1024;
let loading_progress = 0;
let notify_hide_timerid = false;

let hotkey_prefix = 0;
let hotkey_prefix_pressed = false;

Ajax.Base.prototype.initialize = Ajax.Base.prototype.initialize.wrap(
	function (callOriginal, options) {

		if (getInitParam("csrf_token") != undefined) {
			Object.extend(options, options || { });

			if (Object.isString(options.parameters))
				options.parameters = options.parameters.toQueryParams();
			else if (Object.isHash(options.parameters))
				options.parameters = options.parameters.toObject();

			options.parameters["csrf_token"] = getInitParam("csrf_token");
		}

		return callOriginal(options);
	}
);

/* xhr shorthand helpers */

function xhrPost(url, params, complete) {
	console.log("xhrPost:", params);
	return new Ajax.Request(url, {
		parameters: params,
		onComplete: complete
	});
}

function xhrJson(url, params, complete) {
	return xhrPost(url, params, (reply) => {
		try {
			const obj = JSON.parse(reply.responseText);
			complete(obj);
		} catch (e) {
			console.error("xhrJson", e, reply);
			complete(null);
		}

	})
}

/* add method to remove element from array */

Array.prototype.remove = function(s) {
	for (let i=0; i < this.length; i++) {
		if (s == this[i]) this.splice(i, 1);
	}
};

function report_error(message, filename, lineno, colno, error) {
	exception_error(error, null, filename, lineno);
}

function exception_error(e, e_compat, filename, lineno, colno) {
	if (typeof e == "string") e = e_compat;

	if (!e) return; // no exception object, nothing to report.

	try {
		console.error(e);
		const msg = e.toString();

		try {
			xhrPost("backend.php",
				{op: "rpc", method: "log",
					file: e.fileName ? e.fileName : filename,
					line: e.lineNumber ? e.lineNumber : lineno,
					msg: msg, context: e.stack},
				(transport) => {
					console.warn(transport.responseText);
				});

		} catch (e) {
			console.error("Exception while trying to log the error.", e);
		}

		let content = "<div class='fatalError'><p>" + msg + "</p>";

		if (e.stack) {
			content += "<div><b>Stack trace:</b></div>" +
				"<textarea name=\"stack\" readonly=\"1\">" + e.stack + "</textarea>";
		}

		content += "</div>";

		content += "<div class='dlgButtons'>";

		content += "<button dojoType=\"dijit.form.Button\" "+
				"onclick=\"dijit.byId('exceptionDlg').hide()\">" +
				__('Close') + "</button>";
		content += "</div>";

		if (dijit.byId("exceptionDlg"))
			dijit.byId("exceptionDlg").destroyRecursive();

		const dialog = new dijit.Dialog({
			id: "exceptionDlg",
			title: "Unhandled exception",
			style: "width: 600px",
			content: content});

		dialog.show();

	} catch (ei) {
		console.error("Exception while trying to report an exception:", ei);
		console.error("Original exception:", e);

		alert("Exception occured while trying to report an exception.\n" +
			ei.stack + "\n\nOriginal exception:\n" + e.stack);
	}

}

function param_escape(arg) {
	return encodeURIComponent(arg);
}

function notify_real(msg, no_hide, n_type) {

	const n = $("notify");

	if (!n) return;

	if (notify_hide_timerid) {
		window.clearTimeout(notify_hide_timerid);
	}

	if (msg == "") {
		if (n.hasClassName("visible")) {
			notify_hide_timerid = window.setTimeout(function() {
				n.removeClassName("visible") }, 0);
		}
		return;
	}

	/* types:

		1 - generic
		2 - progress
		3 - error
		4 - info

	*/

	msg = "<span class=\"msg\"> " + __(msg) + "</span>";

	if (n_type == 2) {
		msg = "<span><img src=\""+getInitParam("icon_indicator_white")+"\"></span>" + msg;
		no_hide = true;
	} else if (n_type == 3) {
		msg = "<span><img src=\""+getInitParam("icon_alert")+"\"></span>" + msg;
	} else if (n_type == 4) {
		msg = "<span><img src=\""+getInitParam("icon_information")+"\"></span>" + msg;
	}

	msg += " <span><img src=\""+getInitParam("icon_cross")+"\" class=\"close\" title=\"" +
		__("Click to close") + "\" onclick=\"notify('')\"></span>";

	n.innerHTML = msg;

	window.setTimeout(function() {
		// goddamnit firefox
		if (n_type == 2) {
		n.className = "notify notify_progress visible";
			} else if (n_type == 3) {
			n.className = "notify notify_error visible";
			msg = "<span><img src='images/alert.png'></span>" + msg;
		} else if (n_type == 4) {
			n.className = "notify notify_info visible";
		} else {
			n.className = "notify visible";
		}

		if (!no_hide) {
			notify_hide_timerid = window.setTimeout(function() {
				n.removeClassName("visible") }, 5*1000);
		}

	}, 10);

}

function notify(msg, no_hide) {
	notify_real(msg, no_hide, 1);
}

function notify_progress(msg, no_hide) {
	notify_real(msg, no_hide, 2);
}

function notify_error(msg, no_hide) {
	notify_real(msg, no_hide, 3);

}

function notify_info(msg, no_hide) {
	notify_real(msg, no_hide, 4);
}

function setCookie(name, value, lifetime, path, domain, secure) {

	let d = false;

	if (lifetime) {
		d = new Date();
		d.setTime(d.getTime() + (lifetime * 1000));
	}

	console.log("setCookie: " + name + " => " + value + ": " + d);

	int_setCookie(name, value, d, path, domain, secure);

}

function int_setCookie(name, value, expires, path, domain, secure) {
	document.cookie= name + "=" + escape(value) +
		((expires) ? "; expires=" + expires.toGMTString() : "") +
		((path) ? "; path=" + path : "") +
		((domain) ? "; domain=" + domain : "") +
		((secure) ? "; secure" : "");
}

function delCookie(name, path, domain) {
	if (getCookie(name)) {
		document.cookie = name + "=" +
		((path) ? ";path=" + path : "") +
		((domain) ? ";domain=" + domain : "" ) +
		";expires=Thu, 01-Jan-1970 00:00:01 GMT";
	}
}


function getCookie(name) {

	const dc = document.cookie;
	const prefix = name + "=";
	let begin = dc.indexOf("; " + prefix);
	if (begin == -1) {
		begin = dc.indexOf(prefix);
		if (begin != 0) return null;
	}
	else {
		begin += 2;
	}
	let end = document.cookie.indexOf(";", begin);
	if (end == -1) {
		end = dc.length;
	}
	return unescape(dc.substring(begin + prefix.length, end));
}

function toggleSelectRowById(sender, id) {
	const row = $(id);
	return toggleSelectRow(sender, row);
}

/* this is for dijit Checkbox */
function toggleSelectListRow2(sender) {
	const row = sender.domNode.parentNode;
	return toggleSelectRow(sender, row);
}

/* this is for dijit Checkbox */
function toggleSelectRow2(sender, row, is_cdm) {

	if (!row)
		if (!is_cdm)
			row = sender.domNode.parentNode.parentNode;
		else
			row = sender.domNode.parentNode.parentNode.parentNode; // oh ffs

	if (sender.checked && !row.hasClassName('Selected'))
		row.addClassName('Selected');
	else
		row.removeClassName('Selected');

	if (typeof updateSelectedPrompt != undefined)
		updateSelectedPrompt();
}


function toggleSelectRow(sender, row) {

	if (!row) row = sender.parentNode.parentNode;

	if (sender.checked && !row.hasClassName('Selected'))
		row.addClassName('Selected');
	else
		row.removeClassName('Selected');

	if (typeof updateSelectedPrompt != undefined)
		updateSelectedPrompt();
}

// noinspection JSUnusedGlobalSymbols
function displayIfChecked(checkbox, elemId) {
	if (checkbox.checked) {
		Effect.Appear(elemId, {duration : 0.5});
	} else {
		Effect.Fade(elemId, {duration : 0.5});
	}
}

function getURLParam(param){
	return String(window.location.href).parseQuery()[param];
}

// noinspection JSUnusedGlobalSymbols
function closeInfoBox() {
	const dialog = dijit.byId("infoBox");

	if (dialog)	dialog.hide();

	return false;
}

function displayDlg(title, id, param, callback) {
	notify_progress("Loading, please wait...", true);

	const query = { op: "dlg", method: id, param: param };

	xhrPost("backend.php", query, (transport) => {
		try {
			const content = transport.responseText;

			let dialog = dijit.byId("infoBox");

			if (!dialog) {
				dialog = new dijit.Dialog({
					title: title,
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
					},
					content: content
				});
			} else {
				dialog.attr('title', title);
				dialog.attr('content', content);
			}

			dialog.show();

			notify("");

			if (callback) callback(transport);
		} catch (e) {
			exception_error(e);
		}
	});

	return false;
}

function getInitParam(key) {
	return init_params[key];
}

function setInitParam(key, value) {
	init_params[key] = value;
}

function fatalError(code, msg, ext_info) {
	if (code == 6) {
		window.location.href = "index.php";
	} else if (code == 5) {
		window.location.href = "public.php?op=dbupdate";
	} else {

		if (msg == "") msg = "Unknown error";

		if (ext_info) {
			if (ext_info.responseText) {
				ext_info = ext_info.responseText;
			}
		}

		/* global ERRORS */
		if (ERRORS && ERRORS[code] && !msg) {
			msg = ERRORS[code];
		}

		let content = "<div><b>Error code:</b> " + code + "</div>" +
			"<p>" + msg + "</p>";

		if (ext_info) {
			content = content + "<div><b>Additional information:</b></div>" +
				"<textarea style='width: 100%' readonly=\"1\">" +
				ext_info + "</textarea>";
		}

		const dialog = new dijit.Dialog({
			title: "Fatal error",
			style: "width: 600px",
			content: content});

		dialog.show();

	}

	return false;

}

// noinspection JSUnusedGlobalSymbols
function filterDlgCheckAction(sender) {
	const action = sender.value;

	const action_param = $("filterDlg_paramBox");

	if (!action_param) {
		console.log("filterDlgCheckAction: can't find action param box!");
		return;
	}

	// if selected action supports parameters, enable params field
	if (action == 4 || action == 6 || action == 7 || action == 9) {
		new Effect.Appear(action_param, {duration : 0.5});

		Element.hide(dijit.byId("filterDlg_actionParam").domNode);
		Element.hide(dijit.byId("filterDlg_actionParamLabel").domNode);
		Element.hide(dijit.byId("filterDlg_actionParamPlugin").domNode);

		if (action == 7) {
			Element.show(dijit.byId("filterDlg_actionParamLabel").domNode);
		} else if (action == 9) {
			Element.show(dijit.byId("filterDlg_actionParamPlugin").domNode);
		} else {
			Element.show(dijit.byId("filterDlg_actionParam").domNode);
		}

	} else {
		Element.hide(action_param);
	}
}


function explainError(code) {
	return displayDlg(__("Error explained"), "explainError", code);
}

function setLoadingProgress(p) {
	loading_progress += p;

	if (dijit.byId("loading_bar"))
		dijit.byId("loading_bar").update({progress: loading_progress});

	if (loading_progress >= 90)
		Element.hide("overlay");

}

function strip_tags(s) {
	return s.replace(/<\/?[^>]+(>|$)/g, "");
}

function hotkeyPrefixTimeout() {
	const date = new Date();
	const ts = Math.round(date.getTime() / 1000);

	if (hotkey_prefix_pressed && ts - hotkey_prefix_pressed >= 5) {
		console.log("hotkey_prefix seems to be stuck, aborting");
		hotkey_prefix_pressed = false;
		hotkey_prefix = false;
		Element.hide('cmdline');
	}
}

// noinspection JSUnusedGlobalSymbols
function uploadIconHandler(rc) {
	switch (rc) {
		case 0:
			notify_info("Upload complete.");
			if (inPreferences()) {
				updateFeedList();
			} else {
				setTimeout('updateFeedList(false, false)', 50);
			}
			break;
		case 1:
			notify_error("Upload failed: icon is too big.");
			break;
		case 2:
			notify_error("Upload failed.");
			break;
	}
}

// noinspection JSUnusedGlobalSymbols
function removeFeedIcon(id) {
	if (confirm(__("Remove stored feed icon?"))) {

		notify_progress("Removing feed icon...", true);

		const query = { op: "pref-feeds", method: "removeicon", feed_id: id };

		xhrPost("backend.php", query, (transport) => {
			notify_info("Feed icon removed.");
			if (inPreferences()) {
				updateFeedList();
			} else {
				setTimeout('updateFeedList(false, false)', 50);
			}
		});
	}

	return false;
}

// noinspection JSUnusedGlobalSymbols
function uploadFeedIcon() {
	const file = $("icon_file");

	if (file.value.length == 0) {
		alert(__("Please select an image file to upload."));
	} else if (confirm(__("Upload new icon for this feed?"))) {
			notify_progress("Uploading, please wait...", true);
			return true;
		}

	return false;
}

function addLabel(select, callback) {
	const caption = prompt(__("Please enter label caption:"), "");

	if (caption != undefined && caption.trim().length > 0) {

		const query = { op: "pref-labels", method: "add", caption: caption.trim() };

		if (select)
			Object.extend(query, {output: "select"});

		notify_progress("Loading, please wait...", true);

		xhrPost("backend.php", query, (transport) => {
			if (callback) {
				callback(transport);
			} else if (inPreferences()) {
				updateLabelList();
			} else {
				updateFeedList();
			}
		});
	}

}

function quickAddFeed() {
	const query = "backend.php?op=feeds&method=quickAddFeed";

	// overlapping widgets
	if (dijit.byId("batchSubDlg")) dijit.byId("batchSubDlg").destroyRecursive();
	if (dijit.byId("feedAddDlg"))	dijit.byId("feedAddDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "feedAddDlg",
		title: __("Subscribe to Feed"),
		style: "width: 600px",
		show_error: function(msg) {
			const elem = $("fadd_error_message");

			elem.innerHTML = msg;

			if (!Element.visible(elem))
				new Effect.Appear(elem);

		},
		execute: function() {
			if (this.validate()) {
				console.log(dojo.objectToQuery(this.attr('value')));

				const feed_url = this.attr('value').feed;

				Element.show("feed_add_spinner");
				Element.hide("fadd_error_message");

				xhrPost("backend.php", this.attr('value'), (transport) => {
					try {

						try {
							var reply = JSON.parse(transport.responseText);
						} catch (e) {
							Element.hide("feed_add_spinner");
							alert(__("Failed to parse output. This can indicate server timeout and/or network issues. Backend output was logged to browser console."));
							console.log('quickAddFeed, backend returned:' + transport.responseText);
							return;
						}

						const rc = reply['result'];

						notify('');
						Element.hide("feed_add_spinner");

						console.log(rc);

						switch (parseInt(rc['code'])) {
							case 1:
								dialog.hide();
								notify_info(__("Subscribed to %s").replace("%s", feed_url));

								updateFeedList();
								break;
							case 2:
								dialog.show_error(__("Specified URL seems to be invalid."));
								break;
							case 3:
								dialog.show_error(__("Specified URL doesn't seem to contain any feeds."));
								break;
							case 4:
								const feeds = rc['feeds'];

								Element.show("fadd_multiple_notify");

								const select = dijit.byId("feedDlg_feedContainerSelect");

								while (select.getOptions().length > 0)
									select.removeOption(0);

								select.addOption({value: '', label: __("Expand to select feed")});

								let count = 0;
								for (const feedUrl in feeds) {
									select.addOption({value: feedUrl, label: feeds[feedUrl]});
									count++;
								}

								Effect.Appear('feedDlg_feedsContainer', {duration : 0.5});

								break;
							case 5:
								dialog.show_error(__("Couldn't download the specified URL: %s").
								replace("%s", rc['message']));
								break;
							case 6:
								dialog.show_error(__("XML validation failed: %s").
								replace("%s", rc['message']));
								break;
							case 0:
								dialog.show_error(__("You are already subscribed to this feed."));
								break;
						}

					} catch (e) {
						console.error(transport.responseText);
						exception_error(e);
					}
				});
			}
		},
		href: query});

	dialog.show();
}

function createNewRuleElement(parentNode, replaceNode) {
	const form = document.forms["filter_new_rule_form"];
	const query = { op: "pref-filters", method: "printrulename", rule: dojo.formToJson(form) };

	xhrPost("backend.php", query, (transport) => {
		try {
			const li = dojo.create("li");

			const cb = dojo.create("input", { type: "checkbox" }, li);

			new dijit.form.CheckBox({
				onChange: function() {
					toggleSelectListRow2(this) },
			}, cb);

			dojo.create("input", { type: "hidden",
				name: "rule[]",
				value: dojo.formToJson(form) }, li);

			dojo.create("span", {
				onclick: function() {
					dijit.byId('filterEditDlg').editRule(this);
				},
				innerHTML: transport.responseText }, li);

			if (replaceNode) {
				parentNode.replaceChild(li, replaceNode);
			} else {
				parentNode.appendChild(li);
			}
		} catch (e) {
			exception_error(e);
		}
	});
}

function createNewActionElement(parentNode, replaceNode) {
	const form = document.forms["filter_new_action_form"];

	if (form.action_id.value == 7) {
		form.action_param.value = form.action_param_label.value;
	} else if (form.action_id.value == 9) {
		form.action_param.value = form.action_param_plugin.value;
	}

	const query = { op: "pref-filters", method: "printactionname",
		action: dojo.formToJson(form) };

	xhrPost("backend.php", query, (transport) => {
		try {
			const li = dojo.create("li");

			const cb = dojo.create("input", { type: "checkbox" }, li);

			new dijit.form.CheckBox({
				onChange: function() {
					toggleSelectListRow2(this) },
			}, cb);

			dojo.create("input", { type: "hidden",
				name: "action[]",
				value: dojo.formToJson(form) }, li);

			dojo.create("span", {
				onclick: function() {
					dijit.byId('filterEditDlg').editAction(this);
				},
				innerHTML: transport.responseText }, li);

			if (replaceNode) {
				parentNode.replaceChild(li, replaceNode);
			} else {
				parentNode.appendChild(li);
			}

		} catch (e) {
			exception_error(e);
		}
	});
}


function addFilterRule(replaceNode, ruleStr) {
	if (dijit.byId("filterNewRuleDlg"))
		dijit.byId("filterNewRuleDlg").destroyRecursive();

	const query = "backend.php?op=pref-filters&method=newrule&rule=" +
		param_escape(ruleStr);

	const rule_dlg = new dijit.Dialog({
		id: "filterNewRuleDlg",
		title: ruleStr ? __("Edit rule") : __("Add rule"),
		style: "width: 600px",
		execute: function() {
			if (this.validate()) {
				createNewRuleElement($("filterDlg_Matches"), replaceNode);
				this.hide();
			}
		},
		href: query});

	rule_dlg.show();
}

function addFilterAction(replaceNode, actionStr) {
	if (dijit.byId("filterNewActionDlg"))
		dijit.byId("filterNewActionDlg").destroyRecursive();

	const query = "backend.php?op=pref-filters&method=newaction&action=" +
		param_escape(actionStr);

	const rule_dlg = new dijit.Dialog({
		id: "filterNewActionDlg",
		title: actionStr ? __("Edit action") : __("Add action"),
		style: "width: 600px",
		execute: function() {
			if (this.validate()) {
				createNewActionElement($("filterDlg_Actions"), replaceNode);
				this.hide();
			}
		},
		href: query});

	rule_dlg.show();
}

function editFilterTest(query) {

	if (dijit.byId("filterTestDlg"))
		dijit.byId("filterTestDlg").destroyRecursive();

	const test_dlg = new dijit.Dialog({
		id: "filterTestDlg",
		title: "Test Filter",
		style: "width: 600px",
		results: 0,
		limit: 100,
		max_offset: 10000,
		getTestResults: function(query, offset) {
		const updquery = query + "&offset=" + offset + "&limit=" + test_dlg.limit;

		console.log("getTestResults:" + offset);

		xhrPost("backend.php", updquery, (transport) => {
				try {
					const result = JSON.parse(transport.responseText);

					if (result && dijit.byId("filterTestDlg") && dijit.byId("filterTestDlg").open) {
						test_dlg.results += result.length;

						console.log("got results:" + result.length);

						$("prefFilterProgressMsg").innerHTML = __("Looking for articles (%d processed, %f found)...")
							.replace("%f", test_dlg.results)
							.replace("%d", offset);

						console.log(offset + " " + test_dlg.max_offset);

						for (let i = 0; i < result.length; i++) {
							const tmp = new Element("table");
							tmp.innerHTML = result[i];
							dojo.parser.parse(tmp);

							$("prefFilterTestResultList").innerHTML += tmp.innerHTML;
						}

						if (test_dlg.results < 30 && offset < test_dlg.max_offset) {

							// get the next batch
							window.setTimeout(function () {
								test_dlg.getTestResults(query, offset + test_dlg.limit);
							}, 0);

						} else {
							// all done

							Element.hide("prefFilterLoadingIndicator");

							if (test_dlg.results == 0) {
								$("prefFilterTestResultList").innerHTML = "<tr><td align='center'>No recent articles matching this filter have been found.</td></tr>";
								$("prefFilterProgressMsg").innerHTML = "Articles matching this filter:";
							} else {
								$("prefFilterProgressMsg").innerHTML = __("Found %d articles matching this filter:")
									.replace("%d", test_dlg.results);
							}

						}

					} else if (!result) {
						console.log("getTestResults: can't parse results object");

						Element.hide("prefFilterLoadingIndicator");

						notify_error("Error while trying to get filter test results.");

					} else {
						console.log("getTestResults: dialog closed, bailing out.");
					}
				} catch (e) {
					exception_error(e);
				}

			});
		},
		href: query});

	dojo.connect(test_dlg, "onLoad", null, function(e) {
		test_dlg.getTestResults(query, 0);
	});

	test_dlg.show();

}

function quickAddFilter() {
	let query;

	if (!inPreferences()) {
		query = { op: "pref-filters", method: "newfilter",
			feed: getActiveFeedId(), is_cat: activeFeedIsCat() };
	} else {
		query = { op: "pref-filters", method: "newfilter" };
	}

	console.log('quickAddFilter', query);

	if (dijit.byId("feedEditDlg"))
		dijit.byId("feedEditDlg").destroyRecursive();

	if (dijit.byId("filterEditDlg"))
		dijit.byId("filterEditDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "filterEditDlg",
		title: __("Create Filter"),
		style: "width: 600px",
		test: function() {
			const query = "backend.php?" + dojo.formToQuery("filter_new_form") + "&savemode=test";

			editFilterTest(query);
		},
		selectRules: function(select) {
			$$("#filterDlg_Matches input[type=checkbox]").each(function(e) {
				e.checked = select;
				if (select)
					e.parentNode.addClassName("Selected");
				else
					e.parentNode.removeClassName("Selected");
			});
		},
		selectActions: function(select) {
			$$("#filterDlg_Actions input[type=checkbox]").each(function(e) {
				e.checked = select;

				if (select)
					e.parentNode.addClassName("Selected");
				else
					e.parentNode.removeClassName("Selected");

			});
		},
		editRule: function(e) {
			const li = e.parentNode;
			const rule = li.getElementsByTagName("INPUT")[1].value;
			addFilterRule(li, rule);
		},
		editAction: function(e) {
			const li = e.parentNode;
			const action = li.getElementsByTagName("INPUT")[1].value;
			addFilterAction(li, action);
		},
		addAction: function() { addFilterAction(); },
		addRule: function() { addFilterRule(); },
		deleteAction: function() {
			$$("#filterDlg_Actions li[class*=Selected]").each(function(e) { e.parentNode.removeChild(e) });
		},
		deleteRule: function() {
			$$("#filterDlg_Matches li[class*=Selected]").each(function(e) { e.parentNode.removeChild(e) });
		},
		execute: function() {
			if (this.validate()) {

				const query = dojo.formToQuery("filter_new_form");

				xhrPost("backend.php", query, (transport) => {
					if (inPreferences()) {
						updateFilterList();
					}

					dialog.hide();
				});
			}
		},
		href: "backend.php?" + dojo.objectToQuery(query)});

	if (!inPreferences()) {
		const selectedText = getSelectionText();

		const lh = dojo.connect(dialog, "onLoad", function(){
			dojo.disconnect(lh);

			if (selectedText != "") {

				const feed_id = activeFeedIsCat() ? 'CAT:' + parseInt(getActiveFeedId()) :
					getActiveFeedId();

				const rule = { reg_exp: selectedText, feed_id: [feed_id], filter_type: 1 };

				addFilterRule(null, dojo.toJson(rule));

			} else {

				const query = { op: "rpc", method: "getlinktitlebyid", id: getActiveArticleId() };

				xhrPost("backend.php", query, (transport) => {
					const reply = JSON.parse(transport.responseText);

					let title = false;

					if (reply && reply.title) title = reply.title;

					if (title || getActiveFeedId() || activeFeedIsCat()) {

						console.log(title + " " + getActiveFeedId());

						const feed_id = activeFeedIsCat() ? 'CAT:' + parseInt(getActiveFeedId()) :
							getActiveFeedId();

						const rule = { reg_exp: title, feed_id: [feed_id], filter_type: 1 };

						addFilterRule(null, dojo.toJson(rule));
					}
				});
			}
		});
	}

	dialog.show();

}

function unsubscribeFeed(feed_id, title) {

	const msg = __("Unsubscribe from %s?").replace("%s", title);

	if (title == undefined || confirm(msg)) {
		notify_progress("Removing feed...");

		const query = { op: "pref-feeds", quiet: 1, method: "remove", ids: feed_id };

		xhrPost("backend.php", query, (transport) => {
			if (dijit.byId("feedEditDlg")) dijit.byId("feedEditDlg").hide();

			if (inPreferences()) {
				updateFeedList();
			} else {
				if (feed_id == getActiveFeedId())
					setTimeout(function() { viewfeed({feed:-5}) }, 100);

				if (feed_id < 0) updateFeedList();
			}
		});
	}

	return false;
}


function backend_sanity_check_callback(transport) {

	const reply = JSON.parse(transport.responseText);

	if (!reply) {
		fatalError(3, "Sanity check: invalid RPC reply", transport.responseText);
		return;
	}

	const error_code = reply['error']['code'];

	if (error_code && error_code != 0) {
		return fatalError(error_code, reply['error']['message']);
	}

	console.log("sanity check ok");

	const params = reply['init-params'];

	if (params) {
		console.log('reading init-params...');

		for (const k in params) {
			if (params.hasOwnProperty(k)) {
				switch (k) {
					case "label_base_index":
						_label_base_index = parseInt(params[k])
						break;
					case "hotkeys":
						// filter mnemonic definitions (used for help panel) from hotkeys map
						// i.e. *(191)|Ctrl-/ -> *(191)

						const tmp = [];
						for (const sequence in params[k][1]) {
							const filtered = sequence.replace(/\|.*$/, "");
							tmp[filtered] = params[k][1][sequence];
						}

						params[k][1] = tmp;
						break;
				}

				console.log("IP:", k, "=>", params[k]);
			}
		}

		init_params = params;

		// PluginHost might not be available on non-index pages
		window.PluginHost && PluginHost.run(PluginHost.HOOK_PARAMS_LOADED, init_params);
	}

	init_second_stage();
}

// noinspection JSUnusedGlobalSymbols
function genUrlChangeKey(feed, is_cat) {
	if (confirm(__("Generate new syndication address for this feed?"))) {

		notify_progress("Trying to change address...", true);

		const query = { op: "pref-feeds", method: "regenFeedKey", id: feed, is_cat: is_cat };

		xhrJson("backend.php", query, (reply) => {
			const new_link = reply.link;
			const e = $('gen_feed_url');

			if (new_link) {
				e.innerHTML = e.innerHTML.replace(/\&amp;key=.*$/,
					"&amp;key=" + new_link);

				e.href = e.href.replace(/\&key=.*$/,
					"&key=" + new_link);

				new Effect.Highlight(e);

				notify('');

			} else {
				notify_error("Could not change feed URL.");
			}
		});
	}
	return false;
}

// mode = all, none, invert
function selectTableRows(id, mode) {
	const rows = $(id).rows;

	for (let i = 0; i < rows.length; i++) {
		const row = rows[i];
		let cb = false;
		let dcb = false;

		if (row.id && row.className) {
			const bare_id = row.id.replace(/^[A-Z]*?-/, "");
			const inputs = rows[i].getElementsByTagName("input");

			for (let j = 0; j < inputs.length; j++) {
				const input = inputs[j];

				if (input.getAttribute("type") == "checkbox" &&
						input.id.match(bare_id)) {

					cb = input;
					dcb = dijit.getEnclosingWidget(cb);
					break;
				}
			}

			if (cb || dcb) {
				const issel = row.hasClassName("Selected");

				if (mode == "all" && !issel) {
					row.addClassName("Selected");
					cb.checked = true;
					if (dcb) dcb.set("checked", true);
				} else if (mode == "none" && issel) {
					row.removeClassName("Selected");
					cb.checked = false;
					if (dcb) dcb.set("checked", false);

				} else if (mode == "invert") {

					if (issel) {
						row.removeClassName("Selected");
						cb.checked = false;
						if (dcb) dcb.set("checked", false);
					} else {
						row.addClassName("Selected");
						cb.checked = true;
						if (dcb) dcb.set("checked", true);
					}
				}
			}
		}
	}

}

function getSelectedTableRowIds(id) {
	const rows = [];

	const elem_rows = $(id).rows;

	for (let i = 0; i < elem_rows.length; i++) {
		if (elem_rows[i].hasClassName("Selected")) {
			const bare_id = elem_rows[i].id.replace(/^[A-Z]*?-/, "");
			rows.push(bare_id);
		}
	}

	return rows;
}

function editFeed(feed) {
	if (feed <= 0)
		return alert(__("You can't edit this kind of feed."));

	const query = { op: "pref-feeds", method: "editfeed", id: feed };

	console.log("editFeed", query);

	if (dijit.byId("filterEditDlg"))
		dijit.byId("filterEditDlg").destroyRecursive();

	if (dijit.byId("feedEditDlg"))
		dijit.byId("feedEditDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "feedEditDlg",
		title: __("Edit Feed"),
		style: "width: 600px",
		execute: function() {
			if (this.validate()) {
				notify_progress("Saving data...", true);

				xhrPost("backend.php", dialog.attr('value'), () => {
					dialog.hide();
					notify('');
					updateFeedList();
				});
			}
		},
		href: "backend.php?" + dojo.objectToQuery(query)});

	dialog.show();
}

function feedBrowser() {
	const query = { op: "feeds", method: "feedBrowser" };

	if (dijit.byId("feedAddDlg"))
		dijit.byId("feedAddDlg").hide();

	if (dijit.byId("feedBrowserDlg"))
		dijit.byId("feedBrowserDlg").destroyRecursive();

	// noinspection JSUnusedGlobalSymbols
	const dialog = new dijit.Dialog({
		id: "feedBrowserDlg",
		title: __("More Feeds"),
		style: "width: 600px",
		getSelectedFeedIds: function () {
			const list = $$("#browseFeedList li[id*=FBROW]");
			const selected = [];

			list.each(function (child) {
				const id = child.id.replace("FBROW-", "");

				if (child.hasClassName('Selected')) {
					selected.push(id);
				}
			});

			return selected;
		},
		getSelectedFeeds: function () {
			const list = $$("#browseFeedList li.Selected");
			const selected = [];

			list.each(function (child) {
				const title = child.getElementsBySelector("span.fb_feedTitle")[0].innerHTML;
				const url = child.getElementsBySelector("a.fb_feedUrl")[0].href;

				selected.push([title, url]);

			});

			return selected;
		},

		subscribe: function () {
			const mode = this.attr('value').mode;
			let selected = [];

			if (mode == "1")
				selected = this.getSelectedFeeds();
			else
				selected = this.getSelectedFeedIds();

			if (selected.length > 0) {
				dijit.byId("feedBrowserDlg").hide();

				notify_progress("Loading, please wait...", true);

				const query = { op: "rpc", method: "massSubscribe",
					payload: JSON.stringify(selected), mode: mode };

				xhrPost("backend.php", query, () => {
					notify('');
					updateFeedList();
				});

			} else {
				alert(__("No feeds are selected."));
			}

		},
		update: function () {
			Element.show('feed_browser_spinner');

			xhrPost("backend.php", dialog.attr("value"), (transport) => {
				notify('');

				Element.hide('feed_browser_spinner');

				const reply = JSON.parse(transport.responseText);
				const mode = reply['mode'];

				if ($("browseFeedList") && reply['content']) {
					$("browseFeedList").innerHTML = reply['content'];
				}

				dojo.parser.parse("browseFeedList");

				if (mode == 2) {
					Element.show(dijit.byId('feed_archive_remove').domNode);
				} else {
					Element.hide(dijit.byId('feed_archive_remove').domNode);
				}
			});
		},
		removeFromArchive: function () {
			const selected = this.getSelectedFeedIds();

			if (selected.length > 0) {
				if (confirm(__("Remove selected feeds from the archive? Feeds with stored articles will not be removed."))) {
					Element.show('feed_browser_spinner');

					const query = { op: "rpc", method: "remarchive", ids: selected.toString() };

					xhrPost("backend.php", query, () => {
						dialog.update();
					});
				}
			}
		},
		execute: function () {
			if (this.validate()) {
				this.subscribe();
			}
		},
		href: "backend.php?" + dojo.objectToQuery(query)
	});

	dialog.show();
}

// noinspection JSUnusedGlobalSymbols
function showFeedsWithErrors() {
	const query = { op: "pref-feeds", method: "feedsWithErrors" };

	if (dijit.byId("errorFeedsDlg"))
		dijit.byId("errorFeedsDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "errorFeedsDlg",
		title: __("Feeds with update errors"),
		style: "width: 600px",
		getSelectedFeeds: function() {
			return getSelectedTableRowIds("prefErrorFeedList");
		},
		removeSelected: function() {
			const sel_rows = this.getSelectedFeeds();

			if (sel_rows.length > 0) {
				if (confirm(__("Remove selected feeds?"))) {
					notify_progress("Removing selected feeds...", true);

					const query = { op: "pref-feeds", method: "remove",
						ids: sel_rows.toString() };

					xhrPost("backend.php",	query, () => {
						notify('');
						dialog.hide();
						updateFeedList();
					});
				}

			} else {
				alert(__("No feeds are selected."));
			}
		},
		execute: function() {
			if (this.validate()) {
				//
			}
		},
		href: "backend.php?" + dojo.objectToQuery(query)
	});

	dialog.show();
}

function get_timestamp() {
	const date = new Date();
	return Math.round(date.getTime() / 1000);
}

function helpDialog(topic) {
	const query = "backend.php?op=backend&method=help&topic=" + param_escape(topic);

	if (dijit.byId("helpDlg"))
		dijit.byId("helpDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "helpDlg",
		title: __("Help"),
		style: "width: 600px",
		href: query,
	});

	dialog.show();
}

// noinspection JSUnusedGlobalSymbols
function label_to_feed_id(label) {
	return _label_base_index - 1 - Math.abs(label);
}

// noinspection JSUnusedGlobalSymbols
function feed_to_label_id(feed) {
	return _label_base_index - 1 + Math.abs(feed);
}

// http://stackoverflow.com/questions/6251937/how-to-get-selecteduser-highlighted-text-in-contenteditable-element-and-replac
function getSelectionText() {
	let text = "";

	if (typeof window.getSelection != "undefined") {
		const sel = window.getSelection();
		if (sel.rangeCount) {
			const container = document.createElement("div");
			for (let i = 0, len = sel.rangeCount; i < len; ++i) {
				container.appendChild(sel.getRangeAt(i).cloneContents());
			}
			text = container.innerHTML;
		}
	} else if (typeof document.selection != "undefined") {
		if (document.selection.type == "Text") {
			text = document.selection.createRange().textText;
		}
	}

	return text.stripTags();
}

// noinspection JSUnusedGlobalSymbols
function popupOpenUrl(url) {
	const w = window.open("");

	w.opener = null;
	w.location = url;
}

// noinspection JSUnusedGlobalSymbols
function popupOpenArticle(id) {
	const w = window.open("",
		"ttrss_article_popup",
		"height=900,width=900,resizable=yes,status=no,location=no,menubar=no,directories=no,scrollbars=yes,toolbar=no");

	w.opener = null;
	w.location = "backend.php?op=article&method=view&mode=raw&html=1&zoom=1&id=" + id + "&csrf_token=" + getInitParam("csrf_token");
}

function keyeventToAction(e) {

	const hotkeys_map = getInitParam("hotkeys");
	const keycode = e.which;
	const keychar = String.fromCharCode(keycode).toLowerCase();

	if (keycode == 27) { // escape and drop prefix
		hotkey_prefix = false;
	}

	if (keycode == 16 || keycode == 17) return; // ignore lone shift / ctrl

	if (!hotkey_prefix && hotkeys_map[0].indexOf(keychar) != -1) {

		const date = new Date();
		const ts = Math.round(date.getTime() / 1000);

		hotkey_prefix = keychar;
		hotkey_prefix_pressed = ts;

		$("cmdline").innerHTML = keychar;
		Element.show("cmdline");

		e.stopPropagation();

		return false;
	}

	Element.hide("cmdline");

	let hotkey_name = keychar.search(/[a-zA-Z0-9]/) != -1 ? keychar : "(" + keycode + ")";

	// ensure ^*char notation
	if (e.shiftKey) hotkey_name = "*" + hotkey_name;
	if (e.ctrlKey) hotkey_name = "^" + hotkey_name;
	if (e.altKey) hotkey_name = "+" + hotkey_name;
	if (e.metaKey) hotkey_name = "%" + hotkey_name;

	const hotkey_full = hotkey_prefix ? hotkey_prefix + " " + hotkey_name : hotkey_name;
	hotkey_prefix = false;

	let action_name = false;

	for (const sequence in hotkeys_map[1]) {
		if (sequence == hotkey_full) {
			action_name = hotkeys_map[1][sequence];
			break;
		}
	}

	console.log('keyeventToAction', hotkey_full, '=>', action_name);

	return action_name;
}
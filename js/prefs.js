/* global dijit, __ */

let seq = "";

function notify_callback2(transport, sticky) {
	notify_info(transport.responseText, sticky);
}

function updateFeedList() {

	const user_search = $("feed_search");
	let search = "";
	if (user_search) { search = user_search.value; }

	xhrPost("backend.php", { op: "pref-feeds", search: search }, (transport) => {
		dijit.byId('feedConfigTab').attr('content', transport.responseText);
		selectTab("feedConfig", true);
		notify("");
	});
}

function checkInactiveFeeds() {
	xhrPost("backend.php", { op: "pref-feeds", method: "getinactivefeeds" }, (transport) => {
		if (parseInt(transport.responseText) > 0) {
			Element.show(dijit.byId("pref_feeds_inactive_btn").domNode);
		}
	});
}

function updateUsersList(sort_key) {
	const user_search = $("user_search");
	const search = user_search ? user_search.value : "";

	const query = { op: "pref-users", sort:  sort_key, search: search };

	xhrPost("backend.php", query, (transport) => {
		dijit.byId('userConfigTab').attr('content', transport.responseText);
		selectTab("userConfig", true)
		notify("");
	});
}

function addUser() {
	const login = prompt(__("Please enter login:"), "");

	if (login == null) {
		return false;
	}

	if (login == "") {
		alert(__("Can't create user: no login specified."));
		return false;
	}

	notify_progress("Adding user...");

	xhrPost("backend.php", { op: "pref-users", method: "add", login: login }, (transport) => {
		notify_callback2(transport);
		updateUsersList();
	});

}

function editUser(id) {

	const query = "backend.php?op=pref-users&method=edit&id=" +
		param_escape(id);

	if (dijit.byId("userEditDlg"))
		dijit.byId("userEditDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "userEditDlg",
		title: __("User Editor"),
		style: "width: 600px",
		execute: function () {
			if (this.validate()) {
				notify_progress("Saving data...", true);

				xhrPost("backend.php", dojo.formToObject("user_edit_form"), (transport) => {
					dialog.hide();
					updateUsersList();
				});
			}
		},
		href: query
	});

	dialog.show();
}

function editFilter(id) {

	const query = "backend.php?op=pref-filters&method=edit&id=" + param_escape(id);

	if (dijit.byId("feedEditDlg"))
		dijit.byId("feedEditDlg").destroyRecursive();

	if (dijit.byId("filterEditDlg"))
		dijit.byId("filterEditDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "filterEditDlg",
		title: __("Edit Filter"),
		style: "width: 600px",

		test: function () {
			const query = "backend.php?" + dojo.formToQuery("filter_edit_form") + "&savemode=test";

			editFilterTest(query);
		},
		selectRules: function (select) {
			$$("#filterDlg_Matches input[type=checkbox]").each(function (e) {
				e.checked = select;
				if (select)
					e.parentNode.addClassName("Selected");
				else
					e.parentNode.removeClassName("Selected");
			});
		},
		selectActions: function (select) {
			$$("#filterDlg_Actions input[type=checkbox]").each(function (e) {
				e.checked = select;

				if (select)
					e.parentNode.addClassName("Selected");
				else
					e.parentNode.removeClassName("Selected");

			});
		},
		editRule: function (e) {
			const li = e.parentNode;
			const rule = li.getElementsByTagName("INPUT")[1].value;
			addFilterRule(li, rule);
		},
		editAction: function (e) {
			const li = e.parentNode;
			const action = li.getElementsByTagName("INPUT")[1].value;
			addFilterAction(li, action);
		},
		removeFilter: function () {
			const msg = __("Remove filter?");

			if (confirm(msg)) {
				this.hide();

				notify_progress("Removing filter...");

				const query = { op: "pref-filters", method: "remove", ids: this.attr('value').id };

				xhrPost("backend.php", query, () => {
					updateFilterList();
				});
			}
		},
		addAction: function () {
			addFilterAction();
		},
		addRule: function () {
			addFilterRule();
		},
		deleteAction: function () {
			$$("#filterDlg_Actions li[class*=Selected]").each(function (e) {
				e.parentNode.removeChild(e)
			});
		},
		deleteRule: function () {
			$$("#filterDlg_Matches li[class*=Selected]").each(function (e) {
				e.parentNode.removeChild(e)
			});
		},
		execute: function () {
			if (this.validate()) {

				notify_progress("Saving data...", true);

				xhrPost("backend.php", dojo.formToObject("filter_edit_form"), () => {
					dialog.hide();
					updateFilterList();
				});
			}
		},
		href: query
	});

	dialog.show();
}


function getSelectedLabels() {
	const tree = dijit.byId("labelTree");
	const items = tree.model.getCheckedItems();
	const rv = [];

	items.each(function(item) {
		rv.push(tree.model.store.getValue(item, 'bare_id'));
	});

	return rv;
}

function getSelectedUsers() {
	return getSelectedTableRowIds("prefUserList");
}

function getSelectedFeeds() {
	const tree = dijit.byId("feedTree");
	const items = tree.model.getCheckedItems();
	const rv = [];

	items.each(function(item) {
		if (item.id[0].match("FEED:"))
			rv.push(tree.model.store.getValue(item, 'bare_id'));
	});

	return rv;
}

function getSelectedCategories() {
	const tree = dijit.byId("feedTree");
	const items = tree.model.getCheckedItems();
	const rv = [];

	items.each(function(item) {
		if (item.id[0].match("CAT:"))
			rv.push(tree.model.store.getValue(item, 'bare_id'));
	});

	return rv;
}

function getSelectedFilters() {
	const tree = dijit.byId("filterTree");
	const items = tree.model.getCheckedItems();
	const rv = [];

	items.each(function(item) {
		rv.push(tree.model.store.getValue(item, 'bare_id'));
	});

	return rv;

}

function removeSelectedLabels() {

	const sel_rows = getSelectedLabels();

	if (sel_rows.length > 0) {
		if (confirm(__("Remove selected labels?"))) {
			notify_progress("Removing selected labels...");

			const query = { op: "pref-labels", method: "remove",
				ids: sel_rows.toString() };

			xhrPost("backend.php",	query, () => {
				updateLabelList();
			});
		}
	} else {
		alert(__("No labels are selected."));
	}

	return false;
}

function removeSelectedUsers() {

	const sel_rows = getSelectedUsers();

	if (sel_rows.length > 0) {

		if (confirm(__("Remove selected users? Neither default admin nor your account will be removed."))) {
			notify_progress("Removing selected users...");

			const query = { op: "pref-users", method: "remove",
				ids: sel_rows.toString() };

			xhrPost("backend.php", query, () => {
				updateUsersList();
			});
		}

	} else {
		alert(__("No users are selected."));
	}

	return false;
}

function removeSelectedFilters() {

	const sel_rows = getSelectedFilters();

	if (sel_rows.length > 0) {
		if (confirm(__("Remove selected filters?"))) {
			notify_progress("Removing selected filters...");

			const query = { op: "pref-filters", method: "remove",
				ids:  sel_rows.toString() };

			xhrPost("backend.php", query, () => {
				updateFilterList();
			});
		}
	} else {
		alert(__("No filters are selected."));
	}

	return false;
}

function removeSelectedFeeds() {

	const sel_rows = getSelectedFeeds();

	if (sel_rows.length > 0) {
		if (confirm(__("Unsubscribe from selected feeds?"))) {

			notify_progress("Unsubscribing from selected feeds...", true);

			const query = { op: "pref-feeds", method: "remove",
				ids: sel_rows.toString() };

			xhrPost("backend.php", query, () => {
				updateFeedList();
			});
		}

	} else {
		alert(__("No feeds are selected."));
	}

	return false;
}

function editSelectedUser() {
	const rows = getSelectedUsers();

	if (rows.length == 0) {
		alert(__("No users are selected."));
		return;
	}

	if (rows.length > 1) {
		alert(__("Please select only one user."));
		return;
	}

	notify("");

	editUser(rows[0]);
}

function resetSelectedUserPass() {

	const rows = getSelectedUsers();

	if (rows.length == 0) {
		alert(__("No users are selected."));
		return;
	}

	if (rows.length > 1) {
		alert(__("Please select only one user."));
		return;
	}

	if (confirm(__("Reset password of selected user?"))) {
		notify_progress("Resetting password for selected user...");

		const id = rows[0];

		xhrPost("backend.php", { op: "pref-users", method: "resetPass", id: id }, (transport) => {
			notify_info(transport.responseText, true);
		});

	}
}

function selectedUserDetails() {

	const rows = getSelectedUsers();

	if (rows.length == 0) {
		alert(__("No users are selected."));
		return;
	}

	if (rows.length > 1) {
		alert(__("Please select only one user."));
		return;
	}

	const query = "backend.php?op=pref-users&method=userdetails&id=" + param_escape(rows[0]);

	if (dijit.byId("userDetailsDlg"))
		dijit.byId("userDetailsDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "userDetailsDlg",
		title: __("User details"),
		style: "width: 600px",
		execute: function () {
			dialog.hide();
		},
		href: query
	});

	dialog.show();
}


function editSelectedFilter() {
	const rows = getSelectedFilters();

	if (rows.length == 0) {
		alert(__("No filters are selected."));
		return;
	}

	if (rows.length > 1) {
		alert(__("Please select only one filter."));
		return;
	}

	notify("");

	editFilter(rows[0]);

}

function joinSelectedFilters() {
	const rows = getSelectedFilters();

	if (rows.length == 0) {
		alert(__("No filters are selected."));
		return;
	}

	if (confirm(__("Combine selected filters?"))) {
		notify_progress("Joining filters...");

		xhrPost("backend.php", { op: "pref-filters", method: "join", ids: rows.toString() }, () => {
			updateFilterList();
		});
	}
}

function editSelectedFeed() {
	const rows = getSelectedFeeds();

	if (rows.length == 0) {
		alert(__("No feeds are selected."));
		return;
	}

	if (rows.length > 1) {
		return editSelectedFeeds();
	}

	notify("");

	editFeed(rows[0], {});

}

function editSelectedFeeds() {
	const rows = getSelectedFeeds();

	if (rows.length == 0) {
		alert(__("No feeds are selected."));
		return;
	}

	notify_progress("Loading, please wait...");

	if (dijit.byId("feedEditDlg"))
		dijit.byId("feedEditDlg").destroyRecursive();

	xhrPost("backend.php", { op: "pref-feeds", method: "editfeeds", ids: rows.toString() }, (transport) => {
		notify("");

		const dialog = new dijit.Dialog({
			id: "feedEditDlg",
			title: __("Edit Multiple Feeds"),
			style: "width: 600px",
			getChildByName: function (name) {
				let rv = null;
				this.getChildren().each(
					function (child) {
						if (child.name == name) {
							rv = child;
							return;
						}
					});
				return rv;
			},
			toggleField: function (checkbox, elem, label) {
				this.getChildByName(elem).attr('disabled', !checkbox.checked);

				if ($(label))
					if (checkbox.checked)
						$(label).removeClassName('insensitive');
					else
						$(label).addClassName('insensitive');

			},
			execute: function () {
				if (this.validate() && confirm(__("Save changes to selected feeds?"))) {
					const query = this.attr('value');

					/* normalize unchecked checkboxes because [] is not serialized */

					Object.keys(query).each((key) => {
						let val = query[key];

						if (typeof val == "object" && val.length == 0)
							query[key] = ["off"];
					});

					notify_progress("Saving data...", true);

					xhrPost("backend.php", query, () => {
						dialog.hide();
						updateFeedList();
					});
				}
			},
			content: transport.responseText
		});

		dialog.show();
	});
}

function opmlImportComplete(iframe) {
	if (!iframe.contentDocument.body.innerHTML) return false;

	Element.show(iframe);

	notify('');

	if (dijit.byId('opmlImportDlg'))
		dijit.byId('opmlImportDlg').destroyRecursive();

	const content = iframe.contentDocument.body.innerHTML;

	const dialog = new dijit.Dialog({
		id: "opmlImportDlg",
		title: __("OPML Import"),
		style: "width: 600px",
		onCancel: function () {
			window.location.reload();
		},
		execute: function () {
			window.location.reload();
		},
		content: content
	});

	dialog.show();
}

function opmlImport() {

	const opml_file = $("opml_file");

	if (opml_file.value.length == 0) {
		alert(__("Please choose an OPML file first."));
		return false;
	} else {
		notify_progress("Importing, please wait...", true);

		Element.show("upload_iframe");

		return true;
	}
}


function updateFilterList() {
	const user_search = $("filter_search");
	let search = "";
	if (user_search) { search = user_search.value; }

	xhrPost("backend.php", { op: "pref-filters", search: search }, (transport) => {
		dijit.byId('filterConfigTab').attr('content', transport.responseText);
		notify("");
	});
}

function updateLabelList() {
	xhrPost("backend.php", { op: "pref-labels" }, (transport) => {
		dijit.byId('labelConfigTab').attr('content', transport.responseText);
		notify("");
	});
}

function updatePrefsList() {
	xhrPost("backend.php", { op: "pref-prefs" }, (transport) => {
		dijit.byId('genConfigTab').attr('content', transport.responseText);
		notify("");
	});
}

function updateSystemList() {
	xhrPost("backend.php", { op: "pref-system" }, (transport) => {
		dijit.byId('systemConfigTab').attr('content', transport.responseText);
		notify("");
	});
}

function selectTab(id, noupdate) {
	if (!noupdate) {
		notify_progress("Loading, please wait...");

		switch (id) {
			case "feedConfig":
				updateFeedList();
				break;
			case "filterConfig":
				updateFilterList();
				break;
			case "labelConfig":
				updateLabelList();
				break;
			case "genConfig":
				updatePrefsList();
				break;
			case "userConfig":
				updateUsersList();
				break;
			case "systemConfig":
				updateSystemList();
				break;
			default:
				console.warn("unknown tab", id);
		}

		const tab = dijit.byId(id + "Tab");
		dijit.byId("pref-tabs").selectChild(tab);

	}
}

function init_second_stage() {
	document.onkeydown = pref_hotkey_handler;
	setLoadingProgress(50);
	notify("");

	let tab = getURLParam('tab');

	if (tab) {
		tab = dijit.byId(tab + "Tab");
		if (tab) dijit.byId("pref-tabs").selectChild(tab);
	}

	const method = getURLParam('method');

	if (method == 'editFeed') {
		const param = getURLParam('methodparam');

		window.setTimeout(function() { editFeed(param) }, 100);
	}

	setInterval(hotkeyPrefixTimeout, 5*1000);
}

function init() {
	window.onerror = function (message, filename, lineno, colno, error) {
		report_error(message, filename, lineno, colno, error);
	};

	require(["dojo/_base/kernel",
		"dojo/ready",
		"dojo/parser",
		"dojo/_base/loader",
		"dojo/_base/html",
		"dijit/ColorPalette",
		"dijit/Dialog",
		"dijit/form/Button",
		"dijit/form/CheckBox",
		"dijit/form/DropDownButton",
		"dijit/form/FilteringSelect",
		"dijit/form/MultiSelect",
		"dijit/form/Form",
		"dijit/form/RadioButton",
		"dijit/form/ComboButton",
		"dijit/form/Select",
		"dijit/form/SimpleTextarea",
		"dijit/form/TextBox",
		"dijit/form/ValidationTextBox",
		"dijit/InlineEditBox",
		"dijit/layout/AccordionContainer",
		"dijit/layout/AccordionPane",
		"dijit/layout/BorderContainer",
		"dijit/layout/ContentPane",
		"dijit/layout/TabContainer",
		"dijit/Menu",
		"dijit/ProgressBar",
		"dijit/Toolbar",
		"dijit/Tree",
		"dijit/tree/dndSource",
		"dojo/data/ItemFileWriteStore",
		"lib/CheckBoxStoreModel",
		"lib/CheckBoxTree",
		"fox/PrefFeedStore",
		"fox/PrefFilterStore",
		"fox/PrefFeedTree",
		"fox/PrefFilterTree",
		"fox/PrefLabelTree"], function (dojo, ready, parser) {

		ready(function () {
			try {
				parser.parse();

				setLoadingProgress(50);

				const clientTzOffset = new Date().getTimezoneOffset() * 60;
				const params = { op: "rpc", method: "sanityCheck", clientTzOffset: clientTzOffset };

				xhrPost("backend.php", params, (transport) => {
					backend_sanity_check_callback(transport);
				});

			} catch (e) {
				exception_error(e);
			}
		});
	});
}


function validatePrefsReset() {
	if (confirm(__("Reset to defaults?"))) {

		const query = "?op=pref-prefs&method=resetconfig";

		xhrPost("backend.php", { op: "pref-prefs", method: "resetconfig" }, (transport) => {
			updatePrefsList();
			notify_info(transport.responseText);
		});
	}

	return false;
}

function pref_hotkey_handler(e) {
	if (e.target.nodeName == "INPUT" || e.target.nodeName == "TEXTAREA") return;

	const action_name = keyeventToAction(e);

	if (action_name) {
		switch (action_name) {
			case "feed_subscribe":
				quickAddFeed();
				return false;
			case "create_label":
				addLabel();
				return false;
			case "create_filter":
				quickAddFilter();
				return false;
			case "help_dialog":
				helpDialog("main");
				return false;
			default:
				console.log("unhandled action: " + action_name + "; keycode: " + e.which);
		}
	}
}

function removeCategory(id, item) {

	if (confirm(__("Remove category %s? Any nested feeds would be placed into Uncategorized.").replace("%s", item.name))) {
		notify_progress("Removing category...");

		const query = { op: "pref-feeds", method: "removeCat",
			ids: id };

		xhrPost("backend.php", query, () => {
			notify('');
			updateFeedList();
		});
	}
}

function removeSelectedCategories() {
	const sel_rows = getSelectedCategories();

	if (sel_rows.length > 0) {
		if (confirm(__("Remove selected categories?"))) {
			notify_progress("Removing selected categories...");

			const query = { op: "pref-feeds", method: "removeCat",
				ids: sel_rows.toString() };

			xhrPost("backend.php", query, () => {
				updateFeedList();
			});
		}
	} else {
		alert(__("No categories are selected."));
	}

	return false;
}

function createCategory() {
	const title = prompt(__("Category title:"));

	if (title) {
		notify_progress("Creating category...");

		xhrPost("backend.php", { op: "pref-feeds", method: "addCat", cat: title }, () => {
			notify('');
			updateFeedList();
		});
	}
}

function showInactiveFeeds() {
	const query = "backend.php?op=pref-feeds&method=inactiveFeeds";

	if (dijit.byId("inactiveFeedsDlg"))
		dijit.byId("inactiveFeedsDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "inactiveFeedsDlg",
		title: __("Feeds without recent updates"),
		style: "width: 600px",
		getSelectedFeeds: function () {
			return getSelectedTableRowIds("prefInactiveFeedList");
		},
		removeSelected: function () {
			const sel_rows = this.getSelectedFeeds();

			if (sel_rows.length > 0) {
				if (confirm(__("Remove selected feeds?"))) {
					notify_progress("Removing selected feeds...", true);

					const query = { op: "pref-feeds", method: "remove",
						ids: sel_rows.toString() };

					xhrPost("backend.php", query, () => {
						notify('');
						dialog.hide();
						updateFeedList();
					});
				}

			} else {
				alert(__("No feeds are selected."));
			}
		},
		execute: function () {
			if (this.validate()) {
			}
		},
		href: query
	});

	dialog.show();
}

function opmlRegenKey() {
	if (confirm(__("Replace current OPML publishing address with a new one?"))) {
		notify_progress("Trying to change address...", true);

		xhrJson("backend.php", { op: "pref-feeds", method: "regenOPMLKey" }, (reply) => {
			if (reply) {
				const new_link = reply.link;
				const e = $('pub_opml_url');

				if (new_link) {
					e.href = new_link;
					e.innerHTML = new_link;

					new Effect.Highlight(e);

					notify('');

				} else {
					notify_error("Could not change feed URL.");
				}
			}
		});
	}
	return false;
}

function labelColorReset() {
	const labels = getSelectedLabels();

	if (labels.length > 0) {
		if (confirm(__("Reset selected labels to default colors?"))) {

			const query = { op: "pref-labels", method: "colorreset",
				ids: labels.toString() };

			xhrPost("backend.php", query, () => {
				updateLabelList();
			});
		}

	} else {
		alert(__("No labels are selected."));
	}
}

function inPreferences() {
	return true;
}

function editProfiles() {

	if (dijit.byId("profileEditDlg"))
		dijit.byId("profileEditDlg").destroyRecursive();

	const query = "backend.php?op=pref-prefs&method=editPrefProfiles";

	const dialog = new dijit.Dialog({
		id: "profileEditDlg",
		title: __("Settings Profiles"),
		style: "width: 600px",
		getSelectedProfiles: function () {
			return getSelectedTableRowIds("prefFeedProfileList");
		},
		removeSelected: function () {
			const sel_rows = this.getSelectedProfiles();

			if (sel_rows.length > 0) {
				if (confirm(__("Remove selected profiles? Active and default profiles will not be removed."))) {
					notify_progress("Removing selected profiles...", true);

					const query = { op: "rpc", method: "remprofiles",
						ids: sel_rows.toString() };

					xhrPost("backend.php", query, () => {
						notify('');
						editProfiles();
					});
				}

			} else {
				alert(__("No profiles are selected."));
			}
		},
		activateProfile: function () {
			const sel_rows = this.getSelectedProfiles();

			if (sel_rows.length == 1) {
				if (confirm(__("Activate selected profile?"))) {
					notify_progress("Loading, please wait...");

					xhrPost("backend.php", { op: "rpc", method: "setprofile", id: sel_rows.toString() },  () => {
						window.location.reload();
					});
				}

			} else {
				alert(__("Please choose a profile to activate."));
			}
		},
		addProfile: function () {
			if (this.validate()) {
				notify_progress("Creating profile...", true);

				const query = { op: "rpc", method: "addprofile", title: dialog.attr('value').newprofile };

				xhrPost("backend.php", query, () => {
					notify('');
					editProfiles();
				});

			}
		},
		execute: function () {
			if (this.validate()) {
			}
		},
		href: query
	});

	dialog.show();
}

/*
function activatePrefProfile() {

	const sel_rows = getSelectedFeedCats();

	if (sel_rows.length == 1) {

		const ok = confirm(__("Activate selected profile?"));

		if (ok) {
			notify_progress("Loading, please wait...");

			xhrPost("backend.php", { op: "rpc", method: "setprofile", id: sel_rows.toString() },  () => {
				window.location.reload();
			});
		}

	} else {
		alert(__("Please choose a profile to activate."));
	}

	return false;
} */

function clearFeedAccessKeys() {

	if (confirm(__("This will invalidate all previously generated feed URLs. Continue?"))) {
		notify_progress("Clearing URLs...");

		xhrPost("backend.php", { op: "pref-feeds", method: "clearKeys" }, () => {
			notify_info("Generated URLs cleared.");
		});
	}

	return false;
}

function resetFilterOrder() {
	notify_progress("Loading, please wait...");

	xhrPost("backend.php", { op: "pref-filters", method: "filtersortreset" }, () => {
		updateFilterList();
	});
}


function resetFeedOrder() {
	notify_progress("Loading, please wait...");

	xhrPost("backend.php", { op: "pref-feeds", method: "feedsortreset" }, () => {
		updateFeedList();
	});
}

function resetCatOrder() {
	notify_progress("Loading, please wait...");

	xhrPost("backend.php", { op: "pref-feeds", method: "catsortreset" }, () => {
		updateFeedList();
	});
}

function editCat(id, item) {
	const new_name = prompt(__('Rename category to:'), item.name);

	if (new_name && new_name != item.name) {

		notify_progress("Loading, please wait...");

		xhrPost("backend.php", { op: 'pref-feeds', method: 'renamecat', id: id, title: new_name }, () => {
			updateFeedList();
		});
	}
}

function editLabel(id) {
	const query = "backend.php?op=pref-labels&method=edit&id=" +
		param_escape(id);

	if (dijit.byId("labelEditDlg"))
		dijit.byId("labelEditDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "labelEditDlg",
		title: __("Label Editor"),
		style: "width: 600px",
		setLabelColor: function (id, fg, bg) {

			let kind = '';
			let color = '';

			if (fg && bg) {
				kind = 'both';
			} else if (fg) {
				kind = 'fg';
				color = fg;
			} else if (bg) {
				kind = 'bg';
				color = bg;
			}

			const e = $("LICID-" + id);

			if (e) {
				if (fg) e.style.color = fg;
				if (bg) e.style.backgroundColor = bg;
			}

			const query = { op: "pref-labels", method: "colorset", kind: kind,
				ids: id, fg: fg, bg: bg, color: color };

			xhrPost("backend.php", query, () => {
				updateFilterList(); // maybe there's labels in there
			});

		},
		execute: function () {
			if (this.validate()) {
				const caption = this.attr('value').caption;
				const fg_color = this.attr('value').fg_color;
				const bg_color = this.attr('value').bg_color;

				dijit.byId('labelTree').setNameById(id, caption);
				this.setLabelColor(id, fg_color, bg_color);
				this.hide();

				xhrPost("backend.php", this.attr('value'), () => {
					updateFilterList(); // maybe there's labels in there
				});
			}
		},
		href: query
	});

	dialog.show();
}


function customizeCSS() {
	const query = "backend.php?op=pref-prefs&method=customizeCSS";

	if (dijit.byId("cssEditDlg"))
		dijit.byId("cssEditDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "cssEditDlg",
		title: __("Customize stylesheet"),
		style: "width: 600px",
		execute: function () {
			notify_progress('Saving data...', true);

			xhrPost("backend.php", this.attr('value'), () => {
				window.location.reload();
			});

		},
		href: query
	});

	dialog.show();
}

function insertSSLserial(value) {
	dijit.byId("SSL_CERT_SERIAL").attr('value', value);
}

function gotoExportOpml(filename, settings) {
	const tmp = settings ? 1 : 0;
	document.location.href = "backend.php?op=opml&method=export&filename=" + filename + "&settings=" + tmp;
}


function batchSubscribe() {
	const query = "backend.php?op=pref-feeds&method=batchSubscribe";

	// overlapping widgets
	if (dijit.byId("batchSubDlg")) dijit.byId("batchSubDlg").destroyRecursive();
	if (dijit.byId("feedAddDlg"))	dijit.byId("feedAddDlg").destroyRecursive();

	const dialog = new dijit.Dialog({
		id: "batchSubDlg",
		title: __("Batch subscribe"),
		style: "width: 600px",
		execute: function () {
			if (this.validate()) {
				notify_progress(__("Subscribing to feeds..."), true);

				xhrPost("backend.php", this.attr('value'), () => {
					notify("");
					updateFeedList();
					dialog.hide();
				});
			}
		},
		href: query
	});

	dialog.show();
}

function clearPluginData(name) {
	if (confirm(__("Clear stored data for this plugin?"))) {
		notify_progress("Loading, please wait...");

		xhrPost("backend.php", { op: "pref-prefs", method: "clearplugindata", name: name }, () => {
			notify('');
			updatePrefsList();
		});
	}
}

function clearSqlLog() {

	if (confirm(__("Clear all messages in the error log?"))) {

		notify_progress("Loading, please wait...");

		xhrPost("backend.php",	{ op: "pref-system", method: "clearLog" }, () => {
			updateSystemList();
		});

	}
}

function updateSelectedPrompt() {
	// no-op shim for toggleSelectedRow()
}

function gotoMain() {
	document.location.href = "index.php";
}

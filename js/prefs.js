/* global dijit, __ */

const App = {
	init: function() {
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

					Utils.setLoadingProgress(50);

					const clientTzOffset = new Date().getTimezoneOffset() * 60;
					const params = {op: "rpc", method: "sanityCheck", clientTzOffset: clientTzOffset};

					xhrPost("backend.php", params, (transport) => {
						Utils.backendSanityCallback(transport);
					});

				} catch (e) {
					exception_error(e);
				}
			});
		});
	},
	initSecondStage: function() {
		document.onkeydown = () => { App.hotkeyHandler(event) };
		Utils.setLoadingProgress(50);
		notify("");

		let tab = Utils.urlParam('tab');

		if (tab) {
			tab = dijit.byId(tab + "Tab");
			if (tab) {
				dijit.byId("pref-tabs").selectChild(tab);

				switch (Utils.urlParam('method')) {
					case "editfeed":
						window.setTimeout(function () {
							CommonDialogs.editFeed(Utils.urlParam('methodparam'))
						}, 100);
						break;
					default:
						console.warn("initSecondStage, unknown method:", Utils.urlParam("method"));
				}
			}
		} else {
			let tab = localStorage.getItem("ttrss:prefs-tab");

			if (tab) {
				tab = dijit.byId(tab);
				if (tab) {
					dijit.byId("pref-tabs").selectChild(tab);
				}
			}
		}

		dojo.connect(dijit.byId("pref-tabs"), "selectChild", function (elem) {
			localStorage.setItem("ttrss:prefs-tab", elem.id);
		});

	},
	hotkeyHandler: function (event) {
		if (event.target.nodeName == "INPUT" || event.target.nodeName == "TEXTAREA") return;

		const action_name = Utils.keyeventToAction(event);

		if (action_name) {
			switch (action_name) {
				case "feed_subscribe":
					CommonDialogs.quickAddFeed();
					return false;
				case "create_label":
					CommonDialogs.addLabel();
					return false;
				case "create_filter":
					Filters.quickAddFilter();
					return false;
				case "help_dialog":
					Utils.helpDialog("main");
					return false;
				default:
					console.log("unhandled action: " + action_name + "; keycode: " + event.which);
			}
		}
	},
	isPrefs: function() {
		return true;
	}
};

// noinspection JSUnusedGlobalSymbols
const Prefs = {
	clearFeedAccessKeys: function() {
		if (confirm(__("This will invalidate all previously generated feed URLs. Continue?"))) {
			notify_progress("Clearing URLs...");

			xhrPost("backend.php", {op: "pref-feeds", method: "clearKeys"}, () => {
				notify_info("Generated URLs cleared.");
			});
		}

		return false;
	},
	updateEventLog: function() {
		xhrPost("backend.php", { op: "pref-system" }, (transport) => {
			dijit.byId('systemConfigTab').attr('content', transport.responseText);
			notify("");
		});
	},
	clearEventLog: function() {
		if (confirm(__("Clear event log?"))) {

			notify_progress("Loading, please wait...");

			xhrPost("backend.php", {op: "pref-system", method: "clearLog"}, () => {
				this.updateEventLog();
			});
		}
	},
	editProfiles: function() {

		if (dijit.byId("profileEditDlg"))
			dijit.byId("profileEditDlg").destroyRecursive();

		const query = "backend.php?op=pref-prefs&method=editPrefProfiles";

		// noinspection JSUnusedGlobalSymbols
		const dialog = new dijit.Dialog({
			id: "profileEditDlg",
			title: __("Settings Profiles"),
			style: "width: 600px",
			getSelectedProfiles: function () {
				return Tables.getSelected("prefFeedProfileList");
			},
			removeSelected: function () {
				const sel_rows = this.getSelectedProfiles();

				if (sel_rows.length > 0) {
					if (confirm(__("Remove selected profiles? Active and default profiles will not be removed."))) {
						notify_progress("Removing selected profiles...", true);

						const query = {
							op: "rpc", method: "remprofiles",
							ids: sel_rows.toString()
						};

						xhrPost("backend.php", query, () => {
							notify('');
							Prefs.editProfiles();
						});
					}

				} else {
					alert(__("No profiles selected."));
				}
			},
			activateProfile: function () {
				const sel_rows = this.getSelectedProfiles();

				if (sel_rows.length == 1) {
					if (confirm(__("Activate selected profile?"))) {
						notify_progress("Loading, please wait...");

						xhrPost("backend.php", {op: "rpc", method: "setprofile", id: sel_rows.toString()}, () => {
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

					const query = {op: "rpc", method: "addprofile", title: dialog.attr('value').newprofile};

					xhrPost("backend.php", query, () => {
						notify('');
						Prefs.editProfiles();
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
	},
	customizeCSS: function() {
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
	},
	confirmReset: function() {
		if (confirm(__("Reset to defaults?"))) {
			xhrPost("backend.php", {op: "pref-prefs", method: "resetconfig"}, (transport) => {
				Prefs.refresh();
				notify_info(transport.responseText);
			});
		}
	},
	clearPluginData: function(name) {
		if (confirm(__("Clear stored data for this plugin?"))) {
			notify_progress("Loading, please wait...");

			xhrPost("backend.php", {op: "pref-prefs", method: "clearplugindata", name: name}, () => {
				Prefs.refresh();
			});
		}
	},
	refresh: function() {
		xhrPost("backend.php", { op: "pref-prefs" }, (transport) => {
			dijit.byId('genConfigTab').attr('content', transport.responseText);
			notify("");
		});
	}
};

// noinspection JSUnusedGlobalSymbols
const Users = {
	reload: function(sort) {
		const user_search = $("user_search");
		const search = user_search ? user_search.value : "";

		xhrPost("backend.php", { op: "pref-users", sort: sort, search: search }, (transport) => {
			dijit.byId('userConfigTab').attr('content', transport.responseText);
			notify("");
		});
	},
	add: function() {
		const login = prompt(__("Please enter username:"), "");

		if (login) {
			notify_progress("Adding user...");

			xhrPost("backend.php", {op: "pref-users", method: "add", login: login}, (transport) => {
				alert(transport.responseText);
				Users.reload();
			});

		}
	},
	edit: function(id) {
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
						Users.reload();
					});
				}
			},
			href: query
		});

		dialog.show();
	},
	resetSelected: function() {
		const rows = this.getSelection();

		if (rows.length == 0) {
			alert(__("No users selected."));
			return;
		}

		if (rows.length > 1) {
			alert(__("Please select one user."));
			return;
		}

		if (confirm(__("Reset password of selected user?"))) {
			notify_progress("Resetting password for selected user...");

			const id = rows[0];

			xhrPost("backend.php", {op: "pref-users", method: "resetPass", id: id}, (transport) => {
				notify('');
				alert(transport.responseText);
			});

		}
	},
	removeSelected: function() {
		const sel_rows = this.getSelection();

		if (sel_rows.length > 0) {
			if (confirm(__("Remove selected users? Neither default admin nor your account will be removed."))) {
				notify_progress("Removing selected users...");

				const query = {
					op: "pref-users", method: "remove",
					ids: sel_rows.toString()
				};

				xhrPost("backend.php", query, () => {
					this.reload();
				});
			}

		} else {
			alert(__("No users selected."));
		}
	},
	editSelected: function() {
		const rows = this.getSelection();

		if (rows.length == 0) {
			alert(__("No users selected."));
			return;
		}

		if (rows.length > 1) {
			alert(__("Please select one user."));
			return;
		}

		this.edit(rows[0]);
	},
	getSelection :function() {
		return Tables.getSelected("prefUserList");
	}
};

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

function gotoExportOpml(filename, settings) {
	const tmp = settings ? 1 : 0;
	document.location.href = "backend.php?op=opml&method=export&filename=" + filename + "&settings=" + tmp;
}

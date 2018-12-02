'use strict'
/* global dijit, __ */

let App;
let Utils;
let CommonDialogs;
let Filters;
let Users;
let Prefs;

require(["dojo/_base/kernel",
	"dojo/_base/declare",
	"dojo/ready",
	"dojo/parser",
	"fox/AppBase",
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
	"fox/Utils",
	"fox/CommonDialogs",
	"fox/CommonFilters",
	"fox/PrefUsers",
	"fox/PrefHelpers",
	"fox/PrefFeedStore",
	"fox/PrefFilterStore",
	"fox/PrefFeedTree",
	"fox/PrefFilterTree",
	"fox/PrefLabelTree"], function (dojo, declare, ready, parser, AppBase) {

	ready(function () {
		try {
			const _App = declare("fox.App", AppBase, {
				constructor: function() {
					window.onerror = function (message, filename, lineno, colno, error) {
						report_error(message, filename, lineno, colno, error);
					};

					Utils = fox.Utils();
					CommonDialogs = fox.CommonDialogs();
					Filters = fox.CommonFilters();
					Users = fox.PrefUsers();
					Prefs = fox.PrefHelpers();

					parser.parse();

					Utils.setLoadingProgress(50);

					const clientTzOffset = new Date().getTimezoneOffset() * 60;
					const params = {op: "rpc", method: "sanityCheck", clientTzOffset: clientTzOffset};

					xhrPost("backend.php", params, (transport) => {
						try {
							Utils.backendSanityCallback(transport);
						} catch (e) {
							exception_error(e);
						}
					});
				},
				initSecondStage: function() {
					document.onkeydown = () => { App.hotkeyHandler(event) };
					Utils.setLoadingProgress(50);
					Notify.close();

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
			});

			App = new _App();

		} catch (e) {
			exception_error(e);
		}
	});
});

function opmlImportComplete(iframe) {
	if (!iframe.contentDocument.body.innerHTML) return false;

	Element.show(iframe);

	Notify.close();

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
		Notify.progress("Importing, please wait...", true);

		Element.show("upload_iframe");

		return true;
	}
}

function opmlRegenKey() {
	if (confirm(__("Replace current OPML publishing address with a new one?"))) {
		Notify.progress("Trying to change address...", true);

		xhrJson("backend.php", { op: "pref-feeds", method: "regenOPMLKey" }, (reply) => {
			if (reply) {
				const new_link = reply.link;
				const e = $('pub_opml_url');

				if (new_link) {
					e.href = new_link;
					e.innerHTML = new_link;

					new Effect.Highlight(e);

					Notify.close();

				} else {
					Notify.error("Could not change feed URL.");
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

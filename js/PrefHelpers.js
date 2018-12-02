define(["dojo/_base/declare"], function (declare) {
	return declare("fox.PrefHelpers", null, {
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
	});
});

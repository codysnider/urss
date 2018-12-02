'use strict'
/* global __, ngettext */
define(["dojo/_base/declare"], function (declare) {
	return declare("fox.AppBase", null, {
		_initParams: [],
		_rpc_seq: 0,
		hotkey_prefix: 0,
		hotkey_prefix_pressed: false,
		hotkey_prefix_timeout: 0,
		getInitParam: function(k) {
			return this._initParams[k];
		},
		setInitParam: function(k, v) {
			this._initParams[k] = v;
		},
		constructor: function(args) {
			//
		},
		enableCsrfSupport: function() {
			Ajax.Base.prototype.initialize = Ajax.Base.prototype.initialize.wrap(
				function (callOriginal, options) {

					if (App.getInitParam("csrf_token") != undefined) {
						Object.extend(options, options || { });

						if (Object.isString(options.parameters))
							options.parameters = options.parameters.toQueryParams();
						else if (Object.isHash(options.parameters))
							options.parameters = options.parameters.toObject();

						options.parameters["csrf_token"] = App.getInitParam("csrf_token");
					}

					return callOriginal(options);
				}
			);
		},
		urlParam: function(param) {
			return String(window.location.href).parseQuery()[param];
		},
		next_seq: function() {
			this._rpc_seq += 1;
			return this._rpc_seq;
		},
		get_seq: function() {
			return this._rpc_seq;
		},
		setLoadingProgress: function(p) {
			loading_progress += p;

			if (dijit.byId("loading_bar"))
				dijit.byId("loading_bar").update({progress: loading_progress});

			if (loading_progress >= 90)
				Element.hide("overlay");

		},
		keyeventToAction: function(event) {

			const hotkeys_map = App.getInitParam("hotkeys");
			const keycode = event.which;
			const keychar = String.fromCharCode(keycode).toLowerCase();

			if (keycode == 27) { // escape and drop prefix
				this.hotkey_prefix = false;
			}

			if (keycode == 16 || keycode == 17) return; // ignore lone shift / ctrl

			if (!this.hotkey_prefix && hotkeys_map[0].indexOf(keychar) != -1) {

				this.hotkey_prefix = keychar;
				$("cmdline").innerHTML = keychar;
				Element.show("cmdline");

				window.clearTimeout(this.hotkey_prefix_timeout);
				this.hotkey_prefix_timeout = window.setTimeout(() => {
					this.hotkey_prefix = false;
					Element.hide("cmdline");
				}, 3 * 1000);

				event.stopPropagation();

				return false;
			}

			Element.hide("cmdline");

			let hotkey_name = keychar.search(/[a-zA-Z0-9]/) != -1 ? keychar : "(" + keycode + ")";

			// ensure ^*char notation
			if (event.shiftKey) hotkey_name = "*" + hotkey_name;
			if (event.ctrlKey) hotkey_name = "^" + hotkey_name;
			if (event.altKey) hotkey_name = "+" + hotkey_name;
			if (event.metaKey) hotkey_name = "%" + hotkey_name;

			const hotkey_full = this.hotkey_prefix ? this.hotkey_prefix + " " + hotkey_name : hotkey_name;
			this.hotkey_prefix = false;

			let action_name = false;

			for (const sequence in hotkeys_map[1]) {
				if (hotkeys_map[1].hasOwnProperty(sequence)) {
					if (sequence == hotkey_full) {
						action_name = hotkeys_map[1][sequence];
						break;
					}
				}
			}

			console.log('keyeventToAction', hotkey_full, '=>', action_name);

			return action_name;
		},
		cleanupMemory: function(root) {
			const dijits = dojo.query("[widgetid]", dijit.byId(root).domNode).map(dijit.byNode);

			dijits.each(function (d) {
				dojo.destroy(d.domNode);
			});

			$$("#" + root + " *").each(function (i) {
				i.parentNode ? i.parentNode.removeChild(i) : true;
			});
		},
		helpDialog: function(topic) {
			const query = "backend.php?op=backend&method=help&topic=" + encodeURIComponent(topic);

			if (dijit.byId("helpDlg"))
				dijit.byId("helpDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "helpDlg",
				title: __("Help"),
				style: "width: 600px",
				href: query,
			});

			dialog.show();
		},
		displayDlg: function(title, id, param, callback) {
			Notify.progress("Loading, please wait...", true);

			const query = {op: "dlg", method: id, param: param};

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

					Notify.close();

					if (callback) callback(transport);
				} catch (e) {
					exception_error(e);
				}
			});

			return false;
		},
		handleRpcJson: function(transport) {

			const netalert_dijit = dijit.byId("net-alert");
			let netalert = false;

			if (netalert_dijit) netalert = netalert_dijit.domNode;

			try {
				const reply = JSON.parse(transport.responseText);

				if (reply) {

					const error = reply['error'];

					if (error) {
						const code = error['code'];
						const msg = error['msg'];

						console.warn("[handleRpcJson] received fatal error " + code + "/" + msg);

						if (code != 0) {
							fatalError(code, msg);
							return false;
						}
					}

					const seq = reply['seq'];

					if (seq && this.get_seq() != seq) {
						console.log("[handleRpcJson] sequence mismatch: " + seq +
							" (want: " + this.get_seq() + ")");
						return true;
					}

					const message = reply['message'];

					if (message == "UPDATE_COUNTERS") {
						console.log("need to refresh counters...");
						App.setInitParam("last_article_id", -1);
						Feeds.requestCounters(true);
					}

					const counters = reply['counters'];

					if (counters)
						Feeds.parseCounters(counters);

					const runtime_info = reply['runtime-info'];

					if (runtime_info)
						App.parseRuntimeInfo(runtime_info);

					if (netalert) netalert.hide();

					return reply;

				} else {
					if (netalert)
						netalert.show();
					else
						Notify.error("Communication problem with server.");
				}

			} catch (e) {
				if (netalert)
					netalert.show();
				else
					Notify.error("Communication problem with server.");

				console.error(e);
			}

			return false;
		},
		parseRuntimeInfo: function(data) {
			for (const k in data) {
				if (data.hasOwnProperty(k)) {
					const v = data[k];

					console.log("RI:", k, "=>", v);

					if (k == "daemon_is_running" && v != 1) {
						Notify.error("<span onclick=\"App.explainError(1)\">Update daemon is not running.</span>", true);
						return;
					}

					if (k == "update_result") {
						const updatesIcon = dijit.byId("updatesIcon").domNode;

						if (v) {
							Element.show(updatesIcon);
						} else {
							Element.hide(updatesIcon);
						}
					}

					if (k == "daemon_stamp_ok" && v != 1) {
						Notify.error("<span onclick=\"App.explainError(3)\">Update daemon is not updating feeds.</span>", true);
						return;
					}

					if (k == "max_feed_id" || k == "num_feeds") {
						if (App.getInitParam(k) != v) {
							console.log("feed count changed, need to reload feedlist.");
							Feeds.reload();
						}
					}

					this.setInitParam(k, v);
				}
			}

			PluginHost.run(PluginHost.HOOK_RUNTIME_INFO_LOADED, data);
		},
		backendSanityCallback: function (transport) {

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
								_label_base_index = parseInt(params[k]);
								break;
							case "hotkeys":
								// filter mnemonic definitions (used for help panel) from hotkeys map
								// i.e. *(191)|Ctrl-/ -> *(191)

								const tmp = [];
								for (const sequence in params[k][1]) {
									if (params[k][1].hasOwnProperty(sequence)) {
										const filtered = sequence.replace(/\|.*$/, "");
										tmp[filtered] = params[k][1][sequence];
									}
								}

								params[k][1] = tmp;
								break;
						}

						console.log("IP:", k, "=>", params[k]);
						this.setInitParam(k, params[k]);
					}
				}

				// PluginHost might not be available on non-index pages
				window.PluginHost && PluginHost.run(PluginHost.HOOK_PARAMS_LOADED, App._initParams);
			}

			this.initSecondStage();
		},
		explainError: function(code) {
			return this.displayDlg(__("Error explained"), "explainError", code);
		},
	});
});

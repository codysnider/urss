'use strict'
/* global dijit, __ */

let init_params = {};
let _label_base_index = -1024;
let loading_progress = 0;
let notify_hide_timerid = false;

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

/* common helpers not worthy of separate Dojo modules */

const Lists = {
	onRowChecked: function(elem) {
		const checked = elem.domNode ? elem.attr("checked") : elem.checked;
		// account for dojo checkboxes
		elem = elem.domNode || elem;

		const row = elem.up("li");

		if (row)
			checked ? row.addClassName("Selected") : row.removeClassName("Selected");
	}
};

// noinspection JSUnusedGlobalSymbols
const Tables = {
	onRowChecked: function(elem) {
		// account for dojo checkboxes
		const checked = elem.domNode ? elem.attr("checked") : elem.checked;
		elem = elem.domNode || elem;

		const row = elem.up("tr");

		if (row)
			checked ? row.addClassName("Selected") : row.removeClassName("Selected");

	},
	select: function(elemId, selected) {
		$(elemId).select("tr").each((row) => {
			const checkNode = row.select(".dijitCheckBox,input[type=checkbox]")[0];
			if (checkNode) {
				const widget = dijit.getEnclosingWidget(checkNode);

				if (widget) {
					widget.attr("checked", selected);
				} else {
					checkNode.checked = selected;
				}

				this.onRowChecked(widget);
			}
		});
	},
	getSelected: function(elemId) {
		const rv = [];

		$(elemId).select("tr").each((row) => {
			if (row.hasClassName("Selected")) {
				// either older prefix-XXX notation or separate attribute
				const rowId = row.getAttribute("data-row-id") || row.id.replace(/^[A-Z]*?-/, "");

				if (!isNaN(rowId))
					rv.push(parseInt(rowId));
			}
		});

		return rv;
	}
};

const Cookie = {
	set: function (name, value, lifetime) {
		const d = new Date();
		d.setTime(d.getTime() + lifetime * 1000);
		const expires = "expires=" + d.toUTCString();
		document.cookie = name + "=" + encodeURIComponent(value) + "; " + expires;
	},
	get: function (name) {
		name = name + "=";
		const ca = document.cookie.split(';');
		for (let i=0; i < ca.length; i++) {
			let c = ca[i];
			while (c.charAt(0) == ' ') c = c.substring(1);
			if (c.indexOf(name) == 0) return decodeURIComponent(c.substring(name.length, c.length));
		}
		return "";
	},
	delete: function(name) {
		const expires = "expires=Thu, 01-Jan-1970 00:00:01 GMT";
		document.cookie = name + "=" + "" + "; " + expires;
	}
};

/* error reporting */

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

// noinspection JSUnusedGlobalSymbols
function displayIfChecked(checkbox, elemId) {
	if (checkbox.checked) {
		Effect.Appear(elemId, {duration : 0.5});
	} else {
		Effect.Fade(elemId, {duration : 0.5});
	}
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

/* function strip_tags(s) {
	return s.replace(/<\/?[^>]+(>|$)/g, "");
} */

// noinspection JSUnusedGlobalSymbols
function uploadIconHandler(rc) {
	switch (rc) {
		case 0:
			notify_info("Upload complete.");
			if (App.isPrefs()) {
				Feeds.reload();
			} else {
				setTimeout('Feeds.reload(false, false)', 50);
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

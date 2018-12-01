function embedOriginalArticle(id) {
	try {
		const hasSandbox = "sandbox" in document.createElement("iframe");

		if (!hasSandbox) {
			alert(__("Sorry, your browser does not support sandboxed iframes."));
			return;
		}

		let c = false;

		if (isCombinedMode()) {
			c = $$("div#RROW-" + id + " div[class=content-inner]")[0];
		} else if (id == getActiveArticleId()) {
			c = $$(".post .content")[0];
		}

		if (c) {
			const iframe = c.parentNode.getElementsByClassName("embeddedContent")[0];

			if (iframe) {
				Element.show(c);
				c.parentNode.removeChild(iframe);

				if (isCombinedMode()) {
					cdmScrollToArticleId(id, true);
				}

				return;
			}
		}

		const query = { op: "pluginhandler", plugin: "embed_original", method: "getUrl", id: id };

		xhrJson("backend.php", query, (reply) => {
			if (reply) {
				const iframe = new Element("iframe", {
					class: "embeddedContent",
					src: reply.url,
					width: (c.parentNode.offsetWidth - 5) + 'px',
					height: (c.parentNode.parentNode.offsetHeight - c.parentNode.firstChild.offsetHeight - 5) + 'px',
					style: "overflow: auto; border: none; min-height: " + (document.body.clientHeight / 2) + "px;",
					sandbox: 'allow-scripts',
				});

				if (c) {
					Element.hide(c);
					c.parentNode.insertBefore(iframe, c);

					if (isCombinedMode()) {
						cdmScrollToArticleId(id, true);
					}
				}
			}
		});

	} catch (e) {
		exception_error("embedOriginalArticle", e);
	}
}

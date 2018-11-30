var _shorten_expanded_threshold = 1.5; //window heights

function expandSizeWrapper(id) {
	try {
		const row = $(id);

		console.log(row);

		if (row) {
			const content = row.select(".contentSizeWrapper")[0];
			const link = row.select(".expandPrompt")[0];

			if (content) content.removeClassName("contentSizeWrapper");
			if (link) Element.hide(link);

		}
	} catch (e) {
		exception_error("expandSizeWrapper", e);
	}

	return false;

}

require(['dojo/_base/kernel', 'dojo/ready'], function  (dojo, ready) {

	ready(function() {
		PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED_CDM, function(row) {
			window.setTimeout(function() {
				if (row) {
					const c_inner = row.select(".content-inner")[0];
					const c_inter = row.select(".intermediate")[0];

					if (c_inner && c_inter &&
						row.offsetHeight >= _shorten_expanded_threshold * window.innerHeight) {

						c_inter.parentNode.removeChild(c_inter);

						c_inner.innerHTML = "<div class='contentSizeWrapper'>" +
							c_inner.innerHTML +
							c_inter.innerHTML + "</div>" +
							"<button class='expandPrompt' onclick='return expandSizeWrapper(\""+row.id+"\")' href='#'>" +
								__("Click to expand article") + "</button>";
					}
				}
			}, 150);

			return true;
		});
	});

});

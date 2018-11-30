function mailtoArticle(id) {
	try {
		if (!id) {
			const ids = getSelectedArticleIds2();

			if (ids.length == 0) {
				alert(__("No articles are selected."));
				return;
			}

			id = ids.toString();
		}

		if (dijit.byId("emailArticleDlg"))
			dijit.byId("emailArticleDlg").destroyRecursive();

		const query = "backend.php?op=pluginhandler&plugin=mailto&method=emailArticle&param=" + param_escape(id);

		dialog = new dijit.Dialog({
			id: "emailArticleDlg",
			title: __("Forward article by email"),
			style: "width: 600px",
			href: query});

		dialog.show();

	} catch (e) {
		exception_error("emailArticle", e);
	}
}



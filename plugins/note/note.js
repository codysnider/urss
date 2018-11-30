function editArticleNote(id) {
	try {

		var query = "backend.php?op=pluginhandler&plugin=note&method=edit&param=" + param_escape(id);

		if (dijit.byId("editNoteDlg"))
			dijit.byId("editNoteDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "editNoteDlg",
			title: __("Edit article note"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					notify_progress("Saving article note...", true);

					xhrJson("backend.php", this.attr('value'), (reply) => {
                        notify('');
                        dialog.hide();

                        if (reply) {
                            cache_delete("article:" + id);

                            var elem = $("POSTNOTE-" + id);

                            if (elem) {
                                Element.hide(elem);
                                elem.innerHTML = reply.note;

                                if (reply.raw_length != 0)
                                    new Effect.Appear(elem);
                            }
                        }
                    });
				}
			},
			href: query,
		});

		dialog.show();

	} catch (e) {
		exception_error("editArticleNote", e);
	}
}


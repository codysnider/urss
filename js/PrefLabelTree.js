/* global lib,dijit */
define(["dojo/_base/declare", "dojo/dom-construct", "lib/CheckBoxTree", "dijit/form/DropDownButton"], function (declare, domConstruct) {

	return declare("fox.PrefLabelTree", lib.CheckBoxTree, {
		setNameById: function (id, name) {
			const item = this.model.store._itemsByIdentity['LABEL:' + id];

			if (item)
				this.model.store.setValue(item, 'name', name);

		},
		_createTreeNode: function(args) {
			const tnode = this.inherited(arguments);

			const fg_color = this.model.store.getValue(args.item, 'fg_color');
			const bg_color = this.model.store.getValue(args.item, 'bg_color');
			const type = this.model.store.getValue(args.item, 'type');
			const bare_id = this.model.store.getValue(args.item, 'bare_id');

			if (type == 'label') {
				const span = dojo.doc.createElement('span');
				span.innerHTML = '&alpha;';
				span.className = 'labelColorIndicator';
				span.id = 'LICID-' + bare_id;

				span.setStyle({
					color: fg_color,
					backgroundColor: bg_color});

				tnode._labelIconNode = span;

				domConstruct.place(tnode._labelIconNode, tnode.labelNode, 'before');
			}

			return tnode;
		},
		getIconClass: function (item, opened) {
			return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "invisible";
		},
	});

});



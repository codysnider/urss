/* global dijit,lib */
define(["dojo/_base/declare", "dojo/dom-construct", "lib/CheckBoxTree"], function (declare, domConstruct) {

	return declare("fox.PrefFilterTree", lib.CheckBoxTree, {
		_createTreeNode: function(args) {
			const tnode = this.inherited(arguments);

			const enabled = this.model.store.getValue(args.item, 'enabled');
			let param = this.model.store.getValue(args.item, 'param');
			const rules = this.model.store.getValue(args.item, 'rules');

			if (param) {
				param = dojo.doc.createElement('span');
				param.className = (enabled != false) ? 'labelParam' : 'labelParam filterDisabled';
				param.innerHTML = args.item.param[0];
				domConstruct.place(param, tnode.rowNode, 'first');
			}

			if (rules) {
				param = dojo.doc.createElement('span');
				param.className = 'filterRules';
				param.innerHTML = rules;
				domConstruct.place(param, tnode.rowNode, 'next');
			}

			if (this.model.store.getValue(args.item, 'id') != 'root') {
				const img = dojo.doc.createElement('img');
				img.src ='images/filter.png';
				img.className = 'marked-pic';
				tnode._filterIconNode = img;
				domConstruct.place(tnode._filterIconNode, tnode.labelNode, 'before');
			}

			return tnode;
		},

		getLabel: function(item) {
			let label = item.name;

			const feed = this.model.store.getValue(item, 'feed');
			const inverse = this.model.store.getValue(item, 'inverse');

			if (feed)
				label += " (" + __("in") + " " + feed + ")";

			if (inverse)
				label += " (" + __("Inverse") + ")";

			/*		if (item.param)
			 label = "<span class=\"labelFixedLength\">" + label +
			 "</span>" + item.param[0]; */

			return label;
		},
		getIconClass: function (item, opened) {
			return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "invisible";
		},
		getLabelClass: function (item, opened) {
			const enabled = this.model.store.getValue(item, 'enabled');
			return (enabled != false) ? "dijitTreeLabel labelFixedLength" : "dijitTreeLabel labelFixedLength filterDisabled";
		},
		getRowClass: function (item, opened) {
			return (!item.error || item.error == '') ? "dijitTreeRow" :
				"dijitTreeRow Error";
		},
		checkItemAcceptance: function(target, source, position) {
			const item = dijit.getEnclosingWidget(target).item;

			// disable copying items
			source.copyState = function() { return false; };

			return position != 'over';
		},
		onDndDrop: function() {
			this.inherited(arguments);
			this.tree.model.store.save();
		},
	});

});



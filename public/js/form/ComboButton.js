/* global dijit */
define(["dojo/_base/declare", "dijit/form/ComboButton"], function (declare) {
	return declare("fox.form.ComboButton", dijit.form.ComboButton, {
		startup: function() {
			this.inherited(arguments);
			this.dropDown.autoFocus = true; // Allow dropdown menu to be focused on click
		},
		focus: function() {
			return; // Stop dijit.form.ComboButton from keeping focus after closing the menu
		},
	});
});

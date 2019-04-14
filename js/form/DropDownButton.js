/* global dijit */
define(["dojo/_base/declare", "dijit/form/DropDownButton"], function (declare) {
	return declare("fox.form.DropDownButton", dijit.form.DropDownButton, {
		startup: function() {
			this.inherited(arguments);
			this.dropDown.autoFocus = true; // Allow dropdown menu to be focused on click
		},
		focus: function() {
			return; // Stop dijit.form.DropDownButton from keeping focus after closing the menu
		},
	});
});

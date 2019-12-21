/* global dijit */
define(["dojo/_base/declare", "dijit/form/Select"], function (declare) {
	return declare("fox.form.Select", dijit.form.Select, {
		focus: function() {
			return; // Stop dijit.form.Select from keeping focus after closing the menu
		},
	});
});

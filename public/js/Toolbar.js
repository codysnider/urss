/* global dijit */
define(["dojo/_base/declare", "dijit/Toolbar"], function (declare) {
	return declare("fox.Toolbar", dijit.Toolbar, {
		_onContainerKeydown: function(/* Event */ e) {
			return; // Stop dijit.Toolbar from interpreting keystrokes
		},
		_onContainerKeypress: function(/* Event */ e) {
			return; // Stop dijit.Toolbar from interpreting keystrokes
		},
		focus: function() {
			return; // Stop dijit.Toolbar from focusing the first child on click
		},
	});
});

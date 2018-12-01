require(['dojo/_base/kernel', 'dojo/ready'], function (dojo, ready) {
	ready(function () {
		App.updateTitle = function () {
			document.title = "Tiny Tiny RSS";
		};
	});
});

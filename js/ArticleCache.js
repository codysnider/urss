'use strict'
/* global __, ngettext */
define(["dojo/_base/declare"], function (declare) {
	return declare("fox.ArticleCache", null, {
		has_storage: 'sessionStorage' in window && window['sessionStorage'] !== null,
		set: function(id, obj) {
			if (this.has_storage)
				try {
					sessionStorage["article:" + id] = obj;
				} catch (e) {
					sessionStorage.clear();
				}
		},
		get: function(id) {
			if (this.has_storage)
				return sessionStorage["article:" + id];
		},
		clear: function() {
			if (this.has_storage)
				sessionStorage.clear();
		},
		del: function(id) {
			if (this.has_storage)
				sessionStorage.removeItem("article:" + id);
		},
	});
});

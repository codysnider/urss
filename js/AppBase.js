'use strict'
/* global __, ngettext */
define(["dojo/_base/declare"], function (declare) {
	return declare("fox.AppBase", null, {
		_initParams: [],
		getInitParam: function(k) {
			return this._initParams[k];
		},
		setInitParam: function(k, v) {
			this._initParams[k] = v;
		},
		constructor: function(args) {
			//
		},
		enableCsrfSupport: function() {
			Ajax.Base.prototype.initialize = Ajax.Base.prototype.initialize.wrap(
				function (callOriginal, options) {

					if (App.getInitParam("csrf_token") != undefined) {
						Object.extend(options, options || { });

						if (Object.isString(options.parameters))
							options.parameters = options.parameters.toQueryParams();
						else if (Object.isHash(options.parameters))
							options.parameters = options.parameters.toObject();

						options.parameters["csrf_token"] = App.getInitParam("csrf_token");
					}

					return callOriginal(options);
				}
			);
		}
	});
});

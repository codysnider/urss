dojo.addOnLoad(function() {
	PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED_CDM, function(row) {
		if (row) {
			console.log("af_zz_noautoplay!");
			console.log(row);

			var videos = row.getElementsByTagName("video");
			console.log(row.innerHTML);

			for (i = 0; i < videos.length; i++) {

				videos[i].removeAttribute("autoplay");
				videos[i].pause();
				videos[i].onclick = function() {
					this.paused ? this.play() : this.pause();
				}
			}
		}

		return true;
	});

	PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED, function(row) {
		if (row) {
			var videos = row.getElementsByTagName("video");

			for (i = 0; i < videos.length; i++) {
				videos[i].removeAttribute("autoplay");
				videos[i].pause();
				videos[i].onclick = function() {
					this.paused ? this.play() : this.pause();
				}
			}

		}

		return true;
	});

});
function clearArticleAccessKeys() {
	if (confirm(__("This will invalidate all previously shared article URLs. Continue?"))) {
		notify_progress("Clearing URLs...");

		const query = { op: "pluginhandler", plugin: "share", method: "clearArticleKeys" };

		xhrPost("backend.php", query, () => {
			notify_info("Shared URLs cleared.");
		});
	}

	return false;
}




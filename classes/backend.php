<?php
class Backend extends Handler {
	function loading() {
		header("Content-type: text/html");
		print __("Loading, please wait...") . " " .
			"<img src='images/indicator_tiny.gif'>";
	}

	function digestTest() {
		if (isset($_SESSION['uid'])) {
			header("Content-type: text/html");

			$rv = Digest::prepare_headlines_digest($_SESSION['uid'], 1, 1000);

			print "<h1>HTML</h1>";
			print $rv[0];
			print "<h1>Plain text</h1>";
			print "<pre>".$rv[3]."</pre>";
		} else {
			print error_json(6);
		}
	}

	private function display_main_help() {
		$info = get_hotkeys_info();
		$imap = get_hotkeys_map();
		$omap = array();

		foreach ($imap[1] as $sequence => $action) {
			if (!isset($omap[$action])) $omap[$action] = array();

			array_push($omap[$action], $sequence);
		}

		print "<ul class='panel panel-scrollable hotkeys-help' style='height : 300px'>";

		print "<h2>" . __("Keyboard Shortcuts") . "</h2>";

		foreach ($info as $section => $hotkeys) {

			print "<li><hr></li>";
			print "<li><h3>" . $section . "</h3></li>";

			foreach ($hotkeys as $action => $description) {

				if (is_array($omap[$action])) {
					foreach ($omap[$action] as $sequence) {
						if (strpos($sequence, "|") !== FALSE) {
							$sequence = substr($sequence,
								strpos($sequence, "|")+1,
								strlen($sequence));
						} else {
							$keys = explode(" ", $sequence);

							for ($i = 0; $i < count($keys); $i++) {
								if (strlen($keys[$i]) > 1) {
									$tmp = '';
									foreach (str_split($keys[$i]) as $c) {
										switch ($c) {
										case '*':
											$tmp .= __('Shift') . '+';
											break;
										case '^':
											$tmp .= __('Ctrl') . '+';
											break;
										default:
											$tmp .= $c;
										}
									}
									$keys[$i] = $tmp;
								}
							}
							$sequence = join(" ", $keys);
						}

						print "<li>";
					 	print "<div class='hk'><code>$sequence</code></div>";
					  	print "<div class='desc'>$description</div>";
						print "</li>";
					}
				}
			}
		}

		print "</ul>";


	}

	function help() {
		$topic = basename(clean($_REQUEST["topic"])); // only one for now

		if ($topic == "main") {
			$info = get_hotkeys_info();
			$imap = get_hotkeys_map();
			$omap = array();

			foreach ($imap[1] as $sequence => $action) {
				if (!isset($omap[$action])) $omap[$action] = array();

				array_push($omap[$action], $sequence);
			}

			print "<ul class='panel panel-scrollable hotkeys-help' style='height : 300px'>";

			$cur_section = "";
			foreach ($info as $section => $hotkeys) {

				if ($cur_section) print "<li>&nbsp;</li>";
				print "<li><h3>" . $section . "</h3></li>";
				$cur_section = $section;

				foreach ($hotkeys as $action => $description) {

					if (is_array($omap[$action])) {
						foreach ($omap[$action] as $sequence) {
							if (strpos($sequence, "|") !== FALSE) {
								$sequence = substr($sequence,
									strpos($sequence, "|")+1,
									strlen($sequence));
							} else {
								$keys = explode(" ", $sequence);

								for ($i = 0; $i < count($keys); $i++) {
									if (strlen($keys[$i]) > 1) {
										$tmp = '';
										foreach (str_split($keys[$i]) as $c) {
											switch ($c) {
												case '*':
													$tmp .= __('Shift') . '+';
													break;
												case '^':
													$tmp .= __('Ctrl') . '+';
													break;
												default:
													$tmp .= $c;
											}
										}
										$keys[$i] = $tmp;
									}
								}
								$sequence = join(" ", $keys);
							}

							print "<li>";
							print "<div class='hk'><code>$sequence</code></div>";
							print "<div class='desc'>$description</div>";
							print "</li>";
						}
					}
				}
			}

			print "</ul>";
		}

		print "<footer class='text-center'>";
		print "<button dojoType='dijit.form.Button'
			onclick=\"return dijit.byId('helpDlg').hide()\">".__('Close this window')."</button>";
		print "</footer>";

	}
}

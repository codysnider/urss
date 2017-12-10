<?php startup_gettext(); ?>
<html>
<head>
	<title>Tiny Tiny RSS : Login</title>
	<?php echo stylesheet_tag("lib/dijit/themes/claro/claro.css") ?>
	<?php echo stylesheet_tag("css/default.css") ?>
	<link rel="shortcut icon" type="image/png" href="images/favicon.png">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<?php
	foreach (array("lib/prototype.js",
				"lib/dojo/dojo.js",
				"lib/dojo/tt-rss-layer.js",
				"js/functions.js",
				"errors.php?mode=js") as $jsfile) {

		echo javascript_tag($jsfile);

	} ?>

	<script type="text/javascript">
		require({cache:{}});
		Event.observe(window, 'load', function() {
			init();
		});
	</script>
</head>

<body class="claro ttrss_main ttrss_login">

<script type="text/javascript">
function init() {

	require(['dojo/parser','dijit/form/Button','dijit/form/CheckBox','dijit/form/Form',
    	'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'],function(parser){
    		parser.parse();
    		//show tooltip node only after this widget is instaniated.
    		dojo.query('div[dojoType="dijit.Tooltip"]').style({
    			display:''
    		});
		fetchProfiles();
		dijit.byId("bw_limit").attr("checked", getCookie("ttrss_bwlimit") == 'true');
		document.forms.loginForm.login.focus();
    	});

}

function fetchProfiles() {
	try {
		var query = "op=getProfiles&login=" + param_escape(document.forms["loginForm"].login.value);

		if (query) {
			new Ajax.Request("public.php",	{
				parameters: query,
				onComplete: function(transport) {
					if (transport.responseText.match("select")) {
						$('profile_box').innerHTML = transport.responseText;
						//dojo.parser.parse('profile_box');
					}
			} });
		}

	} catch (e) {
		exception_error("fetchProfiles", e);
	}
}


function gotoRegForm() {
	window.location.href = "register.php";
	return false;
}

function bwLimitChange(elem) {
	try {
		var limit_set = elem.checked;

		setCookie("ttrss_bwlimit", limit_set,
			<?php print SESSION_COOKIE_LIFETIME ?>);

	} catch (e) {
		exception_error("bwLimitChange", e);
	}
}
</script>

<?php $return = urlencode($_SERVER["REQUEST_URI"]) ?>

<form action="public.php?return=<?php echo $return ?>"
	dojoType="dijit.form.Form" method="POST" id="loginForm" name="loginForm">

<input dojoType="dijit.form.TextBox" style="display : none" name="op" value="login">

<div class='header'>
	<img src="images/logo_wide.png">
</div>

<div class='form'>

	<fieldset>
		<?php if ($_SESSION["login_error_msg"]) { ?>
		<div class="row-error">
			<?php echo $_SESSION["login_error_msg"] ?>
		</div>
			<?php $_SESSION["login_error_msg"] = ""; ?>
		<?php } ?>
		<div class="row">
			<label><?php echo __("Login:") ?></label>
			<input name="login" class="input input-text" type="text"
				onchange="fetchProfiles()" onfocus="fetchProfiles()" onblur="fetchProfiles()"
				style="width : 220px"
				required="1"
				value="<?php echo $_SESSION["fake_login"] ?>" />
		</div>


		<div class="row">
			<label><?php echo __("Password:") ?></label>
			<input type="password" name="password" required="1"
					style="width : 220px" class="input input-text"
					value="<?php echo $_SESSION["fake_password"] ?>"/>
			<label></label>
		<?php if (strpos(PLUGINS, "auth_internal") !== FALSE) { ?>
			<a class='forgotpass' href="public.php?op=forgotpass"><?php echo __("I forgot my password") ?></a>
		<?php } ?>
		</div>


		<div class="row">
			<label><?php echo __("Profile:") ?></label>

			<span id='profile_box'><select disabled='disabled' dojoType='dijit.form.Select'
				style='width : 220px; margin : 0px'>
				<option><?php echo __("Default profile") ?></option></select></span>

		</div>

		<div class="row">
			<label>&nbsp;</label>
			<input dojoType="dijit.form.CheckBox" name="bw_limit" id="bw_limit" type="checkbox"
				onchange="bwLimitChange(this)">
			<label id="bw_limit_label" style='display : inline' for="bw_limit"><?php echo __("Use less traffic") ?></label>
		</div>

		<div dojoType="dijit.Tooltip" connectId="bw_limit_label" position="below" style="display:none">
<?php echo __("Does not display images in articles, reduces automatic refreshes."); ?>
		</div>

		<?php if (SESSION_COOKIE_LIFETIME > 0) { ?>

		<div class="row">
			<label>&nbsp;</label>
			<input dojoType="dijit.form.CheckBox" name="remember_me" id="remember_me" type="checkbox">
			<label style='display : inline' for="remember_me"><?php echo __("Remember me") ?></label>
		</div>

		<?php } ?>

		<div class="row" style='text-align : right'>
			<button dojoType="dijit.form.Button" type="submit"><?php echo __('Log in') ?></button>
			<?php if (defined('ENABLE_REGISTRATION') && ENABLE_REGISTRATION) { ?>
				<button onclick="return gotoRegForm()" dojoType="dijit.form.Button">
					<?php echo __("Create new account") ?></button>
			<?php } ?>
		</div>

	</fieldset>


</div>

<div class='footer'>
	<a href="http://tt-rss.org/">Tiny Tiny RSS</a>
	&copy; 2005&ndash;<?php echo date('Y') ?> <a href="http://fakecake.org/">Andrew Dolgov</a>
</div>

</form>

</body></html>

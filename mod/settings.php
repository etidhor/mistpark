<?php


function settings_init(&$a) {
	if(local_user()) {
		require_once("mod/profile.php");
		profile_load($a,$a->user['nickname']);
	}
}


function settings_post(&$a) {

	if(! local_user()) {
		notice( t('Permission denied.') . EOL);
		return;
	}
	if(count($a->user) && x($a->user,'uid') && $a->user['uid'] != local_user()) {
		notice( t('Permission denied.') . EOL);
		return;
	}
	if((x($_POST,'npassword')) || (x($_POST,'confirm'))) {

		$newpass = $_POST['npassword'];
		$confirm = $_POST['confirm'];

		$err = false;
		if($newpass != $confirm ) {
			notice( t('Passwords do not match. Password unchanged.') . EOL);
			$err = true;
		}

		if((! x($newpass)) || (! x($confirm))) {
			notice( t('Empty passwords are not allowed. Password unchanged.') . EOL);
			$err = true;
		}

		if(! $err) {
			$password = hash('whirlpool',$newpass);
			$r = q("UPDATE `user` SET `password` = '%s' WHERE `uid` = %d LIMIT 1",
				dbesc($password),
				intval(local_user())
			);
			if($r)
				notice( t('Password changed.') . EOL);
			else
				notice( t('Password update failed. Please try again.') . EOL);
		}
	}

	$theme            = notags(trim($_POST['theme']));
	$username         = notags(trim($_POST['username']));
	$email            = notags(trim($_POST['email']));
	$timezone         = notags(trim($_POST['timezone']));
	$defloc           = notags(trim($_POST['defloc']));

	$allow_location   = (($_POST['allow_location'] == 1) ? 1: 0);
	$publish          = (($_POST['profile_in_directory'] == 1) ? 1: 0);
	$net_publish      = (($_POST['profile_in_netdirectory'] == 1) ? 1: 0);
	$old_visibility   = ((intval($_POST['visibility']) == 1) ? 1 : 0);
	$page_flags       = ((intval($_POST['page-flags'])) ? intval($_POST['page-flags']) : 0);

	$notify = 0;

	if($_POST['notify1'])
		$notify += intval($_POST['notify1']);
	if($_POST['notify2'])
		$notify += intval($_POST['notify2']);
	if($_POST['notify3'])
		$notify += intval($_POST['notify3']);
	if($_POST['notify4'])
		$notify += intval($_POST['notify4']);
	if($_POST['notify5'])
		$notify += intval($_POST['notify5']);

	$email_changed = false;

	$err = '';

	if($username != $a->user['username']) {
        	if(strlen($username) > 40)
                	$err .= t(' Please use a shorter name.');
        	if(strlen($username) < 3)
                	$err .= t(' Name too short.');
	}

	if($email != $a->user['email']) {
		$email_changed = true;
        	if(!eregi('[A-Za-z0-9._%-]+@[A-Za-z0-9._%-]+\.[A-Za-z]{2,6}',$email))
                	$err .= t(' Not valid email.');
	}

	if(strlen($err)) {
		notice($err . EOL);
		return;
	}

	if($timezone != $a->user['timezone']) {
		if(strlen($timezone))
			date_default_timezone_set($timezone);
	}

	$str_group_allow   = perms2str($_POST['group_allow']);
	$str_contact_allow = perms2str($_POST['contact_allow']);
	$str_group_deny    = perms2str($_POST['group_deny']);
	$str_contact_deny  = perms2str($_POST['contact_deny']);

	$r = q("UPDATE `user` SET `username` = '%s', `email` = '%s', `timezone` = '%s',  `allow_cid` = '%s', `allow_gid` = '%s', `deny_cid` = '%s', `deny_gid` = '%s', `notify-flags` = %d, `page-flags` = %d, `default-location` = '%s', `allow_location` = %d, `theme` = '%s'  WHERE `uid` = %d LIMIT 1",
			dbesc($username),
			dbesc($email),
			dbesc($timezone),
			dbesc($str_contact_allow),
			dbesc($str_group_allow),
			dbesc($str_contact_deny),
			dbesc($str_group_deny),
			intval($notify),
			intval($page_flags),
			dbesc($defloc),
			intval($allow_location),
			dbesc($theme),
			intval(local_user())
	);
	if($r)
		notice( t('Settings updated.') . EOL);

	$r = q("UPDATE `profile` 
		SET `publish` = %d, `net-publish` = %d
		WHERE `is-default` = 1 AND `uid` = %d LIMIT 1",
		intval($publish),
		intval($net_publish),
		intval(local_user())
	);

	if($old_visibility != $net_publish) {
		// Update global directory in background
		$php_path = ((strlen($a->config['php_path'])) ? $a->config['php_path'] : 'php');
		$url = $_SESSION['my_url'];
		if($url && strlen(get_config('system','directory_submit_url')))
			proc_close(proc_open("\"$php_path\" \"include/directory.php\" \"$url\" &",
				array(),$foo));
	}

	$_SESSION['theme'] = $theme;
	if($email_changed && $a->config['register_policy'] == REGISTER_VERIFY) {

		// FIXME - set to un-verified, blocked and redirect to logout

	}

	goaway($a->get_baseurl() . '/settings' );
	return; // NOTREACHED
}
		

if(! function_exists('settings_content')) {
function settings_content(&$a) {

	$o = '';
	$o .= '<script>	$(document).ready(function() { $(\'#nav-settings-link\').addClass(\'nav-selected\'); });</script>';

	if(! local_user()) {
		notice( t('Permission denied.') . EOL );
		return;
	}

	require_once('view/acl_selectors.php');

	$p = q("SELECT * FROM `profile` WHERE `is-default` = 1 AND `uid` = %d LIMIT 1",
		intval($_SESSION['uid'])
	);
	if(count($p))
		$profile = $p[0];

	$username = $a->user['username'];
	$email    = $a->user['email'];
	$nickname = $a->user['nickname'];
	$timezone = $a->user['timezone'];
	$notify   = $a->user['notify-flags'];
	$defloc   = $a->user['default-location'];

	if(! strlen($a->user['timezone']))
		$timezone = date_default_timezone_get();

	$pageset_tpl = load_view_file('view/pagetypes.tpl');
	$pagetype = replace_macros($pageset_tpl,array(
		'$normal'         => (($a->user['page-flags'] == PAGE_NORMAL)      ? " checked=\"checked\" " : ""),
		'$soapbox'        => (($a->user['page-flags'] == PAGE_SOAPBOX)     ? " checked=\"checked\" " : ""),
		'$community'      => (($a->user['page-flags'] == PAGE_COMMUNITY)   ? " checked=\"checked\" " : ""),
		'$freelove'       => (($a->user['page-flags'] == PAGE_FREELOVE)    ? " checked=\"checked\" " : ""),
		'$page_normal'    => PAGE_NORMAL,
		'$page_soapbox'   => PAGE_SOAPBOX,
		'$page_community' => PAGE_COMMUNITY,
		'$page_freelove'  => PAGE_FREELOVE
	));


	$opt_tpl = load_view_file("view/profile-in-directory.tpl");
	$profile_in_dir = replace_macros($opt_tpl,array(
		'$yes_selected' => (($profile['publish'])      ? " checked=\"checked\" " : ""),
		'$no_selected'  => (($profile['publish'] == 0) ? " checked=\"checked\" " : "")
	));

	if(strlen(get_config('system','directory_submit_url'))) {
		$opt_tpl = load_view_file("view/profile-in-netdir.tpl");

		$profile_in_net_dir = replace_macros($opt_tpl,array(
			'$yes_selected' => (($profile['net-publish'])      ? " checked=\"checked\" " : ""),
			'$no_selected'  => (($profile['net-publish'] == 0) ? " checked=\"checked\" " : "")
		));
	}
	else
		$profile_in_net_dir = '';

	$loc_checked = (($a->user['allow_location'] == 1)      ? " checked=\"checked\" " : "");

	$invisible = (((! $profile['publish']) && (! $profile['net-publish']))
		? true : false);

	if($invisible)
		notice( t('Profile is <strong>not published</strong>.') . EOL );

	$nickname_block = load_view_file("view/settings_nick_set.tpl");
	
	$nickname_subdir = '';
	if(strlen($a->get_path())) {
		$subdir_tpl = load_view_file('view/settings_nick_subdir.tpl');
		$nickname_subdir = replace_macros($subdir_tpl, array(
			'$baseurl' => $a->get_baseurl(),
			'$nickname' => $nickname,
			'$hostname' => $a->get_hostname()
		));
	}

	$theme_selector = '<select name="theme" id="theme-select" >';
	$files = glob('view/theme/*');
	if($files) {
		foreach($files as $file) {
			$f = basename($file);
			$selected = (($f == $_SESSION['theme']) || ($f === 'default' && (! x($_SESSION,'theme')))
				? ' selected="selected" ' : '' );
			$theme_selector .= '<option val="' . basename($file) . '"' . $selected . '>' . basename($file) . '</option>';
		}
	}
	$theme_selector .= '</select>';


	$nickname_block = replace_macros($nickname_block,array(
		'$nickname' => $nickname,
		'$uid' => $_SESSION['uid'],
		'$subdir' => $nickname_subdir,
		'$basepath' => $a->get_hostname(),
		'$baseurl' => $a->get_baseurl()));	

	$stpl = load_view_file('view/settings.tpl');

	$celeb = ((($a->user['page-flags'] == PAGE_SOAPBOX) || ($a->user['page-flags'] == PAGE_COMMUNITY)) ? true : false);

	$o .= replace_macros($stpl,array(
		'$baseurl' => $a->get_baseurl(),
		'$uid' => $_SESSION['uid'],
		'$username' => $username,
		'$email' => $email,
		'$nickname_block' => $nickname_block,
		'$timezone' => $timezone,
		'$zoneselect' => select_timezone($timezone),
		'$defloc' => $defloc,
		'$loc_checked' => $loc_checked,
		'$profile_in_dir' => $profile_in_dir,
		'$profile_in_net_dir' => $profile_in_net_dir,
		'$permissions' => t('Default Post Permissions'),
		'$visibility' => $profile['net-publish'],
		'$aclselect' => populate_acl($a->user,$celeb),
		'$sel_notify1' => (($notify & NOTIFY_INTRO)   ? ' checked="checked" ' : ''),
		'$sel_notify2' => (($notify & NOTIFY_CONFIRM) ? ' checked="checked" ' : ''),
		'$sel_notify3' => (($notify & NOTIFY_WALL)    ? ' checked="checked" ' : ''),
		'$sel_notify4' => (($notify & NOTIFY_COMMENT) ? ' checked="checked" ' : ''),
		'$sel_notify5' => (($notify & NOTIFY_MAIL)    ? ' checked="checked" ' : ''),
		'$theme' => $theme_selector,
		'$pagetype' => $pagetype
	));

	return $o;

}}
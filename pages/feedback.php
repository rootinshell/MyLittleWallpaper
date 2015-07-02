<?php
// Check that correct entry point was used
if (!defined('INDEX')) exit();
global $user;

// @todo Rewrite
/*$visits = $db->getrecord('visits', Array('field' => 'id', 'value' => 1));

if (!empty($_SERVER['HTTP_USER_AGENT']) && is_bot($_SERVER['HTTP_USER_AGENT']) === 0) {
	$data = Array(
		'count' => $visits['count'] + 1,
	);
	$db->saveArray('visits', $data, 1);
	$data = Array(
		'ip' => USER_IP,
		'url' => $_SERVER['REQUEST_URI'],
		'time' => gmdate('Y-m-d H:i:s'),
		'user_agent' => $_SERVER['HTTP_USER_AGENT'],
	);
	$db->saveArray('visit_log', $data);
}

$ban = $db->getrecord('ban', Array('field' => 'ip', 'value' => USER_IP));

if (!empty($ban['ip']) && $ban['ip'] == USER_IP) $banned = TRUE; else $banned = FALSE;
$redirect = FALSE;
$error = FALSE;
//if ($banned) exit();
if (isset($_POST['name']) && !$banned) {
	if ($user->getIsAnonymous()) {
		$resp = recaptcha_check_answer (RECAPTCHA_PRIVATE, $_SERVER["REMOTE_ADDR"], $_POST["recaptcha_challenge_field"], $_POST["recaptcha_response_field"]);
		if (!$resp->is_valid) {
			$error = 'Invalid captcha.';
		}
	}
	if (!$error) {
		if (!empty($_POST['feedback'])) {
			$phpmailer = new PHPMailer();
			$phpmailer->From = 'feedback@mylittlewallpaper.com';
			$phpmailer->FromName = 'My Little Wallpaper feedback';
			$phpmailer->Body = utf8_decode('New feedback on My Little Wallpaper.'."\n\n".
			'Name: '.$_POST['name']."\n".
			'Contact: '.$_POST['contact']."\n".
			'IP: '.USER_IP."\n\n".
			'Feedback:'."\n\n".$_POST['feedback']);
			$phpmailer->Subject = 'My Little Wallpaper feedback';
			$phpmailer->Encoding = 'quoted-printable';
			$phpmailer->AddAddress('fifth_element@derpymail.org');
			$phpmailer->AddAddress('petri.haikonen@ecxol.net');
			if (!$phpmailer->Send()) {
				$error = $phpmailer->ErrorInfo;
			}
			if (!$error) {
				$savedata = Array(
					'user_id' => (!$user->getIsAnonymous() ? $user->getId() : 0),
					'name' => $_POST['name'],
					'contact' => $_POST['contact'],
					'feedback' => $_POST['feedback'],
					'ip' => USER_IP,
					'time' => gmdate('Y-m-d H:i:s'),
				);
				$db->saveArray('feedback', $savedata);
				$_SESSION['success'] = TRUE;
				$redirect = TRUE;
				header('Location: '.$pubpath.'feedback.php');
			}
		} else $error = 'Please give any feedback before sending.';
	}
}
$active = 'feedback';
$thetitle = 'Feedback | ';
$tags_add_meta = '';
$rss = '';


require_once('lib/header.php');

echo '<script type="text/javascript">
var RecaptchaOptions = {
	lang : \'en\',
	theme : \'clean\'
};
</script>';

echo '<div id="content"><div>';
echo '<h1>Submit feedback</h1>';
if (!$banned) {
	echo '<p>Name and contact -fields are optional.</p>';
	echo '<form style="padding:10px 0 0 0;" class="uploadform" method="post" action="'.$pubpath.'feedback.php" enctype="multipart/form-data" accept-charset="utf-8">';
	if (isset($_SESSION['success'])) echo '<div class="success">Feedback sent.</div>';
	if ($error) echo '<div class="error">'.$error.'</div>';
	echo '<div><label>Your name:</label><input type="text" autocomplete="off" name="name" style="width:300px;" value="'.(!empty($_POST['name']) ? Format::htmlEntities($_POST['name']) : '').'"/></div>';
	echo '<div><label>Contact:</label><input type="text" autocomplete="off" name="contact" style="width:300px;" value="'.(!empty($_POST['contact']) ? Format::htmlEntities($_POST['contact']) : '').'" /></div>';
	echo '<div><label style="float:left;padding-top:6px;">Feedback:<br /></label><textarea name="feedback" style="width:300px;height:80px;"></textarea><br /></div>';
	
	if ($user->getIsAnonymous()) {
		echo recaptcha_get_html(RECAPTCHA_PUBLIC);
	}

	echo '<br /><input type="submit" value="Send feedback" />';
	echo '</form>';
} else {
	echo '<p>Your IP is on the blacklist.</p>';
}
echo '</div></div>';

require_once('lib/footer.php');
if (!$redirect && isset($_SESSION['success'])) unset($_SESSION['success']);*/
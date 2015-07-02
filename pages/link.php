<?php
// Check that correct entry point was used
if (!defined('INDEX')) exit();
global $page;

require_once(ROOT_DIR . 'classes/Wallpaper.php');

if (!empty($page)) {
	$wallpaperData = $db->getRecord('wallpaper', Array('field' => 'file', 'value' => $page));
	if (!empty($wallpaperData['id']) && $wallpaperData['deleted'] == '0') {
		$wallpaper = new Wallpaper($wallpaperData);
		$data = Array(
			'clicks' => ($wallpaperData['clicks'] + 1),
		);
		if (!empty($_SERVER['HTTP_USER_AGENT']) && is_bot($_SERVER['HTTP_USER_AGENT']) === 0) {
			// @todo Handle saving in Wallpaper class
			$db->saveArray('wallpaper', $data, $wallpaperData['id']);
			$data = Array(
				'ip' => USER_IP,
				'url' => $_SERVER['REQUEST_URI'],
				'time' => gmdate('Y-m-d H:i:s'),
				'user_agent' => $_SERVER['HTTP_USER_AGENT'],
			);
			$db->saveArray('visit_log', $data);
			$data = Array(
				'ip' => USER_IP,
				'file_id' => $wallpaperData['id'],
				'time' => gmdate('Y-m-d H:i:s'),
			);
			$db->saveArray('click_log', $data);
		}
		header('Location: ' . $wallpaper->getDirectDownloadLink());
	} else {
		require_once(ROOT_DIR . 'pages/errors/404.php');
	}
} else {
	require_once(ROOT_DIR . 'pages/errors/404.php');
}
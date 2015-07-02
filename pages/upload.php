<?php
// Check that correct entry point was used
if (!defined('INDEX')) {
	exit();
}
global $user, $db;

require_once(ROOT_DIR . 'classes/output/BasicPage.php');
require_once(ROOT_DIR . 'classes/Colours.php');
DEFINE('ACTIVE_PAGE', 'upload');

$ban = $db->getrecord('ban', Array('field' => 'ip', 'value' => USER_IP));
if (!empty($ban['ip']) && $ban['ip'] == USER_IP)
	$banned = TRUE; else $banned = FALSE;
$redirect = FALSE;
$error = FALSE;
$non_other_error = FALSE;

$submitPage = new BasicPage();
$submitPage->setPageTitleAddition('Submit');

if (CATEGORY == 'all') {
	$pageContents = '<div id="content"><div>';
	$pageContents .= '<h1>Submit a wallpaper</h1>';
	$pageContents .= '<p>Please select a category before submitting a wallpaper.</p>';
	$pageContents .= '</div></div>';
} else {
	if (isset($_POST['name']) && !$banned) {
		if ($user->getIsAnonymous()) {
			$resp = recaptcha_check_answer(RECAPTCHA_PRIVATE, $_SERVER["REMOTE_ADDR"], $_POST["recaptcha_challenge_field"], $_POST["recaptcha_response_field"]);
			if (!$resp->is_valid) {
				$error = 'Invalid captcha.';
			}
		}
		if (!$error) {
			if ((!empty($_POST['url']) && $_POST['upltype'] == 'dA') || ($_POST['upltype'] == 'other')) {
				$fileid = uniqid('', TRUE);
				$theUrl = '';
				if ($_POST['upltype'] == 'dA') {
					$url = $_POST['url'];
					if (preg_match("/^http:\\/\\/[^.]*\\.deviantart\\.com\\/art\\/.*$/", $url)) {
						$theUrl = $url;
					} elseif (preg_match("/^http:\\/\\/fav\\.me\\/.*$/", $url)) {
						$ch = curl_init($url);
						curl_setopt($ch, CURLOPT_HEADER, true);
						curl_setopt($ch, CURLOPT_NOBODY, true);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						$response = curl_exec($ch);
						curl_close($ch);
						$header = "Location: ";
						$pos = strpos($response, $header);
						if ($pos !== false) {
							$pos += strlen($header);
							$theUrl = substr($response, $pos, strpos($response, "\r\n", $pos) - $pos);
						}
					}
					if ($theUrl == '') {
						$error = 'Not a valid deviantART URL.';
					} else {
						if (preg_match("/^http:\\/\\/[^.]*\\.deviantart\\.com\\/art\\/.*-[0-9]*$/", $theUrl)) {
							$id = preg_replace("/^http:\\/\\/[^.]*\\.deviantart\\.com\\/art\\/.*-([0-9]*?)$/", "$1", $theUrl);
							$ch = curl_init();
							// URL
							curl_setopt($ch, CURLOPT_URL, 'http://backend.deviantart.com/oembed?url=' . urlencode($theUrl));
							// And we want it to return the answer
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

							$json = @json_decode(curl_exec($ch), TRUE);
							curl_close($ch);
							if (is_array($json) && $json['type'] == 'photo') {
								$imageurl = 'http://www.deviantart.com/download/' . urlencode($id) . '/' . basename($json['url']);
								$title = $json['title'];
							} else {
								$error = 'Unable to get data from deviantART (1).';
							}
						} else {
							$error = 'Unable to get data from deviantART (2).';
						}
					}
					if (!$error) {
						if ($user->getIsAdmin()) {
							$target = ROOT_DIR . FILE_FOLDER . $fileid;
						} else {
							$target = ROOT_DIR . FILE_FOLDER . 'moderate/' . $fileid;
						}
						if (substr($imageurl, 0, 7) === 'http://') {
							exec('wget -O ' . escapeshellarg($target) . ' ' . escapeshellarg($imageurl));
						}
						if (file_exists($target)) {
							$virus_scan = exec('clamdscan --remove ' . escapeshellarg($target) . ' | grep Infected');
							$parts = explode(':', $virus_scan);
							$infected = trim($parts[1]);
							if ($infected != '0') {
								@unlink($target);
								$error = 'Virus found in the image.';
							} else {
								$realname = basename($imageurl);
							}
						} else {
							$non_other_error = TRUE;
							$error = 'Image or title not found (dA) - 2.';
						}
					}
				} else {
					if (!empty($_FILES)) {
						if ($_FILES['Filedata']['error'] == UPLOAD_ERR_OK) {
							if ($user->getIsAdmin()) {
								$target = ROOT_DIR . FILE_FOLDER . $fileid;
							} else {
								$target = ROOT_DIR . FILE_FOLDER . 'moderate/' . $fileid;
							}
							if (move_uploaded_file($_FILES['Filedata']['tmp_name'], $target)) {
								// I would recommend clamav just in case
								$virus_scan = exec('clamdscan --remove ' . escapeshellarg($target) . ' | grep Infected');
								$parts = explode(':', $virus_scan);
								$infected = trim($parts[1]);
								if ($infected != '0') {
									@unlink($target);
									$error = 'Virus found in the image.';
								} else {
									$realname = mb_convert_encoding($_FILES['Filedata']['name'], "UTF-8", "UTF-8,ISO-8859-1");
									$title = $_POST['name'];
								}
							} else {
								$error = 'File upload failed.';
							}
						} else {
							switch ($_FILES['Filedata']['error']) {
								case UPLOAD_ERR_FORM_SIZE:
									$error = 'The file is too big, limit ' . FILESIZE_FORMAT($_POST['MAX_FILE_SIZE']) . '.';
									break;
								case UPLOAD_ERR_INI_SIZE:
									$error = 'The file is too big, limit ' . FILESIZE_FORMAT(FILESIZE_BYTES(ini_get('upload_max_filesize'))) . '.';
									break;
								case UPLOAD_ERR_PARTIAL:
									$error = 'Only part of the file was sent.';
									break;
								case UPLOAD_ERR_NO_FILE:
									$error = 'No file.';
									break;
								case UPLOAD_ERR_NO_TMP_DIR:
									$error = 'Cannot find the file upload temporary folder.';
									break;
								case UPLOAD_ERR_CANT_WRITE:
									$error = 'Unable to write the file.';
									break;
								case UPLOAD_ERR_EXTENSION:
									$error = 'PHP prevented file upload.';
									break;
							}
							if (!empty($_FILES['Filedata']['tmp_name'])) {
								@unlink($_FILES['Filedata']['tmp_name']);
							}
						}
					} else {
						$error = 'No file.';
					}
				}
				if (!$error) {
					$imgdata = @getimagesize($target);
					if (!$imgdata) {
						$error = 'Not an image (1)';
					} else {
						list($source_X, $source_Y, $imgtype) = $imgdata;
						switch ($imgtype) {
							case 1:
								break;
							case 2:
								break;
							case 3:
								break;
							default:
								$error = 'Not an image (2)';
						}
					}
					if (!$error) {
						if ($user->getIsAdmin()) {
							$saveauthor = '';
							$authorlist = explode(',', $_POST['author']);
							$author_array = Array();
							foreach ($authorlist as $tag) {
								$tag = trim($tag);
								if (str_replace(' ', '', $tag) != '') {
									$res = $db->query("SELECT id, name FROM tag_artist WHERE name = ?", Array($tag));
									$found = false;
									while ($rivi = $res->fetch(PDO::FETCH_ASSOC)) {
										$found = true;
										$author_array[] = $rivi['id'];
										if ($saveauthor == '')
											$saveauthor = $tag;
									}
									if (!$found) {
										$author_array[] = $db->saveArray('tag_artist', Array('name' => $tag));
									}
								}
							}
							$data = Array(
								'submitter_id' => $user->getId(),
								'name' => $title,
								'url' => (!empty($theUrl) ? $theUrl : $_POST['url']),
								'file' => $fileid,
								'filename' => $realname,
								'width' => $source_X,
								'height' => $source_Y,
								'mime' => image_type_to_mime_type($imgtype),
								'timeadded' => time(),
								'no_resolution' => (!empty($_POST['no_resolution']) && $_POST['no_resolution'] == '1' ? 1 : 0),
								'direct_with_link' => 1,
								'status_check' => '200',
								'last_checked' => gmdate('Y-m-d H:i:s'),
								'series' => CATEGORY_ID,
							);
							$imageid = $db->saveArray('wallpaper', $data);
							foreach ($author_array as $auth) {
								$data = Array(
									'tag_artist_id' => $auth,
									'wallpaper_id' => $imageid,
								);
								$db->saveArray('wallpaper_tag_artist', $data);
							}
							$taglist = explode(',', $_POST['tags']);
							foreach ($taglist as $tag) {
								$tag = trim($tag);
								if (str_replace(' ', '', $tag) != '') {
									$res = $db->query("SELECT id, name FROM tag WHERE name = ?", Array($tag));
									while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
										$data = Array(
											'tag_id' => $row['id'],
											'wallpaper_id' => $imageid,
										);
										$db->saveArray('wallpaper_tag', $data);
									}
								}
							}

							$fields = Array(Array('table' => 'tag', 'field' => 'id'));
							$join = Array(
								Array(
									'table' => 'wallpaper_tag',
									'condition' => Array(
										Array(
											Array(
												'table' => 'wallpaper_tag',
												'field' => 'tag_id',
											),
											Array(
												'table' => 'tag',
												'field' => 'id',
											),
										),
									),
								),
							);
							$conditions = Array();
							$conditions[] = Array(
								'table' => 'wallpaper_tag',
								'field' => 'wallpaper_id',
								'value' => $imageid,
								'operator' => '=',
							);
							$conditions[] = Array(
								'table' => 'tag',
								'field' => 'type',
								'value' => 'character',
								'operator' => '=',
							);
							$order = Array(Array('table' => 'tag', 'field' => 'name'));
							$taglist = $db->getlist('tag', $fields, $conditions, $order, NULL, $join);
							$chartags = '';
							$ct_count = 0;
							foreach ($taglist as $tag) {
								if ($chartags != '')
									$chartags .= ',';
								$chartags .= $tag['id'];
								$ct_count++;
							}
							if ($ct_count < 16) {
								$savedata = Array('chartags' => $chartags);
								$db->saveArray('wallpaper', $savedata, $imageid);
							}

							$noaspect = FALSE;
							$platformlist = explode(',', $_POST['platform']);
							foreach ($platformlist as $tag) {
								$tag = trim($tag);
								if (str_replace(' ', '', $tag) != '') {
									$res = $db->query("SELECT id, name FROM tag_platform WHERE name = ?", Array($tag));
									while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
										if ($row['name'] == 'Mobile') {
											$db->saveArray('wallpaper', Array('no_aspect' => 1), $imageid);
											$noaspect = TRUE;
										}
										$data = Array(
											'tag_platform_id' => $row['id'],
											'wallpaper_id' => $imageid,
										);
										$db->saveArray('wallpaper_tag_platform', $data);
									}
								}
							}
							$clrs = new GetCommonColours();
							$clrsresult = $clrs->getColours(ROOT_DIR . FILE_FOLDER . $fileid);

							foreach ($clrsresult as $cl) {
								$colours = array_keys($cl['colours']);
								$col = $colours[0];
								$amnt = $cl['percent'];
								$tag_r = base_convert(substr($col, 0, 2), 16, 10);
								$tag_g = base_convert(substr($col, 2, 2), 16, 10);
								$tag_b = base_convert(substr($col, 4, 2), 16, 10);
								$savedata = Array('wallpaper_id' => $imageid, 'tag_r' => $tag_r, 'tag_g' => $tag_g, 'tag_b' => $tag_b, 'tag_colour' => $col, 'amount' => round($amnt, 2));
								$db->saveArray('wallpaper_tag_colour', $savedata);
							}

							if (!$noaspect) {
								$aspect = aspect($source_X, $source_Y);
								$res = $db->query("SELECT id, name FROM tag_aspect WHERE name = ?", Array($aspect));
								while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
									$data = Array(
										'tag_aspect_id' => $row['id'],
										'wallpaper_id' => $imageid,
									);
									$db->saveArray('wallpaper_tag_aspect', $data);
								}
							}
							$_SESSION['success'] = TRUE;
							$redirect = TRUE;
						} else {
							if (!empty($_POST['type']) && $_POST['type'] == 'mobile')
								$type = 'mobile'; else $type = 'desktop';
							if ($type != 'mobile')
								$aspect = aspect($source_X, $source_Y); else $aspect = '';
							if ($type == 'mobile') {
								if ($_POST['mobiletype'] == 'androidlive')
									$mobiletype = 'androidlive'; else $mobiletype = 'generic';
							} else $mobiletype = '';
							$data = Array(
								'user_id' => $user->getId(),
								'name' => $title,
								'author' => $_POST['author'],
								'tags' => $_POST['tags'],
								'aspect' => $aspect,
								'url' => (!empty($theUrl) ? $theUrl : $_POST['url']),
								'file' => $fileid,
								'filename' => $realname,
								'width' => $source_X,
								'height' => $source_Y,
								'mime' => image_type_to_mime_type($imgtype),
								'timeadded' => time(),
								'ip' => USER_IP,
								'type' => $type,
								'mobile_type' => $mobiletype,
								'series' => CATEGORY_ID,
							);
							$imageid = $db->saveArray('wallpaper_submit', $data);
							$_SESSION['success'] = TRUE;
							$redirect = TRUE;
						}
						header('Location: ' . PUB_PATH_CAT . 'upload');
					} else {
						@unlink($target);
					}
				}
			} else {
				$error = 'No URL.';
			}
		}
	}

	$submitPage->setJavascript('$(document).ready(function(){
		$("#tags").bind("keydown", function(event) {
			if (event.keyCode === $.ui.keyCode.TAB && $(this).data("autocomplete").menu.active) {
				event.preventDefault();
			}
		}).autocomplete({
			source: function(request, response) {
				$.getJSON("' . PUB_PATH_CAT . 'ajax/btag_search", {
					term: extractLast(request.term)
				}, response);
			},
			search: function() {
				var term = extractLast(this.value);
				if (term.length < 2) {
					return false;
				}
			},
			focus: function() {
				return false;
			},
			select: function(event, ui) {
				var terms = split(this.value);
				terms.pop();
				terms.push(ui.item.value);
				terms.push("");
				this.value = terms.join(", ");
				return false;
			}
		}).data("autocomplete")._renderItem = function(ul, item) {
			if (item.desc != "") {
				return $("<li>")
					.data("item.autocomplete", item)
					.append("<a style=\"line-height:1.1;\">" + item.label + "<br><span class=\"autocomplete_extra\">" + item.desc + "</span></a>")
					.appendTo(ul);
			} else {
				return $("<li>")
					.data("item.autocomplete", item)
					.append("<a>" + item.label + "</a>")
					.appendTo(ul);
			}
		};
		$("#author").bind("keydown", function(event) {
			if (event.keyCode === $.ui.keyCode.TAB && $(this).data("autocomplete").menu.active) {
				event.preventDefault();
			}
		}).autocomplete({
			source: function(request, response) {
				$.getJSON("' . PUB_PATH_CAT . 'ajax/author_search", {
					term: extractLast(request.term)
				}, response);
			},
			search: function() {
				var term = extractLast(this.value);
				if (term.length < 2) {
					return false;
				}
			},
			focus: function() {
				return false;
			},
			select: function(event, ui) {
				var terms = split(this.value);
				terms.pop();
				terms.push(ui.item.value);
				terms.push("");
				this.value = terms.join(", ");
				return false;
			}
		}).data("autocomplete")._renderItem = function(ul, item) {
			if (item.desc != "") {
				return $("<li>")
					.data("item.autocomplete", item)
					.append("<a style=\"line-height:1.1;\">" + item.label + "<br><span class=\"autocomplete_extra\">" + item.desc + "</span></a>")
					.appendTo(ul);
			} else {
				return $("<li>")
					.data("item.autocomplete", item)
					.append("<a>" + item.label + "</a>")
					.appendTo(ul);
			}
		};
		$("#platform").bind("keydown", function(event) {
			if (event.keyCode === $.ui.keyCode.TAB && $(this).data("autocomplete").menu.active) {
				event.preventDefault();
			}
		}).autocomplete({
			source: function(request, response) {
				$.getJSON("' . PUB_PATH_CAT . 'ajax/platform_search", {
					term: extractLast(request.term)
				}, response);
			},
			search: function() {
				var term = extractLast(this.value);
				if (term.length < 2) {
					return false;
				}
			},
			focus: function() {
				return false;
			},
			select: function(event, ui) {
				var terms = split(this.value);
				terms.pop();
				terms.push(ui.item.value);
				terms.push("");
				this.value = terms.join(", ");
				return false;
			}
		});
		$("#duplicate_found").dialog({
			autoOpen: false,
			modal: true,
			resizable: false,
			width: 600,
			buttons: [
				{
					text: "Submit anyway",
					click: function() {
						$(this).dialog("close");
						preventform = false;
						$("#submitwallpaper").submit();
					}
				},
				{
					text: "Cancel",
					click: function() {
						$(this).dialog("close");
					}
				}
			]
		});
		$("#submitwallpaper").submit(function(e) {
			if (preventform) {
				e.preventDefault();
				$.ajax({
					url: "' . PUB_PATH_CAT . 'ajax/checkduplicate?url=" + encodeURIComponent($("#wallpaper_url").val()),
					success: function(data) {
						if (data.result != \'OK\') {
							if (data.result == \'Found\') {
								$("#duplicate_found span.dialogtext").html("The wallpaper you\'re about to submit was found in the database. If you\'re submitting several wallpapers from one page (for example a deviation with several wallpapers), just ignore this warning.");
							} else {
								$("#duplicate_found span.dialogtext").html("The wallpaper you\'re about to submit was found in the moderation queue. If you\'re submitting several wallpapers from one page (for example a deviation with several wallpapers), just ignore this warning.");
							}
							$("#duplicate_found").dialog("open");
						} else {
							preventform = false;
							$("#submitwallpaper").submit();
						}
					}
				});
			}
		});
	});
	var preventform = true;
	var RecaptchaOptions = {
		lang : \'en\',
		theme : \'clean\'
	};

	function changeupltype(el) {
		$(".upl_type_legend").hide();
		if ($(el).val() == "dA") {
			$("#upl_title").hide();
			$("#upl_image").hide();
			$("#upl_da_legend").show();
			if ($.browser.msie) {
				$("#upl_file_field").replaceWith($("#upl_file_field").clone());
			} else {
				$("#upl_file_field").val("");
			}
		} else {
			$("#upl_title").show();
			$("#upl_image").show();
		}
	}');

	$pageContents = '<div id="duplicate_found" style="display:none;" title="Wallpaper found"><p style="font-size:12px;"><span class="ui-icon ui-icon-alert" style="float: left; margin: 0 7px 20px 0;"></span><span class="dialogtext"></span></p></div>';

	$pageContents .= '<div id="content"><div>';
	if (!$banned) {
		$type = 'other';

		if ($type == 'dA') {
			$hide = Array('title', 'image');
		} else {
			$hide = Array();
		}

		$pageContents .= '<h1>Submit a wallpaper</h1><br />Read the instructions below before submitting:<ul><li><strong>Imake must be in JPEG or PNG format.</strong></li><li><strong>Upload the full-size image (even if the size is huge, like 10000x5625).</strong></li><li><strong>The image size for desktop wallpapers must be at least 1366x768.</strong></li><li>Author(s) -field autocomplete to existing artists to make entering artist easier. If an author doesn\'t exist, just write the author name.</li><li>You can enter more authors than one by separating author names with commas.</li><li>Tags -field autocomplete to existing tags to make entering tags easier. If a tag doesn\'t exist, just write it.</li><li>You can enter more tags than one by separating tags with commas.</li></ul>';
		if (!$user->getIsAdmin())
			$pageContents .= 'Uploaded images are moderated before they actually appear on the wallpaper listing.';
		if ($error)
			$pageContents .= '<div class="error">' . $error . '</div>';
		if (isset($_SESSION['success']))
			$pageContents .= '<div class="success">Wallpaper uploaded successfully.</div>';

		$pageContents .= '<form class="labelForm" id="submitwallpaper" method="post" action="' . PUB_PATH_CAT . 'upload" enctype="multipart/form-data" accept-charset="utf-8">';
		$pageContents .= '<div><label>Type:</label><select name="upltype" onchange="changeupltype(this);">';
		//$pageContents .= '<option value="dA">deviantART</option>';
		$pageContents .= '<option value="other"' . ($type == 'other' ? ' selected="selected"' : '') . '>Other</option>';
		$pageContents .= '</select>';
		//$pageContents .= '<p class="upl_type_legend" id="upl_da_legend" style="margin:8px 0 4px 0;font-size:11px;">Uploader tries to get the title and image from given deviantART URL. The URL must be the full URL (that is as a link on the deviation title) or fav.me URL.<br /><br />Note that the deviation must be in JPEG or PNG format, otherwise you need to upload the image manually.</p>';
		$pageContents .= '</div>';
		$pageContents .= '<div id="upl_title"' . (in_array('title', $hide) ? ' style="display:none;"' : '') . '><label>Title:</label><input type="text" autocomplete="off" name="name" style="width:300px;" value="' . (!empty($_POST['name']) ? Format::htmlEntities($_POST['name']) : (!empty($title) ? Format::htmlEntities($title) : '')) . '"/></div>';
		$pageContents .= '<div><label>Author(s):</label><input type="text" autocomplete="off" name="author" id="author" style="width:300px;" value="' . (!empty($_POST['author']) ? Format::htmlEntities($_POST['author']) : '') . '" /></div>';
		$pageContents .= '<div><label>Tags:</label><input type="text" autocomplete="off" name="tags" id="tags" style="width:300px;" value="' . (!empty($_POST['tags']) ? Format::htmlEntities($_POST['tags']) : '') . '" /></div>';
		if ($user->getIsAdmin()) {
			$pageContents .= '<div><label>Platform:</label><input type="text" autocomplete="off" name="platform" id="platform" style="width:300px;" value="' . (!empty($_POST['platform']) ? Format::htmlEntities($_POST['platform']) : 'Desktop, ') . '" /></div>';
			$pageContents .= '<div><label>Don\'t show resolution</label><input type="checkbox" value="1" name="no_resolution" ' . (!empty($_POST['no_resolution']) ? 'checked="checked" ' : '') . '/></div>';
		}
		$pageContents .= '<div><label>Source URL <strong style="font-size:18px;color:#000;">*</strong>:</label><input type="text" autocomplete="off" name="url" id="wallpaper_url" style="width:300px;" value="' . (!empty($_POST['url']) ? Format::htmlEntities($_POST['url']) : '') . '" /><br /></div>';
		$pageContents .= '<div id="upl_image"' . (in_array('image', $hide) ? ' style="display:none;"' : '') . '><label>Image <strong style="font-size:18px;color:#000;">*</strong>:</label><input type="hidden" name="MAX_FILE_SIZE" value="' . FILESIZE_BYTES(ini_get('upload_max_filesize')) . '" /><input type="file" id="upl_file_field" name="Filedata" /><br /><small>Max size ' . FILESIZE_FORMAT(FILESIZE_BYTES(ini_get('upload_max_filesize'))) . '</small></div>';

		if ($user->getIsAnonymous()) {
			$pageContents .= recaptcha_get_html(RECAPTCHA_PUBLIC);
		}

		$pageContents .= '<p><strong style="font-size:18px;color:#000;">*</strong> Required field</p>';
		$pageContents .= '<input type="submit" value="Send image" />';
		$pageContents .= '</form>';
	} else {
		$pageContents .= '<p>Your IP is on the blacklist.</p>';
	}
	$pageContents .= '</div></div>';

	if (!$redirect && isset($_SESSION['success'])) {
		unset($_SESSION['success']);
	}
}
$submitPage->setHtml($pageContents);

$response = new Response($submitPage);
$response->output();
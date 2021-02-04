<?php

/**
 *  Part of the Link Tools plugin for MyBB 1.8.
 *  Copyright (C) 2021 Laird Shaw
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'lkt-regen-preview.php');
require_once "./global.php";

$lang->load(C_LKT);

if ((int)$mybb->user['uid'] < 1) {
	error_no_permission();
}

add_breadcrumb($lang->lkt_regen_breadcrumb, 'lkt-regen-preview.php');

$regen_msg = '';
$return_link = '';

if ($pid = $mybb->get_input('pid', MyBB::INPUT_INT)) {
	$query = $db->simple_select('posts', 'message', "pid = {$pid}");
	if (!($message = $db->fetch_field($query, 'message'))) {
		error($lang->lkt_err_regen_no_post_or_msg);
	} else {
		$urls = lkt_extract_urls($message, /*$exclude_videos = */true);
		$terms = lkt_retrieve_terms($urls);
	}
} else if ($url = $mybb->get_input('url')) {
	$urls[] = $url;
	$terms = lkt_retrieve_terms($urls, /*$set_false_on_not_found = */true);
	if (!$terms || !$terms[$url]) {
		error($lang->sprintf($lang->lkt_err_regen_url_not_found_in_db, htmlspecialchars_uni($url)));
	}
} else	error($lang->lkt_err_regen_no_pid_or_url);

$ch = curl_init();
if ($ch === false) {
	error('Failed to initialise cURL.');
}
if (!curl_setopt_array($ch, array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => true,
	CURLOPT_TIMEOUT        => lkt_curl_timeout,
	CURLOPT_USERAGENT      => lkt_curl_useragent,
))) {
	curl_close($ch);
	error('Failed to set cURL options.');
}
foreach ($terms as $url => $term_url) {
	if ($regen_msg) $regen_msg .= '<br />'.PHP_EOL;
	$res = lkt_url_has_needs_preview($term_url, $preview, $has_db_entry, true);
	if ($res === false) {
		$regen_msg .= $lang->sprintf($lang->lkt_err_regen_url_no_helper, htmlspecialchars_uni($url));
	} else if ($res === -1) {
		$regen_msg .= $lang->sprintf($lang->lkt_err_regen_url_too_soon , lkt_preview_regen_min_wait_secs, htmlspecialchars_uni($url));
	} else {
		curl_setopt($ch, CURLOPT_URL, $term_url);
		$content = curl_exec($ch);
		if ($content
		    &&
		    ($header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE)) !== false
		     &&
		     ($response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) !== false
		   ) {
			$headers = substr($content, 0, $header_size);
			$content_type = lkt_get_content_type_from_hdrs($headers);
			$html = substr($content, $header_size);
			$preview = lkt_get_gen_link_preview($term_url, $html, $content_type, $res, $has_db_entry);
		}
		if ($preview === false) {
			$regen_msg .= $lang->sprintf($lang->lkt_err_regen_no_preview_returned, htmlspecialchars_uni($url));
		} else	$regen_msg .= $lang->sprintf($lang->lkt_success_regen_url, htmlspecialchars_uni($url));
	}
}
curl_close($ch);

if ($pid_ret_to = $mybb->get_input('return_pid', MyBB::INPUT_INT)) {
	$link_url  = get_post_link($pid_ret_to);
	$link_text = $lang->lkt_regen_page_return_link;
	eval('$return_link = "'.$templates->get('linktools_regen_page_return_link').'";');
}

eval('$html = "'.$templates->get('linktools_preview_regen_page').'";');
output_page($html);
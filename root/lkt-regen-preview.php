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

define("IN_MYBB", 1);
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

foreach ($terms as $url => $term_url) {
	if ($regen_msg) $regen_msg .= '<br />'.PHP_EOL;
	$res = lkt_url_has_needs_preview($term_url, $preview, $has_db_entry, true);
	if ($res === false) {
		$regen_msg .= $lang->sprintf($lang->lkt_err_regen_url_no_helper, htmlspecialchars_uni($url));
	} else if ($res === -1) {
		$regen_msg .= $lang->sprintf($lang->lkt_err_regen_url_too_soon , lkt_preview_regen_min_wait_secs, htmlspecialchars_uni($url));
	} else {
		$preview = lkt_get_gen_link_preview($term_url, file_get_contents($term_url), $res, $has_db_entry);
		if (!$preview) {
			$regen_msg .= $lang->sprintf($lang->lkt_err_regen_no_preview_returned, htmlspecialchars_uni($url));
		} else	$regen_msg .= $lang->sprintf($lang->lkt_success_regen_url, htmlspecialchars_uni($url));
	}
}

if ($pid_ret_to = $mybb->get_input('return_pid', MyBB::INPUT_INT)) {
	$link_url  = get_post_link($pid_ret_to);
	$link_text = $lang->lkt_regen_page_return_link;
	eval('$return_link = "'.$templates->get('linktools_regen_page_return_link').'";');
}

eval('$html = "'.$templates->get('linktools_preview_regen_page').'";');
output_page($html);
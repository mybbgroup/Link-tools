<?php

define("IN_MYBB", 1);
require_once "./global.php";

/** @todo Limit the size of the output in case many large posts match. */

header("Content-type: application/json; charset={$charset}");

if (!function_exists('dlw_get_posts_for_urls')) {
	$lang->load('duplicate_link_warner');
	die(json_encode(array('error' => $lang->dlw_err_not_active)));
}

if (!empty($mybb->input['url'])) {
	$urls = (array)$mybb->input['url'];
	$post_edit_times = array();
	if (!empty($mybb->input['pid']) && !empty($mybb->input['edtm'])) {
		foreach ((array)$mybb->input['pid'] as $i => $pid) {
			$post_edit_times[$pid] = ((array)$mybb->input['edtm'])[$i];
		}
	}
	$paged_urls = !empty($mybb->input['paged_urls']) ? (array)$mybb->input['paged_urls'] : array();
	$paged_ids = !empty($mybb->input['paged_ids']) ? (array)$mybb->input['paged_ids'] : array();
	list($matching_posts, $forum_names, $unreturned_count) = dlw_get_posts_for_urls($mybb->input['url'], $post_edit_times, $paged_urls, $paged_ids);
	echo json_encode(array('matching_posts' => $matching_posts, 'unreturned_count' => $unreturned_count));
}

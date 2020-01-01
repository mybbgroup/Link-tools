<?php

define("IN_MYBB", 1);
require_once "./global.php";

/**
 * @todo Implement permissions: only those with the right to post
 *       should be able to make requests to this page.
 * @todo Limit the size of the output in case many large posts match.
 */

header("Content-type: application/json; charset={$charset}");

if (!function_exists('dlw_get_posts_for_urls')) {
	$lang->load('duplicate_link_warner');
	die(json_encode(array('error' => $lang->dlw_err_not_active)));
}

if (!empty($mybb->input['urls'])) {
	$urls = (array)$mybb->input['urls'];

	// Add any missing URLs to the DB after resolving redirects
	dlw_get_and_add_urls($urls);

	$post_edit_times = array();
	if (!empty($mybb->input['pids']) && !empty($mybb->input['edtms'])) {
		foreach ((array)$mybb->input['pids'] as $i => $pid) {
			$post_edit_times[$pid] = ((array)$mybb->input['edtms'])[$i];
		}
	}
	list($matching_posts, $forum_names, $further_results) = dlw_get_posts_for_urls($urls, $post_edit_times);
	echo json_encode(array('matching_posts' => $matching_posts, 'further_results' => $further_results));
}

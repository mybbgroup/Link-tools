<?php

/**
 *  Part of the Link Tools plugin for MyBB 1.8.
 *  Copyright (C) 2020 Laird Shaw
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
require_once "./global.php";

/**
 * @todo Implement permissions: only those with the right to post
 *       should be able to make requests to this page.
 * @todo Limit the size of the output in case many large posts match.
 */

header("Content-type: application/json; charset={$charset}");

if (!function_exists('lkt_get_posts_for_urls')) {
	$lang->load('linktools');
	die(json_encode(array('error' => $lang->lkt_err_not_active)));
}

if (!empty($mybb->input['urls'])) {
	$urls = (array)$mybb->input['urls'];

	// Add any missing URLs to the DB after resolving redirects
	lkt_get_and_add_urls($urls);

	$post_edit_times = array();
	if (!empty($mybb->input['pids']) && !empty($mybb->input['edtms'])) {
		foreach ((array)$mybb->input['pids'] as $i => $pid) {
			$post_edit_times[$pid] = ((array)$mybb->input['edtms'])[$i];
		}
	}
	list($matching_posts, $forum_names, $further_results) = lkt_get_posts_for_urls($urls, $post_edit_times);
	echo json_encode(array('matching_posts' => $matching_posts, 'further_results' => $further_results));
}

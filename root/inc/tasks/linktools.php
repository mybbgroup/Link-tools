<?php

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

function task_linktools($task) {
	global $db, $lang;

	if (!isset($lang->linktools)) {
		$lang->load('linktools');
	}

	// Process first any posts whose URLs have not yet been extracted.

	$res = $db->simple_select('posts', 'count(*) as cnt', 'lkt_got_urls = FALSE');
	$cnt = $db->fetch_array($res)['cnt'];

	// Defensive programming: this loop should always terminate but we set a maximum number of
	// iterations just in case, as roughly double that which we would expect.
	$max_iterations = ceil($cnt/lkt_default_rebuild_links_items_per_page) * 2;
	while (lkt_extract_and_store_urls_for_posts(lkt_default_rebuild_links_items_per_page) > 0 && $i < $max_iterations) {
		$i++;
	}

	// Next, resolve any as-yet unresolved terminating redirects.

	// We only iterate once in this task when getting terminating redirects - we don't want to
	// waste bandwidth in case the task times out towards the end of an iteration, and the task
	// will run again soon anyhow.
	lkt_get_and_store_terms(lkt_default_rebuild_term_items_per_page);

	add_task_log($task, $lang->lkt_task_ran);
}

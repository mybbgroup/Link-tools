<?php

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

const dlw_term_tries_secs = array(
	15*60,         // 15 minutes
	60*60,         // 1 hour
	24*60*60,      // 1 day
	7*24*60*60,    // 1 week
	28*7*24*60*60, // 4 weeks
);

function task_duplicate_link_warner($task) {
	global $db, $lang;

	if (!isset($lang->duplicate_link_warner)) {
		$lang->load('duplicate_link_warner');
	}

	$rows = $urls = [];

	$res = $db->simple_select('urls', 'url, urlid, last_term_try, term_tries', 'got_term = false AND term_tries <= '.count(dlw_term_tries_secs));

	while (($row = $db->fetch_array($res))) {
		if (in_array(dlw_get_scheme($row['url']), array('http', 'https', ''))
		    &&
		    ($row['term_tries'] == 0 || ($row['last_term_try'] + dlw_term_tries_secs[$row['term_tries']-1] < time()))
		) {
			$urls[] = $row['url'];
			$ids[$row['url']] = $row['urlid'];
		}
	}

	$terms = dlw_get_url_term_redirs($urls);

	foreach ($terms as $url => $term) {
		if ($term !== null) {
			if ($term === false) {
				$db->write_query('UPDATE '.TABLE_PREFIX.'urls SET term_tries = term_tries + 1, last_term_try = '.time().' WHERE urlid='.$ids[$url]);
			} else  {
				$fields = array(
					'url_term'      => $db->escape_string($term),
					'url_term_norm' => $db->escape_string(dlw_normalise_url($term)),
					'got_term'      => 1,
				);
				$db->update_query('urls', $fields, 'urlid = '.$ids[$url]);
			}
		}
	}

	add_task_log($task, $lang->dlw_task_ran);
}

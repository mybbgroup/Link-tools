<?php

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

# Should semantically match the equivalent variable in ../../jscripts/duplicate_link_warner.js
const dlw_valid_schemes = array('http', 'https', 'ftp', 'sftp', '');

/**
 * Supported array entry formats:
 *
 * 1. 'key'
 * 2. 'key=value'
 * 3. 'key' => 'domain'
 * 4. 'key' => array('domain1', 'domain2', ...)
 * 5. 'key=value' => 'domain'
 * 6. 'key=value' => array('domain1', 'domain2', ...)
 *
 * 'domain' can be '*' in which case it matches all domains. This is implicit for formats #1 and #2.
 */
const dlw_ignored_query_params = array('fbclid' => '*', 'feature=youtu.be' => 'youtube.com');

const dlw_default_rebuild_items_per_page = 250;
const dlw_rehit_delay_in_secs = 3;

/** @todo Warn upon installation/activation that the link table needs to be populated. Add a column to the posts table that indicates whether its links have been added to the url and post_urls tables. Use this table to handle the situation in which the plugin is deactivated for a period such that only some of the posts are not reflected in the url and url_post tables. */
/** @todo Consider what (should) happen(s) when a URL whose length exceeds the size of its associated database column is posted. */
/** @todo We are currently (or rather, soon will be) handling HTTP redirects (via the term columns of the url table); should we also handle HTML redirects? */
/** @todo Maybe add a global and/or per-user setting to disable checking for matching non-opening posts. */
/** @todo Limit the number of returned matching posts to a sane value and consider how to provide access to the remainder. */


$plugins->add_hook('datahandler_post_insert_thread'         , 'dlw_hookin__datahandler_post_insert_thread'         );
$plugins->add_hook('newthread_start'                        , 'dlw_hookin__newthread_start'                        );
$plugins->add_hook('datahandler_post_insert_post_end'       , 'dlw_hookin__datahandler_post_insert_post_end'       );
$plugins->add_hook('datahandler_post_insert_thread_end'     , 'dlw_hookin__datahandler_post_insert_thread_end'     );
$plugins->add_hook('datahandler_post_update_end'            , 'dlw_hookin__datahandler_post_update_end'            );
$plugins->add_hook('admin_tools_recount_rebuild_output_list', 'dlw_hookin__admin_tools_recount_rebuild_output_list');
$plugins->add_hook('admin_tools_recount_rebuild'            , 'dlw_hookin__admin_tools_recount_rebuild'            );

define('C_DLW', str_replace('.php', '', basename(__FILE__)));

function duplicate_link_warner_info() {
	global $lang;
	if (!isset($lang->duplicate_link_warner)) {
		$lang->load('duplicate_link_warner');
	}

	return array(
		'name'          => $lang->dlw_name,
		'description'   => $lang->dlw_desc,
		'website'       => '',
		'author'        => 'Laird Shaw',
		'authorsite'    => '',
		'version'       => '0.0.5.dev.code-may-change',
		'guid'          => '',
		'codename'      => C_DLW,
		'compatibility' => '18*'
	);
}

function duplicate_link_warner_install() {
	global $db;

	if (!$db->table_exists('urls')) {
		// 2083 was chosen because it is the maximum size URL
		// that Internet Explorer will accept
		// (other major browsers have higher limits).
		$db->query('
CREATE TABLE '.TABLE_PREFIX.'urls (
  urlid         int unsigned NOT NULL auto_increment,
  url           varchar(2083) NOT NULL,
  url_norm      varchar(2083) NOT NULL,
  url_term      varchar(2083) NOT NULL DEFAULT url,
  url_term_norm varchar(2083) NOT NULL DEFAULT url_norm,
  got_term      boolean       NOT NULL DEFAULT FALSE,
  term_tries    tinyint unsigned NOT NULL DEFAULT 0,
  last_term_try int unsigned  NOT NULL default 0,
  UNIQUE        url           (url(400)),
  KEY           url_norm      (url_norm(200)),
  KEY           url_term_norm (url_term_norm(200)),
  PRIMARY KEY   (urlid)
)'.$db->build_create_table_collation().';');
	}

	if (!$db->table_exists('post_urls')) {
		$db->query('
CREATE TABLE '.TABLE_PREFIX.'post_urls (
  pid         int unsigned NOT NULL,
  urlid       int unsigned NOT NULL,
  PRIMARY KEY (urlid, pid)
)'.$db->build_create_table_collation().';');
	}

}

function duplicate_link_warner_uninstall() {
	global $db;

	if ($db->table_exists('urls')) {
		$db->drop_table('urls');
	}

	if ($db->table_exists('post_urls')) {
		$db->drop_table('post_urls');
	}

	$db->delete_query('tasks', "file='duplicate_link_warner'");
}

function duplicate_link_warner_is_installed() {
	global $db;

	return $db->table_exists('urls') && $db->table_exists('post_urls');
}

function duplicate_link_warner_activate() {
	global $db, $plugins, $cache, $lang;

	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('newthread', '({\\$smilieinserter})', '{$smilieinserter}{$duplicate_link_warner_div}');
	find_replace_templatesets('newthread', '({\\$codebuttons})'   , '{$codebuttons}{$duplicate_link_warner_js}'    );

	$task_exists = $db->simple_select('tasks', 'tid', "file='duplicate_link_warner'", array('limit' => '1'));
	if ($db->num_rows($task_exists) == 0) {
		require_once MYBB_ROOT . '/inc/functions_task.php';
		$new_task = array(
			'title' => $db->escape_string($lang->dlw_task_title),
			'description' => $db->escape_string($lang->dlw_task_description),
			'file' => 'duplicate_link_warner',
			'minute'      => '0',
			'hour'        => '0',
			'day'         => '*',
			'weekday'     => '*',
			'month'       => '*',
			'enabled'     => 1,
			'logging'     => 1,
		);
		$new_task['nextrun'] = fetch_next_run($new_task);
		$db->insert_query('tasks', $new_task);
		$plugins->run_hooks('admin_tools_tasks_add_commit');
		$cache->update_tasks();
	}
}

function duplicate_link_warner_deactivate() {
	global $db;

	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('newthread', '({\\$duplicate_link_warner_div})', '', 0);
	find_replace_templatesets('newthread', '({\\$duplicate_link_warner_js})' , '', 0);
	$db->update_query('tasks', array('enabled' => 0), 'file=\'duplicate_link_warner\'');
}

function dlw_get_scheme($url) {
	$scheme = '';
	if (preg_match('(^[a-z]+(?=:))', $url, $match)) {
		$scheme = $match[0];
	}

	return $scheme;
}

function dlw_has_valid_scheme($url) {
	return (in_array(dlw_get_scheme($url), dlw_valid_schemes));
}

# Should be kept in sync with the extract_url_from_mycode_tag() method of the DLW object in ../jscripts/duplicate_link_warner.js
function dlw_extract_url_from_mycode_tag(&$text, &$urls, $re, $indexes_to_use = array(1)) {
	if (preg_match_all($re, $text, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$url = '';
			foreach ($indexes_to_use as $i) {
				$url .= $match[$i];
			}
			dlw_test_add_url($url, $urls);
		}
		$text = preg_replace($re, ' ', $text);
	}

}

# Based heavily on the corresponding code in postParser::mycode_auto_url_callback() in ../class_parser.php
#
# Should be kept in sync with the strip_unmatched_closing_parens() method of the DLW object in ../jscripts/duplicate_link_warner.js
function dlw_strip_unmatched_closing_parens($url) {
	// Allow links like http://en.wikipedia.org/wiki/PHP_(disambiguation) but detect mismatching braces
	while (my_substr($url, -1) == ')') {
		if(substr_count($url, ')') > substr_count($url, '(')) {
			$url = my_substr($url, 0, -1);
		} else {
			break;
		}

		// Example: ([...] http://en.wikipedia.org/Example_(disambiguation).)
		$last_char = my_substr($url, -1);
		while ($last_char == '.' || $last_char == ',' || $last_char == '?' || $last_char == '!') {
			$url = my_substr($url, 0, -1);
			$last_char = my_substr($url, -1);
		}
	}

	return $url;
}

# Based on the code in postParser::mycode_auto_url_callback() in ../class_parser.php,
# with regexes from    postParser::mycode_auto_url().
#
# Should be kept in sync with the extract_bare_urls() method of the DLW object in ../jscripts/duplicate_link_warner.js
function dlw_extract_bare_urls(&$text, &$urls) {
	$urls_matched = [];
	$text = ' '.$text;
	$text_new = $text;

	static $re_start = '[\s\(\)\[\>]';

	foreach (array(
		"#($re_start)(http|https|ftp|news|irc|ircs|irc6){1}(://)([^\/\"\s\<\[\.]+\.([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?([\w\/\)]))#ius",
		"#($re_start)(www|ftp)(\.)(([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?([\w\/\)]))#ius"
	) as $re) {
		if (preg_match_all($re, $text, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE)) {
			foreach ($matches as $match) {
				$url = $match[2][0].$match[3][0].dlw_strip_unmatched_closing_parens($match[4][0]);
				$urls_matched[] = $url;
				dlw_test_add_url($url, $urls);
				// Blank out the matched URLs.
				$text_new = substr($text_new, 0, $match[2][1]).str_repeat(' ', strlen($url)).substr($text_new, $match[2][1] + strlen($url));
			}
		}
	}

	$text_new = my_substr($text, 1);
	$text = $text_new;
}

# Should be kept in sync with the test_add_url() method of the DLW object in ../jscripts/duplicate_link_warner.js
function dlw_test_add_url($url, &$urls) {
	if (dlw_has_valid_scheme($url) && !in_array($url, $urls)) {
		$urls[] = $url;
	}
}

# Should be kept in sync with the extract_urls() method of the DLW object in ../jscripts/duplicate_link_warner.js
function dlw_extract_urls($text) {
	$urls = array();

	# First, strip out all [img] tags.
	# [img] tag regexes from postParser::parse_mycode() in ../inc/class_parser.php.
	$text = preg_replace("#\[img\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", ' ', $text);
	$text = preg_replace("#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", ' ', $text);
	$text = preg_replace("#\[img align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", ' ', $text);
	$text = preg_replace("#\[img=([1-9][0-9]*)x([1-9][0-9]*) align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", ' ', $text);

	# [url] tag regexes from postParser::cache_mycode() in ../class_parser.php.
	dlw_extract_url_from_mycode_tag($text, $urls, "#\[url\]((?!javascript)[a-z]+?://)([^\r\n\"<]+?)\[/url\]#si", array(1, 2));
	dlw_extract_url_from_mycode_tag($text, $urls, "#\[url\]((?!javascript:)[^\r\n\"<]+?)\[/url\]#i", array(1));
	dlw_extract_url_from_mycode_tag($text, $urls, "#\[url=((?!javascript)[a-z]+?://)([^\r\n\"<]+?)\](.+?)\[/url\]#si", array(1, 2));
	dlw_extract_url_from_mycode_tag($text, $urls, "#\[url=((?!javascript:)[^\r\n\"<]+?)\](.+?)\[/url\]#si", array(1));

	# [video] tag regex from postParser::parse_mycode() in ../class_parser.php.
	dlw_extract_url_from_mycode_tag($text, $urls, "#\[video=(.*?)\](.*?)\[/video\]#i", array(2));

	dlw_extract_bare_urls($text, $urls);

	# Comprehensive regex matching non-relative international URIs as shared here:
	# https://stackoverflow.com/a/190405
	# with backslashes escaped, and with a "u" modifier added as suggested here:
	# https://stackoverflow.com/a/4338569
	#
	# Should semantically match the equivalent variable in ../../jscripts/duplicate_link_warner.js with the exception noted there.
/*
	static $uriRegex = '/[a-z](?:[-a-z0-9\\+\\.])*:(?:\\/\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:])*@)?(?:\\[(?:(?:(?:[0-9a-f]{1,4}:){6}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|::(?:[0-9a-f]{1,4}:){5}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){4}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4}:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){3}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,2}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){2}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,3}[0-9a-f]{1,4})?::[0-9a-f]{1,4}:(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,4}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,5}[0-9a-f]{1,4})?::[0-9a-f]{1,4}|(?:(?:[0-9a-f]{1,4}:){0,6}[0-9a-f]{1,4})?::)|v[0-9a-f]+[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:]+)\\]|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}|(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=@])*)(?::[0-9]*)?(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|\\/(?:(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*)?|(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|(?!(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])))(?:\\?(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])|[\\x{E000}-\\x{F8FF}\\x{F0000}-\\x{FFFFD}|\\x{100000}-\\x{10FFFD}\\/\\?])*)?(?:\\#(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])|[\\/\\?])*)?/iu';

	if (preg_match_all($uriRegex, $text, $matches, PREG_PATTERN_ORDER)) {
		foreach ($matches[0] as $url) {
			dlw_test_add_url(dlw_strip_unmatched_closing_parens($url), $urls);
		}
	}
*/

	return $urls;
}

// The below notes are on the intended post-implementation behaviour - but it hasn't yet
// been implemented; none of these parameters/returns are yet functional. Their aim is to
// prevent a situation in which a vast number of posts match a URL, the full contents of
// which are downloaded to the browser. i.e., the intend is to allow for the implementation
// of paging in the event of a large number of matches. A simpler alternative to consider is
// to just provide a message something like "[X] other posts also contain this/these URL(s)".
//
// ---
//
// The third entry of the returned array, $unreturned_count, includes in the count
// only unreturned matching posts that do not contain any of the URLs in $paged_urls,
// since those posts are assumed to have already been counted by the caller via one or more
// prior calls to this function.
//
// In theory, it is possible that none of those URLs were present in one or more matching posts
// at last call, and have since been edited into that/those matching posts, and thus that
// that/those post/s *should* be counted, but handling such rare scenarios seems to me
// to be overly obsessive.
//
// And, to be clear: $unreturned_count of course does not include any posts with IDs in the $paged_ids
// argument either.
function dlw_get_posts_for_urls($urls, $post_edit_times = array(), $paged_urls = array(), $paged_ids = array()) {
	global $db, $parser;

	$unreturned_count = 0;

	sort($urls);

	$urls_norm = dlw_normalise_urls($urls);

	if (!$parser) {
		require_once MYBB_ROOT.'inc/class_parser.php';
		$parser = new postParser;
	}
	$parse_opts = array(
		'allow_html'   => false,
		'allow_mycode' => true,
		'allow_smilies' => true,
		'allow_imgcode' => true,
		'allow_videocode' => true,
		'nofollow_on' => 1,
		'filter_badwords' => 1
	);

	$conds = "u.url_term_norm IN ('".implode("', '", array_map(array($db, 'escape_string'), $urls_norm))."')";

	$fids = get_unviewable_forums(true);
	if ($inact_fids = get_inactive_forums()) {
		if ($fids) $fids .= ',';
		$fids .= $inact_fids;
	}
	if ($fids) {
		$conds = '('.$conds.') and f.fid NOT IN ('.$fids.')';
	}
	$conds = '('.$conds.') and p.visible > 0';

	$res = $db->query('
SELECT          u2.url as matching_url, u.url_norm AS queried_norm_url,
                p.pid, p.uid AS uid_post, p.username AS username_post, p.dateline as dateline_post, p.message, p.subject AS subject_post, p.edittime,
                t.tid, t.uid AS uid_thread, t.username AS username_thread, t.subject AS subject_thread, t.firstpost, t.dateline as dateline_thread,
                (p.pid = t.firstpost) AS isfirstpost,
                x.prefix,
                f.fid, f.name as forum_name, f.parentlist
FROM            '.$db->table_prefix.'urls u
LEFT OUTER JOIN '.$db->table_prefix.'urls u2
ON              u2.url_term_norm = u.url_term_norm
INNER JOIN      '.$db->table_prefix.'post_urls pu
ON              u2.urlid = pu.urlid
INNER JOIN      '.$db->table_prefix.'posts p
ON              pu.pid = p.pid
LEFT OUTER JOIN '.$db->table_prefix.'forums f
ON              p.fid = f.fid
LEFT OUTER JOIN '.$db->table_prefix.'threads t
ON              p.tid = t.tid
LEFT OUTER JOIN '.$db->table_prefix.'threadprefixes x
ON              t.prefix = x.pid
WHERE           '.$conds.'
ORDER BY        isfirstpost DESC, p.dateline DESC');

	$all_matching_urls_in_quotes_flag = false;
	$forum_names = array();
	$matching_posts = array();
	while (($row = $db->fetch_array($res))) {
		$forum_names[$row['fid']] = $row['forum_name'];
		if (!isset($matching_posts[$row['pid']])) {
			$matching_posts[$row['pid']] = $row;
			unset($matching_posts[$row['pid']]['matching_url']);
			unset($matching_posts[$row['pid']]['queried_norm_url']);
			$matching_posts[$row['pid']]['message'] = $parser->parse_message($row['message'], $parse_opts);
			$matching_posts[$row['pid']]['all_urls'] = dlw_extract_urls($row['message']);
			// The raw URLs (i.e., not normalised) present in this post that were a match for
			// the raw URLs (again, not normalised) for which we are querying, in that
			// both terminate (i.e., after following all redirects) in the same normalised URL.
			$matching_posts[$row['pid']]['matching_urls_in_post'] = [];
			// The raw URLs for which we are querying that are matched in this post, in the
			// same order as the above array (i.e., entries at the same index in both arrays
			// both terminate in the same normalised URL).
			$matching_posts[$row['pid']]['matching_urls'] = [];
			$stripped = dlw_strip_nestable_mybb_tag($row['message'], 'quote');
			$urls_quotes_stripped = dlw_extract_urls($stripped);
			$matching_posts[$row['pid']]['are_all_matching_urls_in_quotes'] = (array_intersect($urls, $urls_quotes_stripped) == array());
			if ($matching_posts[$row['pid']]['are_all_matching_urls_in_quotes']) {
				$all_matching_urls_in_quotes_flag = true;
			}
			foreach (explode(',', $row['parentlist']) as $fid) {
				if (empty($forum_names[$fid])) {
					$forum_names[$fid] = null;
				}
			}
		}
		$matching_posts[$row['pid']]['matching_urls_in_post'][] = $row['matching_url'];
		$matching_posts[$row['pid']]['matching_urls'        ][] = $urls[array_search($row['queried_norm_url'], $urls_norm)];
	}

	$db->free_result($res);

	if (!$matching_posts) {
		return array(null, $forum_names, 0);
	}

	uasort($matching_posts, function ($post1, $post2) use ($urls) {
		$grade_post = function($post) {
			return ($post['pid'] == $post['firstpost']
			        ? ($urls == $post['matching_urls']
			           ? (count($urls) == count($post['all_urls'])
			              ? 6
			              : 5
			             )
			           : 4
			          )
			        : ($urls == $post['matching_urls']
			           ? ($post['all_urls'] == $post['matching_urls']
			              ? 3
			              : 2
			             )
			           : 1  
			          )
			        );
		};
		$grade1 = $grade_post($post1);
		$grade2 = $grade_post($post2);

		return ($grade1 < $grade2
		        ? 1
		        : ($grade1 > $grade2
		           ? -1
		           : 0
		          )
		        );
	});

	// Strip all values other than pid and edittime from any matching posts for which,
	// based on the supplied function parameter $post_edit_times, the caller
	// already has the relevant information because the post has not been edited since
	// last returned.
	foreach ($matching_posts as &$post) {
		if (array_key_exists($post['pid'], $post_edit_times) && $post_edit_times[$post['pid']] == $post['edittime']) {
			$post = array_intersect_key($post, array('pid' => true, 'edittime' => true));
		}
	}
	unset($post);

	$missing_fids = array();
	foreach ($forum_names as $fid => $name) {
		if (is_null($name)) {
			$missing_fids[] = $fid;
		}
	}
	if ($missing_fids) {
		$res = $db->simple_select('forums', 'fid,name', 'fid IN ('.implode(',', $missing_fids).')');
		while (($post = $db->fetch_array($res))) {
			$forum_names[$post['fid']] = $post['name'];
		}
	}

	foreach ($matching_posts as &$post) {
		$post['flinks'     ] = dlw_get_flinks($post['parentlist'], $forum_names);
		$post['tlink'      ] = dlw_get_threadlink($post['tid'], $post['subject_thread']);
		$post['nav_bit_img'] = '<img src="images/nav_bit.png" alt="" />';
		$post['ulink_p'    ] = dlw_get_usernamelink($post['uid_post'], $post['username_post']);
		$post['ulink_t'    ] = dlw_get_usernamelink($post['uid_thread'], $post['username_thread']);
		$post['dtlink_t'   ] = my_date('relative', $post['dateline_thread']);
		$post['dtlink_p'   ] = my_date('relative', $post['dateline_post']);
		$post['plink'      ] = dlw_get_postlink($post['pid'], $post['subject_post']);
	}

	return array($matching_posts, $forum_names, $unreturned_count);
}

function dlw_hookin__datahandler_post_insert_post_end($posthandler) {
	dlw_handle_new_post($posthandler);
}

function dlw_hookin__datahandler_post_insert_thread_end($posthandler) {
	dlw_handle_new_post($posthandler);
}

function dlw_handle_new_post($posthandler) {
	if ($posthandler->data['savedraft']) {
		return;
	}

	dlw_get_and_add_urls_of_post($posthandler->data['message'], $posthandler->pid);
}

function dlw_get_and_add_urls_of_post($message, $pid) {
	$urls = dlw_extract_urls($message);
	$redirs = dlw_get_url_term_redirs($urls);
	if ($urls) {
		dlw_add_urls_for_pid($urls, $redirs, $pid);
	}

	return $urls;
}

function dlw_add_urls_for_pid($urls, $redirs, $pid) {
	global $db;

	$now = time();
	foreach ($urls as $url) {
		$target = $redirs[$url];
		for ($try = 1; $try <= 2; $try++) {
			$res = $db->simple_select('urls', 'urlid', 'url = \''.$db->escape_string($url).'\'');
			if ($row = $db->fetch_array($res)) {
				$urlid = $row['urlid'];
			} else {
				if (!$db->write_query('INSERT INTO '.TABLE_PREFIX.'urls (url, url_norm, url_term, url_term_norm, got_term, term_tries, last_term_try) VALUES (\''.$db->escape_string($url).'\', \''.$db->escape_string(dlw_normalise_url($url)).'\', \''.$db->escape_string($target === false ? $url : $target).'\', \''.$db->escape_string(dlw_normalise_url($target === false ? $url : $target)).'\', '.($target === false ? "0, '1', '$now'" : "'1', 0, 0").')', /* $hide_errors = */ $try < 2)) {
					// We retry in this scenario (without raising the error) because
					// it is theoretically possible that the URL was inserted
					// by another process in between the select and the insert,
					// and that the error is due to a violation of the uniqueness
					// constraint on the `url` column.
					continue;
				}
				$urlid = $db->insert_id();
			}
			$db->insert_query('post_urls', array(
				'urlid' => $urlid,
				'pid'   => $pid
			));

			break;
		}
	}
}

function dlw_normalise_urls($urls) {
	$ret = array();

	foreach ($urls as $url) {
		$ret[] = dlw_normalise_url($url);
	}

	return $ret;
}

function dlw_get_norm_server_from_url($url) {
	$server = false;
	$parsed_url = dlw_parse_url($url);
	if (isset($parsed_url['host'])) {
		// Normalise domain to non-www-prefixed.
		$server = dlw_normalise_domain($parsed_url['host']);
	}

	return $server;
}

/**
 * Resolves and returns the immediate redirects for each URL in $urls.
 *
 * Uses the non-blocking functionality of cURL so that multiple URLs can be checked simultaneously,
 * but avoids hitting the same web server more than once every dlw_rehit_delay_in_secs seconds.
 *
 * This function only makes sense for $urls with a protocol of http:// or https://. $urls missing a
 * scheme are assumed to use the http:// protocol. For all other protocols, the $url is deemed to
 * terminate at itself.
 *
 * @param $urls Array.
 * @param $server_last_hit_times Array. The UNIX epoch timestamps at which each server was last polled,
 *                                      indexed by normalised (any 'www.' prefix removed) server name.
 * @param $use_head_method Boolean. If true, the HEAD method is used for all requests, otherwise
 *                                  the GET method is used.
 * @return An array with two array entries, $redirs and $deferred_urls.
 *         $redirs contains the immediate redirects of each of the URLs in $urls (which form
 *                   the keys of $redir array), if any.
 *                 If a URL does not redirect, then that URL's entry is set to itself.
 *                 If a link-specific error occurs for a URL, e.g. web server timeout,
 *                   then that URL's entry is set to false.
 *                 If a non-link-specific error occurs, such as failure to initialise a generic cURL handle,
 *                   then that URL's entry is set to null.
 *         $deferred_urls lists any URLs that were deferred because requesting it would have polled its
 *                        server within dlw_rehit_delay_in_secs seconds of the last time it was polled.
 */
function dlw_get_url_redirs($urls, &$server_last_hit_times = array(), $use_head_method = true) {
	$redirs = $deferred_urls = $curl_handles = [];

	$ts_now = microtime(true);
	$seen_servers = [];
	$i = 0;
	while ($i < count($urls)) {
		$url = $urls[$i];
		$server = dlw_get_norm_server_from_url($url);
		if ($server) {
			$seen_already = isset($seen_servers[$server]);
			$seen_servers[$server] = true;
			$server_wait = -1;
			if (isset($server_last_hit_times[$server])) {
				$time_since = $ts_now - $server_last_hit_times[$server];
				$server_wait = dlw_rehit_delay_in_secs - $time_since;
			}
			if ($seen_already || $server_wait > 0) {
				$deferred_urls[] = $url;
				array_splice($urls, $i, 1);

				continue;
			}
		}

		$i++;
	}

	if (($mh = curl_multi_init()) === false) {
		return false;
	}

	foreach ($urls as $url) {
		if (!in_array(dlw_get_scheme($url), array('http', 'https', ''))) {
			$redirs[$url] = $url;
			continue;
		}

		if (($ch = curl_init()) === false) {
			$redirs[$url] = null;
			continue;
		}

		// Strip from any # in the URL onwards because URLs with fragments
		// appear to be buggy either in certain older versions of cURL and/or
		// web server environments from which cURL is called.
		list($url) = explode('#', $url, 2);

		if (!curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_NOBODY         => $use_head_method,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_USERAGENT      => 'The MyBB Duplicate Link Warner plugin',
		))) {
			curl_close($ch);
			$redirs[$url] = null;
			continue;
		}
		if (curl_multi_add_handle($mh, $ch) !== CURLM_OK/*==0*/) {
			$redirs[$url] = null;
			continue;
		}
		$curl_handles[$url] = $ch;
	}

	$active = null;
	do {
		$mrc = curl_multi_exec($mh, $active);
	} while ($mrc == CURLM_CALL_MULTI_PERFORM);

	while ($active && $mrc == CURLM_OK) {
		if (curl_multi_select($mh) != -1) {
			do {
				$mrc = curl_multi_exec($mh, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}
	}

	foreach ($curl_handles as $url => $ch) {
		if ($ch) {
			$content = curl_multi_getcontent($ch);
			$server_last_hit_times[dlw_get_norm_server_from_url($url)] = microtime(true);
			if ($content
			    &&
			    ($header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE)) !== false
			    &&
			    ($response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) !== false
			) {
				$headers = substr($content, 0, $header_size);
				$target = $url;
				if ($response_code != 200) {
					if (preg_match('/^Location: (.*)$/im', $headers, $matches)) {
						$target = trim($matches[1]);
					}
				}
				$redirs[$url] = $target;
			} else {
				$redirs[$url] = false;
			}

			curl_multi_remove_handle($mh, $ch);
		} else	$redirs[$url] = false;
	}

	curl_multi_close($mh);

	return array($redirs, $deferred_urls);
}

function dlw_get_url_term_redirs_auto($urls) {
	static $repl_regexes = array(
		'(^http(?:s)?://(?:www.)?youtube\\.com/watch\\?v=([^&]+)(?:&feature=youtu\\.be)?$)'
						=> 'https://www.youtube.com/watch\\?v=\\1',
		'(^http(?:s)?://(?:(?:www|en)\\.)wikipedia.org/wiki/(.*)$)'
						=> 'https://en.wikipedia.org/wiki/\\1',
		'(^http(?:s)?://(?:(?:www|en)\\.)wikipedia.org/w/index.php\\?title=([^&]+)$)'
						=> 'https://en.wikipedia.org/wiki/\\1',
	);

	$redirs = [];

	foreach ($urls as $url) {
		foreach ($repl_regexes as $search => $replace) {
			if (preg_match($search, $url)) {
				$redirs[$url] = preg_replace($search, $replace, $url);
				$redirs[$redirs[$url]] = $redirs[$url];
			}
		}
	}

	return $redirs;
}

/**
 * Resolves and returns the terminating redirect targets (i.e., after following as many
 * redirects as possible) for each URL in $urls.
 *
 * Tries the HEAD method first to save bandwidth but if that results in an error, then
 * retries using the GET method.
 *
 * This function only makes sense for $urls with a protocol of http:// or https://.
 *
 * @param $urls Array.
 * @return An array indexed by each URL in $urls. Each entry is either:
 *         1. The URL's terminating redirect target (which might be itself).
 *         2. False in the case that a link-specific error occurred, e.g. web server timeout
 *            or redirect loop.
 *         3. Null in the case that a non-link-specific error occurred, such as failure to
 *            initialise a generic cURL handle.
 */
function dlw_get_url_term_redirs($urls) {
	$terms = $redirs = $server_last_hit_times = $to_retry = array();
	static $min_wait_flag_value = 99999;

	$redirs = dlw_get_url_term_redirs_auto($urls);

	$use_head_method = true;
	list($redirs2, $deferred_urls) = dlw_get_url_redirs(array_diff($urls, array_keys($redirs)), $server_last_hit_times, $use_head_method);
	if ($redirs2 === false && !$redirs) {
		return false;
	}

	// Defensive programming: in case this loop somehow becomes infinite,
	// terminate it based on the assumption that there would be no more than
	// five redirects per URL on average.
	$max_iterations = count($urls) * 5;
	$num_iterations = 0;
	do {
		$urls2 = $to_retry = [];
		$redirs = array_merge($redirs, $redirs2);
		foreach ($redirs as $url => $target) {
			if ($target && $target !== -1 && !isset($redirs[$target])) {
				$urls2[] = $target;
			} else if ($target === false && $use_head_method) {
				$to_retry[] = $url;
			}
		}
		$urls2 = array_values(array_unique(array_merge($deferred_urls, $urls2)));
		if (!$urls2 && $to_retry) {
			$use_head_method = false;
			$urls2 = $to_retry;
		}

		$min_wait = $min_wait_flag_value;
		$ts_now = microtime(true);
		foreach ($urls2 as $url) {
			$server = dlw_get_norm_server_from_url($url);
			if (!$server || !isset($server_last_hit_times[$server])) {
				$min_wait = 0;
				break;
			} else {
				$time_since = $ts_now - $server_last_hit_times[$server];
				$server_wait = dlw_rehit_delay_in_secs - $time_since;
				if ($server_wait < 0) {
					$min_wait = 0;
					break;
				} else {
					$min_wait = min($min_wait, $server_wait);
				}
			}
		}
		if ($min_wait == $min_wait_flag_value) $min_wait = 0;

		usleep($min_wait * 1000000);

		list($redirs2, $deferred_urls) = dlw_get_url_redirs($urls2, $server_last_hit_times, $use_head_method);
		if ($redirs2 === false) {
			return false;
		}
		if ($to_retry) foreach ($redirs2 as $url => &$target) {
			if ($target === false && in_array($url, $to_retry)) {
				$target = -1; // Don't retry more than once (retry is only on false, not -1).
			}
		}
		unset($target);
		$use_head_method = true;
		$num_iterations++;
	} while (($urls2 || $deferred_urls) && $num_iterations < $max_iterations);

	foreach ($redirs as $url => &$target) {
		if ($target === -1) {
			$target = false;
		}
	}
	unset($target);

	foreach ($urls as $url) {
		$term = $url;
		while ($term && $term != $redirs[$term]) {
			$term = $redirs[$term];
			// Abort on redirect loop.
			if ($term == $url) {
				$term = false;
				break;
			}
		}
		$terms[$url] = $term;
	}

	return $terms;
}

/**
 * Parses the URL with the PHP builtin function. One catch is that the builtin
 * does not handle intuitively those "URLs" without a scheme such as the following:
 *     example.com/somepath/file.html
 * though it does handle intuitively the same URL prefixed with a double forward slash.
 *
 * For the example "URL" above, instead of setting 'host' to 'example.com', and
 * 'path' to '/somepath/file.html', parse_url() does not set 'host' at all, and
 * sets 'path' to 'example.com/somepath/file.html'.
 *
 * To avoid this, we prefix the "URL" with an http scheme in that scenario.
 */
function dlw_parse_url($url) {
	$tmp_url = trim($url);
	$scheme = dlw_get_scheme($url);
	if ($scheme == '' && strpos($url, '//') !== 0) {
		$tmp_url = 'http://'.$tmp_url;
	}
	return parse_url($tmp_url);
}

function dlw_normalise_domain($domain) {
	static $prefix = 'www.';

	$domain = trim($domain);
	while (strpos($domain, $prefix) === 0) {
		$domain = substr($domain, strlen($prefix));
	}

	return $domain;
}


function dlw_normalise_url($url) {
	$strip_www_prefix = false;

	$parsed_url = dlw_parse_url($url);

	switch ($parsed_url['scheme']) {
	case 'http':
	case 'https':
	case '':
		$ret = 'http(s)://';
		$strip_www_prefix = true;
		$default_ports = array(80, 443);
		break;
	case 'ftp':
	case 'sftp':
		$ret = '(s)ftp://';
		$default_ports = array(21, 22);
		break;
	default:
		// Assume that $urls_parsed was generated from dlw_has_valid_scheme() and
		// thus that the scheme has already been validated.
		//
		// We shouldn't reach here though - the case statements above should
		// comprehensively cover all entries in dlw_valid_schemes.
		$ret = $parsed_url['scheme'].'://';
		$default_ports = array();
		break;
	}

	$domain = $parsed_url['host'];
	if ($strip_www_prefix) {
		$domain = dlw_normalise_domain($domain);
	}

	$ret .= $domain;

	if (isset($parsed_url['port']) && !in_array($parsed_url['port'], $default_ports)) {
		$ret .= ':'.$parsed_url['port'];
	}

	$ret .= ($parsed_url['path'] == '' ? '/' : $parsed_url['path']);

	if (isset($parsed_url['query'])) {
		$query = str_replace('&amp;', '&', $parsed_url['query']);
		$arr = explode('&', $query);
		sort($arr);
		foreach (dlw_ignored_query_params as $param => $domains) {
			if (is_int($param)) {
				$param = $domains;
				$domains = '*';
			}
			if (trim($domains) !== '*') {
				$domains = (array)$domains;
				foreach ($domains as &$dom) {
					$dom = dlw_normalise_domain($dom);
				}
				if (!in_array($domain, $domains)) {
					continue;
				}
			}

			$found = false;
			if (strpos($param, '=') === false) {
				for ($idx = 0; $idx < count($arr); $idx++) {
					list($key) = explode('=', $arr[$idx], 2);
					if ($key === $param) {
						$found = true;
						break;
					}
				}
			} else if (($idx = array_search($param, $arr)) !== false) {
				$found = true;
			}
			if ($found) {
				array_splice($arr, $idx, 1);
				continue;
			}
		}
		if ($arr) $ret .= '?'.implode('&', $arr);
	}

	return $ret; // We discard user, password, and fragment.
}

function dlw_hookin__datahandler_post_update_end($posthandler) {
	global $db;

	if (isset($posthandler->data['message'])) {
		$db->delete_query('post_urls', "pid={$posthandler->pid}");
		dlw_get_and_add_urls_of_post($posthandler->data['message'], $posthandler->pid);
	}
}

function dlw_hookin__admin_tools_recount_rebuild_output_list() {
	global $lang, $form_container, $form;
	if (!isset($lang->duplicate_link_warner)) {
		$lang->load('duplicate_link_warner');
	}

	$form_container->output_cell("<label>{$lang->dlw_rebuild}</label><div class=\"description\">{$lang->dlw_rebuild_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field('dlw_posts_per_page', dlw_default_rebuild_items_per_page, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array('name' => 'do_rebuildlinks')));
	$form_container->construct_row();
}

function dlw_hookin__admin_tools_recount_rebuild() {
	global $db, $mybb, $lang;
	if (!isset($lang->duplicate_link_warner)) {
		$lang->load('duplicate_link_warner');
	}

	if($mybb->request_method == "post") {
		if (!isset($mybb->input['page']) || $mybb->get_input('page', MyBB::INPUT_INT) < 1) {
			$mybb->input['page'] = 1;
		}

		if (isset($mybb->input['do_rebuildlinks'])) {
			if($mybb->input['page'] == 1) {
				// Log admin action
				log_admin_action($lang->dlw_admin_log_rebuild);
			}

			if (!$mybb->get_input('dlw_posts_per_page', MyBB::INPUT_INT)) {
				$mybb->input['dlw_posts_per_page'] = dlw_default_rebuild_items_per_page;
			}

			$page = $mybb->get_input('page', MyBB::INPUT_INT);
			$per_page = $mybb->get_input('dlw_posts_per_page', MyBB::INPUT_INT);
			if ($per_page <= 0) {
				$per_page = dlw_default_rebuild_items_per_page;
			}
			$start = ($page-1) * $per_page;
			$end = $start + $per_page;

			if ($page == 1) {
				$db->write_query('DELETE FROM '.TABLE_PREFIX.'post_urls');
				$db->write_query('DELETE FROM '.TABLE_PREFIX.'urls');
			}

			$res = $db->simple_select('posts', 'pid, message', '', array(
				'order_by'    => 'pid',
				'order_dir'   => 'ASC',
				'limit_start' => $start,
				'limit'       => $per_page,
			));
			$inc = $db->num_rows($res);

			$post_urls = $urls_all = [];
			while (($post = $db->fetch_array($res))) {
				$urls = dlw_extract_urls($post['message']);
				$post_urls[$post['pid']] = $urls;
				$urls_all = array_merge($urls_all, $urls);
			}
			$urls_all = array_values(array_unique($urls_all));
			$db->free_result($res);

			// Don't waste time and bandwidth trying to resolve redirects that have
			// already been resolved and are stored in the DB.
			$existing_redirs = [];
			$res = $db->simple_select('urls', 'url, url_term', 'url in (\''.implode("', '", array_map(array($db, 'escape_string'), $urls)).'\')');
			while (($row = $db->fetch_array($res))) {
				$existing_redirs[$row['url']] = $row['url_term'];
			}

			$redirs = dlw_get_url_term_redirs(array_diff($urls_all, array_keys($existing_redirs)));
			$redirs = array_merge($existing_redirs, $redirs);
			foreach ($post_urls as $pid => $urls) {
				dlw_add_urls_for_pid($urls, $redirs, $pid);
			}

			$res = $db->simple_select('posts', 'count(*) AS num_posts');
			$finish = $db->fetch_array($res)['num_posts'];

			// The first two parameters seem to be semantically switched within this function, so that's the way I've passed them.
			check_proceed($finish, $start + $inc, ++$page, $per_page, 'dlw_posts_per_page', 'do_rebuildlinks', $lang->dwl_success_rebuild);
		}
	}
}

function dlw_hookin__datahandler_post_insert_thread($posthandler) {
	global $db, $mybb, $templates, $lang, $headerinclude, $header, $footer;

	if ($mybb->get_input('ignore_dup_link_warn') || $posthandler->data['savedraft']) {
		return;
	}

	if (!isset($lang->duplicate_link_warner)) {
		$lang->load('duplicate_link_warner');
	}

	$urls = dlw_extract_urls($posthandler->data['message']);
	if (!$urls) {
		return;
	}

	list($matching_posts, $forum_names) = dlw_get_posts_for_urls($urls);

	$dismissed_arr = $mybb->get_input('dlw_dismissed') ? json_decode($mybb->get_input('dlw_dismissed'), true) : array();
	foreach ($dismissed_arr as $pid => $dismissed_urls) {
		if (array_key_exists($pid, $matching_posts)) {
			$matching_posts[$pid]['matching_urls'] = array_diff($matching_posts[$pid]['matching_urls'], $dismissed_urls);
			if (!$matching_posts[$pid]['matching_urls']) {
				unset($matching_posts[$pid]);
			}
		}
	}

	if (!$matching_posts) {
		return;
	}

	$dlw_found_posts_count = '<div class="red_alert">'.$lang->sprintf($dismissed_arr ?  $lang->dlw_found_posts_count_undismissed : $lang->dlw_found_posts_count, count($matching_posts), count($matching_posts) == 1 ? $lang->dlw_post_singular : $lang->dlw_posts_plural).'</div>';
	$dlw_found_posts = '';
	foreach ($matching_posts as $post) {
		if ($dlw_found_posts) $dlw_found_posts .= '<br />'."\n";
		$dlw_found_posts .= dlw_get_post_output($post, $forum_names);
	}

	$inputs = '';
	foreach ($mybb->input as $key => $val) {
		$inputs .= '<input type="hidden" name="'.htmlspecialchars_uni($key).'" value="'.htmlspecialchars_uni($val).'" />'."\n";
	}

	$savedraftbutton = '';
	if($mybb->user['uid'])
	{
		eval("\$savedraftbutton = \"".$templates->get("post_savedraftbutton", 1, 0)."\";");
	}

	/** @todo Perhaps turn this into a template so it can be customised. */
	$btns = <<<EOF
<div style="text-align:center">
	<input type="submit" class="button" name="ignore_dup_link_warn" value="{$lang->dlw_post_anyway}" accesskey="s" />
	<input type="submit" class="button" name="previewpost" value="{$lang->preview_post}" />
	{$savedraftbutton}
</div>
EOF;

	/** @todo Perhaps turn this into a (set of) template(s) so it can be customised. */
	$toggle_btn = $all_matching_urls_in_quotes_flag
	              ? '<div style="text-align:center"><button id="id_btn_toggle_quote_posts" onclick="dlw_toggle_hidden_posts();" type="button">'.$lang->dlw_btn_toggle_msg_hide.'</button></div>'
	              : '';
	$op = <<<EOF
<html>
<head>
<title>{$mybb->settings['bbname']}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="newthread.php?fid={$fid}&amp;processed=1" method="post" enctype="multipart/form-data" name="input">
{$inputs}
{$dlw_found_posts_count}
{$btns}
{$toggle_btn}
{$dlw_found_posts}
{$btns}
</form>
<br class="clear" />
{$footer}
<script type="text/javascript">
//<![CDATA[
function dlw_toggle_hidden_posts() {
	var nodes = document.querySelectorAll('.all_matching_urls_in_quotes');
	if (nodes.length > 0) {
		var val = (nodes[0].style.display == 'none' ? '' : 'none');
		for (var i = 0; i < nodes.length; i++) {
			nodes[i].style.display = val;
		}
		document.getElementById('id_btn_toggle_quote_posts').innerHTML = (val == 'none' ? '{$lang->dlw_btn_toggle_msg_show}' : '{$lang->dlw_btn_toggle_msg_hide}');
	}
}
//]]>
</script>
</body>
</html>
EOF;

	output_page($op);
	exit;
}

function dlw_hookin__newthread_start() {
	global $mybb, $lang, $duplicate_link_warner_div, $duplicate_link_warner_js;

	if (!isset($lang->duplicate_link_warner)) {
		$lang->load('duplicate_link_warner');
	}

	/** @todo Perhaps turn this into a template so that the style can be customised. */
	$duplicate_link_warner_div = "\n".'<div id="dlw-msg-sidebar-div" style="margin: auto; width: 170px; margin-top: 20px;"></div>';
	$dlw_msg_started_by       = addslashes($lang->dlw_started_by);
	$dlw_msg_opening_post     = addslashes($lang->dlw_opening_post);
	$dlw_msg_non_opening_post = addslashes($lang->dlw_non_opening_post);
	$dlw_msg_posted_by        = addslashes($lang->dlw_posted_by);
	$dlw_msg_matching_url_singular = addslashes($lang->dlw_matching_url_singular);
	$dlw_msg_matching_urls_plural  = addslashes($lang->dlw_matching_urls_plural );
	$dlw_msg_url1_as_url2          = addslashes($lang->dlw_msg_url1_as_url2     );
	$dlw_previously_dismissed = json_encode($mybb->get_input('dlw_dismissed') ? json_decode($mybb->get_input('dlw_dismissed'), true) : array(), JSON_PRETTY_PRINT);

	$duplicate_link_warner_js = <<<EOF
<script type="text/javascript" src="{$mybb->settings['bburl']}/jscripts/duplicate_link_warner.js"></script>
<script type="text/javascript">
var dlw_msg_started_by            = '{$dlw_msg_started_by}';
var dlw_msg_opening_post          = '{$dlw_msg_opening_post}';
var dlw_msg_non_opening_post      = '{$dlw_msg_non_opening_post}';
var dlw_msg_posted_by             = '{$dlw_msg_posted_by}';
var dlw_msg_matching_url_singular = '{$dlw_msg_matching_url_singular}';
var dlw_msg_matching_urls_plural  = '{$dlw_msg_matching_urls_plural}';
var dlw_msg_url1_as_url2          = '{$dlw_msg_url1_as_url2}';
var dlw_previously_dismissed      = {$dlw_previously_dismissed};
</script>';
EOF;
}

function dlw_get_link($url, $text) {
	return '<a href="'.htmlspecialchars_uni($url).'">'.htmlspecialchars_uni($text).'</a>';
}

function dlw_get_forumlink($fid, $name) {
	return dlw_get_link(get_forum_link($fid), $name);
}

function dlw_get_threadlink($tid, $name) {
	return dlw_get_link(get_thread_link($tid), $name);
}

function dlw_get_usernamelink($uid, $name) {
	return dlw_get_link(get_profile_link($uid), $name);
}

function dlw_get_postlink($pid, $name) {
	return dlw_get_link(get_post_link($pid).'#pid'.$pid, $name);
}

function dlw_get_flinks($parentlist, $forum_names) {
	$flinks = '';
	foreach (explode(',', $parentlist) as $fid) {
		if ($flinks ) $flinks .= ' &raquo; ';
		$flinks .= dlw_get_forumlink($fid, $forum_names[$fid]);
	}

	return $flinks;
}

/** @todo Perhaps turn this into a (set of) template(s) so it can be customised. */
function dlw_get_post_output($post, $forum_names) {
	global $lang;

	$is_first_post = ($post['firstpost'] == $post['pid']);
	$ret = '<div'.($post['are_all_matching_urls_in_quotes'] ? ' class="all_matching_urls_in_quotes"': '').'>'."\n";
	$ret .= '<div>'.$post['flinks'].'<br />'.$post['nav_bit_img'].$post['tlink'].'</div>'."\n";
	$ret .= '<div>'.$lang->dlw_started_by.' '.$post['ulink_t'].', '.$post['dtlink_t'].'</div>'."\n";
	$ret .= '<div>'.($is_first_post ? '<span style="border: 1px solid #a5161a; background-color: #fbe3e4; color: #a5161a; border-radius: 10px; -moz-border-radius: 10px; -webkit-border-radius: 10px; padding-left: 10px; padding-right: 10px;">'.$lang->dlw_opening_post.'</span>' : $lang->dlw_non_opening_post.' '.$post['plink'].' '.$lang->dlw_posted_by.' '.$post['ulink_p'].', '.$post['dtlink_p']).'</div>'."\n";
	$ret .= '<div>'.(count($post['matching_urls']) == 1 ? $lang->dlw_matching_url_singular : $lang->dlw_matching_urls_plural)."\n";
	$ret .= '<ul style="padding: 0 auto; margin: 0;">'."\n";
	for ($i = 0; $i < count($post['matching_urls']); $i++) {
		$url = $post['matching_urls'][$i];
		$url_esc = htmlspecialchars_uni($url);
		$link = '<a href="'.$url_esc.'">'.$url_esc.'</a>';
		$ret .= '<li style="padding: 0; margin: 0;">';
		if ($post['matching_urls_in_post'][$i] != $url) {
			$url2 = $post['matching_urls_in_post'][$i];
			$url_esc2 = htmlspecialchars_uni($url2);
			$link2 = '<a href="'.$url_esc2.'">'.$url_esc2.'</a>';
			$ret .= $lang->sprintf($lang->dlw_msg_url1_as_url2, $link, $link2);
		} else	$ret .= $link;
		$ret .= '</li>'."\n";
	}
	$ret .= '</ul></div>'."\n";
	$ret .= '<div style="border: 1px solid grey; border-radius: 10px;-moz-border-radius:10px;-webkit-border-radius:10px; padding: 10px; background-color: white;">'.$post['message'].'</div>'."\n";
	$ret .= '</div>'."\n";

	return $ret;
}

function dlw_strip_nestable_mybb_tag($message, $tagname) {
	$dlw_validate_start_tag_ending = function ($message, $pos) {
		if ($pos >= strlen($message)) {
			return false;
		}
		if ($message[$pos] == ' ' || $message[$pos] == '=') {
			while (++$pos < strlen($message)) {
				if ($message[$pos] == '[') {
					return false;
				} else if ($message[$pos] == ']') {
					return true;
				}
			}
			return false;
		} else {
			return $message[$pos] == ']';
		}
	};

	$pos = 0;
	while (($pos = strpos($message, '['.$tagname, $pos)) !== false) {
		if (!$dlw_validate_start_tag_ending($message, $pos+strlen('['.$tagname))) {
			$pos++;
			continue;
		}

		$open_count = 1;
		$pos_c = $pos;
		while (true) {
			$pos_i = $pos_c;
			$pos_c = strpos($message, '[/'.$tagname.']', $pos_i + 1);
			$pos2 = $pos_i;
			do {
				$pos2++;
				if ($pos2 >= strlen($message)) {
					$pos2 = false;
					break;
				}
				$pos2  = strpos($message, '['.$tagname , $pos2);
			} while ($pos2 !== false && !$dlw_validate_start_tag_ending($message, $pos2+strlen('['.$tagname)));
			if ($pos_c === false) {
				$pos_c = strlen($message) - strlen('[/'.$tagname.']') - 1;
				break;
			}
			if ($pos2 !== false && $pos2 < $pos_c) {
				$open_count++;
				$pos_c = $pos2 + 1;
			} else {
				$open_count--;
				if ($open_count == 0) break;
			}
		}
		$message = substr($message, 0, $pos).substr($message, $pos_c + strlen('</'.$tagname.'>'));
		$pos = 0;
	}

	return $message;
}

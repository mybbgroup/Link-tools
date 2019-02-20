<?php

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

# Should semantically match the equivalent variable in ../../jscripts/duplicate_link_warner.js
const dlw_valid_schemes = array('http', 'https', 'ftp', 'sftp', '');
const dlw_default_rebuild_items_per_page = 250;

/** @todo Consider what (should) happen(s) when a URL whose length exceeds the size of its associated database column is posted. */
/** @todo Implement the task to populate the term columns of the url table. */
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

function duplicate_link_warner_info()
{
	global $lang;
	$lang->load('config_duplicate_link_warner');

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
}

function duplicate_link_warner_is_installed() {
	global $db;

	return $db->table_exists('urls') && $db->table_exists('post_urls');
}

function duplicate_link_warner_activate() {
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('newthread', '({\\$smilieinserter})', '{$smilieinserter}{$duplicate_link_warner_div}');
	find_replace_templatesets('newthread', '({\\$codebuttons})'   , '{$codebuttons}{$duplicate_link_warner_js}'    );
}

function duplicate_link_warner_deactivate() {
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('newthread', '({\\$duplicate_link_warner_div})', '', 0);
	find_replace_templatesets('newthread', '({\\$duplicate_link_warner_js})' , '', 0);
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

	$conds = "u.url_norm IN ('".implode("', '", array_map(array($db, 'escape_string'), $urls_norm))."')";

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

	dlw_add_urls_of_post($posthandler->data['message'], $posthandler->pid);
}

function dlw_add_urls_of_post($message, $pid) {
	global $db;

	$urls = dlw_extract_urls($message);
	if ($urls) {
		for ($i = 0; $i < count($urls); $i++) {
			for ($try = 1; $try <= 2; $try++) {
				$res = $db->simple_select('urls', 'urlid', 'url = \''.$db->escape_string($urls[$i]).'\'');
				if ($row = $db->fetch_array($res)) {
					$urlid = $row['urlid'];
				} else {
					if (!$db->write_query('INSERT INTO '.TABLE_PREFIX.'urls (url, url_norm) VALUES (\''.$db->escape_string($urls[$i]).'\', \''.$db->escape_string(dlw_normalise_url($urls[$i])).'\')', /* $hide_errors = */ $try < 2)) {
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

	return $urls;
}

function dlw_normalise_urls($urls) {
	$ret = array();

	foreach ($urls as $url) {
		$ret[] = dlw_normalise_url($url);
	}

	return $ret;
}

/** @todo Implement ignored query parameters (e.g. fbclid) during normalisation. */
function dlw_normalise_url($url) {
	$strip_www_prefix = false;

	$scheme = dlw_get_scheme($url);

	// Parse the URL with the PHP builtin function. One catch is that this function
	// does not handle intuitively those URLs without a scheme such as the following:
	//     example.com/somepath/file.html
	// though it does handle intuitively the same URL prefixed with a double forward slash.
	//
	// For the example URL, instead of setting 'host' to 'example.com', and
	// 'path' to '/somepath/file.html', parse_url() does not set 'host' at all, and
	// sets 'path' to 'example.com/somepath/file.html'.
	//
	// To avoid this, we prefix with an http scheme in that scenario.
	$tmp_url = $url;
	if ($scheme == '' && strpos($url, '//') !== 0) {
		$tmp_url = 'http://'.$tmp_url;
	}
	$parsed_url = parse_url($tmp_url);

	switch ($scheme) {
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
		$prefix = 'www.';
		while (strpos($domain, $prefix) === 0) {
			$domain = substr($domain, strlen($prefix));
		}
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
		$ret .= '?'.implode('&', $arr);
	}

	return $ret; // We discard user, password, and fragment.
}

function dlw_hookin__datahandler_post_update_end($posthandler) {
	global $db;

	if (isset($posthandler->data['message'])) {
		$db->delete_query('post_urls', "pid={$posthandler->pid}");
		dlw_add_urls_of_post($posthandler->data['message'], $posthandler->pid);
	}
}

function dlw_hookin__admin_tools_recount_rebuild_output_list() {
	global $lang, $form_container, $form;
	$lang->load('config_duplicate_link_warner');

	$form_container->output_cell("<label>{$lang->dlw_rebuild}</label><div class=\"description\">{$lang->dlw_rebuild_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field('dlw_posts_per_page', dlw_default_rebuild_items_per_page, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array('name' => 'do_rebuildlinks')));
	$form_container->construct_row();
}

function dlw_hookin__admin_tools_recount_rebuild() {
	global $db, $mybb, $lang;
	$lang->load('config_duplicate_link_warner');

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

			$url_posts = array();

			$res = $db->simple_select('posts', 'pid, message', '', array(
				'order_by'    => 'pid',
				'order_dir'   => 'ASC',
				'limit_start' => $start,
				'limit'       => $per_page,
			));

			$inc = $db->num_rows($res);
			while (($post = $db->fetch_array($res))) {
				$urls = dlw_add_urls_of_post($post['message'], $post['pid']);
				foreach ($urls as $url) {
					if (!isset($url_posts[$url])) {
						$url_posts[$url] = [];
					}
					$url_posts[$url][] = $post['pid'];
				}
			}

			$res = $db->simple_select('posts', 'count(*) AS num_posts');
			$finish = $db->fetch_array($res)['num_posts'];

			// The first two parameters seem to be semantically switched within this function, so that's the way I've passed them.
			check_proceed($finish, $start + $inc, ++$page, $per_page, 'dlw_posts_per_page', 'do_rebuildlinks', $lang->dwl_success_rebuild);
		}

		/** @todo Now populate the terminating columns of the url table for the added entries. */
	}
}

function dlw_hookin__datahandler_post_insert_thread($posthandler) {
	global $db, $mybb, $templates, $lang, $headerinclude, $header, $footer;

	if ($mybb->get_input('ignore_dup_link_warn') || $posthandler->data['savedraft']) {
		return;
	}

	$lang->load('duplicate_link_warner');

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

	$lang->load('duplicate_link_warner');
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

<?php

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}


/** @todo Maybe add a global and/or per-user setting to disable checking for matching non-opening posts. */
/** @todo Limit the number of returned matching posts to a sane value and consider how to provide access to the remainder. */


$plugins->add_hook('datahandler_post_insert_thread', 'duplicate_link_warner__hook_datahandler_post_insert_thread');
$plugins->add_hook('newthread_start'               , 'duplicate_link_warner__hook_newthread_start'               );

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

function dlw_extract_urls($text) {
	# Comprehensive regex matching non-relative international URIs as shared here:
	# https://stackoverflow.com/a/190405
	# with backslashes escaped, and with a "u" modifier added as suggested here:
	# https://stackoverflow.com/a/4338569
	#
	# Note also an ad hoc modification added to the end of this regex to avoid matching URLs followed by a [/img] tag.
	#
	# Should semantically match the equivalent variable in ../../jscripts/duplicate_link_warner.js with the exception noted there.
	static $uriRegex = '/[a-z](?:[-a-z0-9\\+\\.])*:(?:\\/\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:])*@)?(?:\\[(?:(?:(?:[0-9a-f]{1,4}:){6}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|::(?:[0-9a-f]{1,4}:){5}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){4}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4}:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){3}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,2}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){2}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,3}[0-9a-f]{1,4})?::[0-9a-f]{1,4}:(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,4}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,5}[0-9a-f]{1,4})?::[0-9a-f]{1,4}|(?:(?:[0-9a-f]{1,4}:){0,6}[0-9a-f]{1,4})?::)|v[0-9a-f]+[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:]+)\\]|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}|(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=@])*)(?::[0-9]*)?(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|\\/(?:(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*)?|(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|(?!(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])))(?:\\?(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])|[\\x{E000}-\\x{F8FF}\\x{F0000}-\\x{FFFFD}|\\x{100000}-\\x{10FFFD}\\/\\?])*)?(?:\\#(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])|[\\/\\?])*)?(?!\\[\\/img\\])/iu';

	# Should semantically match the equivalent variable in ../../jscripts/duplicate_link_warner.js
	static $valid_schemes = array('http', 'https', 'ftp', 'sftp');

	$urls = array();
	if (preg_match_all($uriRegex, $text, $matches, PREG_PATTERN_ORDER)) {
		foreach ($matches[0] as $url) {
			$res = parse_url($url);
			if (!empty($res['scheme']) && in_array($res['scheme'], $valid_schemes)
			    && !in_array($url, $urls)
			   ) {
				/**
				 * @todo Remove any trailing closing parenthesis not matching an opening parenthesis within the URL,
				 * and then match that logic in ../../jscripts/duplicate_link_warner.js.
				 */
				$urls[] = $url;
			}
		}
	}

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

	$conds = '';
	foreach ($urls as $url) {
		if ($conds) $conds .= ' OR ';
		$conds .= 'message LIKE \'%'.$db->escape_string($url).'%\'';
	}

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
SELECT          p.pid, p.uid AS uid_post, p.username AS username_post, p.dateline as dateline_post, p.message, p.subject AS subject_post, p.edittime,
                t.tid, t.uid AS uid_thread, t.username AS username_thread, t.subject AS subject_thread, t.firstpost, t.dateline as dateline_thread,
                (p.pid = t.firstpost) AS isfirstpost,
                x.prefix,
                f.fid, f.name as forum_name, f.parentlist
FROM            '.$db->table_prefix.'posts p
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
	while (($post = $db->fetch_array($res))) {
		$forum_names[$post['fid']] = $post['forum_name'];
		$urls2 = dlw_extract_urls($post['message']);
		sort($urls2);
		$matching_urls = array_values(array_intersect($urls, $urls2));
		if ($matching_urls) {
			$post['all_urls'     ] = $urls2;
			$post['matching_urls'] = $matching_urls;
			$stripped = dlw_strip_nestable_mybb_tag($post['message'], 'quote');
			$urls2_quotes_stripped = dlw_extract_urls($stripped);
			$post['are_all_matching_urls_in_quotes'] = (array_intersect($urls, $urls2_quotes_stripped) == array());
			if ($post['are_all_matching_urls_in_quotes']) $all_matching_urls_in_quotes_flag = true;
			$post['message'] = $parser->parse_message($post['message'], $parse_opts);
			$matching_posts[$post['pid']] = $post;
			foreach (explode(',', $post['parentlist']) as $fid) {
				if (empty($forum_names[$fid])) {
					$forum_names[$fid] = null;
				}
			}
		}
	}
	$db->free_result($res);

	if (!$matching_posts) {
		return array(null, $forum_names, 0);
	}

	uasort($matching_posts, function ($post1, $post2) use ($urls) {
		$grade_post = function($post) {
			return ($post['pid'] == $post['firstpost']
			        ? ($urls == $post['matching_urls']
			           ? ($urls == $post['all_urls']
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

function duplicate_link_warner__hook_datahandler_post_insert_thread($posthandler) {
	global $db, $mybb, $templates, $lang, $headerinclude, $header, $footer;

	if ($mybb->get_input('ignore_dup_link_warn') || $mybb->get_input('savedraft')) {
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

function duplicate_link_warner__hook_newthread_start() {
	global $mybb, $lang, $duplicate_link_warner_div, $duplicate_link_warner_js;

	$lang->load('duplicate_link_warner');
	/** @todo Perhaps turn this into a template so that the style can be customised. */
	$duplicate_link_warner_div = "\n".'<div id="dlw-msg-sidebar-div" style="margin: auto; width: 170px; margin-top: 20px;"></div>';
	$dlw_msg_started_by       = addslashes($lang->dlw_started_by);
	$dlw_msg_opening_post     = addslashes($lang->dlw_opening_post);
	$dlw_msg_non_opening_post = addslashes($lang->dlw_non_opening_post);
	$dlw_msg_posted_by= addslashes($lang->dlw_posted_by);
	$dlw_msg_matching_url_singular= addslashes($lang->dlw_matching_url_singular);
	$dlw_msg_matching_urls_plural= addslashes($lang->dlw_matching_urls_plural);
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
	foreach ($post['matching_urls'] as $url) {
		$url_esc = htmlspecialchars_uni($url);
		$ret .= '<li style="padding: 0; margin: 0;"><a href="'.$url_esc.'">'.$url_esc.'</a></li>'."\n";
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

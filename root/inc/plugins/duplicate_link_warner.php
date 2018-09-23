<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}


// @TODO Maybe make use of the 'are_all_matching_urls_in_quotes' attribute to perhaps hide by default matching posts within which all matching URLs occur within quotes only. Maybe make this configurable either globally or per-user.
// @TODO Maybe add a global and/or per-user setting to disable checking for matching non-opening posts.


$plugins->add_hook('datahandler_post_insert_thread', 'duplicate_link_warner__hook_datahandler_post_insert_thread');

function duplicate_link_warner_info()
{
	global $lang;
	$lang->load('config_duplicate_link_warner');

	return array(
		"name"          => $lang->dlw_name,
		"description"   => $lang->dlw_desc,
		"website"       => "",
		"author"        => "Laird Shaw",
		"authorsite"    => "",
		"version"       => "0.0.3",
		"guid"          => "",
		"codename"      => str_replace('.php', '', basename(__FILE__)),
		"compatibility" => "18*"
	);
}

function dlw_extract_urls($text) {
	# Comprehensive regex by eyelidlessness matching non-relative international URIs as shared here:
	# http://stackoverflow.com/questions/161738/what-is-the-best-regular-expression-to-check-if-a-string-is-a-valid-url
	# with backslashes escaped, and with a "u" modifier added as suggested by OmnipotentEntity here:
	# http://stackoverflow.com/questions/4337248/php-regexp-for-national-domains
	#
	# Note also an ad hoc modification added to the end of this regex to avoid matching URLs followed by a [/img] tag.
	static $uriRegex = '/[a-z](?:[-a-z0-9\\+\\.])*:(?:\\/\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:])*@)?(?:\\[(?:(?:(?:[0-9a-f]{1,4}:){6}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|::(?:[0-9a-f]{1,4}:){5}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){4}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4}:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){3}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,2}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){2}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,3}[0-9a-f]{1,4})?::[0-9a-f]{1,4}:(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,4}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,5}[0-9a-f]{1,4})?::[0-9a-f]{1,4}|(?:(?:[0-9a-f]{1,4}:){0,6}[0-9a-f]{1,4})?::)|v[0-9a-f]+[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:]+)\\]|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}|(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=@])*)(?::[0-9]*)?(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|\\/(?:(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*)?|(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|(?!(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])))(?:\\?(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])|[\\x{E000}-\\x{F8FF}\\x{F0000}-\\x{FFFFD}|\\x{100000}-\\x{10FFFD}\\/\\?])*)?(?:\\#(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\x{A0}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFEF}\\x{10000}-\\x{1FFFD}\\x{20000}-\\x{2FFFD}\\x{30000}-\\x{3FFFD}\\x{40000}-\\x{4FFFD}\\x{50000}-\\x{5FFFD}\\x{60000}-\\x{6FFFD}\\x{70000}-\\x{7FFFD}\\x{80000}-\\x{8FFFD}\\x{90000}-\\x{9FFFD}\\x{A0000}-\\x{AFFFD}\\x{B0000}-\\x{BFFFD}\\x{C0000}-\\x{CFFFD}\\x{D0000}-\\x{DFFFD}\\x{E1000}-\\x{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])|[\\/\\?])*)?(?!\\[\\/img\\])/iu';

	$urls = array();
	if (preg_match_all($uriRegex, $text, $matches, PREG_PATTERN_ORDER)) {
		foreach ($matches[0] as $url) {
			$res = parse_url($url);
			if (!empty($res['scheme']) && in_array($res['scheme'], array('http', 'https', 'ftp', 'sftp'))
			    && !in_array($url, $urls)
			   ) {
				$urls[] = $url;
			}
		}
	}

	return $urls;
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
	sort($urls);

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

	$res = $db->query('
SELECT          p.pid, p.uid AS uid_post, p.username AS username_post, p.dateline as dateline_post, p.message, p.subject AS subject_post,
                t.tid, t.uid AS uid_thread, t.username AS username_thread, t.subject AS subject_thread, t.firstpost, t.dateline as dateline_thread,
                x.prefix,
                f.fid, f.name as forum_name, f.parentlist
FROM            '.$db->table_prefix.'posts p
LEFT OUTER JOIN '.$db->table_prefix.'forums f
ON              p.fid = f.fid
LEFT OUTER JOIN '.$db->table_prefix.'threads t
ON              p.tid = t.tid
LEFT OUTER JOIN '.$db->table_prefix.'threadprefixes x
ON              t.prefix = x.pid
WHERE           '.$conds);

	$all_matching_urls_in_quotes_flag = false;
	$forum_names = array();
	$matching_posts = array();
	while (($row = $db->fetch_array($res))) {
		$forum_names[$row['fid']] = $row['forum_name'];
		$urls2 = dlw_extract_urls($row['message']);
		sort($urls2);
		$common_urls = array_intersect($urls, $urls2);
		if ($common_urls) {
			$row['all_urls'   ] = $urls2;
			$row['common_urls'] = $common_urls;
			$stripped = dlw_strip_nestable_tag($row['message'], 'quote');
			$urls2_quotes_stripped = dlw_extract_urls($stripped);
			$row['are_all_matching_urls_in_quotes'] = (array_intersect($urls, $urls2_quotes_stripped) == array());
			if ($row['are_all_matching_urls_in_quotes']) $all_matching_urls_in_quotes_flag = true;
			$matching_posts[] = $row;
			foreach (explode(',', $row['parentlist']) as $fid) {
				if (empty($forum_names[$fid])) {
					$forum_names[$fid] = null;
				}
			}
		}
	}
	$db->free_result($res);

	if (!$matching_posts) {
		return;
	}

	usort($matching_posts, function ($post1, $post2) use ($urls) {
		$grade_post = function($post) {
			return ($post['pid'] == $post['firstpost']
			        ? ($urls == $post['common_urls']
			           ? ($urls == $post['all_urls']
			              ? 6
			              : 5
			             )
			           : 4
			          )
			        : ($urls == $post['common_urls']
			           ? ($post['all_urls'] == $post['common_urls']
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

	$missing_fids = array();
	foreach ($forum_names as $fid => $name) {
		if (is_null($name)) {
			$missing_fids[] = $fid;
		}
	}
	if ($missing_fids) {
		$res = $db->simple_select('forums', 'fid,name', 'fid IN ('.implode(',', $missing_fids).')');
		while (($row = $db->fetch_array($res))) {
			$forum_names[$row['fid']] = $row['name'];
		}
	}

	$dlw_found_posts_count = '<div class="red_alert">'.$lang->sprintf($lang->dlw_found_posts_count, count($matching_posts), count($matching_posts) == 1 ? $lang->dlw_post_singular : $lang->dlw_posts_plural).'</div>';
	$dlw_found_posts = '';
	foreach ($matching_posts as $row) {
		if ($dlw_found_posts) $dlw_found_posts .= '<br />'.PHP_EOL;
		$dlw_found_posts .= dlw_get_post_output($row, $forum_names);
	}

	$inputs = '';
	foreach ($mybb->input as $key => $val) {
		$inputs .= '<input type="hidden" name="'.htmlspecialchars_uni($key).'" value="'.htmlspecialchars_uni($val).'" />'.PHP_EOL;
	}

	$savedraftbutton = '';
	if($mybb->user['uid'])
	{
		eval("\$savedraftbutton = \"".$templates->get("post_savedraftbutton", 1, 0)."\";");
	}

	$btns = <<<EOF
<div style="text-align:center">
	<input type="submit" class="button" name="ignore_dup_link_warn" value="{$lang->dlw_post_anyway}" accesskey="s" />
	<input type="submit" class="button" name="previewpost" value="{$lang->preview_post}" />
	{$savedraftbutton}
</div>
EOF;

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

function dlw_get_forumlink($fid, $name) {
	return '<a href="'.htmlspecialchars_uni(get_forum_link($fid)).'">'.htmlspecialchars_uni($name).'</a>';
}

function dlw_get_threadlink($tid, $name) {
	return '<a href="'.htmlspecialchars_uni(get_thread_link($tid)).'">'.htmlspecialchars_uni($name).'</a>';
}

function dlw_get_usernamelink($uid, $name) {
	return '<a href="'.htmlspecialchars_uni(get_profile_link($uid)).'">'.htmlspecialchars_uni($name).'</a>';
}

function dlw_get_postlink($pid, $name) {
	return '<a href="'.htmlspecialchars_uni(get_post_link($pid).'#pid'.$pid).'">'.htmlspecialchars_uni($name).'</a>';
}

function dlw_get_post_output($row, $forum_names) {
	global $lang;

	$is_first_post = ($row['firstpost'] == $row['pid']);
	$ret = '<div'.($row['are_all_matching_urls_in_quotes'] ? ' class="all_matching_urls_in_quotes"': '').'>'.PHP_EOL;
	$flinks = '';
	foreach (explode(',', $row['parentlist']) as $fid) {
		if ($flinks ) $flinks .= ' &raquo; ';
		$flinks .= dlw_get_forumlink($fid, $forum_names[$fid]);
	}
	$flinks .= '<br /><img src="images/nav_bit.png" alt="" />'.dlw_get_threadlink($row['tid'], $row['subject_thread']);
	$ret .= '<div>'.$flinks.'</div>'.PHP_EOL;
	$ret .= '<div>'.$lang->dlw_started_by.' '.dlw_get_usernamelink($row['uid_thread'], $row['username_thread']).', '.my_date('relative', $row['dateline_thread']).'</div>'.PHP_EOL;
	$ret .= '<div>'.($is_first_post ? '<span style="border: 1px solid #a5161a; background-color: #fbe3e4; color: #a5161a; border-radius: 10px;-moz-border-radiust:10px;-webkit-border-radius:10px; padding-left: 10px; padding-right: 10px;">'.$lang->dlw_opening_post.'</span>' : $lang->dlw_non_opening_post.' '.dlw_get_postlink($row['pid'], $row['subject_post']).' '.$lang->dlw_posted_by.' '.dlw_get_usernamelink($row['uid_post'], $row['username_post']).', '.my_date('relative', $row['dateline_post'])).'</div>'.PHP_EOL;
	$ret .= '<div>'.(count($row['common_urls']) == 1 ? $lang->dlw_matching_url_singular : $lang->dlw_matching_urls_plural).PHP_EOL;
	$ret .= '<ul style="padding: 0 auto; margin: 0;">'.PHP_EOL;
	foreach ($row['common_urls'] as $url) {
		$url_esc = htmlspecialchars_uni($url);
		$ret .= '<li style="padding: 0; margin: 0;"><a href="'.$url_esc.'">'.$url_esc.'</a></li>'.PHP_EOL;
	}
	$ret .= '</ul></div>'.PHP_EOL;

	global $parser;
	if (!$parser) {
		require_once MYBB_ROOT.'inc/class_parser.php';
		$parser = new postParser;
	}
	$ret .= '<div style="border: 1px solid grey; border-radius: 10px;-moz-border-radiust:10px;-webkit-border-radius:10px; padding: 10px; background-color: white;">'.
	        $parser->parse_message($row['message'], array(
			'allow_html'   => false,
			'allow_mycode' => true,
			'allow_smilies' => true,
			'allow_imgcode' => true,
			'allow_videocode' => true,
			'nofollow_on' => 1,
			'filter_badwords' => 1
		)).'</div>'.PHP_EOL;

	$ret .= '</div>'.PHP_EOL;

	return $ret;
}

function dlw_strip_nestable_tag($message, $tagname) {
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

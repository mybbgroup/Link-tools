<?php

define('IN_MYBB', 1);
define('IGNORE_CLEAN_VARS', 'sid');
define('THIS_SCRIPT', 'dlw_search.php');

require_once './global.php';

$urls = $mybb->input['urls'];
$resulttype = $mybb->get_input('resulttype');
$as_posts = ($resulttype != 'threads');

$sql = dlw_get_url_search_sql($urls);
$res = $db->query($sql);

$pids = array();
$tids = array();
while (($row = $db->fetch_array($res))) {
	$pids[] = $row['pid'];
	$tids[$row['tid']] = true;
}

$sid = md5(uniqid(microtime(), 1));
$searcharray = array(
	'sid' => $db->escape_string($sid),
	'uid' => $mybb->user['uid'],
	'dateline' => TIME_NOW,
	'ipaddress' => $db->escape_binary($session->packedip),
	'threads' => $as_posts ? '' : implode(',', array_keys($tids)),
	'posts' => $as_posts ? implode(',', $pids) : '',
	'resulttype' => $as_posts ? 'posts' : 'threads',
	'querycache' => '',
	'keywords' => ''
);
$db->insert_query('searchlog', $searcharray);
redirect('search.php?action=results&sid='.$sid, $lang->redirect_searchresults);

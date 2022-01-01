<?php

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

$lang->load('linktools');

$sub_tabs = array(
	'linklimits' => array(
		'title' => $lang->lkt_linklimits,
		'description' => $lang->lkt_linklimitsdesc
	)
);

$page->add_breadcrumb_item($lang->lkt_linklimits, "index.php?module=forum-linklimits");

$page->output_header($lang->lkt_linklimits);

$page->output_nav_tabs($sub_tabs, 'linklimits');

$errs = array();

if ($mybb->get_input('my_post_key')) {
	verify_post_check($mybb->get_input('my_post_key'));
	$ll_gids = $mybb->get_input('ll_gids', MyBB::INPUT_ARRAY);
	foreach ($ll_gids as &$ll_gid) {
		$ll_gid = (int)$ll_gid;
		unset($ll_gid);
	}
	$ll_fids = $mybb->get_input('ll_fids', MyBB::INPUT_ARRAY);
	foreach ($ll_fids as &$ll_fid) {
		$ll_fid = (int)$ll_fid;
		unset($ll_fid);
	}
	$maxlinks = $mybb->get_input('maxlinks', MyBB::INPUT_INT);
	$days     = $mybb->get_input('days'    , MyBB::INPUT_INT);

	if ($mybb->get_input('add')) {
		if (!$ll_gids) {
			$errs[] = $lang->lkt_err_nogrps;
		}
		if (!$ll_fids) {
			$errs[] = $lang->lkt_err_nofrms;
		}
		if ($days < 1) {
			$errs[] = $lang->lkt_err_nodays;
		}
		if ($maxlinks < 0) {
			$errs[] = $lang->lkt_err_nomaxlnk;
		}

		if (!$errs) {
			$db->insert_query('link_limits', array(
				'gids' => implode(',', $ll_gids),
				'fids' => implode(',', $ll_fids),
				'maxlinks' => $maxlinks,
				'days'     => $days,
			));
			$ll_gids = $ll_fids = array();
			$maxlinks = '';
			$days = '';
		}
	}

	foreach ($mybb->input as $key => $val) {
		if (substr($key, 0, 5) === 'save_') {
			$save_id = (int)substr($key, 5);
			if ($save_id > 0) {
				$ll_gids_save = $mybb->get_input('ll_gids_'.$save_id, MyBB::INPUT_ARRAY);
				foreach ($ll_gids_save as &$ll_gid) {
					$ll_gid = (int)$ll_gid;
					unset($ll_gid);
				}
				$ll_fids_save = $mybb->get_input('ll_fids_'.$save_id, MyBB::INPUT_ARRAY);
				foreach ($ll_fids_save as &$ll_fid) {
					$ll_fid = (int)$ll_fid;
					unset($ll_fid);
				}
				$fields = array(
					'gids'     => implode(',', $ll_gids_save),
					'fids'     => implode(',', $ll_fids_save),
					'maxlinks' => $mybb->get_input('maxlinks_'.$save_id, MyBB::INPUT_INT),
					'days'     => $mybb->get_input('days_'    .$save_id, MyBB::INPUT_INT),
				);
				$db->update_query('link_limits', $fields, "llid = '$save_id'");
			}
		}
	}

	foreach ($mybb->input as $key => $val) {
		if (substr($key, 0, 4) === 'del_') {
			$del_id = (int)substr($key, 4);
			if ($del_id > 0) {
				$db->delete_query('link_limits', "llid = '$del_id'");
			}
		}
	}
}

if ($errs) echo inline_error($errs);

$form = new Form('index.php?module=forum-linklimits', 'post');
$form_container = new FormContainer($lang->lkt_linklimits);
$form_container->output_row_header($lang->lkt_groups);
$form_container->output_row_header($lang->lkt_forums);
$form_container->output_row_header($lang->lkt_maxlinks);
$form_container->output_row_header($lang->lkt_days);
$form_container->output_row_header($lang->lkt_action);

$groups_cache = $cache->read('usergroups');
if (!is_array($forum_cache)) {
	$forum_cache = cache_forums();
}

$query = $db->simple_select('link_limits', '*');
while ($row = $db->fetch_array($query)) {
	if (isset($mybb->input['edit_'.$row['llid']])) {
		$edit_gids = explode(',', $row['gids']);
		foreach ($edit_gids as &$ll_gid) {
			$ll_gid = (int)$ll_gid;
			unset($ll_gid);
		}
		$edit_fids = explode(',', $row['fids']);
		foreach ($edit_fids as &$ll_fid) {
			$ll_fid = (int)$ll_fid;
			unset($ll_fid);
		}
		$form_container->output_cell($form->generate_group_select("ll_gids_{$row['llid']}[]", $edit_gids, array('multiple' => true, 'size' => 5)));
		$form_container->output_cell($form->generate_forum_select("ll_fids_{$row['llid']}[]", $edit_fids, array('multiple' => true, 'size' => 5)));
		$form_container->output_cell($form->generate_text_box("maxlinks_{$row['llid']}", $row['maxlinks'], array('style' => 'max-width: 3em;')));
		$form_container->output_cell($form->generate_text_box("days_{$row['llid']}"    , $row['days'    ], array('style' => 'max-width: 3em;')));
		$form_container->output_cell(
		                             $form->generate_submit_button($lang->lkt_save  , array('name' => 'save_'.$row['llid'])).
		                             $form->generate_submit_button($lang->lkt_delete, array('name' => 'del_' .$row['llid']))
		);
	} else {
		$forum_links = array();
		foreach (explode(',', $row['fids']) as $ll_fid) {
			$ll_fid = (int)trim($ll_fid);
			if (isset($forum_cache[$ll_fid]['name'])) {
				$forum_links[] = '<a href="'.get_forum_link($ll_fid).'">'.htmlspecialchars_uni($forum_cache[$ll_fid]['name']).'</a>';
			}
		}
		$group_links = array();
		foreach (explode(',', $row['gids']) as $ll_gid) {
			$ll_gid = (int)trim($ll_gid);
			if (isset($groups_cache[$ll_gid])) {
				$group_links[] = format_name(htmlspecialchars_uni($groups_cache[$ll_gid]['title']), $ll_gid);
			}
		}
		$form_container->output_cell(implode('<br />', $group_links));
		$form_container->output_cell(implode('<br />', $forum_links));
		$form_container->output_cell($row['maxlinks']);
		$form_container->output_cell($row['days']);
		$form_container->output_cell(
		                             $form->generate_submit_button($lang->lkt_edit  , array('name' => 'edit_'.$row['llid'])).
		                             $form->generate_submit_button($lang->lkt_delete, array('name' => 'del_' .$row['llid']))
		);
	}
	$form_container->construct_row();
}

$form_container->output_cell($form->generate_group_select('ll_gids[]', $ll_gids, array('id' => 'll_gids', 'multiple' => true, 'size' => 5)));
$form_container->output_cell($form->generate_forum_select('ll_fids[]', $ll_fids, array('id' => 'll_fids', 'multiple' => true, 'size' => 5)));
$form_container->output_cell($form->generate_text_box('maxlinks', $maxlinks, array('style' => 'max-width: 3em;')));
$form_container->output_cell($form->generate_text_box('days'    , $days, array('style' => 'max-width: 3em;')));
$form_container->output_cell($form->generate_submit_button($lang->lkt_add, array('name' => 'add')));
$form_container->construct_row();

$form_container->end();

$form->end();

$page->output_footer();

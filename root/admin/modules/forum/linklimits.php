<?php

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

$lang->load('linktools');

$errs = array();

if ($mybb->get_input('my_post_key')) {
	verify_post_check($mybb->get_input('my_post_key'));

	if (!isset($mybb->input['ajax_save_id']) && !isset($mybb->input['ajax_del_id'])) {
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

		if ($mybb->get_input('add') || isset($mybb->input['ajax_add'])) {
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
				$fields = array(
					'gids' => implode(',', $ll_gids),
					'fids' => implode(',', $ll_fids),
					'maxlinks' => $maxlinks,
					'days'     => $days,
				);
				$llid = $db->insert_query('link_limits', $fields);
				$fields['llid'] = $llid;
				$ll_gids = $ll_fids = array();
				$maxlinks = '';
				$days = '';
			}
		}
	}

	foreach ($mybb->input as $key => $val) {
		if (substr($key, 0, 5) === 'save_' || $key == 'ajax_save_id') {
			$save_id = $key == 'ajax_save_id' ? $val : (int)substr($key, 5);
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
				if ($key == 'ajax_save_id') {
					$fields['llid'] = $save_id;
					break;
				}
			}
		}
	}

	foreach ($mybb->input as $key => $val) {
		if (substr($key, 0, 4) === 'del_' || $key == 'ajax_del_id') {
			$del_id = $key == 'ajax_del_id' ? $val : (int)substr($key, 4);
			if ($del_id > 0) {
				$db->delete_query('link_limits', "llid = '$del_id'");
			}
		}
	}
}

// These variables must be initialised before the JSON-encoded AJAX return below,
// because they are used in the call to lkt_gen_linklim_cells().
// We re-initialise $form afterwards though with its $return value set to false (the default).
$form = new Form('index.php?module=forum-linklimits', 'post', /*$id=*/'', /*$allow_uploads=*/false, /*$name=*/'', /*$return=*/true);
$groups_cache = $cache->read('usergroups');
if (!is_array($forum_cache)) {
	$forum_cache = cache_forums();
}

global $charset;
if (isset($mybb->input['ajax_save_id']) || isset($mybb->input['ajax_add'])) {
	header("Content-type: application/json; charset={$charset}");
	if ($errs) {
		$ret = array('outcome' => 'ERROR', 'errors' => inline_error($errs));
	} else {
		$ret = array_merge(array('outcome' => 'OK'), lkt_gen_linklim_cells($fields, $form, $forum_cache, $groups_cache));
		if (isset($mybb->input['ajax_add'])) {
			$ret['id'] = $llid;
		}
	}
	echo json_encode($ret, JSON_PRETTY_PRINT);
	exit;
} else if (isset($mybb->input['ajax_del_id'])) {
	header("Content-type: application/json; charset={$charset}");
	echo json_encode($errs
	                   ? array('outcome' => 'ERROR', 'errors' => inline_error($errs))
	                   : array('outcome' => 'OK'),
	);
	exit;
}

$sub_tabs = array(
	'linklimits' => array(
		'title' => $lang->lkt_linklimits,
		'description' => $lang->lkt_linklimitsdesc
	)
);

$page->add_breadcrumb_item($lang->lkt_linklimits, "index.php?module=forum-linklimits");

$page->output_header($lang->lkt_linklimits);

$page->output_nav_tabs($sub_tabs, 'linklimits');

if ($errs) echo inline_error($errs);

$form = new Form('index.php?module=forum-linklimits', 'post');
$form_container = new FormContainer($lang->lkt_linklimits);
$form_container->output_row_header($lang->lkt_groups);
$form_container->output_row_header($lang->lkt_forums);
$form_container->output_row_header($lang->lkt_maxlinks);
$form_container->output_row_header($lang->lkt_days);
$form_container->output_row_header($lang->lkt_action);

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
		$cells = lkt_gen_linklim_cells($row, $form, $forum_cache, $groups_cache);
		$form_container->output_cell($cells[0]);
		$form_container->output_cell($cells[1]);
		$form_container->output_cell($cells[2]);
		$form_container->output_cell($cells[3]);
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

$lang_strings = array('lkt_save', 'lkt_edit', 'lkt_cancel', 'lkt_delete');
$js_lang_strings = '';
foreach ($lang_strings as $key) {
	$js_lang_strings .= "lang.{$key} = '".addslashes($lang->$key)."';\n";
}

echo <<<EOF
<script type="text/javascript">
// <![CDATA[
{$js_lang_strings}

var btn_del_click_handler = function(event) {
	var btn_del = this;
	$(this).prop('disabled', true).css('background', 'url(../images/spinner.gif) center no-repeat');
	$.post('index.php?module=forum-linklimits', {my_post_key: '{$mybb->post_code}', ajax_del_id: this.name.substr(4)}, function(data) {
		if (data.outcome == 'OK') {
			$(btn_del).parent().parent().remove();
		} else	$.jGrowl(result.errors, {theme:'jgrowl_error'});
		$(btn_del).prop('disabled', false).css('background', '');
	});
	event.preventDefault();
};

var btn_edit_click_handler = function(event) {
	if (this.name.substr(0, 5) == 'save_') {
		var save_id = this.name.substr(5);

		event.preventDefault();

		var form = $('form')[0];
		var data = new FormData(form);
		data.append('ajax_save_id', save_id);
		var btn_edit = this;
		$(this).prop('disabled', true).css('background', 'url(../images/spinner.gif) center no-repeat');

		$.ajax({
			type: "POST",
			enctype: 'multipart/form-data',
			url: 'index.php?module=forum-linklimits',
			data: data,
			processData: false,
			contentType: false,
			cache: false,
			timeout: 800000,
			success: function (result) {
				if (result && result.outcome == 'OK') {
					$(btn_edit).next().remove(); // Remove the Cancel button
					btn_edit.value = lang.lkt_edit;
					btn_edit.name = 'edit_' + save_id;
					$(btn_edit).parent().parent().children().each(function(i) {
						if (i < 4) {
							$(this).html(result[i]);
						}
					});
				} else	$.jGrowl(result.errors, {theme:'jgrowl_error'});
				$(btn_edit).prop('disabled', false).css('background', '');
			},
			error: function(error) {
				$.jGrowl(error.message, {theme:'jgrowl_error'});
				$(btn_edit).prop('disabled', false).css('background', '');
			}
		});
	} else {
		var edit_id = this.name.substr(5);
		$(this).parent().parent().children().each(function(i) {
			if (i < 4) {
				$(this).children().each(function(j) {
					if (j == 0) {
						$(this).css('display', 'none');
					} else if (j == 1) {
						$(this).css('display', 'inline-block');
					}
				});
			}
		});
		this.value = lang.lkt_save;
		this.name = 'save_' + edit_id;
		var btn_cancel = $('<input/>',
		{
			type: 'submit',
			value: lang.lkt_cancel,
			class: 'submit_button',
			click: function(event) {
				$(this).parent().parent().children().each(function(i) {
					if (i < 4) {
						$(this).children().each(function(j) {
							if (j == 0) {
								$(this).css('display', 'inline-block');
							} else if (j == 1) {
								$(this).css('display', 'none');
							}
						});
					}
				});
				$(this).prev()[0].value = lang.lkt_edit;
				$(this).prev()[0].name = 'edit_' + edit_id;
				event.preventDefault();
				$(this).remove();
			}
		});
		$(this).after(btn_cancel);
	}
	event.preventDefault();
};

$(function() {
	$('input[type="submit"]').each(function(i) {
		if (this.name == 'add') {
			$(this).on('click', function(event) {
				event.preventDefault();

				var form = $('form')[0];
				var data = new FormData(form);
				data.append('ajax_add', '1');
				var btn_add = this;
				$(this).prop('disabled', true).css('background', 'url(../images/spinner.gif) center no-repeat');

				$.ajax({
					type: "POST",
					enctype: 'multipart/form-data',
					url: 'index.php?module=forum-linklimits',
					data: data,
					processData: false,
					contentType: false,
					cache: false,
					timeout: 800000,
					success: function (result) {
						$(btn_add).prop('disabled', false).css('background', '');
						if (result && result.outcome == 'OK') {
							var row_ = $(btn_add).parent().parent();
							row_.after(row_.clone(true));
							$(btn_add).parent().parent().children().each(function(i) {
								if (i < 4) {
									$(this).html(result[i]);
								}
							});
							btn_add.value = lang.lkt_edit;
							console.debug(result.id);
							btn_add.name = 'edit_' + result.id;
							$(btn_add).off('click');
							$(btn_add).on('click', btn_edit_click_handler);
							var btn_del = $('<input/>',
							{
								type: 'submit',
								name: 'del_' + result.id,
								value: lang.lkt_delete,
								class: 'submit_button',
								click: btn_del_click_handler
							});
							$(btn_add).after(btn_del);
						} else	$.jGrowl(result.errors, {theme:'jgrowl_error'});
					},
					error: function(error) {
						$.jGrowl(error.message, {theme:'jgrowl_error'});
						$(btn_add).prop('disabled', false).css('background', '');
					}
				});
			});
		} else if (this.name.substr(0, 4) == 'del_') {
			$(this).on('click', btn_del_click_handler);
		} else if (this.name.substr(0, 5) == 'edit_') {
			$(this).on('click', btn_edit_click_handler);
		}
	});
});
// ]]>
</script>
EOF;

$page->output_footer();

function lkt_gen_linklim_cells($fields, $form, $forum_cache, $groups_cache) {
	$forum_links = $hidden_fids = array();
	foreach (explode(',', $fields['fids']) as $ll_fid) {
		$ll_fid = (int)trim($ll_fid);
		$hidden_fids[] = $ll_fid;
		if (isset($forum_cache[$ll_fid]['name'])) {
			$forum_links[] = '<a href="'.get_forum_link($ll_fid).'">'.htmlspecialchars_uni($forum_cache[$ll_fid]['name']).'</a>';
		}
	}
	$group_links = $hidden_gids = array();
	foreach (explode(',', $fields['gids']) as $ll_gid) {
		$ll_gid = (int)trim($ll_gid);
		$hidden_gids[] = $ll_gid;
		if (isset($groups_cache[$ll_gid])) {
			$group_links[] = format_name(htmlspecialchars_uni($groups_cache[$ll_gid]['title']), $ll_gid);
		}
	}

	return array(
		'<div>'.implode('<br />', $group_links).'</div>'.
		'<div style="display: none;">'.$form->generate_group_select("ll_gids_{$fields['llid']}[]", $hidden_gids, array('multiple' => true, 'size' => 5)).'</div>',

		'<div>'.implode('<br />', $forum_links).'</div>'.
		'<div style="display: none;">'.$form->generate_forum_select("ll_fids_{$fields['llid']}[]", $hidden_fids, array('multiple' => true, 'size' => 5)).'</div>',

		'<div>'.$fields['maxlinks'].'</div>'.
		'<div style="display: none;">'.$form->generate_text_box("maxlinks_{$fields['llid']}", $fields['maxlinks'], array('style' => 'max-width: 3em;')).'</div',

		'<div>'.$fields['days'].'</div>'.
		'<div style="display: none;">'.$form->generate_text_box("days_{$fields['llid']}", $fields['days'], array('style' => 'max-width: 3em;')).'</div'
	);
}

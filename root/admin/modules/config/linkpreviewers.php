<?php

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $cache, $db;

$lrs_plugins = $cache->read('lrs_plugins');
$inst_previewers = !empty($lrs_plugins[C_LKT]['installed_link_previewers'])
                    ? $lrs_plugins[C_LKT]['installed_link_previewers']
                    : array();
$present_previewers = array();
foreach (lkt_get_linkpreviewer_classnames() as $type => $classnames) {
	$present_previewers = array_merge($present_previewers, $classnames);
}

$page->add_breadcrumb_item($lang->lkt_linkpreviewers, "index.php?module=config-linkpreviewers");

$page->output_header($lang->lkt_linkpreviewers);

if ($mybb->get_input('do_update')) {
	$installall = !empty($mybb->get_input('installall'));
	$just_installed = array();
	foreach ($present_previewers as $present_previewer) {
		if (!is_array($inst_previewers[$present_previewer])) {
			$inst_previewers[$present_previewer] = array();
		}
		$input_arr = $mybb->get_input($present_previewer, MyBB::INPUT_ARRAY);
		$install_tpl = (is_array($input_arr) && !empty($input_arr[0])) ? $present_previewer : false;
		$enabled     =  is_array($input_arr) && !empty($input_arr[1]);
		$inst_previewers[$present_previewer]['enabled'] = $enabled ? true : false;
		if (($installall || $install_tpl == $present_previewer) && empty($inst_previewers[$present_previewer]['tpl_installed'])) {
			$previewerobj = $present_previewer::get_instance();
			if ($tplname = $previewerobj->get_template_name(/*$ret_empty_if_default*/true)) {
				$fields = array(
					'title'    => $db->escape_string($tplname),
					'template' => $db->escape_string($previewerobj->get_template_raw()),
					'sid'      => '-2',
					'version'  => '1',
					'dateline' => TIME_NOW
				);
				$db->insert_query('templates', $fields);
				$inst_previewers[$present_previewer]['tpl_installed'] = $previewerobj->get_version();
				$just_installed[$present_previewer] = true;
			}
		}
	}
	if (!$installall) {
		foreach ($inst_previewers as $inst_previewer => $arr) {
			$input_arr = $mybb->get_input($inst_previewer, MyBB::INPUT_ARRAY);
			if (!$just_installed[$inst_previewer] && is_array($input_arr) && $input_arr[0] != $inst_previewer) {
				$tpl_nm = lkt_mk_tpl_nm_frm_classnm($inst_previewer);
				$db->delete_query('templates', "title = '$tpl_nm'");
				$inst_previewers[$inst_previewer]['tpl_installed'] = '';
			}
		}
	}
	if (empty($lrs_plugins)) {
		$lrs_plugins = [];
	}
	if (empty($lrs_plugins[C_LKT])) {
		$lrs_plugins[C_LKT] = [];
	}
	$lrs_plugins[C_LKT]['installed_link_previewers'] = $inst_previewers;
	$cache->update('lrs_plugins', $lrs_plugins);
}

if ($present_previewers || $inst_previewers) {
	$form = new Form('index.php?module=config-linkpreviewers', 'post');
	$form_container = new FormContainer($lang->lkt_linkpreviewers);
	$form_container->output_row_header($lang->lkt_previewer_name);
	$form_container->output_row_header($lang->lkt_template_installed);
	$form_container->output_row_header($lang->lkt_previewer_enabled);
	foreach ($present_previewers as $present_previewer) {
		$is_tpl_installed = !empty($inst_previewers[$present_previewer]['tpl_installed']);
		$is_enabled       = !empty($inst_previewers[$present_previewer]['enabled']);
		$previewerobj = $present_previewer::get_instance();
		$friendly_name = $previewerobj->get_friendly_name();
		$form_container->output_cell($friendly_name);
		$form_container->output_cell($form->generate_check_box($present_previewer.'[0]', $present_previewer, '', array('checked' => $is_tpl_installed)));
		$form_container->output_cell($form->generate_check_box($present_previewer.'[1]', $present_previewer, '', array('checked' => $is_enabled      )));
		$form_container->construct_row();
	}
	foreach (array_diff(array_keys($inst_previewers), $present_previewers) as $msng_previewer) {
		$mfname = $lang->sprintf($lang->lkt_missing_hlp_name, $msng_previewer);
		$form_container->output_cell($mfname);
		$form_container->output_cell($form->generate_check_box($msng_previewer.'[0]', $msng_previewer, '', array('checked' => true)));
		$form_container->output_cell($form->generate_check_box($msng_previewer.'[1]', $msng_previewer, '', array('checked' => false)));
		$form_container->construct_row();
	}
	$form_container->end();
	$form->output_submit_wrapper((array)$form->generate_submit_button($lang->lkt_update, array('name' => 'do_update')));
	$form->end();
	$page->output_footer();
}

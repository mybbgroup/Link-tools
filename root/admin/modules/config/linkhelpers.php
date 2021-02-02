<?php

require_once MYBB_ROOT.'global.php';

global $cache, $db;

$lrs_plugins = $cache->read('lrs_plugins');
$inst_helpers = !empty($lrs_plugins[C_LKT]['installed_link_helpers'])
                  ? $lrs_plugins[C_LKT]['installed_link_helpers']
                  : array();
$present_helpers = array();
foreach (lkt_get_linkhelper_classnames() as $type => $classnames) {
	$present_helpers = array_merge($present_helpers, $classnames);
}

$page->add_breadcrumb_item($lang->lkt_linkhelpers, "index.php?module=config-linkhelpers");

$page->output_header($lang->lkt_linkhelpers);

if ($mybb->get_input('do_update')) {
	$installall = !empty($mybb->get_input('installall'));
	$just_installed = array();
	foreach ($present_helpers as $present_helper) {
		if (!is_array($inst_helpers[$present_helper])) {
			$inst_helpers[$present_helper] = array();
		}
		$input_arr = $mybb->get_input($present_helper, MyBB::INPUT_ARRAY);
		$install_tpl = (is_array($input_arr) && !empty($input_arr[0])) ? $present_helper : false;
		$enabled     =  is_array($input_arr) && !empty($input_arr[1]);
		$inst_helpers[$present_helper]['enabled'] = $enabled ? true : false;
		if (($installall || $install_tpl == $present_helper) && empty($inst_helpers[$present_helper]['tpl_installed'])) {
			$helperobj = $present_helper::get_instance();
			if ($tplname = $helperobj->get_template_name(/*$ret_empty_if_default*/true)) {
				$fields = array(
					'title'    => $db->escape_string($tplname),
					'template' => $db->escape_string($helperobj->get_template_raw()),
					'sid'      => '-2',
					'version'  => '1',
					'dateline' => TIME_NOW
				);
				$db->insert_query('templates', $fields);
				$inst_helpers[$present_helper]['tpl_installed'] = $present_helper::get_version();
				$just_installed[$present_helper] = true;
			}
		}
	}
	if (!$installall) {
		foreach ($inst_helpers as $inst_helper => $arr) {
			$input_arr = $mybb->get_input($inst_helper, MyBB::INPUT_ARRAY);
			if (!$just_installed[$inst_helper] && is_array($input_arr) && $input_arr[0] != $inst_helper) {
				$tpl_nm = LinkHelper::mk_tpl_nm_frm_classnm($inst_helper);
				echo $tpl_nm;
				$db->delete_query('templates', "title = '$tpl_nm'");
				$inst_helpers[$inst_helper]['tpl_installed'] = '';
			}
		}
	}
	$lrs_plugins[C_LKT]['installed_link_helpers'] = $inst_helpers;
	$cache->update('lrs_plugins', $lrs_plugins);
}

if ($present_helpers || $inst_helpers) {
	$form = new Form('index.php?module=config-linkhelpers', 'post');
	$form_container = new FormContainer($lang->lkt_linkhelpers);
	$form_container->output_row_header($lang->lkt_helper_name);
	$form_container->output_row_header($lang->lkt_template_installed);
	$form_container->output_row_header($lang->lkt_helper_enabled);
	foreach ($present_helpers as $present_helper) {
		$is_tpl_installed = !empty($inst_helpers[$present_helper]['tpl_installed']);
		$is_enabled       = !empty($inst_helpers[$present_helper]['enabled']);
		$helperobj = $present_helper::get_instance();
		$friendly_name = $helperobj->get_friendly_name();
		$form_container->output_cell($friendly_name);
		$form_container->output_cell($form->generate_check_box($present_helper.'[0]', $present_helper, '', array('checked' => $is_tpl_installed)));
		$form_container->output_cell($form->generate_check_box($present_helper.'[1]', $present_helper, '', array('checked' => $is_enabled      )));
		$form_container->construct_row();
	}
	foreach (array_diff(array_keys($inst_helpers), $present_helpers) as $msng_helper) {
		$mfname = $lang->sprintf($lang->lkt_missing_hlp_name, $msng_helper);
		$form_container->output_cell($mfname);
		$form_container->output_cell($form->generate_check_box($msng_helper.'[0]', $msng_helper, '', array('checked' => true)));
		$form_container->output_cell($form->generate_check_box($msng_helper.'[1]', $msng_helper, '', array('checked' => false)));
		$form_container->construct_row();
	}
	$form_container->end();
	$form->output_submit_wrapper((array)$form->generate_submit_button($lang->lkt_update, array('name' => 'do_update')));
	$form->end();
	$page->output_footer();
}

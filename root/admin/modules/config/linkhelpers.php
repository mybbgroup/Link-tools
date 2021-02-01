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
$present_helpers = array_diff($present_helpers, array('LinkHelperDefault'));

$page->add_breadcrumb_item($lang->lkt_linkhelpers, "index.php?module=config-linkhelpers");

$page->output_header($lang->lkt_linkhelpers);

if ($mybb->get_input('do_installuninstall')) {
	$inst_helpers_new = $inst_helpers;
	$installall = !empty($mybb->get_input('installall'));
	foreach ($present_helpers as $present_helper) {
		if (($installall || $mybb->get_input($present_helper) == $present_helper) && !isset($inst_helpers[$present_helper])) {
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
				$inst_helpers_new[$present_helper] = $present_helper::get_version();
			}
		}
	}
	if (!$installall) {
		foreach ($inst_helpers as $inst_helper => $version) {
			if ($mybb->get_input($inst_helper) != $inst_helper) {
				$tpl_nm = LinkHelper::mk_tpl_nm_frm_classnm($inst_helper);
				$db->delete_query('templates', "title = '$tpl_nm'");
				unset($inst_helpers_new[$inst_helper]);
			}
		}
	}
	$inst_helpers = $inst_helpers_new;
	$lrs_plugins[C_LKT]['installed_link_helpers'] = $inst_helpers;
	$cache->update('lrs_plugins', $lrs_plugins);
}

if ($present_helpers || $inst_helpers) {
	$form = new Form('index.php?module=config-linkhelpers', 'post');
	$form_container = new FormContainer($lang->lkt_linkhelpers);
	$form_container->output_cell($lang->lkt_template_installed);
	$form_container->construct_row();
	foreach ($present_helpers as $present_helper) {
		$is_installed = isset($inst_helpers[$present_helper]);
		$helperobj = $present_helper::get_instance();
		$friendly_name = $helperobj->get_friendly_name();
		$form_container->output_cell($form->generate_check_box($present_helper, $present_helper, $friendly_name, array('checked' => $is_installed)));
		$form_container->construct_row();
	}
	foreach (array_diff(array_keys($inst_helpers), $present_helpers) as $msng_helper) {
		$mfname = $lang->sprintf($lang->lkt_missing_hlp_name, $msng_helper);
		$form_container->output_cell($form->generate_check_box($msng_helper, $msng_helper, $mfname, array('checked' => 'checked')));
		$form_container->construct_row();
	}
	$form_container->output_cell($form->generate_submit_button($lang->lkt_instuninst_lhelpertpl, array('name' => 'do_installuninstall')));
	$form_container->construct_row();
	$form_container->end();
	$form->end();
	$page->output_footer();
}

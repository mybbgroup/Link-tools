<?php

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $cache, $db;

$lrs_plugins = $cache->read('lrs_plugins');
$inst_helpers = !empty($lrs_plugins[C_LKT]['installed_link_helpers'])
                  ? $lrs_plugins[C_LKT]['installed_link_helpers']
                  : array();
$all_helpers = array_keys($inst_helpers);
foreach (lkt_get_linkhelper_classnames() as $type => $classnames) {
	$all_helpers = array_merge($all_helpers, $classnames);
}
$all_helpers = array_unique($all_helpers);
asort($all_helpers);

$page->add_breadcrumb_item($lang->lkt_linkhelpers_cache_invalidation, "index.php?module=tools-linkhelpers");

$page->output_header($lang->lkt_linkhelpers_cache_invalidation);

if ($mybb->get_input('do_invalidation') && ($hlp_lc = $mybb->get_input('helper'))) {
	$purged_helpers = array();
	foreach ($all_helpers as $helper) {
		if (('linkhelper'.$hlp_lc == strtolower($helper) || $hlp_lc == '[all]') && $helper::get_instance()->get_should_cache_preview()) {
			$purged_helpers[] = $helper;
			$db->update_query('url_previews', array('valid' => '0'), "helper_class_name='".$db->escape_string($helper)."'");
		}
	}

	if ($purged_helpers) {
		$helper_list = '';
		foreach ($purged_helpers as $helper) {
			if ($helper_list) $helper_list .= $lang->comma;
			$helper_list .= htmlspecialchars_uni($helper::get_instance()->get_friendly_name());
		}
		$msg = $lang->sprintf($lang->lkt_linkhelpers_invalidated, $helper_list);
		echo '<p style="color: #00b200; font-weight: bold; font-size: 10px; margin-bottom: 10px;">'.$msg.'</p>';
	}
}

$table = new Table;

foreach ($all_helpers as $helper) {
	$helperobj = $helper::get_instance();
	if ($helperobj->get_should_cache_preview()) {
		$friendly_name = $helperobj->get_friendly_name();
		$hlp_lc = strtolower(preg_replace('(^LinkHelper)', '', $helper));
		$form = new Form('index.php?module=tools-linkhelpers&amp;action=do_invalidation&amp;helper='.htmlspecialchars_uni($hlp_lc), 'post', '', false, '', true);
		$table->construct_cell($form->construct_return.$form->generate_submit_button($friendly_name, array('name' => 'do_invalidation')).$form->end(true), array('class' => 'align_center'));
		$table->construct_row();
	}
}
$form = new Form('index.php?module=tools-linkhelpers&amp;action=do_invalidation&amp;helper=[all]', 'post', '', false, '', true);
$table->construct_cell($form->construct_return.$form->generate_submit_button($lang->lkt_all, array('name' => 'do_invalidation')).$form->end(true), array('class' => 'align_center'));
$table->construct_row();

$table->output($lang->lkt_linkhelpers_cache_invalidation_msg);

if ($mybb->get_input('url_return')) {
	echo '<a href="'.htmlspecialchars_uni($mybb->get_input('url_return')).'">'.$lang->lkt_go_back_link_txt.'</a>';
}

$page->output_footer();

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
$all_previewers = array_keys($inst_previewers);
foreach (lkt_get_linkpreviewer_classnames() as $type => $classnames) {
	$all_previewers = array_merge($all_previewers, $classnames);
}
$all_previewers = array_unique($all_previewers);
asort($all_previewers);

$page->add_breadcrumb_item($lang->lkt_linkpreviewers_cache_invalidation, "index.php?module=tools-linkpreviewers");

$page->output_header($lang->lkt_linkpreviewers_cache_invalidation);

if ($mybb->get_input('do_invalidation') && ($hlp_lc = $mybb->get_input('previewer'))) {
	$purged_previewers = array();
	foreach ($all_previewers as $previewer) {
		if (('linkpreviewer'.$hlp_lc == strtolower($previewer) || $hlp_lc == '[all]') && $previewer::get_instance()->get_should_cache_preview()) {
			$purged_previewers[] = $previewer;
			$db->update_query('url_previews', array('valid' => '0'), "previewer_class_name='".$db->escape_string($previewer)."'");
		}
	}

	if ($purged_previewers) {
		$previewer_list = '';
		foreach ($purged_previewers as $previewer) {
			if ($previewer_list) $previewer_list .= $lang->comma;
			$previewer_list .= htmlspecialchars_uni($previewer::get_instance()->get_friendly_name());
		}
		$msg = $lang->sprintf($lang->lkt_linkpreviewers_invalidated, $previewer_list);
		echo '<p style="color: #00b200; font-weight: bold; font-size: 10px; margin-bottom: 10px;">'.$msg.'</p>';
	}
}

$table = new Table;

foreach ($all_previewers as $previewer) {
	$previewerobj = $previewer::get_instance();
	if ($previewerobj->get_should_cache_preview()) {
		$friendly_name = $previewerobj->get_friendly_name();
		$hlp_lc = strtolower(preg_replace('(^LinkPreviewer)', '', $previewer));
		$form = new Form('index.php?module=tools-linkpreviewers&amp;action=do_invalidation&amp;previewer='.htmlspecialchars_uni($hlp_lc), 'post', '', false, '', true);
		$table->construct_cell($form->construct_return.$form->generate_submit_button($friendly_name, array('name' => 'do_invalidation')).$form->end(true), array('class' => 'align_center'));
		$table->construct_row();
	}
}
$form = new Form('index.php?module=tools-linkpreviewers&amp;action=do_invalidation&amp;previewer=[all]', 'post', '', false, '', true);
$table->construct_cell($form->construct_return.$form->generate_submit_button($lang->lkt_all, array('name' => 'do_invalidation')).$form->end(true), array('class' => 'align_center'));
$table->construct_row();

$table->output($lang->lkt_linkpreviewers_cache_invalidation_msg);

if ($mybb->get_input('url_return')) {
	echo '<a href="'.htmlspecialchars_uni($mybb->get_input('url_return')).'">'.$lang->lkt_go_back_link_txt.'</a>';
}

$page->output_footer();

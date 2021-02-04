<?php

/**
 *  Part of the Link Tools plugin for MyBB 1.8.
 *  Copyright (C) 2020 Laird Shaw
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

# Should semantically match the equivalent variable in ../../jscripts/linktools.js
const lkt_valid_schemes = array('http', 'https', 'ftp', 'sftp', '');

const lkt_default_rebuild_links_items_per_page = 500;
const lkt_default_rebuild_term_items_per_page = 150;
const lkt_default_rebuild_renorm_items_per_page = 500;
const lkt_default_rebuild_linkpreviews_items_per_page = 150;

const lkt_max_matching_posts = 10;

const lkt_urls_limit_for_get_and_store_terms = 2000;

// 2083 was chosen because it is the maximum size URL that Internet Explorer will accept
// (other major browsers have higher limits).
const lkt_max_url_len = 2083;

const lkt_max_helper_class_name_len = 100;
const lkt_max_helper_class_vers_len =  15;

const lkt_use_head_method          = true; // Overridden by the below two being true though, so effectively false.
const lkt_check_for_html_redirects = true;
const lkt_check_for_canonical_tags = true;

const lkt_rehit_delay_in_secs = 3;
const lkt_max_allowable_redirects_for_a_url = 25;
const lkt_max_allowable_redirect_resolution_runtime_secs = 60;
const lkt_curl_timeout = 10;
const lkt_max_url_lock_time = 120;

const lkt_term_tries_secs = array(
	0,             // First attempt has no limits.
	15*60,         // 15 minutes
	60*60,         // 1 hour
	24*60*60,      // 1 day
	7*24*60*60,    // 1 week
	28*7*24*60*60, // 4 weeks
);

const lkt_preview_regen_min_wait_secs = 30;

/**
 * @todo Eliminate broken urls in [url] and [video] tags - don't store them in the DB.
 * @todo Maybe add a global and/or per-user setting to disable checking for matching non-opening posts.
 */

$plugins->add_hook('datahandler_post_insert_thread'         , 'lkt_hookin__datahandler_post_insert_thread'         );
$plugins->add_hook('newthread_start'                        , 'lkt_hookin__newthread_start'                        );
$plugins->add_hook('datahandler_post_insert_post_end'       , 'lkt_hookin__datahandler_post_insert_post_end'       );
$plugins->add_hook('datahandler_post_insert_thread_end'     , 'lkt_hookin__datahandler_post_insert_thread_end'     );
$plugins->add_hook('datahandler_post_update'                , 'lkt_hookin__datahandler_post_update'                );
$plugins->add_hook('datahandler_post_update_end'            , 'lkt_hookin__datahandler_post_update_end'            );
$plugins->add_hook('class_moderation_delete_post'           , 'lkt_hookin__class_moderation_delete_post'           );
$plugins->add_hook('class_moderation_delete_thread_start'   , 'lkt_hookin__common__class_moderation_delete_thread' );
$plugins->add_hook('class_moderation_delete_thread'         , 'lkt_hookin__common__class_moderation_delete_thread' );
$plugins->add_hook('admin_tools_recount_rebuild_output_list', 'lkt_hookin__admin_tools_recount_rebuild_output_list');
$plugins->add_hook('admin_tools_recount_rebuild'            , 'lkt_hookin__admin_tools_recount_rebuild'            );
$plugins->add_hook('global_start'                           , 'lkt_hookin__global_start'                           );
$plugins->add_hook('search_do_search_start'                 , 'lkt_hookin__search_do_search_start'                 );
$plugins->add_hook('admin_config_plugins_activate_commit'   , 'lkt_hookin__admin_config_plugins_activate_commit'   );
$plugins->add_hook('xmlhttp'                                , 'lkt_hookin__xmlhttp'                                );
$plugins->add_hook('admin_config_settings_change'           , 'lkt_hookin__admin_config_settings_change'           );
$plugins->add_hook('admin_page_output_footer'               , 'lkt_hookin__admin_page_output_footer'               );
$plugins->add_hook('postbit'                                , 'lkt_hookin__postbit'                                );
$plugins->add_hook('parse_message_start'                    , 'lkt_hookin__parse_message_start'                    );
$plugins->add_hook('xmlhttp_update_post'                    , 'lkt_hookin__xmlhttp_update_post'                    );
$plugins->add_hook('admin_config_menu'                      , 'lkt_hookin__admin_config_menu'                      );
$plugins->add_hook('admin_config_action_handler'            , 'lkt_hookin__admin_config_action_handler'            );
$plugins->add_hook('editpost_action_start'                  , 'lkt_hookin__editpost_action_start'                  );
$plugins->add_hook('editpost_do_editpost_end'               , 'lkt_hookin__editpost_do_editpost_end'               );

function lkt_hookin__global_start() {
	if (defined('THIS_SCRIPT')) {
		global $templatelist;

		if (THIS_SCRIPT == 'newthread.php') {
			if (isset($templatelist)) $templatelist .= ',';
			$templatelist .= 'linktools_div,linktools_op_post_div,linktools_non_op_post_div,linktools_matching_url_item,linktools_matching_post,linktools_review_buttons,linktools_toggle_button,linktools_review_page,linktools_matching_posts_warning_div';
		} else if (THIS_SCRIPT == 'showthread.php') {
			if (isset($templatelist)) $templatelist .= ',';
			$templatelist .= 'linktools_preview_regen_link,linktools_preview_regen_container';
		} else if (THIS_SCRIPT == 'lkt-regen-preview.php') {
			if (isset($templatelist)) $templatelist .= ',';
			$templatelist .= 'linktools_preview_regen_page,linktools_regen_page_return_link';
		} else if (THIS_SCRIPT == 'editpage.php') {
			if (isset($templatelist)) $templatelist .= ',';
			$templatelist .= 'linktools_cbxdisablelinkpreview';
		}
	}
}

define('C_LKT', str_replace('.php', '', basename(__FILE__)));

function linktools_info() {
	global $lang, $db, $mybb, $plugins_cache, $cache, $admin_session;

	if (!isset($lang->linktools)) {
		$lang->load(C_LKT);
	}

	$ret = array(
		'name'          => $lang->lkt_name,
		'description'   => $lang->lkt_desc,
		'website'       => 'https://mybb.group/Thread-Link-Tools',
		'author'        => 'Laird as a member of the unofficial MyBB Group',
		'authorsite'    => 'https://mybb.group/User-Laird',
		'version'       => '1.0.1-dev',
		// Constructed by converting each digit of 'version' above into two digits (zero-padded if necessary),
		// then concatenating them, then removing any leading zero(es) to avoid the value being interpreted as octal.
		'version_code'  => '10001',
		'guid'          => '',
		'codename'      => C_LKT,
		'compatibility' => '18*'
	);

	if (linktools_is_installed() && is_array($plugins_cache) && is_array($plugins_cache['active']) && $plugins_cache['active'][C_LKT]) {
		$desc = '';
		$desc .= '<ul>'.PHP_EOL;

		if (!empty($admin_session['data']['lkt_plugin_info_upgrade_message'])) {
			$msg = $admin_session['data']['lkt_plugin_info_upgrade_message'].' '.$msg;
			update_admin_session('lkt_plugin_info_upgrade_message', '');
			$desc .= "	<li style=\"list-style-image: url(styles/default/images/icons/success.png)\"><div class=\"success\">{$msg}</div></li>".PHP_EOL;
		}

		$res = $db->simple_select('posts', 'count(*) AS cnt', 'lkt_got_urls = 0');
		$cnt_posts_unextracted = $db->fetch_array($res)['cnt'];
		$res = $db->simple_select('posts', 'count(*) AS cnt');
		$cnt_posts_tot = $db->fetch_array($res)['cnt'];

		$res = $db->simple_select('urls', 'count(*) AS cnt');
		$cnt_links_tot = $db->fetch_array($res)['cnt'];
		$res = $db->simple_select('urls', 'count(*) AS cnt', 'got_term = FALSE');
		$cnt_links_unresolved = $db->fetch_array($res)['cnt'];
		if ($cnt_links_unresolved > 0) {
			$res = $db->simple_select('urls', 'count(*) AS cnt', 'got_term = FALSE AND term_tries > 0');
			$cnt_links_unresolved_tried = $db->fetch_array($res)['cnt'];
			$res = $db->simple_select('urls', 'count(*) AS cnt', 'got_term = FALSE AND term_tries >= '.count(lkt_term_tries_secs));
			$cnt_given_up = $db->fetch_array($res)['cnt'];
			$res = $db->simple_select('urls', 'count(*) as cnt', lkt_get_sql_conds_for_ltt());
			$cnt_eligible = $db->fetch_array($res)['cnt'];
		}

		$desc .= '	<li style="list-style-image: url(styles/default/images/icons/';
		if ($cnt_posts_unextracted == 0) {
			$desc .= 'success.png)">'.$lang->sprintf($lang->lkt_all_links_extracted, number_format($cnt_links_tot), number_format($cnt_posts_tot));
		} else {
			$desc .= 'warning.png)">'.$lang->sprintf($lang->lkt_x_of_y_posts_unextracted, number_format($cnt_posts_unextracted), number_format($cnt_posts_tot));
			if ($cnt_posts_unextracted > 0) {
				$desc .= $lang->sprintf($lang->lkt_to_extract_links_click_here, $cnt_posts_unextracted, '<form method="post" action="'.$mybb->settings['bburl'].'/admin/index.php?module=tools-recount_rebuild" style="display: inline;"><input type="hidden" name="page" value="2" /><input type="hidden" name="my_post_key" value="'.generate_post_check().'" /><input type="submit" name="do_rebuild_links" value="', '" style="background: none; border: none; color: #0066ff; text-decoration: underline; cursor: pointer; display: inline; margin: 0; padding: 0; font-size: inherit;"/></form>');
			}
		}
		$desc .= '</li>'.PHP_EOL;

		$desc .= '	<li style="list-style-image: url(styles/default/images/icons/'.($cnt_links_unresolved == 0 ? 'success' : ($cnt_eligible == 0 ? 'no_change' : 'warning')).'.png)">';
		if ($cnt_links_unresolved == 0) {
			$desc .= $lang->lkt_all_term_links_resolved;
		} else {
			$desc .= $lang->sprintf($lang->lkt_x_of_y_links_unresolved, number_format($cnt_links_unresolved), number_format($cnt_links_tot));
			if ($cnt_links_unresolved_tried > 0    ) {
				if ($cnt_links_unresolved == $cnt_links_unresolved_tried) {
					$desc .= $lang->sprintf($lang->lkt_attempts_unsuccess_made_all_links, number_format($cnt_links_unresolved_tried));
				} else {
					$desc .= $lang->sprintf($lang->lkt_attempts_unsuccess_made_x_links, number_format($cnt_links_unresolved_tried));
				}
				if ($cnt_given_up) $desc .= $lang->sprintf($lang->lkt_given_up_on_x_links, number_format($cnt_given_up));
			}
			if ($cnt_eligible > 0) {
				$desc .= $lang->sprintf($lang->lkt_to_resolve_links_click_here, number_format($cnt_eligible), '<form method="post" action="'.$mybb->settings['bburl'].'/admin/index.php?module=tools-recount_rebuild" style="display: inline;"><input type="hidden" name="page" value="2" /><input type="hidden" name="my_post_key" value="'.generate_post_check().'" /><input type="submit" name="do_rebuild_terms" value="', '" style="background: none; border: none; color: #0066ff; text-decoration: underline; cursor: pointer; display: inline; margin: 0; padding: 0; font-size: inherit;"/></form>');
			} else {
				$desc .= $lang->lkt_no_links_eligible_for_resolution;
			}
		}
		$desc .= '	</li>'.PHP_EOL;

		$lrs_plugins = $cache->read('lrs_plugins');
		$inst_helpers = !empty($lrs_plugins[C_LKT]['installed_link_helpers'])
				? $lrs_plugins[C_LKT]['installed_link_helpers']
				: array();

		$present_helpers = array();
		foreach (lkt_get_linkhelper_classnames() as $type => $classnames) {
			$present_helpers = array_merge($present_helpers, $classnames);
		}
		$present_helpers = array_diff(array_unique($present_helpers), array('LinkHelperDefault'));

		$inst_hlp_tpl_miss_cnt = 0;
		foreach ($present_helpers as $present_helper) {
			if (empty($inst_helpers[$present_helper]['tpl_installed'])) {
				$helperobj = $present_helper::get_instance();
				if ($helperobj->get_template_name(/*$ret_empty_if_default*/true)) {
					$inst_hlp_tpl_miss_cnt++;
				}
			}
		}

		if ($inst_hlp_tpl_miss_cnt) {
			$lang_helper_or_helpers = $inst_hlp_tpl_miss_cnt == 1 ? $lang->lkt_one_helper : $lang->sprintf($lang->lkt_helpers, $inst_hlp_tpl_miss_cnt);
			$desc .= '	<li style="list-style-image: url(styles/default/images/icons/warning.png); color: red;">'.$lang->sprintf($lang->lkt_need_inst_helpers, $lang_helper_or_helpers, '<form method="post" action="'.$mybb->settings['bburl'].'/admin/index.php?module=config-linkhelpers" style="display: inline;"><input type="hidden" name="installall" value="1" /><input type="hidden" name="my_post_key" value="'.generate_post_check().'" /><input type="submit" name="do_update" value="', '" style="background: none; border: none; color: #0066ff; text-decoration: underline; cursor: pointer; display: inline; margin: 0; padding: 0; font-size: inherit;" /></form>').'</li>';
		}

		$desc .= '</ul>'.PHP_EOL;

		$ret['description'] .= $desc;
	}

	return $ret;
}

function linktools_install() {
	$info = linktools_info();
	lkt_install_or_upgrade(false, $info['version_code']);
}

function lkt_install_or_upgrade($from_version, $to_version) {
	global $db;

	if (!$db->table_exists('urls')) {
		// utf8_bin collation was chosen for the varchar columns
		// so that SELECTs are case-sensitive, given that everything
		// after the server name in URLs is case-sensitive.
		$db->query('
CREATE TABLE '.TABLE_PREFIX.'urls (
  urlid         int unsigned NOT NULL auto_increment,
  url           varchar('.lkt_max_url_len.') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  url_norm      varchar('.lkt_max_url_len.') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  url_term      varchar('.lkt_max_url_len.') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  url_term_norm varchar('.lkt_max_url_len.') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  got_term      boolean       NOT NULL DEFAULT FALSE,
  term_tries    tinyint unsigned NOT NULL DEFAULT 0,
  last_term_try int unsigned  NOT NULL DEFAULT 0,
  lock_time     int unsigned  NOT NULL DEFAULT 0,
  KEY           url           (url(168)),
  KEY           url_norm      (url_norm(166)),
  KEY           url_term_norm (url_term_norm(166)),
  PRIMARY KEY   (urlid)
)'.$db->build_create_table_collation().';');
	}

	if (!$db->table_exists('url_previews')) {
		// utf8_bin collation was chosen for the varchar columns
		// so that SELECTs are case-sensitive, given that everything
		// after the server name in URLs is case-sensitive.
		$db->query('
CREATE TABLE '.TABLE_PREFIX.'url_previews (
  url_norm     varchar('.lkt_max_url_len.') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  preview      text             NOT NULL DEFAULT \'\',
  dateline     int(10) unsigned NOT NULL DEFAULT 0,
  valid        tinyint(1)       NOT NULL DEFAULT 1,
  helper_class_name varchar('.lkt_max_helper_class_name_len.') NOT NULL DEFAULT \'\',
  helper_class_vers varchar('.lkt_max_helper_class_vers_len.') NOT NULL DEFAULT \'\',
  KEY         url_norm (url_norm(166)),
  PRIMARY KEY (url_norm(166))
)'.$db->build_create_table_collation().';');
	}

	if (!$db->table_exists('post_urls')) {
		$db->query('
CREATE TABLE '.TABLE_PREFIX.'post_urls (
  pid         int unsigned NOT NULL,
  urlid       int unsigned NOT NULL,
  PRIMARY KEY (urlid, pid)
)'.$db->build_create_table_collation().';');
	}

	if (!$db->field_exists('lkt_got_urls', 'posts')) {
		$db->query("ALTER TABLE ".TABLE_PREFIX."posts ADD lkt_got_urls boolean NOT NULL default FALSE");
	}

	if (!$db->field_exists('lkt_linkpreviewoff', 'posts')) {
		$db->query("ALTER TABLE ".TABLE_PREFIX."posts ADD lkt_linkpreviewoff boolean NOT NULL default FALSE");
	}

	if (!$db->field_exists('lkt_warn_about_links', 'users')) {
		$db->query('ALTER TABLE '.TABLE_PREFIX.'users ADD `lkt_warn_about_links` tinyint(1) NOT NULL default \'1\'');
	}

	// These five functions are compatible with upgrading -
	// they either check for existing database entries before
	// inserting new ones, or they delete existing entries then
	// reinsert them (potentially with changes), or they update
	// existing entries.
	lkt_create_templategroup();
	lkt_insert_templates($from_version);
	lkt_create_settingsgroup();
	lkt_create_settings();
	if ($from_version < (int)'10100') {
		lkt_enable_all_helpers();
	}
}

function linktools_uninstall() {
	global $db, $cache;

	if ($db->table_exists('urls')) {
		$db->drop_table('urls');
	}

	if ($db->table_exists('post_urls')) {
		$db->drop_table('post_urls');
	}

	if ($db->field_exists('lkt_got_urls', 'posts')) {
		$db->query('ALTER TABLE '.TABLE_PREFIX.'posts DROP COLUMN lkt_got_urls');
	}

	if ($db->field_exists('lkt_warn_about_links', 'users')) {
		$db->query('ALTER TABLE '.TABLE_PREFIX.'users DROP column `lkt_warn_about_links`');
	}

	$db->delete_query('tasks', "file='linktools'");

	$db->delete_query('templates', "title LIKE 'linktools\\_%'");
	$db->delete_query('templategroups', "prefix in ('linktools')");

	lkt_delete_stylesheets();

	$lrs_plugins = $cache->read('lrs_plugins');
	unset($lrs_plugins[C_LKT]);
	$cache->update('lrs_plugins', $lrs_plugins);
}

function linktools_is_installed() {
	global $db;

	return $db->table_exists('urls') && $db->table_exists('post_urls');
}

function linktools_activate() {
	global $db, $plugins, $cache, $lang, $lkt_plugin_upgrade_message;

	$info = linktools_info();
	$to_version = $info['version_code'];
	$lrs_plugins = $cache->read('lrs_plugins');
	if (!is_array($lrs_plugins)) {
		$lrs_plugins = array();
	}
	$from_version = isset($lrs_plugins[C_LKT]) ? $lrs_plugins[C_LKT]['version_code'] : false;

	lkt_install_or_upgrade($from_version, $to_version);

	if ($from_version !== false && $to_version > $from_version) {
		$lkt_plugin_upgrade_message = $lang->sprintf($lang->lkt_successful_upgrade_msg, $lang->lkt_name, $info['version']);
		update_admin_session('lkt_plugin_info_upgrade_message', $lang->sprintf($lang->lkt_successful_upgrade_msg_for_info, $info['version']));
	}

	lkt_create_stylesheets();

	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('newthread', '({\\$smilieinserter})', '{$smilieinserter}{$linktools_div}');
	find_replace_templatesets('newthread', '({\\$codebuttons})'   , '{$codebuttons}{$linktools_js}'    );
	find_replace_templatesets('postbit'        , '({\\$post\\[\'poststatus\'\\]})', '{$post[\'poststatus\']}{$post[\'updatepreview\']}');
	find_replace_templatesets('postbit_classic', '({\\$post\\[\'poststatus\'\\]})', '{$post[\'poststatus\']}{$post[\'updatepreview\']}');
	find_replace_templatesets('showthread'     , '(<script\\stype="text/javascript"\\ssrc="{\\$mybb->asset_url}/jscripts/thread.js(?:\\?ver=\\d+)"></script>)', '<script type="text/javascript" src="{$mybb->asset_url}/jscripts/thread.js?ver=1822"></script>
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/linkpreviews.js?ver=1.1.0"></script>');
	find_replace_templatesets('editpost_postoptions', '({\\$disablesmilies})', '{$disablesmilies}<br />{$disablelinkpreviews}');

	$res = $db->simple_select('tasks', 'tid', "file='linktools'", array('limit' => '1'));
	if ($db->num_rows($res) == 0) {
		require_once MYBB_ROOT . '/inc/functions_task.php';
		$new_task = array(
			'title' => $db->escape_string($lang->lkt_task_title),
			'description' => $db->escape_string($lang->lkt_task_description),
			'file' => 'linktools',
			'minute'      => '0',
			'hour'        => '0',
			'day'         => '*',
			'weekday'     => '*',
			'month'       => '*',
			'enabled'     => 1,
			'logging'     => 1,
		);
		$new_task['nextrun'] = fetch_next_run($new_task);
		$db->insert_query('tasks', $new_task);
		$cache->update_tasks();
	} else {
		$tid = $db->fetch_field($res, 'tid');
		$db->update_query('tasks', array('enabled' => 1), "tid={$tid}");
	}

	// Reread as this cache entry is updated in lkt_insert_templates()
	// which is called by lkt_install_or_upgrade() above.
	$lrs_plugins = $cache->read('lrs_plugins');
	$lrs_plugins[C_LKT]['version'     ] = $info['version'     ];
	$lrs_plugins[C_LKT]['version_code'] = $info['version_code'];
	$cache->update('lrs_plugins', $lrs_plugins);
}

function linktools_deactivate() {
	global $db;

	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('newthread', '({\\$linktools_div})', '', 0);
	find_replace_templatesets('newthread', '({\\$linktools_js})' , '', 0);
	find_replace_templatesets('postbit'        , '({\\$post\\[\'updatepreview\'\\]})', '', 0);
	find_replace_templatesets('postbit_classic', '({\\$post\\[\'updatepreview\'\\]})', '', 0);
	find_replace_templatesets('showthread'     , '(\\r?\\n?<script\\stype="text/javascript"\\ssrc="{\\$mybb->asset_url}/jscripts/linkpreviews.js(?:\\?ver=[^"]+)?"></script>)', '', 0);
	find_replace_templatesets('editpost_postoptions', '(<br\\s/>{\\$disablelinkpreviews})', '', 0);

	$db->update_query('tasks', array('enabled' => 0), 'file=\'linktools\'');
}

/**
 * Enables all helpers present in the filesystem and inserts their templates as necessary.
 */
function lkt_enable_all_helpers() {
	global $cache, $db;

	$lrs_plugins = $cache->read('lrs_plugins');
	$inst_helpers = !empty($lrs_plugins[C_LKT]['installed_link_helpers'])
			? $lrs_plugins[C_LKT]['installed_link_helpers']
			: array();
	$present_helpers = array();
	foreach (lkt_get_linkhelper_classnames() as $type => $classnames) {
		$present_helpers = array_merge($present_helpers, $classnames);
	}

	foreach ($present_helpers as $present_helper) {
		if (!is_array($inst_helpers[$present_helper])) {
			$inst_helpers[$present_helper] = array(
				'enabled' => true,
			);
		}
		if (empty($inst_helpers[$present_helper]['enabled'])) {
			$inst_helpers[$present_helper]['enabled'] = true;
			if (empty($inst_helpers[$present_helper]['tpl_installed'])) {
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
				}
			}
		}
	}

	$lrs_plugins[C_LKT]['installed_link_helpers'] = $inst_helpers;
	$cache->update('lrs_plugins', $lrs_plugins);
}

function lkt_hookin__admin_config_plugins_activate_commit() {
	global $message, $lkt_plugin_upgrade_message;

	if (!empty($lkt_plugin_upgrade_message)) {
		$message = $lkt_plugin_upgrade_message;
	}
}

function lkt_create_templategroup() {
	global $db;

	// This function can be called on upgrade, so only create if non-existent
	$res = $db->simple_select('templategroups', 'prefix', "prefix='linktools'");
	if ($db->num_rows($res) < 1) {
		$templateset = array(
			'prefix' => 'linktools',
			'title' => 'Link Tools',
		);
		$db->insert_query('templategroups', $templateset);
	}
}

function lkt_insert_templates($from_version) {
	global $mybb, $cache, $db;

	$templates = array(
		'linktools_div' => array(
			'template' => '<div id="dlw-msg-sidebar-div" style="margin: auto; width: 170px; margin-top: 20px;"></div>',
			'version_at_last_change' => '10000',
		),
		'linktools_op_post_div' => array(
			'template' =>'<div><span class="first-post">{$lang->lkt_opening_post}</span></div>',
			'version_at_last_change' => '10000',
		),
		'linktools_non_op_post_div' => array(
			'template' => '<div>{$lang->lkt_non_opening_post} {$post[\'plink\']} {$lang->lkt_posted_by} {$post[\'ulink_p\']}, {$post[\'dtlink_p\']}</div>',
			'version_at_last_change' => '10000',
		),
		'linktools_matching_url_item' => array(
			'template'=> '<li style="padding: 0; margin: 0;">$matching_url_msg</li>',
			'version_at_last_change' => '10000',
		),
		'linktools_matching_post' => array(
			'template' => '<div$div_main_class>
	<div>{$post[\'flinks\']}<br />{$post[\'nav_bit_img\']}{$post[\'tlink\']}</div>
	<div>{$lang->lkt_started_by} {$post[\'ulink_t\']}, {$post[\'dtlink_t\']}</div>
	$div_posted_by
	<div>
		$lang_matching_url_or_urls
		<ul class="matching-urls">
			$matching_urls_list
		</ul>
	</div>
	<div class="dlw-post-message">{$post[\'message\']}</div>
</div>',
			'version_at_last_change' => '10000',
		),
		'linktools_review_buttons' => array(
			'template' => '<div style="text-align:center">
	<input type="submit" class="button" name="ignore_dup_link_warn" value="{$lang->lkt_post_anyway}" accesskey="s" />
	<input type="submit" class="button" name="previewpost" value="{$lang->preview_post}" />
	{$savedraftbutton}
</div>',
			'version_at_last_change' => '10000',
		),
		'linktools_toggle_button' => array(
			'template' => '<div style="text-align:center"><button id="id_btn_toggle_quote_posts" onclick="lkt_toggle_hidden_posts();" type="button">{$lang->lkt_btn_toggle_msg_hide}</button></div>',
			'version_at_last_change' => '10000',
		),
		'linktools_review_page' => array(
			'template' => '<html>
<head>
<title>{$mybb->settings[\'bbname\']}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="newthread.php?fid={$fid}&amp;processed=1" method="post" enctype="multipart/form-data" name="input">
{$inputs}
{$matching_posts_warning_div}
{$btns}
{$further_results_below_div}
{$toggle_btn}
{$lkt_found_posts}
{$further_results_above_div}
{$btns}
</form>
<br class="clear" />
{$footer}
<script type="text/javascript">
//<![CDATA[
function lkt_toggle_hidden_posts() {
	var nodes = document.querySelectorAll(\'.all_matching_urls_in_quotes\');
	if (nodes.length > 0) {
		var val = (nodes[0].style.display == \'none\' ? \'\' : \'none\');
		for (var i = 0; i < nodes.length; i++) {
			nodes[i].style.display = val;
		}
		document.getElementById(\'id_btn_toggle_quote_posts\').innerHTML = (val == \'none\' ? \'{$lang->lkt_btn_toggle_msg_show}\' : \'{$lang->lkt_btn_toggle_msg_hide}\');
	}
}
//]]>
</script>
</body>
</html>',
			'version_at_last_change' => '10000',
		),
		'linktools_matching_posts_warning_div' => array(
			'template' => '<div class="red_alert">$matching_posts_warning_msg</div>',
			'version_at_last_change' => '10000',
		),
		'linktools_preview_regen_link' => array(
			'template' => '<a href="$link_url">$link_text</a>',
			'version_at_last_change' => '10100',
		),
		'linktools_preview_regen_container' => array(
			'template' => '<div class="lkt-regen-link-container" id="lkt_regen_cont_{$post[\'pid\']}">{$prefix}{$links}</div>',
			'version_at_last_change' => '10100',
		),
		'linktools_preview_regen_page' => array(
			'template' => '<html>
<head>
<title>{$lang->lkt_preview_regen_pg_title}</title>
{$headerinclude}
</head>
<body>
{$header}

{$regen_msg}
<br />
<br />
{$return_link}

{$footer}
</body>
</html>',
			'version_at_last_change' => '10100',
		),
		'linktools_regen_page_return_link' => array(
			'template' => '<a href="$link_url">$link_text</a>',
			'version_at_last_change' => '10100',
		),
		'linktools_cbxdisablelinkpreview' => array(
			'template' => '<label><input type="checkbox" class="checkbox" name="lkt_linkpreviewoff" value="1" tabindex="9"{$linkpreviewoffchecked} /> {$lang->lkt_linkpreviewoff}</label>',
			'version_at_last_change' => '10100',
		),
	);

	// Remove any existing Master templates for this plugin except for
	// those for installed link helpers no longer present in the filesystem.
	require_once __DIR__.'/linktools/LinkHelperBase.php';
	$helper_conds = '';
	$lrs_plugins = $cache->read('lrs_plugins');
	$inst_helpers = !empty($lrs_plugins[C_LKT]['installed_link_helpers'])
	                  ? $lrs_plugins[C_LKT]['installed_link_helpers']
	                  : array();
	$present_helpers = array();
	foreach (lkt_get_linkhelper_classnames() as $type => $classnames) {
		$present_helpers = array_merge($present_helpers, $classnames);
	}
	$inst_but_missing = array_diff(array_keys($inst_helpers), $present_helpers);
	$tplnames = array_map(function($helper) {return LinkHelper::mk_tpl_nm_frm_classnm($helper);}, $inst_but_missing);
	if ($tplnames) {
		$helper_conds = " AND title NOT IN ('".implode("','", $tplnames)."')";
	}
	$db->delete_query('templates', "sid=-2 AND title LIKE 'linktools%'{$helper_conds}");

	foreach ($templates as $template_title => $template_data) {
		// Now, flag any of this plugin's templates that have been modified in the plugin since
		// the version of the plugin from which we are upgrading, flagging all templates if that
		// version number is not available. This ensures that Find Updated Templates detects them
		// *if* the user has also modified them, and without false positives. The way we flag them
		// is to zero the `version` column of the `templates` table where `sid` is not -2 for this
		// plugin's templates.
		if ($from_version === false || $template_data['version_at_last_change'] > $from_version) {
			$db->update_query('templates', array('version' => 0), "title='{$template_title}' AND sid <> -2");
		}

		// And insert/update master templates with SID -2.
		$insert_templates = array(
			'title'    => $db->escape_string($template_title),
			'template' => $db->escape_string($template_data['template']),
			'sid'      => '-2',
			'version'  => '1',
			'dateline' => TIME_NOW
		);
		$db->insert_query('templates', $insert_templates);
	}

	// And now do the same for installed Link Helper templates.
	foreach (array_intersect($present_helpers, array_keys($inst_helpers)) as $helper) {
		$from_version   = $inst_helpers[$helper]['tpl_installed'];
		$latest_version = $helper::get_version();
		$helperobj      = $helper::get_instance();
		if ($tplname = $helperobj->get_template_name(/*$ret_empty_if_default*/true)) {
			if ($latest_version > $from_version) {
				$db->update_query('templates', array('version' => 0), "title='".$db->escape_string($tplname)."' AND sid <> -2");
			}

			$fields = array(
				'title'    => $db->escape_string($tplname),
				'template' => $db->escape_string($helperobj->get_template_raw()),
				'sid'      => '-2',
				'version'  => '1',
				'dateline' => TIME_NOW
			);
			$db->insert_query('templates', $fields);

			$inst_helpers[$helper]['tpl_installed'] = $latest_version;
		}
	}

	$lrs_plugins[C_LKT]['installed_link_helpers'] = $inst_helpers;
	$cache->update('lrs_plugins', $lrs_plugins);
	$lrs_plugins = $cache->read('lrs_plugins');
}

function lkt_get_linkpreview_css() {
	return <<<EOF
.lkt-link-preview {
	border: 1px solid #AAA;
	padding-left: 3px;
	margin-top: 20px;
	max-width: 550px;
	min-height: 35px;
}

.lkt-link-preview a {
	color: inherit;
	text-decoration: none;
}

.lkt-link-preview img {
	float: left;
	width: 35px;
	height: 35px;
	margin-right: 5px;
	object-fit: cover;
}
EOF;
}

function lkt_get_linktools_css() {
	return <<<EOF
#dlw-extra-info, .dlw-post-inner {
	position             : static;
	border               : 1px solid black;
	background-color     : white;
	color                : black;
	margin               : 20px auto;
}

#dlw-warn-summ-box {
	position             : fixed;
	z-index              : 999;
	y-overflow           : scroll;
	border               : 1px solid #a5161a;
	background-color     : #fbe3e4;
	color                : #a5161a;
	margin               : 0;
}

.first-post {
	border: 1px solid #a5161a;
	background-color: #fbe3e4;
	color: #a5161a;
}

#dlw-extra-info, .dlw-post-inner, #dlw-warn-summ-box, .first-post {
	border-radius: 10px;
	-moz-border-radius: 10px;
	-webkit-border-radius: 10px;
	padding-left: 10px;
	padding-right: 10px;
}

#dlw-extra-info, .dlw-post-inner, #dlw-warn-summ-box {
	white-space          : pre-wrap;      /* CSS 3 */
	white-space          : -moz-pre-wrap; /* Mozilla, since 1999 */
	white-space          : -pre-wrap;     /* Opera 4-6 */
	white-space          : -o-pre-wrap;   /* Opera 7 */
	word-wrap            : break-word;    /* Internet Explorer 5.5+ */
}

#dlw-extra-info {
	text-align           : left;
	overflow-y           : scroll;
}

#dlw-extra-info div, #dlw-extra-info ul {
	white-space          : normal;
	word-wrap            : normal;
}

.further-results {
	background-color: orange; border: 1px solid black;
}

.btn-dismiss {
	float: right;
}

.url-list {
	padding: 0 auto;
	margin: 0;
}

.url-list-item {
	padding: 0;
	margin: 0;
}

#dlw-btn-dismiss-summ, #dlw-btn-details-summ-on, #dlw-btn-details-summ-off {
	float: right;
}

ul.matching-urls {
	padding: 0 auto;
	margin: 0;
}

.dlw-post-message {
	padding-top: 10px;
	padding-bottom: 10px;
	border: 1px solid grey;
	background-color: white;	
}
EOF;
}

function lkt_create_stylesheets() {
	global $db;

	// This function is called on activate, so first delete any existing stylesheet.
	lkt_delete_stylesheets();

	$rows = array(
		array(
			'name' => 'linktools.css',
			'tid' => 1,
			'attachedto' => 'newthread.php',
			'stylesheet' => $db->escape_string(lkt_get_linktools_css()),
			'cachefile' => 'linktools.css',
			'lastmodified' => TIME_NOW
		),
		array(
			'name' => 'linkpreview.css',
			'tid' => 1,
			'attachedto' => 'showthread.php',
			'stylesheet' => $db->escape_string(lkt_get_linkpreview_css()),
			'cachefile' => 'linkpreview.css',
			'lastmodified' => TIME_NOW
		),
	);

	require_once MYBB_ADMIN_DIR.'inc/functions_themes.php';

	foreach ($rows as $row) {
		$sid = $db->insert_query('themestylesheets', $row);
		$db->update_query('themestylesheets', array('cachefile' => 'css.php?stylesheet='.$sid), "sid = '".$sid."'", 1);
	}

	$tids = $db->simple_select('themes', 'tid');
	while ($theme = $db->fetch_array($tids)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}

function lkt_delete_stylesheets() {
	global $db;

	$db->delete_query('themestylesheets', "(name = 'linktools.css' OR name = 'linkpreview.css') AND tid = 1");
}

function lkt_create_settingsgroup() {
	global $db, $lang;

	if (!isset($lang->linktools)) {
		$lang->load(C_LKT);
	}

	$res = $db->simple_select('settinggroups', 'gid', "name = '".C_LKT."_settings'", array('limit' => 1));
	$gid = $db->fetch_field($res, 'gid');

	$fields = array(
		'title'        => $db->escape_string($lang->lkt_settings     ),
		'description'  => $db->escape_string($lang->lkt_settings_desc),
	);

	if (!$gid) {
		$res = $db->simple_select('settinggroups', 'MAX(disporder) as max_disporder');
		$disporder = $db->fetch_field($res, 'max_disporder') + 1;
		$fields = array_merge($fields, array(
			'name'         => C_LKT.'_settings',
			'disporder'    => intval($disporder),
			'isdefault'    => 0
		));
		// Insert the plugin settings group into the database.
		$db->insert_query('settinggroups', $fields);
	} else	$db->update_query('settinggroups', $fields, "gid={$gid}");
}

function lkt_create_settings() {
	global $db, $lang;

	if (!isset($lang->linktools)) {
		$lang->load(C_LKT);
	}

	$existing_setting_values = array();
	$res = $db->simple_select('settinggroups', 'gid', "name = '".C_LKT."_settings'", array('limit' => 1));
	$gid = $db->fetch_field($res, 'gid');
	if ($gid) {
		$res2 = $db->simple_select('settings', 'value, name', "gid={$gid}");
		while ($setting = $db->fetch_array($res2)) {
			$existing_setting_values[$setting['name']] = $setting['value'];
		}
		// Delete existing settings, without deleting their group.
		$db->delete_query('settings', "gid='{$gid}'");
	}

	// The settings to (re)create in the database.
	$settings = array(
		'enable_dlw' => array(
			'title'       => $lang->lkt_enable_dlw_title,
			'description' => $lang->lkt_enable_dlw_desc ,
			'optionscode' => 'yesno',
			'value'       => '1',
		),
		'force_dlw' => array(
			'title'       => $lang->lkt_force_dlw_title,
			'description' => $lang->lkt_force_dlw_desc ,
			'optionscode' => 'yesno',
			'value'       => '0',
		),
		'link_preview_type' => array(
			'title'       => $lang->lkt_link_preview_type_title,
			'description' => $lang->lkt_link_preview_type_desc,
			'optionscode' => "select\nall={$lang->lkt_link_preview_type_all}\nnone={$lang->lkt_link_preview_type_none}\nwhitelist={$lang->lkt_link_preview_type_whitelist}\nblacklist={$lang->lkt_link_preview_type_blacklist}",
			'value'       => 'all',
		),
		'link_preview_dom_list' => array(
			'title'       => $lang->lkt_link_preview_dom_list_title,
			'description' => $lang->lkt_link_preview_dom_list_desc,
			'optionscode' => 'textarea',
			'value'       => '',
		),
		'link_preview_disable_self_dom' => array(
			'title'       => $lang->lkt_link_preview_disable_self_dom_title,
			'description' => $lang->lkt_link_preview_disable_self_dom_desc,
			'optionscode' => 'yesno',
			'value'       => '1',
		),
		'link_preview_expiry_period' => array(
			'title'       => $lang->lkt_link_preview_expiry_period_title,
			'description' => $lang->lkt_link_preview_expiry_period_desc,
			'optionscode' => 'numeric',
			'value'       => '7',
		),
		'link_preview_expire_on_new_helper' => array(
			'title'       => $lang->lkt_link_preview_expire_on_new_helper_title,
			'description' => $lang->lkt_link_preview_expire_on_new_helper_desc,
			'optionscode' => 'yesno',
			'value'       => '1',
		),
		'link_preview_on_fly' => array(
			'title'       => $lang->lkt_link_preview_on_fly_title,
			'description' => $lang->lkt_link_preview_on_fly_desc,
			'optionscode' => "select\nalways={$lang->lkt_link_preview_on_fly_always}\nnever={$lang->lkt_link_preview_on_fly_never}\nwhitelist={$lang->lkt_link_preview_on_fly_whitelist}\nblacklist={$lang->lkt_link_preview_on_fly_blacklist}",
			'value'       => 'always',
		),
		'link_preview_on_fly_dom_list' => array(
			'title'       => $lang->lkt_link_preview_on_fly_dom_list_title,
			'description' => $lang->lkt_link_preview_op_fly_dom_list_desc,
			'optionscode' => 'textarea',
			'value'       => '',
		),
		'link_preview_rebuild_scope' => array(
			'title'       => $lang->lkt_link_preview_rebuild_scope_title,
			'description' => $lang->lkt_link_preview_rebuild_scope_desc,
			'optionscode' => "select\nall={$lang->lkt_link_preview_rebuild_scope_all}\nonly_invalid={$lang->lkt_link_preview_rebuild_scope_only_invalid}",
			'value'       => 'only_invalid'
		),
	);

	// (Re)create the settings, retaining the old values where they exist.
	$ordernum = 1;
	foreach ($settings as $name => $setting) {
		$value = isset($existing_setting_values[C_LKT.'_'.$name]) ? $existing_setting_values[C_LKT.'_'.$name] : $setting['value'];
		$insert_settings = array(
			'name'        => $db->escape_string(C_LKT.'_'.$name        ),
			'title'       => $db->escape_string($setting['title'      ]),
			'description' => $db->escape_string($setting['description']),
			'optionscode' => $db->escape_string($setting['optionscode']),
			'value'       => $value                                     ,
			'disporder'   => $ordernum                                  ,
			'gid'         => $gid                                       ,
			'isdefault'   => 0
		);
		$db->insert_query('settings', $insert_settings);
		$ordernum++;
	}

	rebuild_settings();
}

function lkt_get_scheme($url) {
	$scheme = '';
	$url = trim($url);
	if (preg_match('(^[a-z]+(?=:))', $url, $match)) {
		$scheme = $match[0];
	}

	return $scheme;
}

function lkt_has_valid_scheme($url) {
	return (in_array(lkt_get_scheme(trim($url)), lkt_valid_schemes));
}

# Should be kept in sync with the extract_url_from_mycode_tag() method of the DLW object in ../jscripts/linktools.js
function lkt_extract_url_from_mycode_tag(&$text, &$urls, $re, $indexes_to_use = array(1)) {
	if (preg_match_all($re, $text, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$url = '';
			foreach ($indexes_to_use as $i) {
				$url .= $match[$i];
			}
			lkt_test_add_url($url, $urls);
		}
		$text = preg_replace($re, ' ', $text);
	}

	$urls = array_map('trim', $urls);
}

# Based heavily on the corresponding code in postParser::mycode_auto_url_callback() in ../class_parser.php
#
# Should be kept in sync with the strip_unmatched_closing_parens() method of the DLW object in ../jscripts/linktools.js
function lkt_strip_unmatched_closing_parens($url) {
	// Allow links like http://en.wikipedia.org/wiki/PHP_(disambiguation) but detect mismatching braces
	while (my_substr($url, -1) == ')') {
		if(substr_count($url, ')') > substr_count($url, '(')) {
			$url = my_substr($url, 0, -1);
		} else {
			break;
		}

		// Example: ([...] http://en.wikipedia.org/Example_(disambiguation).)
		$last_char = my_substr($url, -1);
		while ($last_char == '.' || $last_char == ',' || $last_char == '?' || $last_char == '!') {
			$url = my_substr($url, 0, -1);
			$last_char = my_substr($url, -1);
		}
	}

	return $url;
}

# Based on the code in postParser::mycode_auto_url_callback() in ../class_parser.php,
# with the second and fourth regexes from MyBB core's postParser::mycode_auto_url(),
# and the first and third adapted independently to support bare URLs within MyCode
# tags such as (where the "=option" is optional):
#     [tag=option]http://www.example.com/dir/file.html?key1=val1&keys2.1=val2.1&keys2.2=val2.2[/tag]
# (the first regex)... as well as without the 'http://' (the third regex).
#
# The independently adapted regexes are necessary over core regexes because in
# the core, mycode_auto_url() is called only *after* all MyCodes have been parsed
# into HTML tags, so the core code can rely on there being no meaningful square
# brackets left. We can't.
#
# Should be kept in sync with the extract_bare_urls() method of the DLW object in ../jscripts/linktools.js
function lkt_extract_bare_urls(&$text, &$urls) {
	$urls_matched = [];
	$text = ' '.$text;
	$text_new = $text;

	foreach (array(
		"#\[([^\]]+)(?:=[^\]]+)?\](http|https|ftp|news|irc|ircs|irc6){1}(://)([^\/\"\s\<\[\.]+\.([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?)\[/\\1\]#ius",
		"#([\s\(\)\[\>])(http|https|ftp|news|irc|ircs|irc6){1}(://)([^\/\"\s\<\[\.]+\.([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?([\w\/\)]))#ius",
		"#\[([^\]]+)(?:=[^\]]+)?\](www|ftp)(\.)(([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?)\[/\\1\]#ius",
		"#([\s\(\)\[\>])(www|ftp)(\.)(([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?([\w\/\)]))#ius"
	) as $re) {
		if (preg_match_all($re, $text, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE)) {
			foreach ($matches as $match) {
				$url = $match[2][0].$match[3][0].lkt_strip_unmatched_closing_parens($match[4][0]);
				$urls_matched[] = $url;
				lkt_test_add_url($url, $urls);
				// Blank out the matched URLs.
				$text_new = substr($text_new, 0, $match[2][1]).str_repeat(' ', strlen($url)).substr($text_new, $match[2][1] + strlen($url));
			}
		}
	}

	$text_new = my_substr($text, 1);
	$text = $text_new;

	$urls = array_map('trim', $urls);
}

# Should be kept in sync with the test_add_url() method of the DLW object in ../jscripts/linktools.js
function lkt_test_add_url($url, &$urls) {
	if (lkt_has_valid_scheme($url) && !in_array($url, $urls)) {
		$urls[] = $url;
	}
}

# Should be kept in sync with the extract_urls() method of the DLW object in ../jscripts/linktools.js
function lkt_extract_urls($text, $exclude_videos = false) {
	$urls = array();

	# First, strip out all [img] tags.
	# [img] tag regexes from postParser::parse_mycode() in ../inc/class_parser.php.
	$text = preg_replace("#\[img\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", ' ', $text);
	$text = preg_replace("#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", ' ', $text);
	$text = preg_replace("#\[img align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", ' ', $text);
	$text = preg_replace("#\[img=([1-9][0-9]*)x([1-9][0-9]*) align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", ' ', $text);

	# [url] tag regexes from postParser::cache_mycode() in ../class_parser.php.
	lkt_extract_url_from_mycode_tag($text, $urls, "#\[url\]((?!javascript)[a-z]+?://)([^\r\n\"<]+?)\[/url\]#si", array(1, 2));
	lkt_extract_url_from_mycode_tag($text, $urls, "#\[url\]((?!javascript:)[^\r\n\"<]+?)\[/url\]#i", array(1));
	lkt_extract_url_from_mycode_tag($text, $urls, "#\[url=((?!javascript)[a-z]+?://)([^\r\n\"<]+?)\](.+?)\[/url\]#si", array(1, 2));
	lkt_extract_url_from_mycode_tag($text, $urls, "#\[url=((?!javascript:)[^\r\n\"<]+?)\](.+?)\[/url\]#si", array(1));

	if (!$exclude_videos) {
		# [video] tag regex from postParser::parse_mycode() in ../class_parser.php.
		lkt_extract_url_from_mycode_tag($text, $urls, "#\[video=(.*?)\](.*?)\[/video\]#i", array(2));
	}

	lkt_extract_bare_urls($text, $urls);

	$urls = array_map('trim', $urls);

	return array_values(array_unique($urls));
}

function lkt_get_url_search_sql($urls, $already_normalised = false, $extra_conditions = '') {
	global $db;

	if ($already_normalised) {
		$urls_norm = $urls;
	} else {
		sort($urls);
		$urls_norm = lkt_normalise_urls($urls);
	}

	$url_paren_list = "('".implode("', '", array_map(array($db, 'escape_string'), $urls_norm))."')";
	$conds = 'u.url_norm IN '.$url_paren_list.' OR u.url_term_norm IN '.$url_paren_list;

	$fids = get_unviewable_forums(true);
	if ($inact_fids = get_inactive_forums()) {
		if ($fids) $fids .= ',';
		$fids .= $inact_fids;
	}
	if ($fids) {
		$conds = '('.$conds.') AND f.fid NOT IN ('.$fids.')';
	}
	$onlyusfids = array();
	$group_permissions = forum_permissions();
	foreach ($group_permissions as $fid => $forum_permissions) {
		if ($forum_permissions['canonlyviewownthreads'] == 1) {
			$onlyusfids[] = $fid;
		}
	}
	if ($onlyusfids) {
		$conds .= '('.
		$conds.' AND ((t.fid IN('.implode(',', $onlyusfids).') AND t.uid="'.$mybb->user['uid'].'") OR t.fid NOT IN('.implode(',', $onlyusfids).')))';
	}
	$conds = '('.$conds.') AND p.visible > 0 '.$extra_conditions;

	return '
SELECT DISTINCT u2.url as matching_url, IF(u.url_norm IN '.$url_paren_list.', u.url_norm, u.url_term_norm) AS queried_norm_url,
                p.pid, p.uid AS uid_post, p.username AS username_post, p.dateline as dateline_post, p.message, p.subject AS subject_post, p.edittime,
                t.tid, t.uid AS uid_thread, t.username AS username_thread, t.subject AS subject_thread, t.firstpost, t.dateline as dateline_thread,
                (p.pid = t.firstpost) AS isfirstpost,
                x.prefix,
                f.fid, f.name as forum_name, f.parentlist
FROM            '.$db->table_prefix.'urls u
LEFT OUTER JOIN '.$db->table_prefix.'urls u2
ON              u2.url_term_norm = u.url_term_norm
INNER JOIN      '.$db->table_prefix.'post_urls pu
ON              u2.urlid = pu.urlid
INNER JOIN      '.$db->table_prefix.'posts p
ON              pu.pid = p.pid
LEFT OUTER JOIN '.$db->table_prefix.'forums f
ON              p.fid = f.fid
LEFT OUTER JOIN '.$db->table_prefix.'threads t
ON              p.tid = t.tid
LEFT OUTER JOIN '.$db->table_prefix.'threadprefixes x
ON              t.prefix = x.pid
WHERE           '.$conds.'
ORDER BY        isfirstpost DESC, p.dateline DESC';
}

// Returns at most lkt_max_matching_posts results. Indicates whether there are further results via the third entry of the returned array.
function lkt_get_posts_for_urls($urls, $post_edit_times = array()) {
	global $db, $parser;

	sort($urls);
	$urls_norm = lkt_normalise_urls($urls);

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

	$sql = lkt_get_url_search_sql($urls_norm, /*$already_normalised= */true);
	$res = $db->query($sql);

	$all_matching_urls_in_quotes_flag = false;
	$forum_names = array();
	$matching_posts = array();
	$further_results = false;
	while (($row = $db->fetch_array($res))) {
		if (count($matching_posts) == lkt_max_matching_posts) {
			$further_results = true;
			break;
		}
		$forum_names[$row['fid']] = $row['forum_name'];
		if (!isset($matching_posts[$row['pid']])) {
			$matching_posts[$row['pid']] = $row;
			unset($matching_posts[$row['pid']]['matching_url']);
			unset($matching_posts[$row['pid']]['queried_norm_url']);
			$matching_posts[$row['pid']]['message'] = $parser->parse_message($row['message'], $parse_opts);
			$matching_posts[$row['pid']]['all_urls'] = lkt_extract_urls($row['message']);
			// The raw URLs (i.e., not normalised) present in this post that were a match for
			// the raw URLs (again, not normalised) for which we are querying, in that
			// both terminate (i.e., after following all redirects) in the same normalised URL.
			$matching_posts[$row['pid']]['matching_urls_in_post'] = [];
			// The raw URLs for which we are querying that are matched in this post, in the
			// same order as the above array (i.e., entries at the same index in both arrays
			// both terminate in the same normalised URL).
			$matching_posts[$row['pid']]['matching_urls'] = [];
			$stripped = lkt_strip_nestable_mybb_tag($row['message'], 'quote');
			$urls_quotes_stripped = lkt_extract_urls($stripped);
			$matching_posts[$row['pid']]['are_all_matching_urls_in_quotes'] = (array_intersect($urls, $urls_quotes_stripped) == array());
			if ($matching_posts[$row['pid']]['are_all_matching_urls_in_quotes']) {
				$all_matching_urls_in_quotes_flag = true;
			}
			foreach (explode(',', $row['parentlist']) as $fid) {
				if (empty($forum_names[$fid])) {
					$forum_names[$fid] = null;
				}
			}
		}
		$matching_posts[$row['pid']]['matching_urls_in_post'][] = $row['matching_url'];
		$matching_posts[$row['pid']]['matching_urls'        ][] = $urls[array_search($row['queried_norm_url'], $urls_norm)];
	}

	$db->free_result($res);

	if (!$matching_posts) {
		return array($matching_posts, $forum_names, false);
	}

	uasort($matching_posts, function ($post1, $post2) use ($urls) {
		$grade_post = function($post) {
			return ($post['pid'] == $post['firstpost']
			        ? ($urls == $post['matching_urls']
			           ? (count($urls) == count($post['all_urls'])
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
		if (array_key_exists($post['pid'], $post_edit_times) && $post_edit_times[$post['pid']] == $post['edittime']) {
			// Strip all values other than pid, edittime, matching_urls, and matching_urls_in_post
			// from any matching posts for which, based on the supplied function parameter
			// $post_edit_times, the caller already has the relevant information because the post
			// has not been edited since last returned.
			$post = array_intersect_key($post, array('pid' => true, 'edittime' => true, 'matching_urls' => true, 'matching_urls_in_post' => true));
		} else {
			$post['flinks'     ] = lkt_get_flinks($post['parentlist'], $forum_names);
			$post['tlink'      ] = lkt_get_threadlink($post['tid'], $post['subject_thread']);
			$post['nav_bit_img'] = '<img src="images/nav_bit.png" alt="" />';
			$post['ulink_p'    ] = lkt_get_usernamelink($post['uid_post'], $post['username_post']);
			$post['ulink_t'    ] = lkt_get_usernamelink($post['uid_thread'], $post['username_thread']);
			$post['dtlink_t'   ] = my_date('relative', $post['dateline_thread']);
			$post['dtlink_p'   ] = my_date('relative', $post['dateline_post']);
			$post['plink'      ] = lkt_get_postlink($post['pid'], $post['subject_post']);
		}
	}

	// Earlier return possible
	return array($matching_posts, $forum_names, $further_results);
}

function lkt_hookin__datahandler_post_insert_post_end($posthandler) {
	lkt_handle_new_post($posthandler);
}

function lkt_hookin__datahandler_post_insert_thread_end($posthandler) {
	lkt_handle_new_post($posthandler);
}

function lkt_handle_new_post($posthandler) {
	if ($posthandler->data['savedraft']) {
		return;
	}

	lkt_get_and_add_urls_of_post($posthandler->data['message'], $posthandler->pid);
}

function lkt_get_and_add_urls_of_post($message, $pid = null) {
	lkt_get_and_add_urls(lkt_extract_urls($message), $pid);
}

function lkt_get_and_add_urls($urls, $pid = null) {
	global $db;

	// Don't waste time and bandwidth resolving redirects for URLs already in the DB.
	$res = $db->simple_select('urls', 'url', "url in ('".implode("', '", array_map(array($db, 'escape_string'), $urls))."')");
	$existing_urls = [];
	while (($row = $db->fetch_array($res))) {
		$existing_urls[] = $row['url'];
	}
	$redirs = lkt_get_url_term_redirs(array_diff($urls, $existing_urls), $got_terms);

	lkt_add_urls_for_pid($urls, $redirs, $got_terms, $pid);

	return $urls;
}

/**
 * If $got_terms is false, then it indicates that no attempt was even made at resolving terminating redirects.
 * Otherwise, it is an array indexed by URLs indicating (true/false) whether or not a terminating redirect was found for the given URL.
 */
function lkt_add_urls_for_pid($urls, $redirs, $got_terms, $pid = null) {
	global $db;

	$now = time();
	foreach ($urls as $url) {
		$target = $redirs[$url];
		for ($try = 1; $try <= 2; $try++) {
			$res = $db->simple_select('urls', 'urlid', 'url = \''.$db->escape_string($url).'\'');
			if ($row = $db->fetch_array($res)) {
				$urlid = $row['urlid'];
			} else {
				$url_fit         = substr($url   , 0, lkt_max_url_len);
				$url_norm_fit    = substr(lkt_normalise_url($url), 0, lkt_max_url_len);
				$target_fit      = substr($target, 0, lkt_max_url_len);
				$target_norm_fit = substr(lkt_normalise_url($target == false ? $url : $target), 0, lkt_max_url_len);
				// Simulate the enforcement of a UNIQUE constraint on the `url` column
				// using a SELECT with a HAVING condition. This prevents the possibility of
				// rows with duplicate values for `url`.
				if (!$db->write_query('
INSERT INTO '.TABLE_PREFIX.'urls (url, url_norm, url_term, url_term_norm, got_term, term_tries, last_term_try)
       SELECT \''.$db->escape_string($url_fit).'\', \''.$db->escape_string($url_norm_fit).'\', \''.$db->escape_string($target == false ? $url_fit : $target_fit).'\', \''.$db->escape_string($target_norm_fit).'\', \''.(!$got_terms || $got_terms[$url] == false ? '0' : '1')."', '".(!$got_terms ? '0' : '1')."', '$now'".'
       FROM '.TABLE_PREFIX.'urls WHERE url=\''.$db->escape_string($url).'\'
       HAVING COUNT(*) = 0')
				    ||
				    $db->affected_rows() <= 0) {
					// We retry in this scenario because it is theoretically possible
					// that the URL was inserted by another process in between the
					// select and the insert, and that the false return is due to the
					// HAVING condition failing.
					continue;
				}
				$urlid = $db->insert_id();
			}

			if ($pid !== null) {
				// We hide errors here because there is a race condition in which this insert could
				// be performed by another process (a task or rebuild) before the current process
				// performs it, in which case the database will reject the insert as violating the
				// uniqueness of the primary
				// key (urlid, pid).
				$db->write_query('INSERT INTO '.TABLE_PREFIX."post_urls (urlid, pid) VALUES ($urlid, $pid)", /* $hide_errors = */true);
			}

			break;
		}
	}

	if ($pid !== null) $db->update_query('posts', array('lkt_got_urls' => 1), "pid=$pid");
}

function lkt_normalise_urls($urls) {
	$ret = array();

	foreach ($urls as $url) {
		$url = trim($url);
		$ret[] = lkt_normalise_url($url);
	}

	return $ret;
}

function lkt_get_norm_server_from_url($url) {
	$server = false;
	$url = trim($url);
	$parsed_url = lkt_parse_url($url);
	if (isset($parsed_url['host'])) {
		// Normalise domain to non-www-prefixed lowercase.
		$server = lkt_normalise_domain($parsed_url['host']);
	}

	return $server;
}

/**
 * Resolves and returns the immediate redirects for each URL in $urls. Optionally, while doing this
 * (with access to the HTML of any terminating redirects), generate link previews for those
 * terminating redirects.
 *
 * Uses the non-blocking functionality of cURL so that multiple URLs can be checked simultaneously,
 * but avoids hitting the same web server more than once every lkt_rehit_delay_in_secs seconds.
 *
 * This function only makes sense for $urls with a protocol of http:// or https://. $urls missing a
 * scheme are assumed to use the http:// protocol. For all other protocols, the $url is deemed to
 * terminate at itself.
 *
 * @param $urls Array.
 * @param $server_last_hit_times Array. The UNIX epoch timestamps at which each server was last polled,
 *                                      indexed by normalised (any 'www.' prefix removed) server name.
 * @param $use_head_method Boolean. If true, the HEAD method is used for all requests, otherwise
 *                                  the GET method is used.
 * @param $check_html_redirects Boolean. Self-explanatory.
 * @param $check_html_canonical_tag Boolean. Self-explanatory.
 * @param $get_link_previews Boolean. Whether or not to generate link previews and store them to the DB.
 *
 * @return An array with two array entries, $redirs and $deferred_urls.
 *         $redirs contains the immediate redirects of each of the URLs in $urls (which form
 *                   the keys of $redir array), if any.
 *                 If a URL does not redirect, then that URL's entry is set to itself.
 *                 If a link-specific error occurs for a URL, e.g. web server timeout,
 *                   then that URL's entry is set to false.
 *                 If a non-link-specific error occurs, such as failure to initialise a generic cURL handle,
 *                   then that URL's entry is set to null.
 *         $deferred_urls lists any URLs that were deferred because requesting it would have polled its
 *                        server within lkt_rehit_delay_in_secs seconds of the last time it was polled.
 */
function lkt_get_url_redirs($urls, &$server_last_hit_times = array(), &$origin_urls = [], $use_head_method = true, $check_html_redirects = false, $check_html_canonical_tag = false, $get_link_previews = true) {
	$redirs = $deferred_urls = $curl_handles = [];

	if (!$urls) return [$redirs, $deferred_urls];

	if ($check_html_redirects || $check_html_canonical_tag || $get_link_previews) {
		$use_head_method = false;
	}

	$ts_now = microtime(true);
	$seen_servers = [];
	$i = 0;
	while ($i < count($urls)) {
		$url = trim($urls[$i]);
		$server = lkt_get_norm_server_from_url($url);
		if ($server) {
			$seen_already = isset($seen_servers[$server]);
			$seen_servers[$server] = true;
			$server_wait = -1;
			if (isset($server_last_hit_times[$server])) {
				$time_since = $ts_now - $server_last_hit_times[$server];
				$server_wait = lkt_rehit_delay_in_secs - $time_since;
			}
			if ($seen_already || $server_wait > 0) {
				$deferred_urls[] = $url;
				array_splice($urls, $i, 1);

				continue;
			}
		}

		$i++;
	}

	if (($mh = curl_multi_init()) === false) {
		return false;
	}

	foreach ($urls as $url) {
		if (!in_array(lkt_get_scheme($url), array('http', 'https', ''))) {
			$redirs[$url] = $url;
			continue;
		}

		if (($ch = curl_init()) === false) {
			$redirs[$url] = null;
			continue;
		}

		// Strip from any # in the URL onwards because URLs with fragments
		// appear to be buggy either in certain older versions of cURL and/or
		// web server environments from which cURL is called.
		list($url_trim_nohash) = explode('#', trim($url), 2);

		if (!curl_setopt_array($ch, array(
			CURLOPT_URL            => $url_trim_nohash,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_NOBODY         => $use_head_method,
			CURLOPT_TIMEOUT        => lkt_curl_timeout,
			CURLOPT_USERAGENT      => 'The MyBB Link Tools plugin',
		))) {
			curl_close($ch);
			$redirs[$url] = null;
			continue;
		}
		if (curl_multi_add_handle($mh, $ch) !== CURLM_OK/*==0*/) {
			$redirs[$url] = null;
			continue;
		}
		$curl_handles[$url] = $ch;
	}

	$active = null;
	do {
		$mrc = curl_multi_exec($mh, $active);
	} while ($mrc == CURLM_CALL_MULTI_PERFORM);

	while ($active && $mrc == CURLM_OK) {
		if (curl_multi_select($mh) != -1) {
			do {
				$mrc = curl_multi_exec($mh, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}
	}

	$ts_now2 = microtime(true);
	foreach ($curl_handles as $url => $ch) {
		if ($ch) {
			$content = curl_multi_getcontent($ch);
			$server_last_hit_times[lkt_get_norm_server_from_url($url)] = $ts_now2;
			if ($content
			    &&
			    ($header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE)) !== false
			    &&
			    ($response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) !== false
			) {
				$headers = substr($content, 0, $header_size);
				$html = substr($content, $header_size);
				$target = $url;
				$matchid = 1;
				$got = false;
				if ($response_code != 200 && preg_match('/^\\s*Location\\s*:(.+)$/im', $headers, $matches)) {
					$got    = true;
					$decode = false;
				} else if ($check_html_canonical_tag && preg_match('(^\\s*Link\\s*:.*<([^>]+)>\\s*;\\s*rel\\s*=\\s*"\\s*canonical\\s*"\\s*)im', $headers, $matches)) {
					$got    = true;
					$decode = false;
				} else if (
				    // Not a perfectly reliable regex in that:
				    // (1) It doesn't check that the tag occurs within the <head> section.
				    // (2) It fails to match when redundant attributes are prepended into
				    //     the tag, but those scenarios should be rare.
				    $check_html_redirects && preg_match('((?|<\\s*meta\\s+http-equiv\\s*=\\s*"\\s*refresh\\s*"\\s*content\\s*=\\s*"\\s*(?:\\d+\\s*;)?\\s*url\\s*=\\s*(\\\'|)?\\s*([^\"\\\']+)\\s*\\1\\s*"|<\\s*meta\\s+content\\s*=\\s*"\\s*(?:\\d+\\s*;)?\\s*url\\s*=\\s*(\\\'|)?\\s*([^\"\\\']+)\\s*\\1\\s*"\\s*(?:http-equiv\\s*=\\s*"\\s*)?refresh\\s*"))is', $html, $matches)
				) {
					$got    = true;
					$decode = true;
					$matchid = 2;
				} else if (
				    // As above.
				    $check_html_canonical_tag && preg_match('((?|<\\s*link\\s+rel\\s*=\\s*"\\s*canonical\\s*"\\s*href\\s*=\\s*"\\s*([^\"]+)"|<\\s*link\\s+href\\s*=\\s*"\\s*([^\"]+)"\\s*rel\\s*=\\s*"\\s*canonical\\s*"))is', $html, $matches)
				) {
					$got    = true;
					$decode = true;
				}
				if ($got) {
					$target = trim($matches[$matchid]);
					if ($decode) {
						$target = html_entity_decode($target);
					}
					$target = lkt_check_absolutise_relative_uri($target, $url);
				}
				if ($target == $url && $html) {
					lkt_get_gen_link_preview($url, $html);
				}
				$redirs[$url] = $target;
				$origin_urls[$target] = $origin_urls[$url];
			} else {
				$redirs[$url] = false;
			}

			curl_multi_remove_handle($mh, $ch);
		} else	$redirs[$url] = false;
	}

	curl_multi_close($mh);

	return array($redirs, $deferred_urls);
}

function lkt_check_canonicalise_path($path) {
	do {
		$new_path = str_replace('//', '/', $path);
		$old_path = $path;
		$path = $new_path;
	} while ($path != $old_path);

	$arr = explode('/', $path);
	$i = 0;
	while ($i < count($arr)) {
		$dot     = ($arr[$i] ==  '.');
		$dbl_dot = ($arr[$i] == '..');
		if ($dot || $dbl_dot) {
			array_splice($arr, $i, 1);
			if ($dbl_dot) {
				$i--;
				if ($i >= 0 && !($i == 0 && $arr[0] == '')) {
					array_splice($arr, $i, 1);
				}
				if ($i < 0) $i = 0;
			}
			continue;
		}
		$i++;
	}

	return implode('/', $arr);
}

function lkt_check_absolutise_relative_uri($target, $source) {
	if (lkt_get_scheme($target) == '' && substr($target, 0, 2) != '//') {
		// The target is a relative URI without a protocol and domain.
		// Add in the protocol and domain.
		$parsed_src = lkt_parse_url($source);
		$prepend = '';
		if (!empty($parsed_src['scheme'])) {
			$prepend .=  $parsed_src['scheme'].'://';
		}
		$prepend .= $parsed_src['host'];

		// Now check whether or not it is root-based.
		// If not, start the target's path in the same
		// directory as the source.
		if ($target[0] != '/') {
			$dir = $parsed_src['path'];
			if ($dir[-1] != '/') $dir = dirname($dir);
			$prepend .= $dir;
			if ($dir != '/') $prepend .= '/';
		}

		$target = $prepend.$target;
	}

	return $target;
}

function lkt_get_url_term_redirs_auto($urls) {
	static $repl_regexes = false;

	if ($repl_regexes === false) {
		$repl_regexes = include MYBB_ROOT.'/inc/plugins/'.C_LKT.'/auto-term-links.php';
		$custfname = MYBB_ROOT.'/inc/plugins/'.C_LKT.'/auto-term-links-custom.php';
		if (is_readable($custfname)) {
			$repl_regexes = array_merge($repl_regexes, include $custfname);
		}
	}

	$redirs = [];

	foreach ($urls as $url) {
		$url = trim($url);
		foreach ($repl_regexes as $search => $replace) {
			if (preg_match($search, $url)) {
				$redirs[$url] = preg_replace($search, $replace, $url);
				$redirs[$redirs[$url]] = $redirs[$url];
				break;
			}
		}
	}

	return $redirs;
}

function lkt_get_linkhelper_classnames() {
	static $LinkHelperClassNames = false;

	require_once __DIR__.'/linktools/LinkHelperBase.php';

	if (!$LinkHelperClassNames) {
		$LinkHelperClassNames = array('3p' => array(), 'dist' => array());
		foreach (array('link-helpers-3rd-party', 'link-helpers-dist') as $subdir) {
			foreach (new DirectoryIterator(__DIR__.'/linktools/'.$subdir) as $file) {
				if ($file->isDot()) {
					continue;
				}
				$filepath = __DIR__.'/linktools/'.$subdir.'/'.$file->getFilename();
				$helper_classname = $file->getBasename('.php');
				require_once $filepath;
				$LinkHelperClassNames[$subdir == 'link-helpers-3rd-party' ? '3p' : 'dist'][] = $helper_classname;
			}
		}
	}

	return $LinkHelperClassNames;
}

/**
 * @param string $term_url A terminating URL.
 * @return Mixed.
 *         Boolean False if a preview is not required for the supplied URL.
 *         Null If a preview is required and a valid one has been retrieved from
 *           the DB, in which case the supplied parameter $preview is set to the
 *           retrieved preview.
 *         Integer. -1 if $manual_regen was set true but it is too soon since
 *           the last regen to perform another one.
 *         String. The name of the prioritised Helper class to generate the
 *           preview if a preview is required but a valid one does not exist in
 *           the DB.
 */
function lkt_url_has_needs_preview($term_url, &$preview, &$has_db_entry, $manual_regen = false) {
	global $db, $mybb, $cache;

	$has_db_entry = null;

	if (!in_array(lkt_get_scheme($term_url), array('http', 'https', ''))) {
		return false;
	}

	// First, check settings to determine whether we need a preview for this type of URL.
	if (!$mybb->settings[C_LKT.'_link_preview_disable_self_dom'] && lkt_get_norm_server_from_url($term_url) == lkt_get_norm_server_from_url($mybb->settings['bburl'])) {
		return false;
	}
	switch ($mybb->settings[C_LKT.'_link_preview_type']) {
		case 'none':
			return false;
		case 'whitelist':
		case 'blacklist':
			$list = preg_split('/\r\n|\n|\r/', $mybb->settings[C_LKT.'_link_preview_dom_list']);
			$whitelisting = $mybb->settings[C_LKT.'_link_preview_type'] == 'whitelist';
			if ($whitelisting && !$list) {
				return false;
			}
			$ret = !$whitelisting;
			foreach ($list as $domain) {
				$listed_domain = lkt_normalise_domain($domain);
				$url_domain = lkt_get_norm_server_from_url($term_url);
				if ($listed_domain && $listed_domain == $url_domain) {
					$ret = $whitelisting;
					break;
				}
			}
			if ($ret === false) {
				return false;
			}
			break;
		case 'all':
		default:
			// Preview needed.
			break;
	}
	$on_the_fly = true;
	if (!$manual_regen) {
		if (THIS_SCRIPT == 'showthread.php') {
			switch ($mybb->settings[C_LKT.'_link_preview_on_fly']) {
			case 'always':
				$on_the_fly = true;
				break;
			case 'never':
				$on_the_fly = false;
				break;
			case 'whitelist':
			case 'blacklist':
				$list = preg_split('/\r\n|\n|\r/', $mybb->settings[C_LKT.'_link_preview_on_fly_dom_list']);
				$whitelisting = $mybb->settings[C_LKT.'_link_preview_on_fly'] == 'whitelist';
				if ($whitelisting && !$list) {
					$on_the_fly = false;
				}
				$on_the_fly = !$whitelisting;
				foreach ($list as $domain) {
					$listed_domain = lkt_normalise_domain($domain);
					$url_domain = lkt_get_norm_server_from_url($term_url);
					if ($listed_domain && $listed_domain == $url_domain) {
						$on_the_fly = $whitelisting;
						break;
					}
				}
				break;
			}
		}
	}

	// Next, get all LinkHelper classes.
	$LinkHelperClassNames = lkt_get_linkhelper_classnames();

	// Load all installed helpers.
	$lrs_plugins = $cache->read('lrs_plugins');
	$inst_helpers = !empty($lrs_plugins[C_LKT]['installed_link_helpers'])
	                  ? $lrs_plugins[C_LKT]['installed_link_helpers']
	                  : array();

	// Now, get the highest-prioritised LinkHelper class for this link type.
	$max_priority = PHP_INT_MIN;
	$priority_helper_classname = '';
	$types = array('3p', 'dist');
	foreach ($types as $helper_type) {
		// Third-party helpers are prioritised over those in the plugin's base distribution.
		if ($helper_type == 'dist' && $priority_helper_classname != '') {
			break;
		}
		foreach ($LinkHelperClassNames[$helper_type] as $helper_class_name) {
			if (!empty($inst_helpers[$helper_class_name]['enabled'])
			    &&
			    $helper_class_name::supports_link($term_url)
			    &&
			    // We use >= because the default link helper has a
			    // priority which equals the initial value of
			    // $max_priority set above.
			    $helper_class_name::get_priority() >= $max_priority
			) {
				$max_priority = $helper_class_name::get_priority();
				$priority_helper_classname = $helper_class_name;
			}
		}
	}

	$regen = false;

	// Now, check whether the preview already exists, is valid, has not
	// yet expired, and is not invalid due to having been generated by a
	// no-longer-prioritised Helper or an earlier version of the
	// still-prioritised Helper (when the relevant plugin setting is
	// enabled).
	$url_norm = lkt_normalise_url($term_url);
	$query = $db->simple_select('url_previews', 'valid, dateline, helper_class_name, helper_class_vers, preview', "url_norm = '".$db->escape_string($url_norm)."'");
	$row = $db->fetch_array($query);
	$has_db_entry = $row ? true : false;
	if ($manual_regen === 'force_regen') {
		$regen = true;
	} else if ($row) {
		if ($manual_regen) {
			$min_wait = lkt_preview_regen_min_wait_secs;
			if (TIME_NOW <= $row['dateline'] + $min_wait) {
				return -1;
			} else	$regen = true;
		} else {
			$expiry_period = $mybb->settings[C_LKT.'_link_preview_expiry_period'];
			$regen = (!$row['valid'] || $expiry_period && $expiry_period * 24*60*60 < TIME_NOW - $row['dateline']);
			if (!$regen && $mybb->settings[C_LKT.'_link_preview_expire_on_new_helper']) {
				$org_helper = $row['helper_class_name'];
				$regen = ($org_helper != $priority_helper_classname || $org_helper::get_version() != $row['helper_class_vers']);
			}
			if (!$regen) {
				$preview = $row['preview'];
			}
			if ($regen && !$on_the_fly) {
				$regen = false;
				$preview = $row['preview'];
			}
		}
	} else	$regen = $on_the_fly;

	// Earlier returns possible.
	return $regen ? $priority_helper_classname : null;
}

/**
 * @param $term_urls Array. Keys are non-normalised URLs; values are their
 *                          non-normalised terminating URLs.
 *
 * @return Array. Keys are non-normalised URLs as supplied in $term_urls;
 *                values are the previews of the corresponding terminating URLs
 *                also as supplied in $term_urls.
 */
function lkt_get_gen_link_previews($term_urls, $force_regen = false) {
	global $db;

	$previews = $lh_data = array();
	$term_urls_uniq = array_values(array_unique($term_urls));
	$norm_term_urls = array();

	foreach ($term_urls_uniq as $term_url) {
		$norm_term_url = lkt_normalise_url($term_url);
		if (!empty($norm_term_urls[$norm_term_url])) {
			continue;
		}
		// There is room for optimisation here: potentially, a database
		// query is made here on each iteration of the loop, which is
		// inefficient.
		$res = lkt_url_has_needs_preview($term_url, $preview, $has_db_entry, $force_regen ? 'force_regen' : false);
		if (is_null($res)) {
			$previews[$term_url] = $preview;
		} else if ($res) {
			$lh_data[$term_url] = array(
				'lh_classname' => $res,
				'has_db_entry' => $has_db_entry
			);
		}
		unset($preview);
		$norm_term_urls[$norm_term_url] = true;
	}
	if ($lh_data) {
		$server_urls = array();
		foreach (array_keys($lh_data) as $url) {
			$server = lkt_get_norm_server_from_url($url);
			if (!isset($server_urls[$server])) {
				$server_urls[$server] = array('last_hit' => 0, 'urls' => array());
			}
			$server_urls[$server]['urls'][] = $url;
		}

		$i = -1;
		do {
			$qry_urls = array();
			$min_wait = PHP_INT_MAX;
			$ts_now = microtime(true);
			foreach ($server_urls as $server => &$val) {
				$srv_urls =& $val['urls'    ];
				if (!$srv_urls) {
					continue;
				}
				$last_hit =  $val['last_hit'];
				if ($last_hit == 0) {
					$server_wait = -1;
				} else {
					$time_since = $ts_now - $last_hit;
					$server_wait = lkt_rehit_delay_in_secs - $time_since;
				}
				if ($server_wait < 0) {
					if (count($server_urls) > 0) {
						$qry_urls[] = array_shift($srv_urls);
					}
				} else	$min_wait = min($min_wait, $server_wait);
			}
			unset($val);
			unset($srv_urls);
			if ($qry_urls) {
				$curl_handles = array();

				if (($mh = curl_multi_init()) === false) {
					return false;
				}

				foreach ($qry_urls as $url) {
					if (($ch = curl_init()) === false) {
						return false;
					}

					// Strip from any # in the URL onwards because URLs with fragments
					// appear to be buggy either in certain older versions of cURL and/or
					// web server environments from which cURL is called.
					list($url_trim_nohash) = explode('#', trim($url), 2);

					if (!curl_setopt_array($ch, array(
						CURLOPT_URL            => $url_trim_nohash,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_HEADER         => true,
						CURLOPT_TIMEOUT        => lkt_curl_timeout,
						CURLOPT_USERAGENT      => 'The MyBB Link Tools plugin',
					))) {
						curl_close($ch);
						return false;
					}
					if (curl_multi_add_handle($mh, $ch) !== CURLM_OK/*==0*/) {
						return false;
					}
					$curl_handles[$url] = $ch;
				}

				$active = null;
				do {
					$mrc = curl_multi_exec($mh, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);

				while ($active && $mrc == CURLM_OK) {
					if (curl_multi_select($mh) != -1) {
						do {
							$mrc = curl_multi_exec($mh, $active);
						} while ($mrc == CURLM_CALL_MULTI_PERFORM);
					}
				}

				$ts_now2 = microtime(true);
				foreach ($curl_handles as $url => $ch) {
					if ($ch) {
						$content = curl_multi_getcontent($ch);
						$server_urls[lkt_get_norm_server_from_url($url)]['last_hit'] = $ts_now2;
						if ($content
						    &&
						    ($header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE)) !== false
						     &&
						     ($response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) !== false
						   ) {
							$html = substr($content, $header_size);
							$previews[$url] = lkt_get_gen_link_preview($url, $html, $lh_data[$url]['lh_classname'], $lh_data[$url]['has_db_entry']);
						}
						curl_multi_remove_handle($mh, $ch);
					}
				}

				curl_multi_close($mh);

			} else if ($min_wait < PHP_INT_MAX) {
				usleep($min_wait * 1000000);
			}
			$i++;
		// The condition with $i is simply defensive programming in case
		// this loop accidentally otherwise becomes infinite.
		} while ($i <= count($term_urls) && ($qry_urls || $min_wait < PHP_INT_MAX && $min_wait > 0));
	}

	$ret = array();
	foreach ($term_urls as $url => $term_url) {
		$ret[$url] = $previews[$term_url];
	}

	return $ret;
}

/**
 * Get the preview for a link, first (re)generating it and storing to the DB if
 * appropriate/necessary. If a Link Helper class name is provided, it is assumed
 * the check for whether a link needs to be (re)generated has already been
 * performed, and resulted in a need for (re)generation via the Link Helper with
 * the provided class name. If, additionally, $has_db_entry is set true, then it
 * is assumed that a database entry for the link already exists, and so an
 * update query is performed rather than an insert query.
 */
function lkt_get_gen_link_preview($term_url, $html, $lh_classname = false, $has_db_entry = null) {
	global $db;

	if (!$lh_classname) {
		$res = lkt_url_has_needs_preview($term_url, $preview, $has_db_entry);
	} else	$res = $lh_classname;

	if ($res === false) {
		return false;
	} else if ($res) {
		// (Re)generate the preview and return it.
		$priority_helper_classname = $res;
		$url_norm = lkt_normalise_url($term_url);
		$helper = $priority_helper_classname::get_instance();
		$preview = $helper->get_preview($term_url, $html);
		$fields = array(
			'valid' => '1',
			'dateline' => TIME_NOW,
			'helper_class_name' => $db->escape_string($priority_helper_classname),
			'helper_class_vers' => $db->escape_string($priority_helper_classname::get_version()),
			'preview' => $db->escape_string($preview),
		);
		if ($has_db_entry) {
			$db->update_query('url_previews', $fields, "url_norm = '".$db->escape_string($url_norm)."'");
		} else {
			$fields['url_norm'] = $db->escape_string($url_norm);
			$db->insert_query('url_previews', $fields);
		}
	} // else $preview was set in the second argument to the call to
	//   lkt_url_has_needs_preview() above.

	return $preview;
}

/**
 * Resolves and returns the terminating redirect targets (i.e., after following as many
 * redirects as possible) for each URL in $urls.
 *
 * Tries the HEAD method first to save bandwidth but if that results in an error, then
 * retries using the GET method.
 *
 * This function only makes sense for $urls with a protocol of http:// or https://.
 *
 * @param $urls Array.
 * @return An array indexed by each URL in $urls. Each entry is either:
 *         1. The URL's terminating redirect target (which might be itself).
 *         2. False in the case that a link-specific error occurred, e.g. web server timeout
 *            or redirect loop.
 *         3. Null in the case that a non-link-specific error occurred, such as failure to
 *            initialise a generic cURL handle.
 */
function lkt_get_url_term_redirs($urls, &$got_terms = array(), $get_link_previews = true) {
	$terms = $redirs = $server_urls = $server_last_hit_times = $to_retry = array();
	static $min_wait_flag_value = 99999;
	static $want_use_head_method = lkt_use_head_method && !(lkt_check_for_canonical_tags || lkt_check_for_html_redirects);

	if (!$urls) return $terms;

	$ts_start = time();

	$origin_urls = array_combine($urls, $urls);

	foreach ($urls as $url) {
		$norm_server = lkt_get_norm_server_from_url($url);
		if (!isset($server_urls[$norm_server])) {
			$server_urls[$norm_server] = 0;
		}
		$server_urls[$norm_server]++;
	}
	$max_urls_for_a_server = 1;
	foreach ($server_urls as $cnt) {
		$max_urls_for_a_server = max($max_urls_for_a_server, $cnt);
	}
	$num_servers = count($server_urls);

	$redirs = lkt_get_url_term_redirs_auto($urls);
	if ($get_link_previews) {
		lkt_get_gen_link_previews($redirs);
	}

	$use_head_method = $want_use_head_method;
	list($redirs2, $deferred_urls) = lkt_get_url_redirs(array_diff($urls, array_keys($redirs)), $server_last_hit_times, $origin_urls, $use_head_method, lkt_check_for_html_redirects, lkt_check_for_canonical_tags, $get_link_previews);
	if ($redirs2 === false && !$redirs) {
		return false;
	}

	$redirs = array_merge($redirs, $redirs2);

	// Defensive programming: in case this loop somehow becomes infinite,
	// terminate it based on:
	//  1. A roughly-determined (and very generous) maximum iteration count.
	//  2. A maximum run time of lkt_max_allowable_redirect_resolution_runtime_secs seconds.
	//  3. A maximum per-url redirect count of lkt_max_allowable_redirects_for_a_url.
	$max_iterations = $max_urls_for_a_server * ($num_servers < 5 ? 5 : $num_servers) * floor(lkt_max_allowable_redirects_for_a_url/3);
	$num_iterations = 0;
	$ts_max = $ts_start + lkt_max_allowable_redirect_resolution_runtime_secs;
	do {
		$max_num_redirected_for_a_url = 0;
		$max_url = null;
		foreach (array_count_values($origin_urls) as $url => $cnt) {
			if ($cnt > $max_num_redirected_for_a_url) {
				$max_url = $url;
				$max_num_redirected_for_a_url = $cnt - 1; // We subtract one because the original URL itself is included in the count of "redirects".
			}
		}
		if ($max_num_redirected_for_a_url > lkt_max_allowable_redirects_for_a_url) {
			break;
		}

		$urls2 = $to_retry = [];
		foreach ($redirs as $url => $target) {
			if ($target && $target !== -1 && !isset($redirs[$target])) {
				$urls2[] = $target;
			} else if ($target === false && $use_head_method) {
				$to_retry[] = $url;
			}
		}
		$urls2 = array_values(array_unique(array_merge($urls2, $deferred_urls)));
		if (!$urls2 && $to_retry) {
			$use_head_method = false;
			$urls2 = $to_retry;
		}

		$min_wait = $min_wait_flag_value;
		$ts_now = microtime(true);
 		foreach ($urls2 as $url) {
			$server = lkt_get_norm_server_from_url($url);
			if (!$server || !isset($server_last_hit_times[$server])) {
				$min_wait = 0;
				break;
			} else {
				$time_since = $ts_now - $server_last_hit_times[$server];
				$server_wait = lkt_rehit_delay_in_secs - $time_since;
				if ($server_wait < 0) {
					$min_wait = 0;
					break;
				} else {
					$min_wait = min($min_wait, $server_wait);
				}
			}
		}
		if ($min_wait == $min_wait_flag_value) $min_wait = 0;

		usleep($min_wait * 1000000);

		list($redirs2, $deferred_urls) = lkt_get_url_redirs($urls2, $server_last_hit_times, $origin_urls, $use_head_method, lkt_check_for_html_redirects, lkt_check_for_canonical_tags);
		if ($redirs2 === false) {
			return false;
		}
		if ($to_retry) foreach ($redirs2 as $url => &$target) {
			if ($target === false && in_array($url, $to_retry)) {
				$target = -1; // Don't retry more than once (retry is only on false, not -1).
			}
		}
		unset($target);

		$redirs = array_merge($redirs, $redirs2);

		$use_head_method = $want_use_head_method;
		$num_iterations++;
	} while (($urls2 || $deferred_urls) && $num_iterations < $max_iterations && time() < $ts_max);

	foreach ($redirs as $url => &$target) {
		if ($target === -1) {
			$target = false;
		}
	}
	unset($target);

	foreach ($urls as $url) {
		$term = $url;
		$seen = [$term => true];
		while ($term && $redirs[$term] && $term != $redirs[$term]) {
			$term = $redirs[$term];
			// Abort on redirect loop.
			if (isset($seen[$term])) {
				$term = false;
				break;
			}
			$seen[$term] = true;
		}
		$terms[$url] = $term;

		// If we broke out of the main loop due to a timeout,
		// we might not have a terminating redirect,
		// i.e., one in which the final entry has the
		// same key as value. In that case, indicate to the
		// calling function that got_term should be set to
		// zero in the database.
		$got_terms[$url] = ($term == $redirs[$term]);
	}

	return $terms;
}

/**
 * Parses the URL with the PHP builtin function. One catch is that the builtin
 * does not handle intuitively those "URLs" without a scheme such as the following,
 * which MyBB core's auto-linker detects:
 *     www.example.com/somepath/file.html
 * though it does handle intuitively the same URL prefixed with a double forward slash.
 *
 * For the example "URL" above, instead of setting 'host' to 'www.example.com', and
 * 'path' to '/somepath/file.html', parse_url() does not set 'host' at all, and
 * sets 'path' to 'example.com/somepath/file.html'.
 *
 * To avoid this, we prefix the "URL" with an http scheme in that scenario, and then
 * strip it back out of the parsed result.
 */
function lkt_parse_url($url) {
	$tmp_url = trim($url);
	$scheme = lkt_get_scheme($url);
	$scheme_is_missing = ($scheme == '' && substr($url, 0, 2) != '//');
	if ($scheme_is_missing) {
		$tmp_url = 'http://'.$tmp_url;
	}
	$parsed_url = parse_url($tmp_url);
	if ($scheme_is_missing) {
		$parsed_url['scheme'] = '';
	}
	return $parsed_url;
}

function lkt_normalise_domain($domain) {
	static $prefix = 'www.';

	$domain = strtolower(trim($domain));
	while (strpos($domain, $prefix) === 0) {
		$domain = substr($domain, strlen($prefix));
	}

	return $domain;
}


function lkt_normalise_url($url) {
	static $ignored_query_params = false;

	if ($ignored_query_params === false) {
		$ignored_query_params = include MYBB_ROOT.'/inc/plugins/'.C_LKT.'/ignored-query-params.php';
		$custfname = MYBB_ROOT.'/inc/plugins/'.C_LKT.'/ignored-query-params-custom.php';
		if (is_readable($custfname)) {
			$ignored_query_params = array_merge($ignored_query_params, include $custfname);
		}
	}

	$strip_www_prefix = false;

	$url = trim($url);

	$parsed_url = lkt_parse_url($url);
	$parsed_url['scheme'] = strtolower($parsed_url['scheme']);

	switch ($parsed_url['scheme']) {
	case 'http':
	case 'https':
	case '':
		$ret = 'http(s)://';
		$strip_www_prefix = true;
		$default_ports = array(80, 443);
		break;
	case 'ftp':
	case 'sftp':
		$ret = '(s)ftp://';
		$default_ports = array(21, 22);
		break;
	default:
		// Assume that $urls_parsed was generated from lkt_has_valid_scheme() and
		// thus that the scheme has already been validated.
		//
		// We shouldn't reach here though - the case statements above should
		// comprehensively cover all entries in lkt_valid_schemes.
		$ret = $parsed_url['scheme'].'://';
		$default_ports = array();
		break;
	}

	$domain = strtolower($parsed_url['host']);
	if ($strip_www_prefix) {
		$domain = lkt_normalise_domain($domain);
	}

	$ret .= $domain;

	if (isset($parsed_url['port']) && !in_array($parsed_url['port'], $default_ports)) {
		$ret .= ':'.$parsed_url['port'];
	}

	$ret .= ($parsed_url['path'] == '' ? '/' : lkt_check_canonicalise_path($parsed_url['path']));

	if (isset($parsed_url['query'])) {
		$query = str_replace('&amp;', '&', $parsed_url['query']);
		$arr = explode('&', $query);
		sort($arr);
		foreach ($ignored_query_params as $param => $domains) {
			if (is_int($param)) {
				$param = $domains;
				$domains = '*';
			}
			if (!(!is_array($domains) && trim($domains) === '*')) {
				$domains = (array)$domains;
				foreach ($domains as &$dom) {
					$dom = lkt_normalise_domain($dom);
				}
				if (!in_array($domain, $domains)) {
					continue;
				}
			}

			$found = false;
			if (strpos($param, '=') === false) {
				for ($idx = 0; $idx < count($arr); $idx++) {
					list($key) = explode('=', $arr[$idx], 2);
					if ($key === $param) {
						$found = true;
						break;
					}
				}
			} else if (($idx = array_search($param, $arr)) !== false) {
				$found = true;
			}
			if ($found) {
				array_splice($arr, $idx, 1);
				continue;
			}
		}
		if ($arr) $ret .= '?'.implode('&', $arr);
	}

	return $ret; // We discard user, password, and fragment.
}

/**
 * This hook-in is called once before the thread is deleted (when the posts are
 * still present in the database) and once afterwards (when they've been deleted).
 * We store the to-be-deleted posts' pids on the first call, and then use them on
 * the second to delete associated entries in the post_urls and urls tables - we
 * do it this way so that we can be sure that the posts are actually deleted
 * before we delete their associated database entries managed by this plugin,
 * and we can't get their pids any other way on the second call because all we
 * have is a tid whose posts entries have all been deleted.
 */
function lkt_hookin__common__class_moderation_delete_thread($tid) {
	static $tid_stored = null;
	static $pids_stored = null;
	global $db;

	if ($tid_stored === null) {
		$tid_stored = $tid;
		$query = $db->simple_select('posts', 'pid', "tid='$tid'");
		$pids_stored = array();
		while ($post = $db->fetch_array($query)) {
			$pids_stored[] = $post['pid'];
		}
	} else if ($pids_stored) {
		$pids = implode(',', $pids_stored);
		$db->delete_query('post_urls', "pid IN ($pids)");
//		lkt_clean_up_dangling_urls();

		$tid_stored = $pids_stored = null;
	}
}

function lkt_hookin__class_moderation_delete_post($pid) {
	global $db;

	$db->delete_query('post_urls', "pid=$pid");
//	lkt_clean_up_dangling_urls();
}

function lkt_clean_up_dangling_urls() {
	global $db;

	// We nest the subquery in a redundant subquery because otherwise MySQL (but not MariaDB)
	// errors out with this message:
	// "SQL Error: 1093 - You can't specify target table 'mybb_urls' for update in FROM clause"
	// This inner nesting technique was discovered here: https://stackoverflow.com/a/45498
	$db->write_query('
DELETE FROM '.TABLE_PREFIX.'urls
WHERE urlid in (
  SELECT urlid FROM (
    SELECT urlid FROM '.TABLE_PREFIX.'urls u
    WHERE 0 = (
      SELECT count(*) FROM '.TABLE_PREFIX.'post_urls pu
      WHERE pu.urlid = u.urlid)
  ) AS nested
)');
}

function lkt_hookin__datahandler_post_update($posthandler) {
	global $db;

	$db->update_query('posts', array('lkt_got_urls' => 0), "pid={$posthandler->pid}");
}

function lkt_hookin__datahandler_post_update_end($posthandler) {
	global $db;

	$db->delete_query('post_urls', "pid={$posthandler->pid}");
	if (isset($posthandler->data['message'])) {
		lkt_get_and_add_urls_of_post($posthandler->data['message'], $posthandler->pid);
	}
}

function lkt_extract_and_store_urls_for_posts($num_posts) {
	global $db;

	$res = $db->simple_select('posts', 'pid, message', 'lkt_got_urls = FALSE', array(
		'order_by'    => 'pid',
		'order_dir'   => 'ASC',
		'limit'       => $num_posts,
	));
	$inc = $db->num_rows($res);

	$post_urls = $urls_all = [];
	while (($post = $db->fetch_array($res))) {
		$urls = lkt_extract_urls($post['message']);
		$post_urls[$post['pid']] = $urls;
		$urls_all = array_merge($urls_all, $urls);
	}
	$urls_all = array_values(array_unique($urls_all));
	$db->free_result($res);

	$redirs = array_combine($urls_all, array_fill(0, count($urls_all), false));
	foreach ($post_urls as $pid => $urls) {
		lkt_add_urls_for_pid($urls, $redirs, false, $pid);
	}

	return $inc;
}

function lkt_get_sql_conds_for_ltt() {
	$conds = 'got_term = FALSE';
	$conds_ltt = '';
	foreach (lkt_term_tries_secs as $i => $secs) {
		if ($conds_ltt) $conds_ltt .= ' OR ';
		$conds_ltt .= '(term_tries = '.$i.' AND last_term_try + '.lkt_term_tries_secs[$i].' < '.time().')';
	}
	if ($conds_ltt) $conds .= ' AND ('.$conds_ltt.')';

	return $conds;
}

function lkt_get_and_store_terms($num_urls, $retrieve_count = false, &$count = 0) {
	global $db, $mybb;

lkt_get_and_store_terms_start:
	$servers = $servers_sought = $servers_tot = $urls_final = $ids = [];

	// The next one hundred or so lines of code ensure that the ratio of the numbers
	// of URLs from different servers in our urls to be polled is the same as that of
	// as-yet unresolved URLs in the database.
	//
	// Why? Because, given that we only make only one request of each server at a time,
	// and pause between successive requests to that server, this optimises the total
	// runtime of all operations - or, at least, that's my understanding unless/until
	// somebody corrects me.
	$conds = '('.lkt_get_sql_conds_for_ltt().') AND '.time().' > lock_time + '.lkt_max_url_lock_time;
	$start = 0;
	$continue = true;
	while ($continue) {
		$res = $db->simple_select(
			'urls',
			'url',
			$conds,
			array(
				'limit_start' => $start,
				'limit' => lkt_urls_limit_for_get_and_store_terms
			),
			array(
				'order_by' => 'urlid',
				'order_dir' => 'ASC'
			)
		);

		$continue = false;
		while ($row = $db->fetch_array($res)) {
			$norm_server = lkt_get_norm_server_from_url($row['url']);
			if (!$norm_server) {
				$norm_server = '';
			}
			$continue = true;
			$count++;
			$start++;
			if (!isset($servers_tot[$norm_server])) {
				$servers_tot[$norm_server] = 0;
			}
			$servers_tot[$norm_server]++;
		}
	}
	asort($servers_tot);
	$servers_tot = array_reverse($servers_tot);
	$sought_cnt = 0;
	foreach ($servers_tot as $server => $cnt) {
		if ($count <= $num_urls) {
			$x = $cnt;
		} else {
			$x = ceil($cnt * $num_urls / $count);
		}
		$servers_sought[$server] = $x;
		$sought_cnt += $x;
		$servers[$server] = 0;
		if ($sought_cnt >= $num_urls) {
			break;
		}
	}

	$start = 0;
	$num_got = 0;
	do {
		if ($start > 0) {
			$done = true;
			foreach ($servers_sought as $server => $cnt) {
				if ($servers[$server] < $cnt) {
					$done = false;
					break;
				}
			}
			if ($done) break;
		}
		$urls_new = array();
		$res = $db->simple_select(
			'urls',
			'url, urlid',
			$conds,
			array(
				'limit_start' => $start,
				'limit' => $num_urls
			),
			array(
				'order_by' => 'urlid',
				'order_dir' => 'ASC'
			)
		);
		while (($row = $db->fetch_array($res))) {
			$urls_new[] = $row['url'];
			$ids[$row['url']] = $row['urlid'];
		}

		if (!$urls_new) break;

		foreach ($urls_new as $url1) {
			$norm_server = lkt_get_norm_server_from_url($url1);
			if (!$norm_server) {
				$norm_server = '';
			}
			if (array_key_exists($norm_server, $servers_sought) && $servers[$norm_server] < $servers_sought[$norm_server]) {
				$urls_final[] = $url1;
				if (!isset($servers[$norm_server])) {
					$servers[$norm_server] = 0;
				}
				$servers[$norm_server]++;
				$num_got++;
			}
			if ($num_got >= $num_urls) {
				break;
			}
		}

		$start += $num_urls;

	} while (count($urls_new) >= $num_urls);

	// Lock the relevant rows in the urls table for two minutes (lkt_max_url_lock_time).
	// If we find that they have ALL already been locked (by some other process also
	// accessing this function) in between the above and now, then go back to the
	// beginning of this function and try again (to find any other unlocked urls).
	$now = time();
	$cnt = 0;
	foreach ($urls_final as $url) {
		if ($db->write_query('
UPDATE '.TABLE_PREFIX.'urls SET lock_time = '.$now.'
WHERE url = \''.$db->escape_string($url).'\' AND '.
      $now.' > lock_time + '.lkt_max_url_lock_time)
		    &&
		    $db->affected_rows() >= 1) {
			$cnt++;
		}
	}
	if (count($urls_final) > 0 && $cnt <= 0) {
		goto lkt_get_and_store_terms_start;
	}

	$terms = lkt_get_url_term_redirs($urls_final, $got_terms);
	if ($terms) {
		// Reopen the DB connection in case it has "gone away" given the potentially long delay while
		// we resolved redirects. This was occurring at times on our (Psience Quest's) host, Hostgator.
		$db->close();
		$db->connect($mybb->config['database']);
		foreach ($terms as $url => $term) {
			if ($term !== null) {
				if ($term === false) {
					$db->write_query('UPDATE '.TABLE_PREFIX.'urls SET term_tries = term_tries + 1, last_term_try = '.time().', lock_time = 0 WHERE urlid='.$ids[$url]);
				} else  {
					$term_fit      = substr($term                   , 0, lkt_max_url_len);
					$term_norm_fit = substr(lkt_normalise_url($term), 0, lkt_max_url_len);
					$fields = array(
						'url_term'      => $db->escape_string($term_fit     ),
						'url_term_norm' => $db->escape_string($term_norm_fit),
						'got_term'      => 1,
						'lock_time'     => 0,
					);
					$db->update_query('urls', $fields, 'urlid = '.$ids[$url]);
				}
			}

		}
	}

	return count($urls_final);
}

function lkt_hookin__admin_tools_recount_rebuild_output_list() {
	global $lang, $form_container, $form;
	if (!isset($lang->linktools)) {
		$lang->load(C_LKT);
	}

	$form_container->output_cell("<label>{$lang->lkt_rebuild_links}</label><div class=\"description\">{$lang->lkt_rebuild_links_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field('lkt_posts_per_page', lkt_default_rebuild_links_items_per_page, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array('name' => 'do_rebuild_links')));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->lkt_rebuild_terms}</label><div class=\"description\">{$lang->lkt_rebuild_terms_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field('lkt_urls_per_page', lkt_default_rebuild_term_items_per_page, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array('name' => 'do_rebuild_terms')));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->lkt_rebuild_renorm}</label><div class=\"description\">{$lang->lkt_rebuild_renorm_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field('lkt_renorm_per_page', lkt_default_rebuild_renorm_items_per_page, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array('name' => 'do_rebuild_renorm')));
	$form_container->construct_row();

	$form_container->output_cell("<label>{$lang->lkt_rebuild_linkpreviews}</label><div class=\"description\">{$lang->lkt_rebuild_linkpreviews_desc}</div>");
	$form_container->output_cell($form->generate_numeric_field('lkt_linkpreviews_per_page', lkt_default_rebuild_linkpreviews_items_per_page, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array('name' => 'do_rebuild_linkpreviews')));
	$form_container->construct_row();
}

function lkt_hookin__admin_tools_recount_rebuild() {
	global $db, $mybb, $lang;
	if (!isset($lang->linktools)) {
		$lang->load(C_LKT);
	}

	if($mybb->request_method == "post") {
		if (!isset($mybb->input['page']) || $mybb->get_input('page', MyBB::INPUT_INT) < 1) {
			$mybb->input['page'] = 1;
		}

		if (isset($mybb->input['do_rebuild_links'])) {
			if($mybb->input['page'] == 1) {
				// Log admin action
				log_admin_action($lang->lkt_success_rebuild_links);
			}

			if (!$mybb->get_input('lkt_posts_per_page', MyBB::INPUT_INT)) {
				$mybb->input['lkt_posts_per_page'] = lkt_default_rebuild_links_items_per_page;
			}

			$page = $mybb->get_input('page', MyBB::INPUT_INT);
			$per_page = $mybb->get_input('lkt_posts_per_page', MyBB::INPUT_INT);
			if ($per_page <= 0) {
				$per_page = lkt_default_rebuild_links_items_per_page;
			}

			if ($page == 1) {
				$db->update_query('posts', array('lkt_got_urls' => 0));
				$db->write_query('DELETE FROM '.TABLE_PREFIX.'post_urls');
				$db->write_query('DELETE FROM '.TABLE_PREFIX.'urls');
			}

			$inc = lkt_extract_and_store_urls_for_posts($per_page);

			$res = $db->simple_select('posts', 'count(*) AS num_posts', 'lkt_got_urls = FALSE');
			$num_left = $db->fetch_array($res)['num_posts'];

			// The first two parameters seem to be semantically switched within this function, so that's the way I've passed them.
			check_proceed($num_left, 0, ++$page, $per_page, 'lkt_posts_per_page', 'do_rebuild_links', $lang->lkt_success_rebuild_links);
		}

		if (isset($mybb->input['do_rebuild_terms'])) {
			if($mybb->input['page'] == 1) {
				// Log admin action
				log_admin_action($lang->lkt_admin_log_rebuild_terms);
			}

			if (!$mybb->get_input('lkt_urls_per_page', MyBB::INPUT_INT)) {
				$mybb->input['lkt_urls_per_page'] = lkt_default_rebuild_term_items_per_page;
			}

			$page = $mybb->get_input('page', MyBB::INPUT_INT);
			$per_page = $mybb->get_input('lkt_urls_per_page', MyBB::INPUT_INT);
			if ($per_page <= 0) {
				$per_page = lkt_default_rebuild_term_items_per_page;
			}

			if ($page == 1) {
				$db->write_query('UPDATE '.TABLE_PREFIX.'urls SET got_term = 0, term_tries = 0, last_term_try = 0, url_term = url, url_term_norm = url_norm');
			}

			$inc = lkt_get_and_store_terms($per_page, true, $finish);

			// The first two parameters seem to be semantically switched within this function, so that's the way I've passed them.
			check_proceed($finish, $inc, ++$page, $per_page, 'lkt_urls_per_page', 'do_rebuild_terms', $lang->lkt_success_rebuild_terms);
		}

		if (isset($mybb->input['do_rebuild_renorm'])) {
			if($mybb->input['page'] == 1) {
				// Log admin action
				log_admin_action($lang->lkt_admin_log_rebuild_renorm);
			}

			if (!$mybb->get_input('lkt_renorm_per_page', MyBB::INPUT_INT)) {
				$mybb->input['lkt_renorm_per_page'] = lkt_default_rebuild_renorm_items_per_page;
			}

			$page = $mybb->get_input('page', MyBB::INPUT_INT);
			$per_page = $mybb->get_input('lkt_renorm_per_page', MyBB::INPUT_INT);
			if ($per_page <= 0) {
				$per_page = lkt_default_rebuild_renorm_items_per_page;
			}

			$offset = ($page - 1) * $per_page;
			$res = $db->simple_select('urls', 'urlid, url, url_term', '', array(
				'order_by'    => 'urlid',
				'order_dir'   => 'ASC',
				'limit_start' => $offset,
				'limit'       => $per_page
			));
			$updates = array();
			while (($row = $db->fetch_array($res))) {
				$updates[$row['urlid']] = array(
					'url_norm'      => $db->escape_string(substr(lkt_normalise_url($row['url'     ]), 0, lkt_max_url_len)),
					'url_term_norm' => $db->escape_string(substr(lkt_normalise_url($row['url_term']), 0, lkt_max_url_len)),
				);
			}
			foreach ($updates as $urlid => $update_fields) {
				$db->update_query('urls', $update_fields, "urlid=$urlid");
			}
			$finish = $db->fetch_array($db->simple_select('urls', 'count(*) AS count'))['count'];

			// The first two parameters seem to be semantically switched within this function, so that's the way I've passed them.
			check_proceed($finish, $offset + $per_page, ++$page, $per_page, 'lkt_renorm_per_page', 'do_rebuild_renorm', $lang->lkt_success_rebuild_renorm);
		}

		if (isset($mybb->input['do_rebuild_linkpreviews'])) {
			if($mybb->input['page'] == 1) {
				// Log admin action
				log_admin_action($lang->lkt_admin_log_rebuild_linkpreviews);
			}

			if (!$mybb->get_input('lkt_linkpreviews_per_page', MyBB::INPUT_INT)) {
				$mybb->input['lkt_linkpreviews_per_page'] = lkt_default_rebuild_linkpreviews_items_per_page;
			}

			$page = $mybb->get_input('page', MyBB::INPUT_INT);
			$per_page = $mybb->get_input('lkt_linkpreviews_per_page', MyBB::INPUT_INT);
			if ($per_page <= 0) {
				$per_page = lkt_default_rebuild_linkpreviews_items_per_page;
			}

			$offset = ($page - 1) * $per_page;
			$res = $db->simple_select('urls', 'urlid, url, url_term', '', array(
				'order_by'    => 'urlid',
				'order_dir'   => 'ASC',
				'limit_start' => $offset,
				'limit'       => $per_page
			));
			$urls_term = array();
			while (($row = $db->fetch_array($res))) {
				$urls_term[$row['url']] = $row['url_term'];
			}

			lkt_get_gen_link_previews($urls_term, $mybb->settings[C_LKT.'_link_preview_rebuild_scope'] == 'all' ? true : false);

			$finish = $db->fetch_field($db->simple_select('urls', 'count(*) AS count'), 'count');

			// The first two parameters seem to be semantically switched within this function, so that's the way I've passed them.
			check_proceed($finish, $offset + $per_page, ++$page, $per_page, 'lkt_linkpreviews_per_page', 'do_rebuild_linkpreviews', $lang->lkt_success_rebuild_linkpreviews);
		}
	}
}

function lkt_hookin__datahandler_post_insert_thread($posthandler) {
	global $db, $mybb, $templates, $lang, $headerinclude, $header, $footer;

	if ($mybb->get_input('ignore_dup_link_warn') || $posthandler->data['savedraft'] ||
	    !($mybb->settings[C_LKT.'_enable_dlw'] && ($mybb->settings[C_LKT.'_force_dlw'] || $mybb->user['lkt_warn_about_links']))) {
		return;
	}

	if (!isset($lang->linktools)) {
		$lang->load(C_LKT);
	}

	$urls = lkt_extract_urls($posthandler->data['message']);
	if (!$urls) {
		return;
	}

	// Add any missing URLs to the DB after resolving redirects
	lkt_get_and_add_urls($urls);

	list($matching_posts, $forum_names, $further_results) = lkt_get_posts_for_urls($urls);

	$dismissed_arr = $mybb->get_input('lkt_dismissed') ? json_decode($mybb->get_input('lkt_dismissed'), true) : array();
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

	$matching_posts_warning_msg = $lang->sprintf(
	  $dismissed_arr
	    ? ($further_results
	       ? $lang->lkt_found_more_than_posts_count_undismissed
	       : $lang->lkt_found_posts_count_undismissed
	      )
	    : ($further_results
	       ? $lang->lkt_found_more_than_posts_count
	       : $lang->lkt_found_posts_count
	      ),
	  count($matching_posts),
	  count($matching_posts) == 1
	    ? $lang->lkt_post_singular
	    : $lang->lkt_posts_plural
	);
	eval("\$matching_posts_warning_div = \"".$templates->get('linktools_matching_posts_warning_div', 1, 0)."\";");

	$lkt_found_posts = '';
	foreach ($matching_posts as $post) {
		if ($lkt_found_posts) $lkt_found_posts .= '<br />'."\n";
		$lkt_found_posts .= lkt_get_post_output($post, $forum_names);
	}

	$inputs = '';
	foreach ($mybb->input as $key => $val) {
		$inputs .= '<input type="hidden" name="'.htmlspecialchars_uni($key).'" value="'.htmlspecialchars_uni($val).'" />'."\n";
	}

	$savedraftbutton = '';
	if ($mybb->user['uid']) {
		eval("\$savedraftbutton = \"".$templates->get('post_savedraftbutton', 1, 0)."\";");
	}

	eval("\$btns = \"".$templates->get('linktools_review_buttons', 1, 0)."\";");

	if ($all_matching_urls_in_quotes_flag) {
		eval("\$toggle_btn = \"".$templates->get('linktools_toggle_button', 1, 0)."\";");
	} else {
		$toggle_btn = '';
	}

	if ($further_results) {
		$urls_esc = '';
		$i = 0;
		foreach ($urls as $url) {
			if ($urls_esc) $urls_esc .= '&';
			$urls_esc .= 'urls['.$i.']='.urlencode($url);
			$i++;
		}
		$url_esc = htmlspecialchars('lkt_search.php?'.$urls_esc.'&showresults=posts');
		$further_results_below_div = '<div class="further-results">'.$lang->sprintf($lang->lkt_further_results_below, count($matching_posts), $url_esc).'</div>';
		$further_results_above_div = '<div class="further-results">'.$lang->sprintf($lang->lkt_further_results_above, count($matching_posts), $url_esc).'</div>';
	} else {
		$further_results_below_div = '';
		$further_results_above_div = '';
	}

	eval("\$op = \"".$templates->get('linktools_review_page', 1, 0)."\";");

	output_page($op);
	exit;
}

function lkt_hookin__newthread_start() {
	global $mybb, $lang, $templates, $linktools_div, $linktools_js;

	if (!$mybb->settings[C_LKT.'_enable_dlw']) {
		return;
	}

	if (!isset($lang->linktools)) {
		$lang->load(C_LKT);
	}

	$linktools_div = "\n";
	eval("\$linktools_div .= \"".$templates->get('linktools_div', 1, 0)."\";");
	$lkt_msg_started_by       = addslashes($lang->lkt_started_by);
	$lkt_msg_opening_post     = addslashes($lang->lkt_opening_post);
	$lkt_msg_non_opening_post = addslashes($lang->lkt_non_opening_post);
	$lkt_msg_posted_by        = addslashes($lang->lkt_posted_by);
	$lkt_msg_matching_url_singular = addslashes($lang->lkt_matching_url_singular);
	$lkt_msg_matching_urls_plural  = addslashes($lang->lkt_matching_urls_plural );
	$lkt_msg_url1_as_url2          = addslashes($lang->lkt_msg_url1_as_url2     );
	$lkt_exist_open_post_contains  = addslashes($lang->lkt_exist_open_post_contains);
	$lkt_exist_post_contains       = addslashes($lang->lkt_exist_post_contains  );
	$lkt_more_than                 = addslashes($lang->lkt_more_than            );
	$lkt_x_exist_open_posts_contain = addslashes($lang->lkt_x_exist_open_posts_contain);
	$lkt_x_exist_posts_contain     = addslashes($lang->lkt_x_exist_posts_contain);
	$lkt_x_of_urls_added           = addslashes($lang->lkt_x_of_urls_added      );
	$lkt_a_url_added               = addslashes($lang->lkt_a_url_added          );
	$lkt_one_is_an_opening_post    = addslashes($lang->lkt_one_is_an_opening_post);
	$lkt_x_are_opening_posts       = addslashes($lang->lkt_x_are_opening_posts  );
	$lkt_further_results_below     = addslashes($lang->lkt_further_results_below);
	$lkt_further_results_above     = addslashes($lang->lkt_further_results_above);
	$lkt_dismiss_warn_for_post     = addslashes($lang->lkt_dismiss_warn_for_post);
	$lkt_show_more                 = addslashes($lang->lkt_show_more            );
	$lkt_show_less                 = addslashes($lang->lkt_show_less            );
	$lkt_undismiss_all_warns       = addslashes($lang->lkt_undismiss_all_warns  );
	$lkt_title_warn_about_links    = addslashes($lang->lkt_title_warn_about_links);
	$lkt_warn_about_links          = addslashes($lang->lkt_warn_about_links     );
	$lkt_previously_dismissed = json_encode($mybb->get_input('lkt_dismissed') ? json_decode($mybb->get_input('lkt_dismissed'), true) : array(), JSON_PRETTY_PRINT);

	$linktools_js = <<<EOF
<script type="text/javascript" src="{$mybb->settings['bburl']}/jscripts/linktools.js"></script>
<script type="text/javascript">
var lkt_setting_warn_about_links  = {$mybb->user['lkt_warn_about_links']};
var lkt_setting_dlw_forced        = {$mybb->settings['linktools_force_dlw']};
var lkt_msg_started_by            = '{$lkt_msg_started_by}';
var lkt_msg_opening_post          = '{$lkt_msg_opening_post}';
var lkt_msg_non_opening_post      = '{$lkt_msg_non_opening_post}';
var lkt_msg_posted_by             = '{$lkt_msg_posted_by}';
var lkt_msg_matching_url_singular = '{$lkt_msg_matching_url_singular}';
var lkt_msg_matching_urls_plural  = '{$lkt_msg_matching_urls_plural}';
var lkt_msg_url1_as_url2          = '{$lkt_msg_url1_as_url2}';
var lkt_exist_open_post_contains  = '{$lkt_exist_open_post_contains}';
var lkt_exist_post_contains       = '{$lkt_exist_post_contains}';
var lkt_more_than                 = '{$lkt_more_than}';
var lkt_x_exist_open_posts_contain = '{$lkt_x_exist_open_posts_contain}';
var lkt_x_exist_posts_contain     = '{$lkt_x_exist_posts_contain}';
var lkt_x_of_urls_added           = '{$lkt_x_of_urls_added}';
var lkt_a_url_added               = '{$lkt_a_url_added}';
var lkt_one_is_an_opening_post    = '{$lkt_one_is_an_opening_post}';
var lkt_x_are_opening_posts       = '{$lkt_x_are_opening_posts}';
var lkt_further_results_below     = '{$lkt_further_results_below}';
var lkt_further_results_above     = '{$lkt_further_results_above}';
var lkt_dismiss_warn_for_post     = '{$lkt_dismiss_warn_for_post}';
var lkt_show_more                 = '{$lkt_show_more}';
var lkt_show_less                 = '{$lkt_show_less}';
var lkt_undismiss_all_warns       = '{$lkt_undismiss_all_warns}';
var lkt_title_warn_about_links    = '{$lkt_title_warn_about_links}';
var lkt_warn_about_links          = '{$lkt_warn_about_links}';
var lkt_previously_dismissed      = {$lkt_previously_dismissed};
</script>';
EOF;
}

function lkt_get_link($url, $text) {
	return '<a href="'.htmlspecialchars_uni($url).'">'.htmlspecialchars_uni($text).'</a>';
}

function lkt_get_forumlink($fid, $name) {
	return lkt_get_link(get_forum_link($fid), $name);
}

function lkt_get_threadlink($tid, $name) {
	return lkt_get_link(get_thread_link($tid), $name);
}

function lkt_get_usernamelink($uid, $name) {
	return lkt_get_link(get_profile_link($uid), $name);
}

function lkt_get_postlink($pid, $name) {
	return lkt_get_link(get_post_link($pid).'#pid'.$pid, $name);
}

function lkt_get_flinks($parentlist, $forum_names) {
	$flinks = '';
	foreach (explode(',', $parentlist) as $fid) {
		if ($flinks ) $flinks .= ' &raquo; ';
		$flinks .= lkt_get_forumlink($fid, $forum_names[$fid]);
	}

	return $flinks;
}

function lkt_get_post_output($post, $forum_names) {
	global $lang, $templates;

	$is_first_post = ($post['firstpost'] == $post['pid']);
	eval("\$div_posted_by = \"".$templates->get($is_first_post ? 'linktools_op_post_div' : 'linktools_non_op_post_div', 1, 0)."\";");

	$div_main_class = ($post['are_all_matching_urls_in_quotes'] ? ' class="all_matching_urls_in_quotes"': '');
	$lang_matching_url_or_urls = count($post['matching_urls']) == 1 ? $lang->lkt_matching_url_singular : $lang->lkt_matching_urls_plural;

	$matching_urls_list = '';
	for ($i = 0; $i < count($post['matching_urls']); $i++) {
		$url = $post['matching_urls'][$i];
		$url_esc = htmlspecialchars_uni($url);
		$link = '<a href="'.$url_esc.'">'.$url_esc.'</a>';
		if ($post['matching_urls_in_post'][$i] != $url) {
			$url2 = $post['matching_urls_in_post'][$i];
			$url_esc2 = htmlspecialchars_uni($url2);
			$link2 = '<a href="'.$url_esc2.'">'.$url_esc2.'</a>';
			$matching_url_msg = $lang->sprintf($lang->lkt_msg_url1_as_url2, $link, $link2);
		} else	$matching_url_msg = $link;
		eval("\$matching_url_item = \"".$templates->get('linktools_matching_url_item', 1, 0)."\";");
		$matching_urls_list .= $matching_url_item;
	}

	eval("\$ret = \"".$templates->get('linktools_matching_post', 1, 0)."\";");

	return $ret;
}

function lkt_strip_nestable_mybb_tag($message, $tagname) {
	$lkt_validate_start_tag_ending = function ($message, $pos) {
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
		if (!$lkt_validate_start_tag_ending($message, $pos+strlen('['.$tagname))) {
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
			} while ($pos2 !== false && !$lkt_validate_start_tag_ending($message, $pos2+strlen('['.$tagname)));
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

function lkt_hookin__search_do_search_start() {
	global $mybb;

	$do_lkt_search = false;

	if ($mybb->input['urls']) {
		$urls = $mybb->input['urls'];
		$do_lkt_search = true;
	} else if ($mybb->input['keywords']) {
		$urls = explode(' ', trim($mybb->input['keywords']));
		$do_lkt_search = true;
		foreach ($urls as $url) {
			if (!lkt_is_url($url)) {
				$do_lkt_search = false;
				break;
			}
		}
	}

	if ($do_lkt_search) {
		lkt_search($urls);
		// Shouldn't be necessary but just in case - we don't want to
		// return control back to the hook's calling context.
		exit;
	}
}

/**
 * Determines whether or not $url is a valid URL.
 * Tolerates missing protocol/scheme, so that, e.g.,
 * somedomain.com and //anotherdomain.com are
 * determined to be valid URLs, but a bare hostname
 * such as somedomain is not.
 *
 * Warning: does not detect international domains
 * (this is a limitation of the PHP core function
 * filter_var()).
 *
 * @param $url string The potential URL.
 * @return boolean True if a valid URL; false otherwise.
 */
function lkt_is_url($url) {
	// Tolerate missing protocol ("scheme") so long as
	// the host (domain) has more than one component.
	$scheme = lkt_get_scheme($url);
	if ($scheme == '') {
		if (substr($url, 0, 2) == '//') {
			$url = 'http:'.$url;
		} else	$url = 'http://'.$url;
	}

	if (filter_var($url, FILTER_VALIDATE_URL)) {
		$parsed = parse_url($url);
		if (strpos($parsed['host'], '.') === false) {
			return false;
		} else	return true;
	} else	return false;
}

function lkt_search($urls) {
	global $mybb, $db, $session, $lang;

	$lang->load('search');

	// Begin code copied, with only minor changes - such as to coding style,
	// a typo correction- from search.php under the hook 'search_do_search_start'.
	// This is an unfortunate duplication, however that core code does not appear
	// to let us hook in better so as to reuse that code.

	// Check if search flood checking is enabled and user is not admin
	if ($mybb->settings['searchfloodtime'] > 0 && $mybb->usergroup['cancp'] != 1) {
		// Fetch the time this user last searched
		if ($mybb->user['uid']) {
			$conditions = "uid='{$mybb->user['uid']}'";
		} else {
			$conditions = "uid='0' AND ipaddress=".$db->escape_binary($session->packedip);
		}
		$timecut = TIME_NOW - $mybb->settings['searchfloodtime'];
		$query = $db->simple_select('searchlog', '*', "$conditions AND dateline > '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_search = $db->fetch_array($query);
		// User's last search was within the flood time, show the error
		if ($last_search['sid']) {
			$remaining_time = $mybb->settings['searchfloodtime'] - (TIME_NOW - $last_search['dateline']);
			if ($remaining_time == 1) {
				$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding_1, $mybb->settings['searchfloodtime']);
			} else {
				$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding, $mybb->settings['searchfloodtime'], $remaining_time);
			}
			error($lang->error_searchflooding);
		}
	}

	if (is_moderator() && !empty($mybb->input['visible'])) {
		$visible = $mybb->get_input('visible', MyBB::INPUT_INT);
	}

	if ($db->can_search != true) {
		error($lang->error_no_search_support);
	}

	// End copied code

	$showresults = $mybb->get_input('showresults');
	$as_posts = ($showresults != 'threads');

	// Begin code copied with minor modifications from perform_search_mysql() in inc/functions_search.php.
	// As above, this is unfortunate, but unavoidable other than by making
	// changes to core code, which we prefer not to do.

	$post_usersql = '';
	$thread_usersql = '';
	$author = $mybb->get_input('author');
	if ($author) {
		$userids = array();
		$author = my_strtolower($author);
		$matchusername = $mybb->get_input('matchusername', MyBB::INPUT_INT);
		if ($matchusername) {
			$user = get_user_by_username($author);
			if ($user) {
				$userids[] = $user['uid'];
			}
		} else {
			switch ($db->type) {
				case 'mysql':
				case 'mysqli':
					$field = 'username';
					break;
				default:
					$field = 'LOWER(username)';
					break;
			}
			$query = $db->simple_select('users', 'uid', "{$field} LIKE '%".$db->escape_string_like($author)."%'");
			while ($user = $db->fetch_array($query)) {
				$userids[] = $user['uid'];
			}
		}

		if (count($userids) < 1) {
			error($lang->error_nosearchresults);
		} else {
			$userids = implode(',', $userids);
			$post_usersql = " AND p.uid IN (".$userids.")";
			$thread_usersql = " AND t.uid IN (".$userids.")";
		}
	}
	$datecut = $post_datecut = $thread_datecut = '';
	$postdate = $mybb->get_input('postdate', MyBB::INPUT_INT);
	if ($postdate) {
		$pddir = $mybb->get_input('pddir', MyBB::INPUT_INT);
		if ($pddir == 0) {
			$datecut = '<=';
		} else {
			$datecut = '>=';
		}
		$now = TIME_NOW;
		$datelimit = $now - (86400 * $postdate);
		$datecut .= "'$datelimit'";
		$post_datecut = " AND p.dateline $datecut";
		$thread_datecut = " AND t.dateline $datecut";
	}

	$thread_replycut = '';
	$numreplies = $mybb->get_input('numreplies', MyBB::INPUT_INT);
	$findthreadst = $mybb->get_input('findthreadst', MyBB::INPUT_INT);
	if ($numreplies != '' && $findthreadst) {
		if ((int)$findthreadst == 1) {
			$thread_replycut = " AND t.replies >= '".(int)$numreplies."'";
		} else {
			$thread_replycut = " AND t.replies <= '".(int)$numreplies."'";
		}
	}

	$thread_prefixcut = '';
	$prefixlist = array();
	$threadprefix = $mybb->get_input('threadprefix', MyBB::INPUT_ARRAY);
	if ($threadprefix && $threadprefix[0] != 'any') {
		foreach ($search['threadprefix'] as $threadprefix) {
			$threadprefix = (int)$threadprefix;
			$prefixlist[] = $threadprefix;
		}
	}
	if (count($prefixlist) == 1) {
		$thread_prefixcut .= " AND t.prefix='$threadprefix' ";
	} else {
		if (count($prefixlist) > 1) {
			$thread_prefixcut = ' AND t.prefix IN ('.implode(',', $prefixlist).')';
		}
	}

	$forumin = '';
	$fidlist = array();
	$forums = $mybb->input['forums'];
	if (!empty($forums) && (!is_array($forums) || $forums[0] != 'all')) {
		if (!is_array($forums)) {
			$forums = array((int)$forums);
		}
		foreach ($forums as $forum) {
			$forum = (int)$forum;
			if ($forum > 0) {
				$fidlist[] = $forum;
				$child_list = get_child_list($forum);
				if (is_array($child_list)) {
					$fidlist = array_merge($fidlist, $child_list);
				}
			}
		}
		$fidlist = array_unique($fidlist);
		if (count($fidlist) >= 1) {
			$forumin = ' AND t.fid IN ('.implode(',', $fidlist).')';
		}
	}

	$permsql = '';
	$onlyusfids = array();

	// Check group permissions if we can't view threads not started by us
	if ($group_permissions = forum_permissions()) {
		foreach ($group_permissions as $fid => $forum_permissions) {
			if (isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1) {
				$onlyusfids[] = $fid;
			}
		}
	}
	if (!empty($onlyusfids)) {
		$permsql .= 'AND ((t.fid IN('.implode(',', $onlyusfids).") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
	}

	require_once MYBB_ROOT.'/inc/functions_search.php';
	$unsearchforums = get_unsearchable_forums();
	if ($unsearchforums) 	{
		$permsql .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if ($inactiveforums) {
		$permsql .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$postthread = $mybb->get_input('postthread', MyBB::INPUT_INT);
	$visiblesql = $post_visiblesql = $plain_post_visiblesql = '';
	if (isset($visible)) {
		if ($visible == 1) {
			$visiblesql = " AND t.visible = '1'";

			if ($postthread == 1) {
				$post_visiblesql = " AND p.visible = '1'";
				$plain_post_visiblesql = " AND visible = '1'";
			}
		} elseif ($visible == -1) {
			$visiblesql = " AND t.visible = '-1'";

			if ($postthread == 1) {
				$post_visiblesql = " AND p.visible = '-1'";
				$plain_post_visiblesql = " AND visible = '-1'";
			}
		} else {
			$visiblesql = " AND t.visible == '0'";

			if ($postthread == 1) {
				$post_visiblesql = " AND p.visible == '0'";
				$plain_post_visiblesql = " AND visible == '0'";
			}
		}
	}

	// End copied code.

	$extra_conditions = "{$post_datecut} {$thread_replycut} {$thread_prefixcut} {$forumin} {$post_usersql} {$permsql} {$tidsql} {$visiblesql} {$post_visiblesql} AND t.closed NOT LIKE 'moved|%'";
	$sql = lkt_get_url_search_sql((array)$urls, false, $extra_conditions);
	$res = $db->query($sql);

	$pids = array();
	$tids = array();
	while (($row = $db->fetch_array($res))) {
		$pids[] = $row['pid'];
		$tids[$row['tid']] = true;
	}

	if (!$pids) {
		error($lang->error_nosearchresults);
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
}

function lkt_hookin__xmlhttp() {
	global $mybb, $db;

	if ($mybb->input['action'] == 'lkt_set_warn_about_links') {
		$lkt_setting_warn_about_links = $mybb->get_input('lkt_setting_warn_about_links', MyBB::INPUT_INT) ? 1 : 0;
		$db->update_query('users', array('lkt_warn_about_links' => $lkt_setting_warn_about_links), "uid='{$mybb->user['uid']}'");
	} else if ($mybb->input['action'] == 'lkt_get_post_regen_cont') {
		$post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));
		if ($post) {
			$urls = lkt_retrieve_terms(lkt_extract_urls($post['message']));
			if ($urls) {
				echo lkt_get_preview_regen_container($post, $urls);
			}
		} echo '';
	}
}

function lkt_hookin__admin_config_settings_change() {
	global $db, $mybb, $lkt_settings_peeker;

	$res = $db->simple_select('settinggroups', 'gid', "name = '".C_LKT."_settings'", array('limit' => 1));
	$gid = $db->fetch_field($res, 'gid');
	$lkt_settings_peeker = ($mybb->input['gid'] == $gid) && ($mybb->request_method != 'post');
}

function lkt_hookin__admin_page_output_footer() {
	global $lkt_settings_peeker;

	if ($lkt_settings_peeker) {
		echo '<script type="text/javascript">
		$(document).ready(function() {
			new Peeker($(".setting_'.C_LKT.'_enable_dlw"), $("#row_setting_'.C_LKT.'_force_dlw"), 1, true)
		});
		</script>';
	}
}

function lkt_get_preview_regen_container($post, $urls) {
	global $mybb, $lang, $templates;

	$lang->load(C_LKT);

	$urls = array_values($urls);
	if (count($urls) == 1) {
		$link_url  = $mybb->settings['bburl'].'/lkt-regen-preview.php?url='.urlencode($urls[0]).'&amp;return_pid='.$post['pid'];
		$link_text = $lang->lkt_regen_link_preview;
		eval('$links = "'.$templates->get('linktools_preview_regen_link', 1, 0).'";');
		$prefix = '';
	} else {
		$link_url = $mybb->settings['bburl'].'/lkt-regen-preview.php?pid='.$post['pid'].'&amp;return_pid='.$post['pid'];
		$link_text = $lang->lkt_all;
		eval('$links = "'.$templates->get('linktools_preview_regen_link', 1, 0).'";');
		foreach ($urls as $i => $url) {
			$links .= $lang->comma;
			$link_url = $mybb->settings['bburl'].'/lkt-regen-preview.php?url='.urlencode($url).'&amp;return_pid='.$post['pid'];
			$link_text = (string)($i+1);
			eval('$links .= "'.$templates->get('linktools_preview_regen_link', 1, 0).'";');
		}
		$prefix = $lang->lkt_regen_link_previews;
	}
	eval('$ret = "'.$templates->get('linktools_preview_regen_container', 1, 0).'";');

	return $ret;
}

function lkt_hookin__postbit($post) {
	global $g_lkt_links;

	if ($g_lkt_links && empty($post['lkt_linkpreviewoff'])) {
		foreach (lkt_get_gen_link_previews(lkt_retrieve_terms($g_lkt_links)) as $preview) {
			$post['message'] .= $preview;
		}
		$post['updatepreview'] = lkt_get_preview_regen_container($post, $g_lkt_links);
	}

	return $post;
}

function lkt_hookin__xmlhttp_update_post() {
	global $g_lkt_links, $post;

	if ($g_lkt_links && empty($post['lkt_linkpreviewoff'])) {
		foreach (lkt_get_gen_link_previews(lkt_retrieve_terms($g_lkt_links)) as $preview) {
			$post['message'] .= $preview;
		}
	}
}

function lkt_hookin__parse_message_start($message) {
	global $g_lkt_links, $mybb;

	if (!(THIS_SCRIPT == 'showthread.php' && $mybb->settings[C_LKT.'_link_preview_on_fly'] == 'never')) {
		$g_lkt_links = lkt_extract_urls($message, /*$exclude_videos = */true);
	} else	$g_lkt_links = array();

	return $message;
}

function lkt_hookin__admin_config_menu(&$sub_menu) {
	global $lang;

	$lang->load(C_LKT);
	$key = max(array_keys($sub_menu)) + 10;
	$sub_menu[$key] = array(
		'id'    => 'linkhelpers'                        ,
		'title' => $lang->lkt_linkhelpers               ,
		'link'  => 'index.php?module=config-linkhelpers',
	);
}

function lkt_hookin__admin_config_action_handler(&$actions) {
	$actions['linkhelpers'] = array(
		'active' => 'linkhelpers'    ,
		'file'   => 'linkhelpers.php',
	);
}

/**
 * In contrast to lkt_get_url_term_redirs(), which queries web servers for
 * terminating URLs, this retrieves those already-web-queried terminating URLs
 * from the table to which they were stored in our database.
 */
function lkt_retrieve_terms($urls, $set_false_on_not_found = false) {
	global $db;

	$urls = array_unique($urls);
	$terms = array();
	$query = $db->simple_select('urls', 'url, url_term', "url_norm IN ('".implode("', '", array_map(function($url) use ($db) {return $db->escape_string(lkt_normalise_url($url));}, $urls))."')");
	while ($row = $db->fetch_array($query)) {
		$terms[$row['url']] = $row['url_term'];
	}

	foreach ($urls as $url) {
		if (!isset($terms[$url])) {
			$terms[$url] = $set_false_on_not_found ? false : $url;
		}
	}

	return $terms;
}

function lkt_hookin__editpost_action_start() {
	global $post, $templates, $disablelinkpreviews, $lang;

	$lang->load(C_LKT);

	$linkpreviewoffchecked = empty($post['lkt_linkpreviewoff']) ? '' : ' checked="checked"';
	eval('$disablelinkpreviews = "'.$templates->get('linktools_cbxdisablelinkpreview').'";');
}

function lkt_hookin__editpost_do_editpost_end() {
	global $post, $mybb, $db;

	$val = !empty($mybb->get_input('lkt_linkpreviewoff', MyBB::INPUT_INT)) ? '1' : '0';
	$db->update_query('posts', array('lkt_linkpreviewoff' => $val), "pid = '{$post['pid']}'");
}

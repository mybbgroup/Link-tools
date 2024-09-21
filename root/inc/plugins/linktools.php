<?php

/**
 *  Part of the Link Tools plugin for MyBB 1.8.
 *  Copyright (C) 2020-2021 Laird Shaw
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
const lkt_default_rebuild_term_items_per_page = 30;
const lkt_default_rebuild_renorm_items_per_page = 500;
const lkt_default_rebuild_linkpreviews_items_per_page = 30;

const lkt_max_matching_posts = 10;

const lkt_urls_limit_for_get_and_store_terms = 2000;

// 2083 was chosen because it is the maximum size URL that Internet Explorer will accept
// (other major browsers have higher limits).
const lkt_max_url_len = 2083;

const lkt_max_previewer_class_name_len = 100;
const lkt_max_previewer_class_vers_len =  15;

const lkt_use_head_method          = true; // Overridden by the below two being true though, so effectively false.
const lkt_check_for_html_redirects = true;
const lkt_check_for_canonical_tags = true;

const lkt_rehit_delay_in_secs = 3;
const lkt_max_allowable_redirects_for_a_url = 25;
const lkt_max_allowable_redirect_resolution_runtime_secs = 60;
const lkt_curl_timeout = 10;
const lkt_max_url_lock_time = 120;
const lkt_curl_useragent = 'The MyBB Link Tools plugin';

const lkt_term_tries_secs = array(
	0,             // First attempt has no limits.
	15*60,         // 15 minutes
	60*60,         // 1 hour
	24*60*60,      // 1 day
	7*24*60*60,    // 1 week
	28*24*60*60,   // 4 weeks
);

const lkt_preview_regen_min_wait_secs = 30;

# [img] tag regexes from postParser::parse_mycode() in ../class_parser.php.
const lkt_img_regexes = array(
	"#\[img\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is",
	"#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is",
	"#\[img align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is",
	"#\[img=([1-9][0-9]*)x([1-9][0-9]*) align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is"
);

global $g_lkt_previews;
$g_lkt_previews = false;

/**
 * @todo Eliminate broken urls in [url] and [video] tags - don't store them in the DB.
 * @todo Maybe add a global and/or per-user setting to disable checking for matching non-opening posts.
 */

$plugins->add_hook('datahandler_post_insert_thread'         , 'lkt_hookin__datahandler_post_insert_thread'         );
$plugins->add_hook('newthread_start'                        , 'lkt_hookin__newthread_start'                        );
$plugins->add_hook('datahandler_post_insert_post_end'       , 'lkt_hookin__datahandler_post_insert_post_end'       );
$plugins->add_hook('datahandler_post_insert_thread_end'     , 'lkt_hookin__datahandler_post_insert_thread_end'     );
$plugins->add_hook('datahandler_post_update'                , 'lkt_hookin__datahandler_post_update'                );
$plugins->add_hook('datahandler_post_update_end'            , 'lkt_hookin__datahandler_post_update_or_merge_end'   );
$plugins->add_hook('datahandler_post_insert_merge'          , 'lkt_hookin__datahandler_post_update_or_merge_end'   );
$plugins->add_hook('class_moderation_delete_post'           , 'lkt_hookin__class_moderation_delete_post'           );
$plugins->add_hook('class_moderation_delete_thread_start'   , 'lkt_hookin__common__class_moderation_delete_thread' );
$plugins->add_hook('class_moderation_delete_thread'         , 'lkt_hookin__common__class_moderation_delete_thread' );
$plugins->add_hook('admin_tools_recount_rebuild_output_list', 'lkt_hookin__admin_tools_recount_rebuild_output_list');
$plugins->add_hook('admin_tools_recount_rebuild'            , 'lkt_hookin__admin_tools_recount_rebuild'            );
$plugins->add_hook('global_start'                           , 'lkt_hookin__global_start'                           );
$plugins->add_hook('search_do_search_start'                 , 'lkt_hookin__search_start'                           );
$plugins->add_hook('search_thread_start'                    , 'lkt_hookin__search_start'                           );
$plugins->add_hook('admin_config_plugins_activate_commit'   , 'lkt_hookin__admin_config_plugins_activate_commit'   );
$plugins->add_hook('xmlhttp'                                , 'lkt_hookin__xmlhttp'                                );
$plugins->add_hook('admin_config_settings_change'           , 'lkt_hookin__admin_config_settings_change'           );
$plugins->add_hook('admin_settings_print_peekers'           , 'lkt_hookin__admin_settings_print_peekers'           );
$plugins->add_hook('postbit'                                , 'lkt_hookin__postbit'                                );
$plugins->add_hook('postbit_prev'                           , 'lkt_hookin__postbit_etc'                            );
$plugins->add_hook('postbit_pm'                             , 'lkt_hookin__postbit_etc'                            );
$plugins->add_hook('postbit_announcement'                   , 'lkt_hookin__postbit_etc'                            );
$plugins->add_hook('parse_message_start'                    , 'lkt_hookin__parse_message_start'                    );
$plugins->add_hook('xmlhttp_update_post'                    , 'lkt_hookin__xmlhttp_update_post'                    );
$plugins->add_hook('admin_config_menu'                      , 'lkt_hookin__admin_config_menu'                      );
$plugins->add_hook('admin_config_action_handler'            , 'lkt_hookin__admin_config_action_handler'            );
$plugins->add_hook('admin_config_permissions'               , 'lkt_hookin__admin_config_permissions'               );
$plugins->add_hook('admin_forum_menu'                       , 'lkt_hookin__admin_forum_menu'                       );
$plugins->add_hook('admin_forum_action_handler'             , 'lkt_hookin__admin_forum_action_handler'             );
$plugins->add_hook('admin_forum_permissions'                , 'lkt_hookin__admin_forum_permissions'                );
$plugins->add_hook('admin_tools_menu'                       , 'lkt_hookin__admin_tools_menu'                       );
$plugins->add_hook('admin_tools_action_handler'             , 'lkt_hookin__admin_tools_action_handler'             );
$plugins->add_hook('admin_formcontainer_output_row'         , 'lkt_hookin__admin_formcontainer_output_row'         );
$plugins->add_hook('admin_user_groups_edit_commit'          , 'lkt_hookin__admin_user_groups_edit_commit'          );
$plugins->add_hook('admin_forum_management_permission_groups', 'lkt_hookin__admin_forum_management_permission_groups');
$plugins->add_hook('admin_load'                             , 'lkt_hookin__admin_load'                             );
$plugins->add_hook('newthread_start'                        , 'lkt_hookin__newthreadorreply_start'                 );
$plugins->add_hook('datahandler_post_insert_thread_post'    , 'lkt_hookin__datahandler_post_insert_thread_post'    );
$plugins->add_hook('newreply_start'                         , 'lkt_hookin__newthreadorreply_start'                 );
$plugins->add_hook('newreply_do_newreply_end'               , 'lkt_hookin__newreply_do_newreply_end'               );
$plugins->add_hook('editpost_action_start'                  , 'lkt_hookin__editpost_action_start'                  );
$plugins->add_hook('editpost_do_editpost_end'               , 'lkt_hookin__editpost_do_editpost_end'               );
$plugins->add_hook('datahandler_post_validate_post'         , 'lkt_hookin__datahandler_post_validate_thread_or_post');
$plugins->add_hook('datahandler_post_validate_thread'       , 'lkt_hookin__datahandler_post_validate_thread_or_post');
$plugins->add_hook('modcp_start'                            , 'lkt_hookin__modcp_start'                            );
$plugins->add_hook('modcp_do_modqueue_start'                , 'lkt_hookin__do_modqueue'                            );
$plugins->add_hook('admin_forum_moderation_queue_commit'    , 'lkt_hookin__do_modqueue'                            );
$plugins->add_hook('moderation_purgespammer_purge'          , 'lkt_hookin__moderation_purgespammer_purge'          );
$plugins->add_hook('moderation_purgespammer_show'           , 'lkt_hookin__moderation_purgespammer_show'           );
$plugins->add_hook('global_intermediate'                    , 'lkt_hookin__global_intermediate'                    );
$plugins->add_hook('admin_page_output_footer'               , 'lkt_hookin__admin_page_output_footer'               );
//$plugins->add_hook('admin_style_templates_edit_template_commit', 'lkt_hookin__admin_style_templates_edit_template_commit');

function lkt_hookin__global_start() {
	if (defined('THIS_SCRIPT')) {
		global $templatelist;

		if (THIS_SCRIPT == 'newthread.php') {
			if (isset($templatelist)) $templatelist .= ',';
			$templatelist .= 'linktools_div,linktools_op_post_div,linktools_non_op_post_div,linktools_matching_url_item,linktools_matching_post,linktools_review_buttons,linktools_toggle_button,linktools_review_page,linktools_matching_posts_warning_div';
		} else if (THIS_SCRIPT == 'showthread.php' || THIS_SCRIPT == 'xmlhttp.php') {
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
	global $lang, $db, $mybb, $plugins_cache, $cache, $admin_session, $config;

	if (!isset($lang->linktools)) {
		$lang->load(C_LKT);
	}

	$ret = array(
		'name'          => $lang->lkt_name,
		'description'   => $lang->lkt_desc,
		'website'       => 'https://mybb.group/Thread-Link-Tools',
		'author'        => 'Laird as a member of the unofficial MyBB Group',
		'authorsite'    => 'https://mybb.group/User-Laird',
		'version'       => '1.5.0-postrelease',
		// Constructed by converting each digit of 'version' above into two digits (zero-padded if necessary),
		// then concatenating them, then removing any leading zero(es) to avoid the value being interpreted as octal.
		'version_code'  => '10500',
		'guid'          => '',
		'codename'      => C_LKT,
		'compatibility' => '18*'
	);

	if (linktools_is_installed() && !empty($plugins_cache['active'][C_LKT])) {
		$desc = '';
		$desc .= '<ul>'.PHP_EOL;

		if (!empty($admin_session['data']['lkt_plugin_info_upgrade_message'])) {
			$msg = $admin_session['data']['lkt_plugin_info_upgrade_message'];
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
				$desc .= $lang->sprintf($lang->lkt_to_extract_links_click_here, $cnt_posts_unextracted, '<form method="post" action="'.$mybb->settings['bburl'].'/'.$config['admin_dir'].'/index.php?module=tools-recount_rebuild" style="display: inline;"><input type="hidden" name="page" value="2" /><input type="hidden" name="my_post_key" value="'.generate_post_check().'" /><input type="submit" name="do_rebuild_links" value="', '" style="background: none; border: none; color: #0066ff; text-decoration: underline; cursor: pointer; display: inline; margin: 0; padding: 0; font-size: inherit;"/></form>');
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
				$desc .= $lang->sprintf($lang->lkt_to_resolve_links_click_here, number_format($cnt_eligible), '<form method="post" action="'.$mybb->settings['bburl'].'/'.$config['admin_dir'].'/index.php?module=tools-recount_rebuild" style="display: inline;"><input type="hidden" name="page" value="2" /><input type="hidden" name="my_post_key" value="'.generate_post_check().'" /><input type="submit" name="do_rebuild_terms" value="', '" style="background: none; border: none; color: #0066ff; text-decoration: underline; cursor: pointer; display: inline; margin: 0; padding: 0; font-size: inherit;"/></form>');
			} else {
				$desc .= $lang->lkt_no_links_eligible_for_resolution;
			}
		}
		$desc .= '	</li>'.PHP_EOL;

		$lrs_plugins = $cache->read('lrs_plugins');
		$inst_previewers = !empty($lrs_plugins[C_LKT]['installed_link_previewers'])
				    ? $lrs_plugins[C_LKT]['installed_link_previewers']
				    : array();

		$present_previewers = array();
		foreach (lkt_get_linkpreviewer_classnames() as $type => $classnames) {
			$present_previewers = array_merge($present_previewers, $classnames);
		}
		$present_previewers = array_diff(array_unique($present_previewers), array('LinkPreviewerDefault'));

		$inst_hlp_tpl_miss_cnt = 0;
		foreach ($present_previewers as $present_previewer) {
			if (empty($inst_previewers[$present_previewer]['tpl_installed'])) {
				$previewerobj = $present_previewer::get_instance();
				if ($previewerobj->get_template_name(/*$ret_empty_if_default*/true)) {
					$inst_hlp_tpl_miss_cnt++;
				}
			}
		}

		if ($inst_hlp_tpl_miss_cnt) {
			$lang_previewer_or_previewers = $inst_hlp_tpl_miss_cnt == 1 ? $lang->lkt_one_previewer : $lang->sprintf($lang->lkt_previewers, $inst_hlp_tpl_miss_cnt);
			$desc .= '	<li style="list-style-image: url(styles/default/images/icons/warning.png); color: red;">'.$lang->sprintf($lang->lkt_need_inst_previewers, $lang_previewer_or_previewers, '<form method="post" action="'.$mybb->settings['bburl'].'/'.$config['admin_dir'].'/index.php?module=config-linkpreviewers" style="display: inline;"><input type="hidden" name="installall" value="1" /><input type="hidden" name="my_post_key" value="'.generate_post_check().'" /><input type="submit" name="do_update" value="', '" style="background: none; border: none; color: #0066ff; text-decoration: underline; cursor: pointer; display: inline; margin: 0; padding: 0; font-size: inherit;" /></form>').'</li>';
		}

		if ($db->field_exists('dateline', 'urls') && ($num_dateable = $db->fetch_field($db->query("
SELECT COUNT(*) AS num_dateable
FROM   (
        ".lkt_get_min_url_dateline_sql()."
       ) subq
"), 'num_dateable')) > 0
		) {
			if ($num_dateable == 1) {
				$lang_dateable_links = $lang->lkt_init_url_dateline;
				$url_key = 'lkt_init_url_dateline_link';
			} else {
				$lang_dateable_links = $lang->sprintf($lang->lkt_init_urls_dateline, $num_dateable);
				$url_key = 'lkt_init_url_dateline_links';
			}
			$desc .= '	<li style="list-style-image: url(styles/default/images/icons/warning.png);">'.$lang_dateable_links.' <form method="post" action="index.php?module=config-plugins&amp;action=lkt_init_url_dateline" style="display: inline;"><input type="hidden" name="my_post_key" value="'.generate_post_check().'" /><input type="submit" name="do_update" value="'.$lang->$url_key.'" style="background: none; border: none; color: #0066ff; text-decoration: underline; cursor: pointer; display: inline; margin: 0; padding: 0; font-size: inherit;" /></form>';
		}

		$desc .= '</ul>'.PHP_EOL;

		$ret['description'] .= $desc;
	}

	return $ret;
}

/**
 * Performs the tasks required upon installation of this plugin.
 */
function linktools_install() {
	// We don't do anything here. Given that a plugin cannot be installed
	// without being simultaneously activated, it is sufficient to call
	// lkt_install_or_upgrade() from linktools_activate().
}

function lkt_install_or_upgrade($from_version, $to_version) {
	global $db, $cache;

	if (!$db->table_exists('urls')) {
		// utf8_bin collation was chosen for the varchar columns
		// so that SELECTs are case-sensitive, given that everything
		// after the server name in URLs is case-sensitive.
		$db->write_query('
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
  got_preview   boolean       NOT NULL DEFAULT FALSE,
  dateline      int(10) unsigned NOT NULL DEFAULT 0,
  spam_class    enum(\'Unspecified\', \'Potential spam\', \'Not spam\', \'Spam\') NOT NULL DEFAULT \'Unspecified\',
  KEY           url           (url(168)),
  KEY           url_norm      (url_norm(166)),
  KEY           url_term_norm (url_term_norm(166)),
  KEY           k_dateline    (dateline),
  KEY           spam_class_dateline (spam_class, dateline),
  PRIMARY KEY   (urlid)
)'.$db->build_create_table_collation().';');
	}

	if (!$db->table_exists('url_previews')) {
		// utf8_bin collation was chosen for the varchar columns
		// so that SELECTs are case-sensitive, given that everything
		// after the server name in URLs is case-sensitive.
		$db->write_query('
CREATE TABLE '.TABLE_PREFIX.'url_previews (
  url_term     varchar('.lkt_max_url_len.') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  preview_data text             NOT NULL,
  dateline     int(10) unsigned NOT NULL DEFAULT 0,
  valid        tinyint(1)       NOT NULL DEFAULT 1,
  previewer_class_name varchar('.lkt_max_previewer_class_name_len.') NOT NULL DEFAULT \'\',
  previewer_class_vers varchar('.lkt_max_previewer_class_vers_len.') NOT NULL DEFAULT \'\',
  KEY         url_term (url_term(166))
)'.$db->build_create_table_collation().';');
	}

	if (!$db->table_exists('post_urls')) {
		$db->write_query('
CREATE TABLE '.TABLE_PREFIX.'post_urls (
  pid         int unsigned NOT NULL,
  urlid       int unsigned NOT NULL,
  PRIMARY KEY (urlid, pid)
)'.$db->build_create_table_collation().';');
	}

	if (!$db->table_exists('link_limits')) {
		$db->write_query('
CREATE TABLE '.TABLE_PREFIX.'link_limits (
  llid     int unsigned NOT NULL auto_increment PRIMARY KEY,
  gids     varchar(256) NOT NULL,
  fids     varchar(256) NOT NULL,
  maxlinks int unsigned NOT NULL,
  days     int unsigned NOT NULL
)'.$db->build_create_table_collation().';');
	}

	if ($db->table_exists('url_previews')) {
		if ($db->field_exists('url_norm', 'url_previews') && !$db->field_exists('url_term', 'url_previews')) {
			$db->write_query('ALTER TABLE '.TABLE_PREFIX.'url_previews CHANGE url_norm url_term varchar('.lkt_max_url_len.') CHARACTER SET utf8 COLLATE utf8_bin NOT NULL');
			$db->delete_query('url_previews', "url_term like 'http(s)://%'");
		}
		if ($db->field_exists('helper_class_name', 'url_previews') && !$db->field_exists('previewer_class_name', 'url_previews')) {
			$db->write_query('ALTER TABLE '.TABLE_PREFIX.'url_previews CHANGE helper_class_name previewer_class_name varchar('.lkt_max_previewer_class_name_len.') NOT NULL DEFAULT \'\'');
			$db->write_query('UPDATE '.TABLE_PREFIX."url_previews SET previewer_class_name = concat('LinkPreviewer', substr(previewer_class_name, 11)) WHERE previewer_class_name LIKE 'LinkHelper%'");
		}
		if ($db->field_exists('helper_class_vers', 'url_previews') && !$db->field_exists('previewer_class_vers', 'url_previews')) {
			$db->write_query('ALTER TABLE '.TABLE_PREFIX.'url_previews CHANGE helper_class_vers previewer_class_vers varchar('.lkt_max_previewer_class_vers_len.') NOT NULL DEFAULT \'\'');
		}
		if ($db->field_exists('preview', 'url_previews') && !$db->field_exists('preview_data', 'url_previews')) {
			$db->write_query('ALTER TABLE '.TABLE_PREFIX.'url_previews CHANGE preview preview_data text NOT NULL');
			$db->delete_query('url_previews', "'1'");
		}
	}

	if (!$db->field_exists('got_preview', 'urls')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'urls ADD got_preview boolean NOT NULL DEFAULT FALSE');
	}


	/** The following two columns and two indexes were added in version 1.5.0. */

	if (!$db->field_exists('dateline', 'urls')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'urls ADD dateline int(10) unsigned NOT NULL DEFAULT 0');
	}

	if (!$db->field_exists('spam_class', 'urls')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'urls ADD spam_class enum(\'Unspecified\', \'Potential spam\', \'Not spam\', \'Spam\') NOT NULL DEFAULT \'Unspecified\'');
	}

	if (!$db->index_exists('urls', 'k_dateline')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'urls ADD KEY k_dateline (dateline)');
	}

	if (!$db->index_exists('urls', 'spam_class_dateline')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'urls ADD KEY spam_class_dateline (spam_class, dateline)');
	}


	if (!$db->field_exists('lkt_got_urls', 'posts')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts ADD lkt_got_urls boolean NOT NULL default FALSE');
	}

	if (!$db->field_exists('lkt_linkpreviewoff', 'posts')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts ADD lkt_linkpreviewoff boolean NOT NULL default FALSE');
	}

	if (!$db->field_exists('lkt_warn_about_links', 'users')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'users ADD `lkt_warn_about_links` tinyint(1) NOT NULL default \'1\'');
	}

	if (!$db->field_exists('lkt_mod_link_in_new_post', 'usergroups')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'usergroups ADD `lkt_mod_link_in_new_post` tinyint(1) NOT NULL default \'0\'');
	}

	if (!$db->field_exists('lkt_mod_link_in_new_post', 'forumpermissions')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'forumpermissions ADD `lkt_mod_link_in_new_post` tinyint(1) NOT NULL default \'0\'');
	}

	if (!$db->field_exists('lkt_mod_edit_link_into_post', 'usergroups')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'usergroups ADD `lkt_mod_edit_link_into_post` tinyint(1) NOT NULL default \'0\'');
	}

	if (!$db->field_exists('lkt_mod_edit_link_into_post', 'forumpermissions')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'forumpermissions ADD `lkt_mod_edit_link_into_post` tinyint(1) NOT NULL default \'0\'');
	}

	$cache->update_usergroups();
	$cache->update_forumpermissions();

	// Convert "Helper" to "Previewer" in the cache as required.
	$lrs_plugins = $cache->read('lrs_plugins');
	if (empty($lrs_plugins)) {
		$lrs_plugins = [];
	}
	if (empty($lrs_plugins[C_LKT])) {
		$lrs_plugins[C_LKT] = [];
	}
	if (!empty($lrs_plugins[C_LKT]['installed_link_helpers']) && empty($lrs_plugins[C_LKT]['installed_link_previewers'])) {
		$lrs_plugins[C_LKT]['installed_link_previewers'] = array();
		foreach ($lrs_plugins[C_LKT]['installed_link_helpers'] as $classname => $value) {
			$classname_new = 'LinkPreviewer'.substr($classname, strlen('LinkHelper'));
			$lrs_plugins[C_LKT]['installed_link_previewers'][$classname_new] = $value;
		}
		unset($lrs_plugins[C_LKT]['installed_link_helpers']);
		$cache->update('lrs_plugins', $lrs_plugins);
	}

	// These six functions are compatible with upgrading -
	// they either check for existing database entries before
	// inserting new ones, or they delete existing entries then
	// reinsert them (potentially with changes), or they update
	// existing entries.
	lkt_create_templategroup();
	lkt_insert_templates($from_version);
	lkt_create_or_update_settings();
	lkt_enable_new_previewers();
	lkt_create_stylesheets();
}

function linktools_uninstall() {
	global $db, $cache;

	lkt_remove_settings();

	if ($db->table_exists('urls')) {
		$db->drop_table('urls');
	}

	if ($db->table_exists('post_urls')) {
		$db->drop_table('post_urls');
	}

	if ($db->table_exists('url_previews')) {
		$db->drop_table('url_previews');
	}

	if ($db->table_exists('link_limits')) {
		$db->drop_table('link_limits');
	}

	if ($db->field_exists('lkt_linkpreviewoff', 'posts')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts DROP COLUMN lkt_linkpreviewoff');
	}

	if ($db->field_exists('lkt_got_urls', 'posts')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'posts DROP COLUMN lkt_got_urls');
	}

	if ($db->field_exists('lkt_warn_about_links', 'users')) {
		$db->write_query('ALTER TABLE '.TABLE_PREFIX.'users DROP column `lkt_warn_about_links`');
	}

	if ($db->field_exists('lkt_mod_link_in_new_post', 'usergroups')) {
		$db->drop_column('usergroups', 'lkt_mod_link_in_new_post');
	}

	if ($db->field_exists('lkt_mod_link_in_new_post', 'forumpermissions')) {
		$db->drop_column('forumpermissions', 'lkt_mod_link_in_new_post');
	}

	if ($db->field_exists('lkt_mod_edit_link_into_post', 'usergroups')) {
		$db->drop_column('usergroups', 'lkt_mod_edit_link_into_post');
	}

	if ($db->field_exists('lkt_mod_edit_link_into_post', 'forumpermissions')) {
		$db->drop_column('forumpermissions', 'lkt_mod_edit_link_into_post');
	}

	$cache->update_usergroups();
	$cache->update_forumpermissions();

	$db->delete_query('tasks', "file='linktools'");

	$db->delete_query('templates', "title LIKE 'linktools\\_%'");
	$db->delete_query('templategroups', "prefix in ('linktools')");

	lkt_delete_stylesheets(/*$master_only = */false);

	$lrs_plugins = $cache->read('lrs_plugins');
	if (isset($lrs_plugins[C_LKT])) {
		unset($lrs_plugins[C_LKT]);
	}
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

	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('newthread', '({\\$smilieinserter})', '{$smilieinserter}{$linktools_div}');
	find_replace_templatesets('newthread', '({\\$codebuttons})'   , '{$codebuttons}{$linktools_js}'    );
	find_replace_templatesets('postbit'        , '({\\$post\\[\'poststatus\'\\]})', '{$post[\'poststatus\']}{$post[\'updatepreview\']}');
	find_replace_templatesets('postbit_classic', '({\\$post\\[\'poststatus\'\\]})', '{$post[\'poststatus\']}{$post[\'updatepreview\']}');
	find_replace_templatesets('showthread'     , '(<script\\stype="text/javascript"\\ssrc="{\\$mybb->asset_url}/jscripts/thread.js(?:\\?ver=\\d+)"></script>)', '<script type="text/javascript" src="{$mybb->asset_url}/jscripts/thread.js?ver=1822"></script>
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/linkpreviews.js?ver=1.1.0"></script>');
	find_replace_templatesets('newthread_postoptions', '({\\$disablesmilies})', '{$disablesmilies}{$disablelinkpreviews}');
	find_replace_templatesets('newreply_postoptions', '({\\$disablesmilies})', '{$disablesmilies}{$disablelinkpreviews}');
	find_replace_templatesets('editpost_postoptions', '({\\$disablesmilies})', '{$disablesmilies}{$disablelinkpreviews}');
	find_replace_templatesets('modcp_modqueue_threads_thread', '({\\$lang->delete}</label>)', '{$lang->delete}</label>'."\n\t\t\t\t\t\t\t".'<label class="label_radio_delete_link_spam" title="{$lang->lkt_delete_link_spam_title}"><input type="radio" class="radio radio_delete_link_spam" name="threads[{$thread[\'tid\']}]" value="delete_link_spam" /> {$lang->lkt_delete_link_spam}</label>');
	find_replace_templatesets('modcp_modqueue_posts_post', '({\\$lang->delete}</label>)', '{$lang->delete}</label>'."\n\t\t\t\t\t\t\t".'<label class="label_radio_delete_link_spam" title="{$lang->lkt_delete_link_spam_title}"><input type="radio" class="radio radio_delete_link_spam" name="posts[{$post[\'pid\']}]" value="delete_link_spam" /> {$lang->lkt_delete_link_spam}</label>');
	find_replace_templatesets('modcp_modqueue_masscontrols', '({\\$lang->mark_all_deletion}</a></li>)', '{$lang->mark_all_deletion}</a></li>'."\n\t".'<li><a href="javascript:void(0)" class="mass_delete_link_spam" onclick="$(\'input.radio_delete_link_spam\').each(function(){ $(this).prop(\'checked\', true); }); return false;">{$lang->lkt_mark_all_deletion_link_spam}</a></li>');
	find_replace_templatesets('moderation_purgespammer', '('.preg_quote('<input class="button" type="submit" value="{$lang->purgespammer_submit}" />').')', '<input type="checkbox" class="checkbox" name="classify_links_as_spam" value="1" checked="checked" />{$lang->lkt_classify_links_as_spam}</label><br /><input class="button" type="submit" value="{$lang->purgespammer_submit}" />');
	find_replace_templatesets('header', '('.preg_quote('{$awaitingusers}').')', '{$awaitingusers}'."\n\t\t\t\t".'{$g_lkt_potential_spam_mod_notice}');

	$res = $db->simple_select('tasks', 'tid', "file='linktools'", array('limit' => '1'));
	if ($db->num_rows($res) == 0) {
		require_once MYBB_ROOT . '/inc/functions_task.php';
		$new_task = array(
			'title' => $db->escape_string($lang->lkt_task_title),
			'description' => $db->escape_string($lang->sprintf($lang->lkt_task_description, lkt_default_rebuild_term_items_per_page)),
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
	find_replace_templatesets('newthread_postoptions', '({\\$disablelinkpreviews})', '', 0);
	find_replace_templatesets('newreply_postoptions', '({\\$disablelinkpreviews})', '', 0);
	find_replace_templatesets('editpost_postoptions', '({\\$disablelinkpreviews})', '', 0);
	find_replace_templatesets('modcp_modqueue_threads_thread', '('.preg_quote("\n\t\t\t\t\t\t\t".'<label class="label_radio_delete_link_spam" title="{$lang->lkt_delete_link_spam_title}"><input type="radio" class="radio radio_delete_link_spam" name="threads[{$thread[\'tid\']}]" value="delete_link_spam" /> {$lang->lkt_delete_link_spam}</label>').')', '', 0);
	find_replace_templatesets('modcp_modqueue_posts_post', '('.preg_quote("\n\t\t\t\t\t\t\t".'<label class="label_radio_delete_link_spam" title="{$lang->lkt_delete_link_spam_title}"><input type="radio" class="radio radio_delete_link_spam" name="posts[{$post[\'pid\']}]" value="delete_link_spam" /> {$lang->lkt_delete_link_spam}</label>').')', '', 0);
	find_replace_templatesets('modcp_modqueue_masscontrols', '('.preg_quote("\n\t".'<li><a href="javascript:void(0)" class="mass_delete_link_spam" onclick="$(\'input.radio_delete_link_spam\').each(function(){ $(this).prop(\'checked\', true); }); return false;">{$lang->lkt_mark_all_deletion_link_spam}</a></li>').')', '', 0);
	find_replace_templatesets('moderation_purgespammer', '('.preg_quote('<input type="checkbox" class="checkbox" name="classify_links_as_spam" value="1" checked="checked" />{$lang->lkt_classify_links_as_spam}</label><br />').')', '', 0);
	find_replace_templatesets('header', '('.preg_quote("\n\t\t\t\t".'{$g_lkt_potential_spam_mod_notice}').')', '', 0);

	$db->update_query('tasks', array('enabled' => 0), 'file=\'linktools\'');
}

/**
 * Enables new (uninstalled) previewers present in the filesystem and inserts their templates as necessary.
 */
function lkt_enable_new_previewers() {
	global $cache, $db;

	$lrs_plugins = $cache->read('lrs_plugins');
	$inst_previewers = !empty($lrs_plugins[C_LKT]['installed_link_previewers'])
			    ? $lrs_plugins[C_LKT]['installed_link_previewers']
			    : array();
	$present_previewers = array();
	foreach (lkt_get_linkpreviewer_classnames() as $type => $classnames) {
		$present_previewers = array_merge($present_previewers, $classnames);
	}

	foreach ($present_previewers as $present_previewer) {
		if (empty($inst_previewers[$present_previewer])) {
			$inst_previewers[$present_previewer] = array('enabled' => true);
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
			'template' => '<br /><label><input type="checkbox" class="checkbox" name="lkt_linkpreviewoff" value="1" tabindex="9"{$linkpreviewoffchecked} /> {$lang->lkt_linkpreviewoff}</label>',
			'version_at_last_change' => '10500',
		),
		'linktools_potential_spam_mod_notice' => array(
			'template' => '<div class="red_alert"><a href="{$config[\'admin_dir\']}/index.php?module=forum-linklisting&amp;spam_class_filters[1]=Potential+spam"> {$pot_spam_link_header_msg}</a></div>',
			'version_at_last_change' => '10500',
		),
	);

	// Remove any existing Master templates for this plugin except for
	// those for installed link previewers no longer present in the filesystem.
	require_once __DIR__.'/linktools/LinkPreviewerBase.php';
	$previewer_conds = '';
	$lrs_plugins = $cache->read('lrs_plugins');
	$inst_previewers = !empty($lrs_plugins[C_LKT]['installed_link_previewers'])
	                    ? $lrs_plugins[C_LKT]['installed_link_previewers']
	                    : array();
	$present_previewers = array();
	foreach (lkt_get_linkpreviewer_classnames() as $type => $classnames) {
		$present_previewers = array_merge($present_previewers, $classnames);
	}
	$inst_but_missing = array_diff(array_keys($inst_previewers), $present_previewers);
	$tplnames = array_map(function($previewer) use ($db) {return lkt_mk_tpl_nm_frm_classnm($db->escape_string($previewer));}, $inst_but_missing);
	if ($tplnames) {
		$previewer_conds = " AND title NOT IN ('".implode("','", $tplnames)."')";
	}
	$db->delete_query('templates', "sid=-2 AND title LIKE 'linktools%'{$previewer_conds}");

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

	// And now do the same for installed Link Previewer templates.
	foreach (array_intersect($present_previewers, array_keys($inst_previewers)) as $previewer) {
		$from_version   = $inst_previewers[$previewer]['tpl_installed'];
		$previewerobj   = $previewer::get_instance();
		$latest_version = $previewerobj->get_version();
		if ($tplname = $previewerobj->get_template_name(/*$ret_empty_if_default*/true)) {
			if ($latest_version > $from_version) {
				$db->update_query('templates', array('version' => 0), "title='".$db->escape_string($tplname)."' AND sid <> -2");
			}

			$fields = array(
				'title'    => $db->escape_string($tplname),
				'template' => $db->escape_string($previewerobj->get_template_raw()),
				'sid'      => '-2',
				'version'  => '1',
				'dateline' => TIME_NOW
			);
			$db->insert_query('templates', $fields);

			$inst_previewers[$previewer]['tpl_installed'] = $latest_version;
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
	$lrs_plugins = $cache->read('lrs_plugins');
}

function lkt_get_modcp_css() {
	return <<<EOF
.modqueue_controls, .modqueue_mass {
	width: inherit !important;
}
EOF;
}

function lkt_get_linkpreview_css() {
	return <<<EOF
.lkt-link-preview {
	border: 1px solid #AAA;
	padding-left: 3px;
	margin-top: 20px;
	max-width: 550px;
	min-height: 35px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.lkt-link-preview a {
	color: inherit;
	text-decoration: none;
}

.lkt-link-preview img {
	float: left;
	width: 35px;
	height: 35px !important; /*The !important is necessary here because the MyBBFancyBox plugin sets img height to auto*/
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

	// This function is called on upgrade, so first delete any existing master stylesheets.
	lkt_delete_stylesheets(/*$master_only = */true);

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
	       array(
			'name' => 'linktools_modcp.css',
			'tid' => 1,
			'attachedto' => 'modcp.php',
			'stylesheet' => $db->escape_string(lkt_get_modcp_css()),
			'cachefile' => 'linktools_modcp.css',
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

function lkt_delete_stylesheets($master_only) {
	global $db;

	$db->delete_query('themestylesheets', "(name = 'linktools.css' OR name = 'linkpreview.css' OR name = 'linktools_modcp.css')".($master_only ? ' AND tid = 1' : ''));
}

/**
 * Gets the gid of this plugin's setting group, if any.
 *
 * @return The gid or false if the setting group does not exist.
 */
function lkt_get_gid() {
	global $db;
	$prefix = C_LKT.'_';

	$query = $db->simple_select('settinggroups', 'gid', "name = '{$prefix}settings'", array(
		'order_by' => 'gid',
		'order_dir' => 'DESC',
		'limit' => 1
	));

	return $db->fetch_field($query, 'gid');
}

/**
 * Creates or updates this plugin's settings.
 */
function lkt_create_or_update_settings() {
	global $db, $lang;
	$prefix = C_LKT.'_';

	$lang->load(C_LKT);

	$setting_group = array(
		'name'         => $prefix.'settings',
		'title'        => $db->escape_string($lang->lkt_settings_title),
		'description'  => $db->escape_string($lang->lkt_settings_desc ),
		'isdefault'    => 0
	);
	$gid = lkt_get_gid();
	if (empty($gid)) {
		// Insert the plugin's settings group into the database.
		$query = $db->query('SELECT MAX(disporder) as max_disporder FROM '.TABLE_PREFIX.'settinggroups');
		$setting_group['disporder'] = intval($db->fetch_field($query, 'max_disporder')) + 1;
		$db->insert_query('settinggroups', $setting_group);
		$gid = $db->insert_id();
	} else {
		// Update the plugin's settings group in the database.
		$db->update_query('settinggroups', $setting_group, "gid='{$gid}'");
	}

	// Get the plugin's existing settings, if any.
	$existing_settings = array();
	$query = $db->simple_select('settings', 'name', "gid='{$gid}'");
	while ($setting = $db->fetch_array($query)) {
		$existing_settings[] = $setting['name'];
	}

	// Define the plugin's (new/updated) settings.
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
		'link_preview_active_forums' => array(
			'title'       => $lang->lkt_link_preview_active_forums_title,
			'description' => $lang->lkt_link_preview_active_forums_desc,
			'optionscode' => 'forumselect',
			'value'       => -1,
		),
		'link_preview_active_post_type' => array(
			'title'       => $lang->lkt_link_preview_active_post_type_title,
			'description' => $lang->lkt_link_preview_active_post_type_desc,
			'optionscode' => "select\nfirst={$lang->lkt_link_preview_active_post_first}\nreplies={$lang->lkt_link_preview_active_post_replies}\nboth={$lang->lkt_link_preview_active_post_both}",
			'value'       => 'both',
		),
		'link_preview_expiry_period' => array(
			'title'       => $lang->lkt_link_preview_expiry_period_title,
			'description' => $lang->lkt_link_preview_expiry_period_desc,
			'optionscode' => 'numeric',
			'value'       => '7',
		),
		'link_preview_expire_on_new_previewer' => array(
			'title'       => $lang->lkt_link_preview_expire_on_new_previewer_title,
			'description' => $lang->lkt_link_preview_expire_on_new_previewer_desc,
			'optionscode' => 'yesno',
			'value'       => '1',
		),
		'link_preview_on_fly' => array(
			'title'       => $lang->lkt_link_preview_on_fly_title,
			'description' => $lang->lkt_link_preview_on_fly_desc,
			'optionscode' => "select\nalways={$lang->lkt_link_preview_on_fly_always}\nnever={$lang->lkt_link_preview_on_fly_never}\nwhitelist={$lang->lkt_link_preview_on_fly_whitelist}\nblacklist={$lang->lkt_link_preview_on_fly_blacklist}",
			'value'       => 'never',
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
		'link_preview_not_in_quotes' => array(
			'title'       => $lang->lkt_link_preview_not_in_quotes_title,
			'description' => $lang->lkt_link_preview_not_in_quotes_desc,
			'optionscode' => 'yesno',
			'value'       => '1',
		),
		'link_preview_skip_if_contains_img' => array(
			'title'       => $lang->lkt_link_preview_skip_if_contains_img_title,
			'description' => $lang->lkt_link_preview_skip_if_contains_img_desc,
			'optionscode' => 'yesno',
			'value'       => '1',
		),
		'links_per_listing_page' => array(
			'title'       => $lang->lkt_links_per_listing_page_title,
			'description' => $lang->lkt_links_per_listing_page_desc,
			'optionscode' => 'numeric',
			'value'       => '40',
		),
		'anti_link_spam_usergroups' => array(
			'title'       => $lang->lkt_anti_link_spam_usergroups_title,
			'description' => $lang->lkt_anti_link_spam_usergroups_desc,
			'optionscode' => 'groupselect',
			'value'       => '',
		),
		'anti_link_spam_max_account_age_days' => array(
			'title'       => $lang->lkt_anti_link_spam_max_account_age_days_title,
			'description' => $lang->lkt_anti_link_spam_max_account_age_days_desc,
			'optionscode' => 'numeric',
			'value'       => '10',
		),
		'anti_link_spam_max_post_count' => array(
			'title'       => $lang->lkt_anti_link_spam_max_post_count_title,
			'description' => $lang->lkt_anti_link_spam_max_post_count_desc,
			'optionscode' => 'numeric',
			'value'       => '5',
		),
		'anti_link_spam_qualifying_action' => array(
			'title'       => $lang->lkt_anti_link_spam_qualifying_action_title,
			'description' => $lang->lkt_anti_link_spam_qualifying_action_desc,
			'optionscode' => "select\nnew_post={$lang->lkt_anti_link_spam_qualifying_action_new_post}\nedit_post={$lang->lkt_anti_link_spam_qualifying_action_edit_post}\neither={$lang->lkt_anti_link_spam_qualifying_action_either}",
			'value'       => 'either',
		),
		'anti_link_spam_response_action' => array(
			'title'       => $lang->lkt_anti_link_spam_response_action_title,
			'description' => $lang->lkt_anti_link_spam_response_action_desc,
			'optionscode' => "select\npurge_delete_spammer={$lang->lkt_anti_link_spam_response_action_purge_delete_spammer}\npurge_ban_spammer={$lang->lkt_anti_link_spam_response_action_purge_ban_spammer}\nreject_post_or_edit={$lang->lkt_anti_link_spam_response_action_reject_post_or_edit}\nmoderate_post={$lang->lkt_anti_link_spam_response_action_moderate_post}",
			'value'       => 'purge_delete_spammer',
		),
		'anti_link_spam_response_classify_same_post' => array(
			'title'       => $lang->lkt_anti_link_spam_response_classify_same_post_title,
			'description' => $lang->lkt_anti_link_spam_response_classify_same_post_desc,
			'optionscode' => "select\nas_spam_abs={$lang->lkt_anti_link_spam_response_classify_opt_as_spam_abs}\nas_spam={$lang->lkt_anti_link_spam_response_classify_opt_as_spam}\nas_potential_spam={$lang->lkt_anti_link_spam_response_classify_opt_as_potential_spam}\nno_change={$lang->lkt_anti_link_spam_response_classify_opt_no_change}",
			'value'       => 'as_potential_spam',
		),
		'anti_link_spam_response_classify_other_posts' => array(
			'title'       => $lang->lkt_anti_link_spam_response_classify_other_posts_title,
			'description' => $lang->lkt_anti_link_spam_response_classify_other_posts_desc,
			'optionscode' => "select\nas_spam_abs={$lang->lkt_anti_link_spam_response_classify_opt_as_spam_abs}\nas_spam={$lang->lkt_anti_link_spam_response_classify_opt_as_spam}\nas_potential_spam={$lang->lkt_anti_link_spam_response_classify_opt_as_potential_spam}\nno_change={$lang->lkt_anti_link_spam_response_classify_opt_no_change}",
			'value'       => 'as_potential_spam',
		),
	);

	// Delete existing settings no longer present in the plugin's current version.
	$new_settings = array_map(function($e) use ($prefix) {return $prefix.$e;}, array_keys($settings));
	$to_delete = array_diff($existing_settings, $new_settings);
	if ($to_delete) {
		$names_esc_cs_qt = "'".implode("', '", array_map([$db, 'escape_string'], $to_delete))."'";
		$db->delete_query('settings', "name IN ({$names_esc_cs_qt}) AND gid='{$gid}'");
	}

	// Insert into, or update in, the database each of this plugin's settings.
	$disporder = 1;
	$inserts = [];
	foreach ($settings as $name => $setting) {
		$fields = array(
			'name'        => $db->escape_string($prefix.$name          ),
			'title'       => $db->escape_string($setting['title'      ]),
			'description' => $db->escape_string($setting['description']),
			'optionscode' => $db->escape_string($setting['optionscode']),
			'value'       => $db->escape_string($setting['value'      ]),
			'disporder'   => $disporder                                 ,
			'gid'         => $gid                                       ,
			'isdefault'   => 0                                          ,
		);
		if (in_array($prefix.$name, $existing_settings)) {
			// Update the already-existing setting while retaining its value.
			unset($fields['value']);
			$db->update_query('settings', $fields, "name='{$prefix}{$name}' AND gid='{$gid}'");
		} else {
			// Queue the new setting for insertion.
			$inserts[] = $fields;
		}
		$disporder++;
	}

	if ($inserts) {
		// Insert the queued new settings.
		$db->insert_query_multiple('settings', $inserts);
	}

	rebuild_settings();
}

/**
 * Removes this plugin's settings, including its settings group.
 * Accounts for the possibility that the settings group + settings were
 * accidentally created multiple times.
 */
function lkt_remove_settings() {
	global $db;
	$prefix = C_LKT.'_';

	$rebuild = false;
	$query = $db->simple_select('settinggroups', 'gid', "name = '{$prefix}settings'");
	while ($gid = $db->fetch_field($query, 'gid')) {
		$db->delete_query('settinggroups', "gid='{$gid}'");
		$db->delete_query('settings', "gid='{$gid}'");
		$rebuild = true;
	}
	if ($rebuild) {
		rebuild_settings();
	}
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
function lkt_extract_url_from_mycode_tag(&$text, &$urls, $re, $indexes_to_use = array(1), $skip_if_contains_img = false) {
	if (preg_match_all($re, $text, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE)) {
		foreach ($matches as $match) {
			$url = '';
			$contains_img = false;
			if ($skip_if_contains_img) {
				foreach (lkt_img_regexes as $re) {
					if (preg_match($re, $match[0][0])) {
						$contains_img = true;
						break;
					}
				}
			}
			if (!$contains_img) {
				foreach ($indexes_to_use as $i) {
					$url .= $match[$i][0];
				}
				$url_match = array('endpos' => $match[0][1] + strlen($match[0][0]) - 1, 'url' => trim($url));
				lkt_test_add_url($url_match, $urls);
			}
			$text = substr($text, 0, $match[0][1]).str_repeat('*', strlen($match[0][0])).substr($text, $match[0][1] + strlen($match[0][0]));
		}
	}
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
	$text = ' '.$text;
	$text_new = $text;

	foreach (array(
		"#\[([^\]]+)(?:=[^\]]+)?\](http|https|ftp|news|irc|ircs|irc6){1}(://)([^\/\"\s\<\[\.]+\.([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?)\[/\\1\]#ius",
		"#(\[*\]|[\s\(\)\[\>])(http|https|ftp|news|irc|ircs|irc6){1}(://)([^\/\"\s\<\[\.]+\.([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?([\w\/\)]))#ius",
		"#\[([^\]]+)(?:=[^\]]+)?\](www|ftp)(\.)(([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?)\[/\\1\]#ius",
		"#(\[*\]|[\s\(\)\[\>])(www|ftp)(\.)(([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?([\w\/\)]))#ius"
	) as $re) {
		if (preg_match_all($re, $text_new, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE)) {
			foreach ($matches as $match) {
				$url = $match[2][0].$match[3][0].lkt_strip_unmatched_closing_parens($match[4][0]);
				$url_match = array('endpos' => $match[2][1] + strlen($url) - 1, 'url' => trim($url));
				lkt_test_add_url($url_match, $urls);
				// Blank out the matched URLs.
				$text_new = substr($text_new, 0, $match[2][1]).str_repeat('$', strlen($url)).substr($text_new, $match[2][1] + strlen($url));
			}
		}
	}

	$text_new = my_substr($text, 1);
	$text = $text_new;
}

# Should be kept in sync with the test_add_url() method of the DLW object in ../jscripts/linktools.js
function lkt_test_add_url($url_match, &$urls) {
	if (lkt_has_valid_scheme($url_match['url']) && !in_array($url_match, $urls)) {
		$urls[] = $url_match;
	}
}

# Should be kept in sync with the extract_urls() method of the DLW object in ../jscripts/linktools.js
function lkt_extract_urls($text, $exclude_videos = false) {
	global $mybb;

	$fn_blank_out = function ($matches) {return str_repeat(' ', strlen($matches[0]));};

	$urls = array();

	$skip_if_contains_img = $mybb->settings[C_LKT.'_link_preview_skip_if_contains_img'] ? true : false;

	# [url] tag regexes from postParser::cache_mycode() in ../class_parser.php.
	lkt_extract_url_from_mycode_tag($text, $urls, "#\[url\]((?!javascript)[a-z]+?://)([^\r\n\"<]+?)\[/url\]#si", array(1, 2), $skip_if_contains_img);
	lkt_extract_url_from_mycode_tag($text, $urls, "#\[url\]((?!javascript:)[^\r\n\"<]+?)\[/url\]#i", array(1), $skip_if_contains_img);
	lkt_extract_url_from_mycode_tag($text, $urls, "#\[url=((?!javascript)[a-z]+?://)([^\r\n\"<]+?)\](.+?)\[/url\]#si", array(1, 2), $skip_if_contains_img);
	lkt_extract_url_from_mycode_tag($text, $urls, "#\[url=((?!javascript:)[^\r\n\"<]+?)\](.+?)\[/url\]#si", array(1), $skip_if_contains_img);

	# Blank out all [video] tags if required.
	if ($exclude_videos) {
		# [video] tag regex from postParser::parse_mycode() in ../class_parser.php.
		$text = preg_replace_callback("#\[video=(.*?)\](.*?)\[/video\]#i", $fn_blank_out, $text);
	}

	# [video] tag regex from postParser::parse_mycode() in ../class_parser.php.
	lkt_extract_url_from_mycode_tag($text, $urls, "#\[video=(.*?)\](.*?)\[/video\]#i", array(2));

	# Blank out all [img] tags so their enclosing urls aren't matched by lkt_extract_bare_urls() below.
	foreach (lkt_img_regexes as $re) {
		$text = preg_replace_callback($re, $fn_blank_out, $text);
	}

	lkt_extract_bare_urls($text, $urls);

	usort($urls, function($a, $b) {return $a['endpos'] == $b['endpos'] ? 0 : ($a['endpos'] < $b['endpos'] ? -1 : 1);});

	$endposns = $urls_ret = array();
	foreach ($urls as $url_match) {
		if (!in_array($url_match['url'], $urls_ret)) {
			$urls_ret[] = $url_match['url'];
			$endposns[] = $url_match['endpos'];
		}
	}

	return array($urls_ret, $endposns);
}

function lkt_get_url_search_sql($urls, $already_normalised = false, $extra_conditions = '', $raw_only = false) {
	global $db;

	if ($already_normalised) {
		$urls_norm = $urls;
	} else if (!$raw_only) {
		sort($urls);
		$urls_norm = lkt_normalise_urls($urls);
	}

	if ($raw_only) {
		$url_paren_list = "('".implode("', '", array_map(array($db, 'escape_string'), $urls))."')";
		$conds = 'u.url IN '.$url_paren_list;
	} else {
		$url_paren_list = "('".implode("', '", array_map(array($db, 'escape_string'), $urls_norm))."')";
		$conds = 'u.url_norm IN '.$url_paren_list.' OR u.url_term_norm IN '.$url_paren_list;
	}

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
		if (isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1) {
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
	global $db, $parser, $all_matching_urls_in_quotes_flag;

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
			list($matching_posts[$row['pid']]['all_urls']) = lkt_extract_urls($row['message']);
			// The raw URLs (i.e., not normalised) present in this post that were a match for
			// the raw URLs (again, not normalised) for which we are querying, in that
			// both terminate (i.e., after following all redirects) in the same normalised URL.
			$matching_posts[$row['pid']]['matching_urls_in_post'] = [];
			// The raw URLs for which we are querying that are matched in this post, in the
			// same order as the above array (i.e., entries at the same index in both arrays
			// both terminate in the same normalised URL).
			$matching_posts[$row['pid']]['matching_urls'] = [];
			$stripped = lkt_strip_nestable_mybb_tag($row['message'], 'quote');
			list($urls_quotes_stripped) = lkt_extract_urls($stripped);
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
		$grade_post = function($post) use ($urls) {
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

function lkt_purge_spammer($uid_in, $type) {
	global $mybb, $db, $cache, $plugins, $lang, $uid, $user;

	$uid = $uid_in;

	if ($uid < 0) {
		// We can't purge a guest, so just return.
		return;
	} else if (!is_super_admin($uid)) { // Don't mistakenly ban/delete a super-admin.
		if (!isset($lang->linktools)) {
			$lang->load(C_LKT);
		}

		$user = get_user($uid);

		// For the sake of the hook from core also triggered below, temporarily change
		// the 'purgespammerbandelete' setting to accord with the type of purge we're
		// undertaking here.
		$saved_purgespammerbandelete = $mybb->settings['purgespammerbandelete'];
		$mybb->settings['purgespammerbandelete'] = $type;

		// Due to the condition in the user handler methods called below which avoids
		// action when $uid == $mybb->user['uid'], temporarily change $mybb->user['uid']
		// so that it does not equal $uid.
		$saved_user_uid = $mybb->user['uid'];
		$mybb->user['uid'] = 0;


		// Begin code heavily based on the corresponding code in moderation.php.
		// This duplication is unfortunately necessary because that code isn't easily
		// callable from here.

		// Run the hooks first to avoid any issues when we delete the user.
		$plugins->run_hooks('moderation_purgespammer_purge');

		require_once MYBB_ROOT.'inc/datahandlers/user.php';
		$userhandler = new UserDataHandler('delete');

		if ($type == 'ban') {
			// First delete everything
			$userhandler->delete_content($uid);
			$userhandler->delete_posts($uid);

			// Next ban him/her (or update the banned reason; this shouldn't happen)
			$query = $db->simple_select('banned', 'uid', "uid = '{$uid}'");
			if ($db->num_rows($query) > 0) {
				$banupdate = array(
					'reason' => $db->escape_string($mybb->settings['purgespammerbanreason'])
				);
				$db->update_query('banned', $banupdate, "uid = '{$uid}'");
			} else {
				$insert = array(
					'uid' => $uid,
					'gid' => (int)$mybb->settings['purgespammerbangroup'],
					'oldgroup' => 2,
					'oldadditionalgroups' => '',
					'olddisplaygroup' => 0,
					'admin' => 0,
					'dateline' => TIME_NOW,
					'bantime' => '---',
					'lifted' => 0,
					'reason' => $db->escape_string($mybb->settings['purgespammerbanreason'])
				);
				$db->insert_query('banned', $insert);
			}


			// Begin code NOT present in the code otherwise duplicated from moderation.php that
			// seemingly *ought* to have been present - we add it here.

			// Move the user to the banned group
			$update_array = array(
				'usergroup' => (int)$mybb->settings['purgespammerbangroup'],
				'displaygroup' => 0,
				'additionalgroups' => '',
			);
			$db->update_query('users', $update_array, "uid = {$uid}");

			// End code NOT present in the code otherwise duplicated from moderation.php.


			// Add the IPs to the banfilters
			if ($mybb->settings['purgespammerbanip'] == 1) {
				foreach (array($user['regip'], $user['lastip']) as $ip) {
					$ip = my_inet_ntop($db->unescape_binary($ip));
					$query = $db->simple_select('banfilters', 'type', "type = 1 AND filter = '".$db->escape_string($ip)."'");
					if ($db->num_rows($query) == 0) {
						$insert = array(
							'filter' => $db->escape_string($ip),
							'type' => 1,
							'dateline' => TIME_NOW
						);
						$db->insert_query('banfilters', $insert);
					}
				}
			}

			// Clear the profile
			$userhandler->clear_profile($uid, $mybb->settings['purgespammerbangroup']);

			$cache->update_bannedips();
			$cache->update_awaitingactivation();

			// Update reports cache
			$cache->update_reportedcontent();
		} else if ($type == 'delete') {
			$userhandler->delete_user($uid, 1);
		}

		// Submit the user to Stop Forum Spam
		if (!empty($mybb->settings['purgespammerapikey'])) {
			$sfs = @fetch_remote_file('http://stopforumspam.com/add.php?username='.urlencode($user['username']).'&ip_addr='.urlencode(my_inet_ntop($db->unescape_binary($user['lastip']))).'&email='. urlencode($user['email']).'&api_key='.urlencode($mybb->settings['purgespammerapikey']));
		}

		log_moderator_action(array('uid' => $uid, 'username' => $user['username']), $lang->lkt_purge_link_spammer_modlog);

		// End code heavily based on the corresponding code in moderation.php


		// Restore the temporarily changed setting.
		$mybb->settings['purgespammerbandelete'] = $saved_purgespammerbandelete;

		// Restore the temporarily changed user ID.
		$mybb->user['uid'] = $saved_user_uid;

	}
}

function lkt_handle_new_post($posthandler) {
	global $g_lkt_moderate_post, $g_lkt_links_incl_vids, $g_lkt_redirs, $g_lkt_got_terms;

	if ($posthandler->data['savedraft']) {
		return;
	}

	$uid = isset($posthandler->data['uid']) ? $posthandler->data['uid'] : 0;

	// Store any links and their association with this new post to the database
	// (when $g_lkt_moderate_post is true, we will have stored them already in the
	// validation hook, but won't have associated them with this post by providing
	// a pid as we do here).
	if (isset($g_lkt_links_incl_vids)) {
		lkt_store_urls($g_lkt_links_incl_vids, $g_lkt_redirs, $g_lkt_got_terms, $posthandler->pid);
		$redirs = $g_lkt_redirs;
	} else	$redirs = lkt_resolve_and_store_urls_from_message($posthandler->data['message'], $posthandler->pid)[0];

	// Moderate this new post if...
	if (
	    // ... we determined in the earlier validation hook that it ought to be,
	    // given the anti-link spam policy...
	    !empty($g_lkt_moderate_post)
	    // ...or links were included in it and posts by members of this usergroup
	    // in this forum are set to be moderated in that scenario
	    ||
	    $redirs
	    &&
	    forum_permissions($posthandler->data['fid'], $uid)['lkt_mod_link_in_new_post']
	) {
		global $lang;

		if (!isset($lang->linktools)) {
			$lang->load(C_LKT);
		}

		$posthandler->return_values['visible'] = 0;
		require_once MYBB_ROOT.'inc/class_moderation.php';
		$moderation = new Moderation;
		$moderation->unapprove_posts([$posthandler->pid]);
		$lang_key = !empty($g_lkt_moderate_post) ? 'lkt_redirect_newreply_anti_link_spam_moderation' : 'lkt_redirect_newreply_moderation';
		$lang->redirect_newreply_moderation = $lang->$lang_key;
	}
}

function lkt_resolve_and_store_urls_from_message($message, $pid = null) {
	return lkt_resolve_and_store_urls_from_list(lkt_extract_urls($message)[0], $pid);
}

function lkt_resolve_and_store_urls_from_list($urls, $pid = null, $spam_class = 'Unspecified', $override_spam_class_policy = 'ignore') {
	list($redirs, $got_terms) = lkt_get_resolved_urls_from_list($urls);
	lkt_store_urls($urls, $redirs, $got_terms, $pid, $spam_class, $override_spam_class_policy);

	return [$redirs, $got_terms];
}

function lkt_get_resolved_urls_from_list($urls) {
	global $db;

	// Don't waste time and bandwidth resolving redirects for URLs already in the DB.
	$res = $db->simple_select('urls', 'url, url_term, got_term', "url in ('".implode("', '", array_map(array($db, 'escape_string'), $urls))."')");
	$got_terms = $existing_urls = $existing_redirs = [];
	while (($row = $db->fetch_array($res))) {
		$existing_urls     []            = $row['url'     ];
		$existing_redirs   [$row['url']] = $row['url_term'];
		$got_terms         [$row['url']] = $row['got_term'];
	}
	$redirs = lkt_resolve_url_terms(array_diff($urls, $existing_urls), $got_terms);

	foreach (array_intersect($urls, $existing_urls) as $url) {
		$redirs[$url] = $existing_redirs[$url];
	}

	return [$redirs, $got_terms];
}

/**
 * Stores a list of URLs to the database.
 *
 * @param array $urls        An array of URLs as strings.
 * @param array $redirs      An array each key of which is a URL from $urls and each value of which is the URL in which
 *                           that URL terminates after all redirects have been followed.
 * @param mixed $got_terms   Boolean false if no attempt was made at resolving terminating redirects, otherwise,
 *                           an array indexed by URLs from $urls indicating (boolean true/false) whether or not
 *                           a terminating URL (after resolving all redirects) was found for the given URL.
 * @param integer $pid       The post ID of the post with which to associate the URLs, if any. Null indicates not to
 *                           associate the URLs with any post.
 * @param string $spam_class The value to store or update for the `spam_class` field in the `urls` table for each URL.
 *                           One of 'Unspecified', 'Potential spam', 'Not spam', and 'Spam'.
 * @param string $override_spam_class_policy The policy for updating the `spam_class` database field where a URL
 *                                           already exists in the `urls` database table.
 *                                           One of 'override', 'conditional', 'ignore', where:
 *                                           'override'    means "unconditionally update to the value of $spam_class",
 *                                           'conditional' means "update to the value of $spam_class when the existing
 *                                                                value of the `spam_class` field is 'Unspecified' or
 *                                                                'Potential spam', and
 *                                           'ignore'      means "do not update any existing value".
 */
function lkt_store_urls($urls, $redirs, $got_terms, $pid = null, $spam_class = 'Unspecified', $override_spam_class_policy = 'ignore') {
	global $db;

	$now = time();
	foreach ($urls as $url) {
		$target = (!$got_terms || empty($got_terms[$url])) ? '' : $redirs[$url];
		for ($try = 1; $try <= 2; $try++) {
			$res = $db->simple_select('urls', 'urlid', 'url = \''.$db->escape_string($url).'\'');
			if ($row = $db->fetch_array($res)) {
				$urlid = $row['urlid'];
				if (in_array($override_spam_class_policy, ['override', 'conditional'])) {
					$conds = "url = '".$db->escape_string($url)."'";
					if ($override_spam_class_policy == 'conditional') {
						$conds .= " AND spam_class IN ('Unspecified', 'Potential spam')";
					}
					$db->update_query('urls', ['spam_class' => $spam_class], $conds);
				}
			} else {
				$url_fit         = substr($url   , 0, lkt_max_url_len);
				$url_norm_fit    = substr(lkt_normalise_url($url), 0, lkt_max_url_len);
				$target_fit      = substr($target, 0, lkt_max_url_len);
				$target_norm_fit = substr(lkt_normalise_url($target == false ? $url : $target), 0, lkt_max_url_len);
				// Simulate the enforcement of a UNIQUE constraint on the `url` column
				// using a SELECT with a HAVING condition. This prevents the possibility of
				// rows with duplicate values for `url`.
				if (!$db->write_query('
INSERT INTO '.TABLE_PREFIX.'urls (url, url_norm, url_term, url_term_norm, got_term, term_tries, last_term_try, spam_class, dateline)
       SELECT \''.$db->escape_string($url_fit).'\', \''.$db->escape_string($url_norm_fit).'\', \''.$db->escape_string($target == false ? $url_fit : $target_fit).'\', \''.$db->escape_string($target_norm_fit).'\', \''.(!$got_terms || empty($got_terms[$url]) ? '0' : '1')."', '".(!$got_terms ? '0' : '1')."', '$now', '".$db->escape_string($spam_class)."', '$now'".'
       FROM '.TABLE_PREFIX.'urls WHERE url=\''.$db->escape_string($url).'\'
       HAVING COUNT(*) = 0')
				    ||
				    $db->affected_rows() <= 0) {
					// We retry in this scenario because it is theoretically possible
					// that the URL was inserted by another process in between the
					// select and the insert, and that the false return is due to the
					// HAVING condition failing. On retrying in that case, the
					// simple_select() above will allow us to identify the `urlid` of
					// the URL as inserted by that other process, so that we can
					// ensure below that it is associated with the post with ID $pid.
					continue;
				}
				$urlid = $db->insert_id();
			}

			if ($pid !== null) {
				// We hide errors here because there is a race condition in which this insert could
				// be performed by another process (a task or rebuild) before the current process
				// performs it, in which case the database will reject the insert as violating the
				// uniqueness of the primary key (urlid, pid).
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
 * (with access to the HTML of any terminating redirects), generates link previews for those
 * terminating redirects.
 *
 * Uses the non-blocking functionality of cURL so that multiple URLs can be checked simultaneously,
 * but avoids hitting the same web server more than once every lkt_rehit_delay_in_secs seconds.
 *
 * This function only makes sense for $urls with a protocol of http:// or https://. $urls missing a
 * scheme are assumed to use the http:// protocol. For all other protocols, the $url is deemed to
 * terminate at itself.
 *
 * @param array   $urls
 * @param array   $server_last_hit_times    The UNIX epoch timestamps at which each server was last
 *                                           polled, indexed by normalised (any 'www.' prefix
 *                                           removed) server name.
 * @param boolean $use_head_method          If true, the HEAD method is used for all requests,
 *                                           otherwise the GET method is used.
 * @param boolean $check_html_redirects     Self-explanatory.
 * @param boolean $check_html_canonical_tag Self-explanatory.
 * @param boolean $get_link_previews        Whether or not to generate link previews and store them
 *                                           to the DB.
 *
 * @return Array Contains two array entries, $redirs and $deferred_urls.
 *         $redirs contains the immediate redirects of each of the URLs in $urls (which form
 *                   the keys of the $redirs array), if any.
 *                 If a URL does not redirect, then that URL's entry is set to itself.
 *                 If a link-specific error occurs for a URL, e.g. web server timeout,
 *                   then that URL's entry is set to false.
 *                 If a non-link-specific error occurs, such as failure to initialise a generic cURL
 *                   handle, then that URL's entry is set to null.
 *         $deferred_urls an array containing any URLs that were deferred because querying them
 *                        would have hit their server within lkt_rehit_delay_in_secs seconds
 *                        of the last time we hit that server.
 */
function lkt_get_url_redirs($urls, &$server_last_hit_times = array(), &$origin_urls = [], $use_head_method = true, $check_html_redirects = false, $check_html_canonical_tag = false, $get_link_previews = true) {
	$redirs = $deferred_urls = $curl_handles = [];

	if (!$urls) return [$redirs, $deferred_urls];
	$urls = array_values($urls);

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
		return array(array_combine($urls, array_fill(0, count($urls), false)), array());
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

		$opts = array(
			CURLOPT_URL            => $url_trim_nohash,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_NOBODY         => $use_head_method,
			CURLOPT_TIMEOUT        => lkt_curl_timeout,
			CURLOPT_USERAGENT      => 'The MyBB Link Tools plugin',
		);
		foreach (lkt_get_extra_curl_opts() as $k => $v) {
			$opts[$k] = $v;
		}
		if (!curl_setopt_array($ch, $opts)) {
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
					$content_type = lkt_get_content_type_from_hdrs($headers);
					$charset = lkt_get_charset($headers, $html);
					if ($get_link_previews) {
						lkt_get_gen_link_preview($url, $html, $content_type, $charset);
					}
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

function lkt_resolve_url_terms_auto($urls) {
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

function lkt_get_linkpreviewer_classnames() {
	static $LinkPreviewerClassNames = false;

	require_once __DIR__.'/linktools/LinkPreviewerBase.php';

	if (!$LinkPreviewerClassNames) {
		$LinkPreviewerClassNames = array('3p' => array(), 'dist' => array());
		foreach (array('link-previewers-3rd-party', 'link-previewers-dist') as $subdir) {
			foreach (new DirectoryIterator(__DIR__.'/linktools/'.$subdir) as $file) {
				if ($file->isDot() || strtolower($file->getExtension()) != 'php') {
					continue;
				}
				$filepath = __DIR__.'/linktools/'.$subdir.'/'.$file->getFilename();
				$previewer_classname = $file->getBasename('.php');
				require_once $filepath;
				$LinkPreviewerClassNames[$subdir == 'link-previewers-3rd-party' ? '3p' : 'dist'][] = $previewer_classname;
			}
		}
	}

	return $LinkPreviewerClassNames;
}

/**
 * Determines whether or not the provided URL has or requires a preview, based
 * on the plugin's ACP domain settings and the enabled Previewer classes.
 *
 * @param string $term_url     A terminating URL.
 * @param mixed  $manual_regen 1. Boolean true indicates that this is a
 *                                user-requested manual regeneration of the
 *                                preview subject to the wait period.
 *                             2. String 'force_regen' indicates the same except
 *                                without the need to respect the wait period.
 *                             3. Boolean false indicates that this is not a
 *                                user-requested manual regeneration.
 * @param mixed  $content_type A string indicating the content-type of the page
 *                             at $term_url, or boolean false if unknown.
 * @param mixed  $content      A string containing the content of the page at
 *                             $term_url, or boolean false if unknown.
 * @return Array Contains at least two entries, the first keyed by
 *         'result', with value as one of the constants defined below, and the
 *         second keyed by 'has_db_entry', indicating (true/false/null) whether
 *         the preview for this URL has a database entry. The value for the
 *         'result' key has the following meanings:
 *          LKT_PV_NOT_REQUIRED : a preview is not required for the supplied URL.
 *          LKT_PV_DATA_FOUND   : valid preview data was retrieved for the
 *                                supplied URL, in which case the returned array
 *                                also contains that preview data, indexed by
 *                                'preview_data', and the LinkPreviewer class
 *                                which can generate the preview from that data,
 *                                indexed as 'previewer'.
 *          LKT_PV_TOO_SOON     : $manual_regen was set true but it is too soon
 *                                since the last regen to perform another one.
 *          LKT_PV_GOT_PREVIEWER: a preview is required but its data is not
 *                                cached, in which case the returned array also
 *                                contains the name of the prioritised Link
 *                                Previewer class which should be used to
 *                                generate the preview, indexed by 'previewer'.
 *                                The returned array also contains a 'provis'
 *                                key indexing a boolean value to indicate
 *                                whether the Previewer is provisional, in which
 *                                case whether it becomes final depends on its
 *                                support for the page's yet-to-be-downloaded
 *                                content and/or its content-type.
 */
define('LKT_PV_NOT_REQUIRED' , 1);
define('LKT_PV_DATA_FOUND'   , 2);
define('LKT_PV_TOO_SOON'     , 3);
define('LKT_PV_GOT_PREVIEWER', 4);
function lkt_url_has_needs_preview($term_url, $manual_regen = false, $content_type = false, $content = false) {
	global $db, $mybb, $cache;

	static $cached_returns = array();

	if (!empty($cached_returns[$term_url]) && $cached_returns[$term_url]['args'] == array($manual_regen, $content_type, $content)) {
		return $cached_returns[$term_url]['return'];
	}

	$has_db_entry = null;

	if (!in_array(lkt_get_scheme($term_url), array('http', 'https', ''))) {
		$ret = array('result' => LKT_PV_NOT_REQUIRED, 'has_db_entry' => $has_db_entry);
		goto lkt_url_has_needs_preview_end;
	}

	// First, check settings to determine whether we need a preview for this type of URL.
	if ($mybb->settings[C_LKT.'_link_preview_disable_self_dom'] && lkt_get_norm_server_from_url($term_url) == lkt_get_norm_server_from_url($mybb->settings['bburl'])) {
		$ret = array('result' => LKT_PV_NOT_REQUIRED, 'has_db_entry' => $has_db_entry);
		goto lkt_url_has_needs_preview_end;
	}
	switch ($mybb->settings[C_LKT.'_link_preview_type']) {
		case 'none':
			$ret = array('result' => LKT_PV_NOT_REQUIRED, 'has_db_entry' => $has_db_entry);
			goto lkt_url_has_needs_preview_end;
		case 'whitelist':
		case 'blacklist':
			$list = preg_split('/\r\n|\n|\r/', $mybb->settings[C_LKT.'_link_preview_dom_list']);
			$whitelisting = $mybb->settings[C_LKT.'_link_preview_type'] == 'whitelist';
			if ($whitelisting && !$list) {
				return array('result' => LKT_PV_NOT_REQUIRED, 'has_db_entry' => $has_db_entry);
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
				$ret = array('result' => LKT_PV_NOT_REQUIRED, 'has_db_entry' => $has_db_entry);
				goto lkt_url_has_needs_preview_end;
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

	// Next, get all LinkPreviewer classes.
	$LinkPreviewerClassNames = lkt_get_linkpreviewer_classnames();

	// Load all installed Previewers.
	$lrs_plugins = $cache->read('lrs_plugins');
	$inst_previewers = !empty($lrs_plugins[C_LKT]['installed_link_previewers'])
	                    ? $lrs_plugins[C_LKT]['installed_link_previewers']
	                    : array();

	// Now, get the highest-prioritised LinkPreviewer class for this link type.
	$max_priority_provis                 = $max_priority        = PHP_INT_MIN;
	$priority_previewer_classname_provis = $priority_previewer_classname = '';
	$types = array('3p', 'dist');
	$have_content = ($content_type !== false && $content !== false);
	foreach ($types as $previewer_type) {
		foreach ($LinkPreviewerClassNames[$previewer_type] as $previewer_class_name) {
			if (!empty($inst_previewers[$previewer_class_name]['enabled'])) {
				$previewerobj = $previewer_class_name::get_instance();
				if ($have_content) {
					$supported = $previewerobj->supports_page($term_url, $content_type, $content);
					if ($supported && $previewerobj->get_priority() >= $max_priority) {
						$max_priority = $previewerobj->get_priority();
						$priority_previewer_classname = $previewer_class_name;
					}
				} else {
					$supported = $previewerobj->supports_link($term_url);
					if ($supported) {
						// We use >= in these tests because the default
						// link previewer has a priority which equals the
						// initial value of $max_priority set above
						// (PHP_INT_MIN).
						if ($previewerobj->needs_content_for() & LinkPreviewer::NC_FOR_SUPPORT) {
							if ($previewerobj->get_priority() >= $max_priority_provis) {
								$max_priority_provis = $previewerobj->get_priority();
								$priority_previewer_classname_provis = $previewer_class_name;
							}
						} else if ($previewerobj->get_priority() >= $max_priority) {
							$max_priority = $previewerobj->get_priority();
							$priority_previewer_classname = $previewer_class_name;
						}
					}
				}
			}
		}
	}

	$is_provisional =
	   !$have_content
	   &&
	   (($max_priority_provis > $max_priority
	     ||
	     $priority_previewer_classname_provis === 'LinkPreviewerDefault'
	    )
	    &&
	    !($priority_previewer_classname
	      &&
	      $priority_previewer_classname::get_instance()->needs_content_for() === LinkPreviewer::NC_NEVER_AND_FINAL
	     )
	   );

	$regen = false;

	// Now, check whether the preview data already exists, is valid, has not
	// yet expired, and is not invalid due to having been generated by a
	// no-longer-prioritised Previewer or an earlier version of the
	// still-prioritised Previewer (when the relevant plugin setting is
	// enabled).
	$query = $db->simple_select('url_previews', 'valid, dateline, previewer_class_name, previewer_class_vers, preview_data', "url_term = '".$db->escape_string($term_url)."'");
	$row = $db->fetch_array($query);
	$has_db_entry = $row ? true : false;
	if ($manual_regen === 'force_regen'
	    ||
	    $row && $priority_previewer_classname
	    &&
	    $row['previewer_class_name'] != $priority_previewer_classname
	    &&
	    $priority_previewer_classname::get_instance()->needs_content_for() === LinkPreviewer::NC_NEVER_AND_FINAL
	   ) {
		$regen = true;
	} else if ($row) {
		if ($manual_regen) {
			$min_wait = lkt_preview_regen_min_wait_secs;
			if (TIME_NOW <= $row['dateline'] + $min_wait) {
				$ret = array('result' => LKT_PV_TOO_SOON, 'has_db_entry' => $has_db_entry);
				goto lkt_url_has_needs_preview_end;
			} else	$regen = true;
		} else {
			$expiry_period = $mybb->settings[C_LKT.'_link_preview_expiry_period'];
			$regen = (!$row['valid'] || $expiry_period && $expiry_period * 24*60*60 < TIME_NOW - $row['dateline']);
			if (!$regen && $mybb->settings[C_LKT.'_link_preview_expire_on_new_previewer']) {
				$org_previewer = $row['previewer_class_name'];
				// The "Expire link previews on previewer change" setting does not
				// apply to Previewers which depend on content or content type,
				// because the finalisation of those Previewers requires a query
				// of the link's web server, defeating the purpose of using
				// the cache where possible.
				$regen = (!$is_provisional && ($org_previewer != $priority_previewer_classname || class_exists($org_previewer) && $org_previewer::get_instance()->get_version() != $row['previewer_class_vers']));
			}
			if (!$regen) {
				$preview_data = unserialize($row['preview_data']);
			}
			if ($regen && !$on_the_fly) {
				$regen = false;
				$preview_data = unserialize($row['preview_data']);
			}
		}
	} else	$regen = $on_the_fly;

	$ret = array('provis' => $is_provisional, 'has_db_entry' => $has_db_entry, 'previewer'  => $is_provisional ? $priority_previewer_classname_provis : $priority_previewer_classname);
	if ($regen) {
		$ret['result']       = LKT_PV_GOT_PREVIEWER;
	} else if ($row && !empty($inst_previewers[$row['previewer_class_name']]['enabled'])) {
		$ret['result']       = LKT_PV_DATA_FOUND;
		$ret['preview_data'] = $preview_data;
		$ret['previewer']    = $row['previewer_class_name'];
	} else if (!empty($ret['previewer'])) {
		$ret['result']       = LKT_PV_GOT_PREVIEWER;
	} else	$ret['result']       = LKT_PV_NOT_REQUIRED;

lkt_url_has_needs_preview_end:
	$cached_returns[$term_url] = array('args' => array($manual_regen, $content_type, $content), 'return' => $ret);
	// Early return possible.
	return $ret;
}

/**
 * @param array   $term_urls   Entries are non-normalised terminating URLs.
 * @param boolean $force_regen Whether to force a regeneration of the preview
 *                              data.
 *
 * @return array Keys are non-normalised URLs as supplied in $term_urls;
 *               values are the previews of the corresponding terminating URLs
 *               also as supplied in $term_urls.
 */
function lkt_get_gen_link_previews($term_urls, $force_regen = false) {
	global $db;

	$previews = $pv_data = array();
	$term_urls_uniq = array_values(array_unique($term_urls));

	foreach ($term_urls_uniq as $term_url) {
		// There is room for optimisation here: potentially, a database
		// query is made here on each iteration of the loop, which is
		// inefficient.
		$res = lkt_url_has_needs_preview($term_url, $force_regen ? 'force_regen' : false);
		if ($res['result'] === LKT_PV_DATA_FOUND) {
			$previewerobj = $res['previewer']::get_instance();
			$previews[$term_url] = $previewerobj->get_preview($term_url, $res['preview_data']);
		} else if ($res['result'] === LKT_PV_GOT_PREVIEWER && $res['previewer']) {
			if ($res['provis']) {
				$pv_data[$term_url] = array(
					'pv_classname' => $res['previewer'   ],
					'pv_provis'    =>                true ,
					'has_db_entry' => $res['has_db_entry']
				);
			} else {
				if ($res['previewer']::get_instance()->needs_content_for() & LinkPreviewer::NC_FOR_GEN_PV) {
					$pv_data[$term_url] = array(
						'pv_classname' => $res['previewer'   ],
						'pv_provis'    =>                false,
						'has_db_entry' => $res['has_db_entry']
					);
				} else	$previews[$term_url] = lkt_get_gen_link_preview($term_url, '', '', '', $res['previewer'], $res['has_db_entry']);
			}
		} else	$previews[$term_url] = '';
	}
	if ($pv_data) {
		$server_urls = array();
		foreach (array_keys($pv_data) as $url) {
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
					$ret = array();
					foreach ($term_urls as $url => $term_url) {
						$ret[$url] = '';
					}
					return $ret;
				}

				foreach ($qry_urls as $url) {
					if (($ch = curl_init()) === false) {
						$previews[$url] = '';
						continue;
					}

					// Strip from any # in the URL onwards because URLs with fragments
					// appear to be buggy either in certain older versions of cURL and/or
					// web server environments from which cURL is called.
					list($url_trim_nohash) = explode('#', trim($url), 2);

					$opts = array(
						CURLOPT_URL            => $url_trim_nohash,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_HEADER         => true,
						CURLOPT_TIMEOUT        => lkt_curl_timeout,
						CURLOPT_USERAGENT      => lkt_curl_useragent,
					);
					foreach (lkt_get_extra_curl_opts() as $k => $v) {
						$opts[$k] = $v;
					}
					if (!curl_setopt_array($ch, $opts)) {
						curl_close($ch);
						$previews[$url] = '';
						continue;
					}
					if (curl_multi_add_handle($mh, $ch) !== CURLM_OK/*==0*/) {
						$previews[$url] = '';
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
						$server_urls[lkt_get_norm_server_from_url($url)]['last_hit'] = $ts_now2;
						if ($content
						    &&
						    ($header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE)) !== false
						    &&
						    ($response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) !== false
						   ) {
							$headers = substr($content, 0, $header_size);
							$content_type = lkt_get_content_type_from_hdrs($headers);
							$html = substr($content, $header_size);
							$charset = lkt_get_charset($headers, $html);
							$have_preview = false;
						} else {
							$headers = '';
							$content_type = 'text/html';
							$html = '';
							$charset = '';
							$have_preview = false;
						}
						if ($pv_data[$url]['pv_provis']) {
							$res = lkt_url_has_needs_preview($url, $force_regen ? 'force_regen' : false, $content_type, $content);
							if ($res['result'] === LKT_PV_DATA_FOUND) {
								$previewerobj = $res['previewer']::get_instance();
								$previews[$url] = $previewerobj->get_preview($term_url, $res['preview_data']);
								$have_preview = true;
							}
							if ($res['result'] === LKT_PV_GOT_PREVIEWER) {
								$pv_data[$url]['pv_classname'] = $res['previewer'];
							}
						}
						if (!$have_preview) {
							$previews[$url] = lkt_get_gen_link_preview($url, $html, $content_type, $charset, $pv_data[$url]['pv_classname'], $pv_data[$url]['has_db_entry']);
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

function lkt_get_charset($headers, $html) {
	$charset = lkt_get_charset_from_content_decl(lkt_get_header($headers, 'content-type'));
	if (preg_match('(<head(?:\\s[^>]*>|>)(.*?)</head>)sim', $html, $matches)) {
		$head = $matches[1];
		$tag_attrs = lkt_get_attrs_of_tags($head, 'meta');
		foreach ($tag_attrs as $attrs) {
			if (!empty($attrs['http-equiv']) && $attrs['http-equiv'] == 'content-type' && !empty($attrs['content'])) {
				$charset = lkt_get_charset_from_content_decl($attrs['content']);
			} else if (!empty($attrs['charset'])) {
				$charset = $attrs['charset'];
			}
		}
	}

	return $charset;
}

function lkt_get_attrs_of_tags($head, $tagname, $lowercase_keys = true) {
	$attrs = array();
	if (preg_match_all('(<'.preg_quote($tagname).'\\s+([^>]*)>)', $head, $matches, PREG_PATTERN_ORDER)) {
		foreach ($matches[1] as $inner) {
			$attrs_tmp = array();
			if (preg_match_all('(([^=]+)=\\s*("[^"]*"|[^\\s]+))', trim($inner), $matches2, PREG_SET_ORDER)) {
				foreach ($matches2 as $m2) {
					$key = trim($m2[1]);
					if ($lowercase_keys) {
						$key = my_strtolower($key);
					}
					$val = trim($m2[2], '"');

					$attrs_tmp[$key] = $val;
				}
			}
			$attrs[] = $attrs_tmp;
		}
	}

	return $attrs;
}

function lkt_get_charset_from_content_decl($content_decl) {
	$charset = '';

	$a = explode(';', $content_decl, 2);
	if (count($a) == 2) {
		$a2 = explode('=', trim($a[1]), 2);
		if (count($a2) == 2 && my_strtolower(trim($a2[0])) == 'charset') {
			$charset = trim($a2[1]);
		}
	}

	return $charset;
}

function lkt_get_content_type_from_hdrs($headers) {
	// Strip off any charset declaration
	return trim(explode(';', lkt_get_header($headers, 'content-type'), 2)[0]);
}

function lkt_get_header($headers, $header_name) {
	$header_name = strtolower(trim($header_name));
	foreach (preg_split('/\r\n|\n|\r/', $headers) as $header) {
		$arr = explode(':', $header, 2);
		if (count($arr) >= 2) {
			$hdr_name = trim($arr[0]);
			if (strtolower($hdr_name) == $header_name) {
				$hdr_content = trim($arr[1]);

				return $hdr_content;
			}
		}
	}

	return false;
}

/* Heavily based on:
 * https://www.php.net/manual/en/function.mb-list-encodings.php#122266
 */
function lkt_get_valid_mb_encodings() {
	static $ret = null;

	if (is_null($ret)) {
		$ret = array_unique(
			array_merge(
				$enc = mb_list_encodings(),
				call_user_func_array(
					'array_merge',
					array_map(
						'mb_encoding_aliases',
						$enc
					)
				)
			)
		);
	}

	return $ret;
}

/**
 * Get the preview for a link, first generating its data and storing that to the
 * DB if appropriate/necessary.
 *
 * If a Link Previewer class name is provided, then it is assumed the check for
 * whether the link's preview data needs to be (re)generated has already been
 * performed, and resulted in a need for (re)generation via the Link Previewer
 * with the provided class name. If, additionally, $has_db_entry is set true,
 * then it is assumed that a database entry for the link's data already exists,
 * and so an update query is performed rather than an insert query.
 */
function lkt_get_gen_link_preview($term_url, $html, $content_type, $charset = '', $pv_classname = false, $has_db_entry = null, $force_regen = false) {
	global $db;

	$preview = '';

	if (!$pv_classname) {
		$res = lkt_url_has_needs_preview($term_url, $force_regen, $content_type, $html);
	} else	$res = array('result' => LKT_PV_GOT_PREVIEWER, 'previewer' => $pv_classname, 'has_db_entry' => $has_db_entry);

	if ($res['result'] == LKT_PV_NOT_REQUIRED) {
		return false;
	} else if ($res['result'] == LKT_PV_GOT_PREVIEWER && $res['previewer']) {
		// We need to (re)generate the preview data.
		$has_db_entry         = $res['has_db_entry'];
		$previewerobj         = $res['previewer']::get_instance();
		$should_cache_preview = $previewerobj->get_should_cache_preview();

		// Handle different character sets by converting them to UTF8.
		if (strtolower($charset) != 'utf-8') {
			$from = in_array($charset, lkt_get_valid_mb_encodings()) ? $charset : mb_detect_encoding($html);
			if ($from) {
				$html = mb_convert_encoding($html, 'utf-8', $from);
			}
		}

		$preview_data = $previewerobj->get_preview_data($term_url, $html, $content_type);
		if ($should_cache_preview) {
			$fields = array(
				'valid'                => '1',
				'dateline'             => TIME_NOW,
				'previewer_class_name' => $db->escape_string($res['previewer']),
				'previewer_class_vers' => $db->escape_string($previewerobj->get_version()),
				'preview_data'         => $db->escape_string(serialize($preview_data)),
			);
			if ($has_db_entry) {
				$db->update_query('url_previews', $fields, "url_term = '".$db->escape_string($term_url)."'");
			} else {
				$fields['url_term'] = $db->escape_string($term_url);
				// Simulate a UNIQUE constraint on the `url_norm` column
				// using HAVING. We can't use an actual UNIQUE
				// constraint because the DB's maximum allowable key
				// length is so short that we often enough end up with
				// duplicate keys for different values.
				$db->write_query('INSERT INTO '.TABLE_PREFIX.'url_previews (valid, dateline, previewer_class_name, previewer_class_vers, preview_data, url_term)
	SELECT \''.$fields['valid'].'\', \''.$fields['dateline'].'\', \''.$fields['previewer_class_name'].'\', \''.$fields['previewer_class_vers'].'\', \''.$fields['preview_data'].'\', \''.$fields['url_term'].'\'
	FROM '.TABLE_PREFIX.'url_previews WHERE url_term=\''.$fields['url_term'].'\'
	HAVING COUNT(*) = 0');
			}
		} else {
			$previewobj = $res['previewer']::get_instance();
			$preview_data = $previewobj->get_preview_data($term_url, $html, $content_type);
		}
	} else if ($res['result'] == LKT_PV_DATA_FOUND) {
		$previewerobj = $res['previewer']::get_instance();
		$preview_data = $res['preview_data'];
	}

	if (!empty($previewerobj)) {
		$preview = $previewerobj->get_preview($term_url, $preview_data);
	} else	$preview = '';

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
 * @param array $urls
 * @return array Indexed by each URL in $urls. Each entry is either:
 *         1. The URL's terminating redirect target (which might be itself).
 *         2. False in the case that a link-specific error occurred, e.g. web server timeout
 *            or redirect loop.
 *         3. Null in the case that a non-link-specific error occurred, such as failure to
 *            initialise a generic cURL handle.
 */
function lkt_resolve_url_terms($urls, &$got_terms = array(), $get_link_previews = true) {
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

	$redirs = lkt_resolve_url_terms_auto($urls);
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
		while ($term && isset($redirs[$term]) && $term != $redirs[$term]) {
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
		$got_terms[$url] = (isset($redirs[$term]) && $term == $redirs[$term]);
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

function lkt_normalise_url($url, $skip_ignored_query_params = false) {
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

	$ret .= (empty($parsed_url['path']) ? '/' : lkt_check_canonicalise_path($parsed_url['path']));

	if (isset($parsed_url['query'])) {
		$query = str_replace('&amp;', '&', $parsed_url['query']);
		$arr = explode('&', $query);
		sort($arr);
		if (!$skip_ignored_query_params) {
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
		}
		if ($arr) $ret .= '?'.implode('&', $arr);
	}

	return $ret; // We discard user, password, and fragment.
}

/**
 * This hook-in is called once before the thread is deleted (when the posts are
 * still present in the database) and once afterwards (when they've been
 * deleted).
 *
 * We store the to-be-deleted posts' pids on the first call, and then use them
 * on the second to delete associated entries in the post_urls and urls tables.
 * We do it this way so that we can be sure that the posts are actually deleted
 * before we delete their associated database entries managed by this plugin,
 * and we can't get their pids any other way on the second call because all we
 * have is the tid of the thread within which the relevant posts have already
 * been deleted.
 */
function lkt_hookin__common__class_moderation_delete_thread($tid) {
	static $pids_stored = null;
	global $db;

	if ($pids_stored === null) {
		$query = $db->simple_select('posts', 'pid', "tid='$tid'");
		$pids_stored = array();
		while ($post = $db->fetch_array($query)) {
			$pids_stored[] = $post['pid'];
		}
	} else if ($pids_stored) {
		$pids = implode(',', $pids_stored);
		$db->delete_query('post_urls', "pid IN ($pids)");
//		lkt_clean_up_dangling_urls();

		$pids_stored = null;
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

function lkt_hookin__datahandler_post_update_or_merge_end($posthandler) {
	global $db, $mybb, $message, $post, $g_lkt_moderate_post;

	$existing_urls_norm = [];
	$query = $db->query("
SELECT          urls.url_norm,
                urls.url_term_norm
FROM            {$db->table_prefix}urls urls
LEFT OUTER JOIN {$db->table_prefix}post_urls pu
ON              urls.urlid = pu.urlid
WHERE           pu.pid = '{$posthandler->pid}'");
	while ($row = $db->fetch_array($query)) {
		$existing_urls_norm[$row['url_norm']] = $row['url_term_norm'];
	}

	$db->delete_query('post_urls', "pid={$posthandler->pid}");
	if (isset($posthandler->data['message'])) {
		global $g_lkt_links_incl_vids, $g_lkt_redirs, $g_lkt_got_terms;

		if (!empty($posthandler->return_values['merge'])) {
			// This is a merger of posts, so clear the global cache variable holding only
			// the links from the NEW post that is to be merged into the original post.
			// That cache variable was set back in
			// lkt_hookin__datahandler_post_validate_thread_or_post().
			// We want it to be regenerated below so as to contain the links for
			// the FULL post AFTER the merge.
			$g_lkt_links_incl_vids = null;
		}
		if (!isset($g_lkt_links_incl_vids)) {
			$g_lkt_links_incl_vids = lkt_extract_urls($posthandler->data['message'])[0];
			list($g_lkt_redirs, $g_lkt_got_terms) = lkt_get_resolved_urls_from_list($g_lkt_links_incl_vids);
		}
		lkt_store_urls($g_lkt_links_incl_vids, $g_lkt_redirs, $g_lkt_got_terms, $posthandler->pid);
		$new_urls = $g_lkt_redirs;
	} else	$new_urls = [];

	$new_urls_norm = [];
	foreach ($new_urls as $url => $term_url) {
		$new_urls_norm[lkt_normalise_url($url)] = empty($term_url) ? $term_url : lkt_normalise_url($term_url);
	}

	// Remove preexisting URLs from the URLs in the updated post to end up with a list of URLs
	// that were added in the update. Check based on normalised URLs.
	foreach ($existing_urls_norm as $url_norm => $term_url_norm) {
		if (isset($new_urls_norm[$url_norm])) {
			unset($new_urls_norm[$url_norm]);
		}
		if (isset($new_urls_norm[$term_url_norm])) {
			unset($new_urls_norm[$term_url_norm]);
		}
		while (($new_url_norm = array_search($url_norm, $new_urls_norm)) !== false) {
			unset($new_urls_norm[$new_url_norm]);
		}
		while (($new_url_norm = array_search($term_url_norm, $new_urls_norm)) !== false) {
			unset($new_urls_norm[$new_url_norm]);
		}
	}

	$uid = isset($posthandler->data['uid']) ? $posthandler->data['uid'] : 0;

	// Moderate this new post if...
	if (
	    // ... we determined in the earlier validation hook that it ought to be,
	    // given the anti-link spam policy...
	    !empty($g_lkt_moderate_post)
	    // ...or links were included in it and posts by members of this usergroup
	    // in this forum are set to be moderated in that scenario.
	    ||
	    $new_urls_norm
	    &&
	    forum_permissions($posthandler->data['fid'], $uid)['lkt_mod_edit_link_into_post']
	) {
		global $lang;

		if (!isset($lang->linktools)) {
			$lang->load(C_LKT);
		}

		$posthandler->return_values['visible'] = 0;
		require_once MYBB_ROOT.'inc/class_moderation.php';
		$moderation = new Moderation;
		$moderation->unapprove_posts([$posthandler->pid]);
		$lang_key = !empty($g_lkt_moderate_post) ? 'lkt_redirect_edit_anti_link_spam_moderation' : 'lkt_redirect_post_link_edit_moderation';
		$lang->redirect_newreply_moderation = $lang->redirect_post_moderation = $lang->$lang_key;
	}

	if (THIS_SCRIPT == 'xmlhttp.php' && $mybb->input['action'] === 'edit_post' && $mybb->input['do'] == 'update_post' && empty($post['lkt_linkpreviewoff']) && lkt_should_show_pv($post)) {
		$message = lkt_insert_preview_placeholders($message);
	}
}

function lkt_should_show_pv($post) {
	global $mybb;

	$ret = false;
	if ($mybb->settings['linktools_link_preview_active_forums'] == -1 || in_array($post['fid'], explode(',', $mybb->settings['linktools_link_preview_active_forums']))) {
		if ($mybb->settings['linktools_link_preview_active_post_type'] == 'both') {
			$ret = true;
		} else {
			$thread = get_thread($post['tid']);
			if ($thread) {
				$is_first_post = ($post['pid'] == $thread['firstpost']);
				if ($mybb->settings['linktools_link_preview_active_post_type'] == 'first'   &&  $is_first_post
				    ||
				    $mybb->settings['linktools_link_preview_active_post_type'] == 'replies' && !$is_first_post
				   ) {
					$ret = true;
				}
			}
		}
	}

	return $ret;
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
		list($urls) = lkt_extract_urls($post['message']);
		$post_urls[$post['pid']] = $urls;
		$urls_all = array_merge($urls_all, $urls);
	}
	$urls_all = array_values(array_unique($urls_all));
	$db->free_result($res);

	$redirs = array_combine($urls_all, array_fill(0, count($urls_all), false));
	foreach ($post_urls as $pid => $urls) {
		lkt_store_urls($urls, $redirs, false, $pid);
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

/**
 * The purpose of this function is to ensure that the ratio of the numbers of
 * URLs from different servers in our URLs to be polled based on some criteria
 * is the same as that of as-yet unpolled (by that same criteria) URLs in the
 * database.
 *
 * Why? Because, given that we only poll each server once at a time, and pause
 * between successive requests to that server, this minimises the total runtime
 * of all operations - or, at least, that's my understanding unless/until
 * somebody corrects me.
 */
function lkt_proportion_urls_by_server($num_urls, $conds, $field, &$count = 0, &$ids = array()) {
	global $db;

	$servers = $servers_sought = $servers_tot = $urls_final = $ids = array();

	if (!$num_urls) return $urls_final;

	$start = 0;
	$continue = true;
	while ($continue) {
		$res = $db->simple_select(
			'urls',
			$field,
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
			$norm_server = lkt_get_norm_server_from_url($row[$field]);
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
			"$field, urlid",
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
			$urls_new[] = $row[$field];
			$ids[$row[$field]] = $row['urlid'];
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

	// Early return possible
	return $urls_final;
}

function lkt_get_and_store_terms($num_urls, &$count = 0) {
	global $db, $mybb;

	$conds = '('.lkt_get_sql_conds_for_ltt().') AND '.time().' > lock_time + '.lkt_max_url_lock_time;

	do {
		$urls_final = lkt_proportion_urls_by_server($num_urls, $conds, /*$field = */'url', $count, $ids);

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
	} while (count($urls_final) > 0 && $cnt <= 0);

	$terms = lkt_resolve_url_terms($urls_final, $got_terms);
	if ($terms) {
		// Reopen the DB connection in case it has "gone away" given the potentially long delay while
		// we resolved redirects. This was occurring at times on our (Psience Quest's) host, Hostgator.
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

			$inc = lkt_get_and_store_terms($per_page, $finish);

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
				if ($mybb->settings[C_LKT.'_link_preview_rebuild_scope'] == 'all') {
					$db->update_query('urls', array('got_preview' => 0));
					$db->delete_query('url_previews');
				}
			}

			if (!$mybb->get_input('lkt_linkpreviews_per_page', MyBB::INPUT_INT)) {
				$mybb->input['lkt_linkpreviews_per_page'] = lkt_default_rebuild_linkpreviews_items_per_page;
			}

			$page = $mybb->get_input('page', MyBB::INPUT_INT);
			$per_page = $mybb->get_input('lkt_linkpreviews_per_page', MyBB::INPUT_INT);
			if ($per_page <= 0) {
				$per_page = lkt_default_rebuild_linkpreviews_items_per_page;
			}

			$conds = 'got_preview = FALSE';

			$urls_term = lkt_proportion_urls_by_server(
				$per_page,
				$conds,
				/*$field = */'url_term',
				$count,
				$ids
			);

			$previews = lkt_get_gen_link_previews($urls_term, $mybb->settings[C_LKT.'_link_preview_rebuild_scope'] == 'all' ? true : false);

			if ($ids) {
				$ids_str = '';
				foreach ($urls_term as $url_term) {
					if (!empty($ids[$url_term])) {
						if ($ids_str) $ids_str .= ',';
						$ids_str .= $ids[$url_term];
					}
				}
				$db->update_query('urls', array('got_preview' => 1), 'urlid IN ('.$ids_str.')');
			}

			$finish = $db->fetch_field($db->simple_select('urls', 'count(*) AS count', $conds), 'count');

			// The first two parameters seem to be semantically switched within this function, so that's the way I've passed them.
			check_proceed(count($previews), 0, ++$page, $per_page, 'lkt_linkpreviews_per_page', 'do_rebuild_linkpreviews', $lang->lkt_success_rebuild_linkpreviews);
		}
	}
}

function lkt_hookin__datahandler_post_insert_thread($posthandler) {
	global $db, $mybb, $templates, $lang, $headerinclude, $header, $footer, $all_matching_urls_in_quotes_flag;

	if ($mybb->get_input('ignore_dup_link_warn') || $posthandler->data['savedraft'] ||
	    !($mybb->settings[C_LKT.'_enable_dlw'] && ($mybb->settings[C_LKT.'_force_dlw'] || $mybb->user['lkt_warn_about_links']))) {
		return;
	}

	if (!isset($lang->linktools)) {
		$lang->load(C_LKT);
	}

	list($urls) = lkt_extract_urls($posthandler->data['message']);
	if (!$urls) {
		return;
	}

	// Add any missing URLs to the DB after resolving redirects
	lkt_resolve_and_store_urls_from_list($urls);

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
		if (is_array($val)) {
			foreach ($val as $idx => $value) {
				$inputs .= '<input type="hidden" name="'.htmlspecialchars_uni($key).'['.htmlspecialchars($idx).']" value="'.htmlspecialchars_uni($value).'" />'."\n";
			}
		} else	$inputs .= '<input type="hidden" name="'.htmlspecialchars_uni($key).'" value="'.htmlspecialchars_uni($val).'" />'."\n";
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
		$url_esc = htmlspecialchars('search.php?action=do_search&'.$urls_esc.'&showresults=posts');
		$further_results_below_div = '<div class="further-results">'.$lang->sprintf($lang->lkt_further_results_below, count($matching_posts), $url_esc).'</div>';
		$further_results_above_div = '<div class="further-results">'.$lang->sprintf($lang->lkt_further_results_above, count($matching_posts), $url_esc).'</div>';
	} else {
		$further_results_below_div = '';
		$further_results_above_div = '';
	}

	$fid = $posthandler->data['fid'];

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

	$lang_strings = array(
		'lkt_started_by', 'lkt_opening_post', 'lkt_non_opening_post', 'lkt_posted_by', 'lkt_matching_url_singular', 'lkt_matching_urls_plural', 'lkt_msg_url1_as_url2', 'lkt_exist_open_post_contains', 'lkt_exist_post_contains', 'lkt_more_than', 'lkt_x_exist_open_posts_contain', 'lkt_x_exist_posts_contain', 'lkt_x_of_urls_added', 'lkt_a_url_added', 'lkt_one_is_an_opening_post', 'lkt_x_are_opening_posts', 'lkt_further_results_below', 'lkt_further_results_above', 'lkt_dismiss_warn_for_post', 'lkt_show_more', 'lkt_show_less', 'lkt_dismiss_all_warnings', 'lkt_undismiss_all_warns', 'lkt_title_warn_about_links', 'lkt_warn_about_links',
	);
	$lkt_previously_dismissed = json_encode($mybb->get_input('lkt_dismissed') ? json_decode($mybb->get_input('lkt_dismissed'), true) : array(), JSON_PRETTY_PRINT);

	// This user setting won't be present when posting as a guest, so check for its existence, and default to "warn" for guests.
	$lkt_warn_about_links = isset($mybb->user['lkt_warn_about_links']) ? $mybb->user['lkt_warn_about_links'] : 1;

	$linktools_js = <<<EOF
<script type="text/javascript" src="{$mybb->settings['bburl']}/jscripts/linktools.js?1.3.3"></script>
<script type="text/javascript">
lkt_setting_warn_about_links     = {$lkt_warn_about_links};
lkt_setting_dlw_forced           = {$mybb->settings['linktools_force_dlw']};
EOF;
	foreach ($lang_strings as $key) {
		$linktools_js .= "lang.{$key} = '".addslashes($lang->$key)."';\n";
	}
	$linktools_js .= '</script>'."\n";
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

function lkt_strip_nestable_mybb_tag($message, $tagname, $blank_out = false) {
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
		$msg_org = $message;
		$message = substr($msg_org, 0, $pos);
		if ($blank_out) {
			$message .= str_repeat(' ', $pos_c - $pos + strlen('[/'.$tagname.']'));
		}
		$message .= substr($msg_org, $pos_c + strlen('[/'.$tagname.']'));
		$pos = 0;
	}

	return $message;
}

function lkt_hookin__search_start() {
	global $mybb;

	$do_lkt_search = false;

	if ($mybb->get_input('urls')) {
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
 * @param string $url The potential URL.
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

	// Begin code copied, with only minor changes - such as to coding style and
	// a typo correction - from search.php under the hook 'search_do_search_start'.
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
		if (!empty($last_search['sid'])) {
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
	// changes to core code, which we prefer to avoid.

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
	$forums = $mybb->get_input('forums');
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

	$tidsql = '';
	if ($mybb->get_input('tid', MyBB::INPUT_INT)) {
		$tidsql = " AND t.tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'";
	}

	// End copied code.

	$extra_conditions = "{$post_datecut} {$thread_replycut} {$thread_prefixcut} {$forumin} {$post_usersql} {$permsql} {$tidsql} {$visiblesql} {$post_visiblesql} AND t.closed NOT LIKE 'moved|%'";
	$sql = lkt_get_url_search_sql((array)$urls, false, $extra_conditions, $mybb->get_input('raw_only', MyBB::INPUT_INT));
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
	global $mybb, $db, $charset;

	switch ($mybb->input['action']) {
	case 'lkt_set_warn_about_links':
		$lkt_setting_warn_about_links = $mybb->get_input('lkt_setting_warn_about_links', MyBB::INPUT_INT) ? 1 : 0;
		$db->update_query('users', array('lkt_warn_about_links' => $lkt_setting_warn_about_links), "uid='{$mybb->user['uid']}'");
		break;
	case 'lkt_get_post_regen_cont':
		$post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));
		if ($post) {
			list($urls) = lkt_extract_urls($post['message']);
			if ($urls) {
				echo lkt_get_preview_regen_container($post, $urls);
			}
		} echo '';
		break;
	case 'lkt_get_posts_for_urls':
		header("Content-type: application/json; charset={$charset}");

		if (!empty($mybb->input['urls'])) {
			$urls = (array)$mybb->input['urls'];

			// Add any missing URLs to the DB after resolving redirects
			lkt_resolve_and_store_urls_from_list($urls);

			$post_edit_times = array();
			if (!empty($mybb->input['pids']) && !empty($mybb->input['edtms'])) {
				foreach ((array)$mybb->input['pids'] as $i => $pid) {
					$post_edit_times[$pid] = ((array)$mybb->input['edtms'])[$i];
				}
			}
			list($matching_posts, $forum_names, $further_results) = lkt_get_posts_for_urls($urls, $post_edit_times);
			echo json_encode(array('matching_posts' => $matching_posts, 'further_results' => $further_results));
		}
		break;
	}
}

function lkt_hookin__admin_config_settings_change() {
	global $db, $mybb, $lkt_settings_peeker;

	$res = $db->simple_select('settinggroups', 'gid', "name = '".C_LKT."_settings'", array('limit' => 1));
	$gid = $db->fetch_field($res, 'gid');
	$lkt_settings_peeker = ($mybb->get_input('gid') == $gid) && ($mybb->request_method != 'post');
}

function lkt_hookin__admin_settings_print_peekers(&$peekers) {
	global $lkt_settings_peeker;

	if (!empty($lkt_settings_peeker)) {
		$peekers[] = 'new Peeker($(".setting_'.C_LKT.'_enable_dlw"), $("#row_setting_'.C_LKT.'_force_dlw"), 1, true)';
		$peekers[] = 'new Peeker($("#setting_'.C_LKT.'_link_preview_type"), $("#row_setting_'.C_LKT.'_link_preview_dom_list"), /^(whitelist|blacklist)$/, false)';
		$peekers[] = 'new Peeker($("#setting_'.C_LKT.'_link_preview_on_fly"), $("#row_setting_'.C_LKT.'_link_preview_on_fly_dom_list"), /^(whitelist|blacklist)$/, false)';
	}
}

function lkt_get_preview_regen_container($post, $urls) {
	global $mybb, $lang, $templates;

	$lang->load(C_LKT);

	$urls = array_values($urls);
	$term_urls = lkt_retrieve_terms($urls);
	$pvregenurls = array();
	foreach ($term_urls as $url => $term_url) {
		if (lkt_url_has_needs_preview($term_url)['result'] != LKT_PV_NOT_REQUIRED) {
			$pvregenurls[] = $url;
		}
	}
	if ($pvregenurls) {
		if (count($pvregenurls) == 1) {
			$link_url  = $mybb->settings['bburl'].'/lkt-regen-preview.php?url='.urlencode($pvregenurls[0]).'&amp;return_pid='.$post['pid'];
			$link_text = $lang->lkt_regen_link_preview;
			eval('$links = "'.$templates->get('linktools_preview_regen_link', 1, 0).'";');
			$prefix = '';
		} else {
			$link_url = $mybb->settings['bburl'].'/lkt-regen-preview.php?pid='.$post['pid'].'&amp;return_pid='.$post['pid'];
			$link_text = $lang->lkt_all;
			eval('$links = "'.$templates->get('linktools_preview_regen_link', 1, 0).'";');
			foreach ($pvregenurls as $i => $url) {
				$links .= $lang->comma;
				$link_url = $mybb->settings['bburl'].'/lkt-regen-preview.php?url='.urlencode($url).'&amp;return_pid='.$post['pid'];
				$link_text = (string)($i+1);
				eval('$links .= "'.$templates->get('linktools_preview_regen_link', 1, 0).'";');
			}
			$prefix = $lang->lkt_regen_link_previews;
		}
		eval('$ret = "'.$templates->get('linktools_preview_regen_container', 1, 0).'";');
	} else	$ret = '';

	return $ret;
}

function lkt_hookin__postbit_etc($post) {
	$post['updatepreview'] = '';

	return $post;
}

function lkt_hookin__postbit($post) {
	global $g_lkt_previews, $g_lkt_links_excl_vids;

	if ($g_lkt_previews && empty($post['lkt_linkpreviewoff'])) {
		$post['message'] = str_replace(array_keys($g_lkt_previews), array_values($g_lkt_previews), $post['message']);
		$post['updatepreview'] = lkt_get_preview_regen_container($post, $g_lkt_links_excl_vids);
	} else	$post['updatepreview'] = '';

	$g_lkt_previews = false;

	return $post;
}

function lkt_hookin__xmlhttp_update_post() {
	global $g_lkt_previews, $post;

	if ($g_lkt_previews && empty($post['lkt_linkpreviewoff'])) {
		$post['message'] = str_replace(array_keys($g_lkt_previews), array_values($g_lkt_previews), $post['message']);
	}

	$g_lkt_previews = false;
}

function lkt_hookin__parse_message_start($message) {
	global $g_lkt_previews, $mybb, $post;

	if (empty($post['pid'])
	    ||
	    !(THIS_SCRIPT == 'showthread.php'
	      ||
	      THIS_SCRIPT == 'newreply.php' && $mybb->input['action'] == 'do_newreply' && $mybb->request_method == 'post'
	     )
	   ) {
		return $message;
	}

	// We check for $g_lkt_previews being false because this hook is
	// called for any signature of the post after the post itself.
	// That's why we set $g_lkt_previews to false in
	// lkt_hookin__postbit(). False indicates this is the first call
	// for this post, i.e., the post message itself rather than its
	// signature.
	if ($g_lkt_previews === false && empty($post['lkt_linkpreviewoff']) && lkt_should_show_pv($post)) {
		$message = lkt_insert_preview_placeholders($message);
	}

	// Early return possible
	return $message;
}

function lkt_insert_preview_placeholders($message) {
	global $g_lkt_previews, $g_lkt_links_excl_vids, $mybb, $post;

	$msg = $message;
	if ($mybb->settings[C_LKT.'_link_preview_not_in_quotes']) {
		$msg = lkt_strip_nestable_mybb_tag($msg, 'quote', true);
	}

	$links = lkt_extract_urls($msg, /*$exclude_videos = */true);
	$g_lkt_links_excl_vids = $links[0];

	$g_lkt_previews = $insertions = array();

	$i = 0;
	foreach (lkt_get_gen_link_previews(lkt_retrieve_terms($links[0])) as $preview) {
		$endpos = $links[1][$i];
		while ($endpos < strlen($msg) && $msg[$endpos++] != "\n") ;
		$insertions[$i] = array('inspos' => $endpos, 'preview' => $preview);
		$i++;
	}
	$curr = 0;
	$segments = array();
	foreach ($insertions as $i => $insertion) {
		if (empty($insertions[$i]['preview'])) {
			continue;
		}
		$segments[] = substr($message, $curr, $insertions[$i]['inspos'] - $curr);
		$uniqid = '';
		for ($j = 0; $j < 20; $j++) {
			$uniqid = uniqid('lkpv_', true);
			if (strpos($message, $uniqid) === false && empty($g_lkt_previews[$uniqid])) {
				break;
			} else	$uniqid = '';
		}
		if ($uniqid) {
			$inspos = $insertions[$i]['inspos'];
			if ($inspos == strlen($message) && ($message[$inspos-1] != "\n")) {
				// Prevent our replacement token from
				// being appended to any bare URL which
				// ends the post.
				$uniqid = "\n".$uniqid;
			}
			$g_lkt_previews[$uniqid] = $insertions[$i]['preview'];
			$segments[] = $uniqid.($inspos < strlen($message) - 1 && !in_array($message[$inspos], array("\r", "\n")) ? "\n" : '');
		}
		$curr = $insertions[$i]['inspos'];
	}
	$segments[] = substr($message, $curr, strlen($message));
	$message = implode('', $segments);

	return $message;
}

function lkt_hookin__admin_config_menu(&$sub_menu) {
	global $lang;

	$lang->load(C_LKT);
	$key = max(array_keys($sub_menu)) + 10;
	$sub_menu[$key] = array(
		'id'    => 'linkpreviewers'                        ,
		'title' => $lang->lkt_linkpreviewers               ,
		'link'  => 'index.php?module=config-linkpreviewers',
	);
}

function lkt_hookin__admin_config_action_handler(&$actions) {
	$actions['linkpreviewers'] = array(
		'active' => 'linkpreviewers'    ,
		'file'   => 'linkpreviewers.php',
	);
}

function lkt_hookin__admin_config_permissions(&$admin_permissions) {
	global $lang;

	$lang->load(C_LKT);

	$admin_permissions['linkpreviewers'] = $lang->lkt_can_manage_link_previewers;
}

function lkt_hookin__admin_tools_menu(&$sub_menu) {
	global $lang;

	$lang->load(C_LKT);
	$key = max(array_keys($sub_menu)) + 10;
	$sub_menu[$key] = array(
		'id'    => 'linkpreviewers'                            ,
		'title' => $lang->lkt_linkpreviewers_cache_invalidation,
		'link'  => 'index.php?module=tools-linkpreviewers'     ,
	);
}

function lkt_hookin__admin_tools_action_handler(&$actions) {
	$actions['linkpreviewers'] = array(
		'active' => 'linkpreviewers'    ,
		'file'   => 'linkpreviewers.php',
	);

	return $actions;
}

/**
 * In contrast to lkt_resolve_url_terms(), which queries web servers for
 * terminating URLs, this retrieves those already-web-queried terminating URLs
 * from the table to which they were stored in our database.
 */
function lkt_retrieve_terms($urls, $set_false_on_not_found = false) {
	global $db;

	$urls = array_unique($urls);
	$terms = array();
	$query = $db->simple_select('urls', 'url, url_term', "url IN ('".implode("', '", array_map(function($url) use ($db) {return $db->escape_string($url);}, $urls))."')");
	while ($row = $db->fetch_array($query)) {
		$terms[$row['url']] = $row['url_term'];
	}

	$terms_ordered = array();
	foreach ($urls as $url) {
		if (!isset($terms[$url])) {
			$term_url = $set_false_on_not_found ? false : $url;
		} else	$term_url = $terms[$url];
		$terms_ordered[$url] = $term_url;
	}

	return $terms_ordered;
}

function lkt_hookin__newthreadorreply_start() {
	global $mybb, $templates, $disablelinkpreviews, $lang;

	$lang->load(C_LKT);

	if (!empty($mybb->input['previewpost'])) {
		$linkpreviewoffchecked = empty($mybb->get_input('lkt_linkpreviewoff', MyBB::INPUT_INT)) ? '' : ' checked="checked"';
	} else	$linkpreviewoffchecked = '';
	eval('$disablelinkpreviews = "'.$templates->get('linktools_cbxdisablelinkpreview').'";');
}

function lkt_hookin__datahandler_post_insert_thread_post($posthandler) {
	global $mybb;

	$val = !empty($mybb->get_input('lkt_linkpreviewoff', MyBB::INPUT_INT)) ? '1' : '0';
	$posthandler->post_insert_data['lkt_linkpreviewoff'] = $val;
}

function lkt_hookin__newreply_do_newreply_end() {
	global $pid, $mybb, $db;

	if (!empty($pid)) {
		$val = !empty($mybb->get_input('lkt_linkpreviewoff', MyBB::INPUT_INT)) ? '1' : '0';
		$db->update_query('posts', array('lkt_linkpreviewoff' => $val), "pid = '{$pid}'");
	}
}

function lkt_hookin__editpost_action_start() {
	global $post, $mybb, $templates, $disablelinkpreviews, $lang;

	$lang->load(C_LKT);

	if (empty($mybb->input['previewpost'])) {
		$linkpreviewoffchecked = empty($post['lkt_linkpreviewoff']) ? '' : ' checked="checked"';
	} else	$linkpreviewoffchecked = empty($mybb->get_input('lkt_linkpreviewoff', MyBB::INPUT_INT)) ? '' : ' checked="checked"';
	eval('$disablelinkpreviews = "'.$templates->get('linktools_cbxdisablelinkpreview').'";');
}

function lkt_hookin__editpost_do_editpost_end() {
	global $post, $mybb, $db;

	$val = !empty($mybb->get_input('lkt_linkpreviewoff', MyBB::INPUT_INT)) ? '1' : '0';
	$db->update_query('posts', array('lkt_linkpreviewoff' => $val), "pid = '{$post['pid']}'");
}

function lkt_hookin__admin_style_templates_edit_template_commit() {
	global $mybb;

	$previewer = false;
	$prefix = 'linktools_linkpreview_';
	if (substr($mybb->input['title'], 0, strlen($prefix)) == $prefix) {
		$pv_lc = substr($mybb->input['title'], strlen($prefix));
		foreach (lkt_get_linkpreviewer_classnames() as $type => $classnames) {
			foreach ($classnames as $classname) {
				if ('linkpreviewer'.$pv_lc == strtolower($classname)) {
					$previewer = $classname;
					break;
				}
			}
		}
	}

	if ($previewer && ($previewerobj = $previewer::get_instance()) && $previewerobj->get_should_cache_preview()) {
		global $db, $page, $lang, $templates, $expand_str2;

		if ($mybb->input['continue']) {
			if ($mybb->input['from'] == 'diff_report') {
				$url_return = 'index.php?module=style-templates&action=edit_template&title='.urlencode($mybb->input['title']).'&sid='.$mybb->get_input('sid', MyBB::INPUT_INT).$expand_str2.'&amp;from=diff_report';
			} else	$url_return = 'index.php?module=style-templates&action=edit_template&title='.urlencode($mybb->input['title']).'&sid='.$mybb->get_input('sid', MyBB::INPUT_INT).$expand_str2;
		} else {
			if ($mybb->input['from'] == 'diff_report') {
				$url_return = 'index.php?module=style-templates&amp;action=find_updated';
			} else	$url_return = 'index.php?module=style-templates&sid='.$mybb->get_input('sid', MyBB::INPUT_INT).$expand_str2."#group_{$group}";
		}

		$friend_nm_esc = htmlspecialchars_uni($previewerobj->get_friendly_name());
		$title = $lang->sprintf($lang->lkt_preview_previewer_tpl_chg_pg_title, $friend_nm_esc);
		$page->output_header($title);

		$gid = $db->fetch_field($db->simple_select('settinggroups', 'gid', "name='linktools_settings'"), 'gid');
		$invalidate_previewer_msg = $lang->sprintf($lang->lkt_invalidate_previewer_msg, $friend_nm_esc, $gid);

		$table = new Table;
		$table->construct_cell($invalidate_previewer_msg);
		$table->construct_row();
		$form = new Form('index.php?module=tools-linkpreviewers&amp;action=do_invalidation&amp;previewer='.htmlspecialchars_uni($pv_lc).'&amp;url_return='.urlencode($url_return), 'post');
		$table->construct_cell($form->generate_submit_button($lang->sprintf($lang->lkt_inval_pv_cache_for, $friend_nm_esc), array('name' => 'do_invalidation')), array('class' => 'align_center'));
		$table->construct_row();
		$heading = $lang->sprintf($lang->lkt_preview_previewer_tpl_chg_pg_heading, $friend_nm_esc);
		$table->output($heading);

		$page->output_footer();
		exit;
	}
}

function lkt_mk_tpl_nm_frm_classnm($classname) {
	$prefix = 'linkpreviewer';
	$name = strtolower($classname);
	if (my_substr($name, 0, strlen($prefix)) == $prefix) {
		$name = my_substr($name, strlen($prefix));
	}

	return 'linktools_linkpreview_'.$name;
}

function lkt_get_extra_curl_opts() {
	$fname = __DIR__.'/linktools/extra-curl-opts.php';
	if (is_readable($fname)) {
		$extra_opts = include $fname;
	}
	if (!empty($extra_opts) && is_array($extra_opts)) {
		return $extra_opts;
	} else	return array();
}

function lkt_hookin__admin_forum_action_handler($actions) {
	$actions['linklimits'] = array(
		'active' => 'linklimits',
		'file'   => 'linklimits.php',
	);
	$actions['linklisting'] = array(
		'active' => 'linklisting',
		'file'   => 'linklisting.php',
	);

	return $actions;
}

function lkt_hookin__admin_forum_menu($sub_menu) {
	global $lang;

	$lang->load(C_LKT);
	$key = max(array_keys($sub_menu)) + 10;
	$sub_menu[$key] = array('id' => 'linklimits', 'title' => $lang->lkt_linklimits, 'link' => 'index.php?module=forum-linklimits');
	$sub_menu[$key + 10] = array('id' => 'linklisting', 'title' => $lang->lkt_linklisting_and_import, 'link' => 'index.php?module=forum-linklisting');

	return $sub_menu;
}

function lkt_hookin__admin_forum_permissions(&$admin_permissions) {
	global $lang;

	$lang->load(C_LKT);

	$admin_permissions['linklimits'] = $lang->lkt_can_manage_link_limits;
	$admin_permissions['linklisting'] = $lang->lkt_can_manage_link_listings;
}

function lkt_hookin__admin_formcontainer_output_row($pluginargs) {
	global $mybb, $lang, $form, $groupscache;

	$lang->load(C_LKT);

	if (!empty($lang->moderation_options) && $pluginargs['title'] == $lang->moderation_options) {
		if (empty($groupscache)) {
			$groupscache = $mybb->cache->read('usergroups');
		}
		$gid = $mybb->get_input('gid', MyBB::INPUT_INT);
		if (!empty($groupscache[$gid])) {
			$usergroup = $groupscache[$gid];

			$cbx_opts = array('id' => 'id_lkt_mod_link_in_new_post');
			if ($usergroup['lkt_mod_link_in_new_post']) {
				$cbx_opts['checked'] = true;
			}
			$pluginargs['content'] .= '<div class="group_settings_bit">'.$form->generate_check_box('lkt_mod_link_in_new_post', '1', $lang->lkt_mod_link_in_new_post, $cbx_opts).'</div>';

			$cbx_opts = array('id' => 'id_lkt_mod_edit_link_into_post');
			if ($usergroup['lkt_mod_edit_link_into_post']) {
				$cbx_opts['checked'] = true;
			}
			$pluginargs['content'] .= '<div class="group_settings_bit">'.$form->generate_check_box('lkt_mod_edit_link_into_post', '1', $lang->lkt_mod_edit_link_into_post, $cbx_opts).'</div>';
		}
	}
}

function lkt_hookin__admin_user_groups_edit_commit() {
	global $db, $mybb, $updated_group;

	$updated_group['lkt_mod_link_in_new_post'   ] = $db->escape_string($mybb->get_input('lkt_mod_link_in_new_post'  , MyBB::INPUT_INT));
	$updated_group['lkt_mod_edit_link_into_post'] = $db->escape_string($mybb->get_input('lkt_mod_edit_link_into_post', MyBB::INPUT_INT));
}

function lkt_hookin__admin_forum_management_permission_groups($groups) {
	$groups['lkt_mod_link_in_new_post'   ] = 'moderate';
	$groups['lkt_mod_edit_link_into_post'] = 'moderate';
	return $groups;
}

function lkt_get_min_url_dateline_sql() {
	global $db;

	return "
        SELECT subq2__.urlid, MIN(subq2__.min_url_dateline) AS min_url_dateline
        FROM   (SELECT urlid, MIN(min_dateline) AS min_url_dateline
                 FROM   (
                         SELECT          u.urlid,
                                         u.url,
                                         MIN(p.dateline) AS min_dateline
                         FROM            {$db->table_prefix}urls u
                         INNER JOIN      {$db->table_prefix}post_urls pu
                         ON              pu.urlid = u.urlid
                         LEFT OUTER JOIN {$db->table_prefix}posts p
                         ON              p.pid = pu.pid
                         WHERE           p.dateline IS NOT NULL
                                         AND
                                         u.dateline = 0
                         GROUP BY        u.urlid, u.url
                         UNION
                         SELECT          u2.urlid,
                                         u2.url,
                                         MIN(pv.dateline) AS min_dateline
                         FROM            {$db->table_prefix}urls u2
                         INNER JOIN      {$db->table_prefix}url_previews pv
                         ON              pv.url_term = u2.url
                         WHERE           u2.dateline = 0
                         GROUP BY        u2.urlid, u2.url
                        ) subq1__
                GROUP BY urlid
               ) subq2__
        GROUP BY urlid
        HAVING subq2__.urlid IS NOT NULL AND MIN(subq2__.min_url_dateline) IS NOT NULL
";
}

function lkt_hookin__admin_load() {
	global $mybb, $lang, $db;

	if ($mybb->get_input('action') == 'lkt_init_url_dateline') {
		$db->write_query("
UPDATE          {$db->table_prefix}urls u
LEFT OUTER JOIN (".lkt_get_min_url_dateline_sql()."
                ) subq
ON              u.urlid = subq.urlid
SET             u.dateline = subq.min_url_dateline
WHERE           u.dateline = 0
");
		$lang_key = $db->affected_rows() == 1 ? 'lkt_init_url_dateline_success' : 'lkt_init_url_datelines_success';
		$lang->load(C_LKT);
		flash_message($lang->$lang_key, 'success');
		admin_redirect('index.php?module=config-plugins');
	}
}

function lkt_hookin__datahandler_post_validate_thread_or_post($posthandler) {
	global $db, $mybb, $cache, $lang, $g_lkt_links_incl_vids, $g_lkt_redirs, $g_lkt_got_terms, $g_lkt_moderate_post;

	$do_anti_link_spam = $took_action = $g_lkt_moderate_post = false;

	if (!isset($g_lkt_links_incl_vids)) {
		$g_lkt_links_incl_vids = lkt_extract_urls($posthandler->data['message'])[0];
	}

	// If links were included in this new post, then...
	if (!empty($g_lkt_links_incl_vids)) {
		$lang->load(C_LKT);
		$qual_action_type = !empty($posthandler->data['pid']) ? 'edit_post' : 'new_post';
		$uid = isset($posthandler->data['edit_uid'])
		         ? $posthandler->data['edit_uid']
		         : (isset($posthandler->data['uid'])
		              ? $posthandler->data['uid']
		              : 0
		           );

		// ...resolve them into their terminating links, caching the result in global variables
		// so that if we don't save them to the database in this hook, we can do that in
		// a later hook without having to re-query them from external web servers.
		list($g_lkt_redirs, $g_lkt_got_terms) = lkt_get_resolved_urls_from_list($g_lkt_links_incl_vids);

		// Then check whether the anti-link spam policy applies.
		if (lkt_anti_link_spam_policy_applies($uid, $qual_action_type)) {
			// It does, so now check whether any of the included links are actually classified as spam.
			$all_urls_in_post = array_filter(array_unique(array_merge(array_keys($g_lkt_redirs), array_values($g_lkt_redirs))));
			if (lkt_urls_contain_spam_url($all_urls_in_post)) {
				// At least one of them is, so store all of the URLs in this post to the DB prior to
				// (potentially, next) auto-classifying them (store them without a pid; they will be
				// associated with this post's pid a later hook if applicable - that is, if we are only
				// moderating this post).
				lkt_store_urls($g_lkt_links_incl_vids, $g_lkt_redirs, $g_lkt_got_terms);

				// Then, if applicable, classify all other links in this post (stored above if they
				// weren't already) as stipulated in the settings.
				$this_post_urls_list_esc = "'".implode("', '", array_map([$db, 'escape_string'], lkt_normalise_urls($all_urls_in_post)))."'";
				if (in_array($mybb->settings[C_LKT.'_anti_link_spam_response_classify_same_post'], ['as_spam_abs', 'as_spam', 'as_potential_spam'])) {
					$class = 'Spam';
					$conds = '';
					if ($mybb->settings[C_LKT.'_anti_link_spam_response_classify_same_post'] == 'as_spam') {
						$conds = " AND spam_class IN ('Unspecified', 'Potential spam')";
					} else if ($mybb->settings[C_LKT.'_anti_link_spam_response_classify_same_post'] == 'as_potential_spam') {
						$class = 'Potential spam';
						$conds = " AND spam_class = 'Unspecified'";
					}
					$db->update_query('urls', ['spam_class' => $class], "(url_norm IN ({$this_post_urls_list_esc}) OR url_term_norm IN ({$this_post_urls_list_esc})){$conds}");
				}

				// Then, if applicable, classify all other links posted across all posts by this member
				// as stipulated in the settings.
				if (in_array($mybb->settings[C_LKT.'_anti_link_spam_response_classify_other_posts'], ['as_spam_abs', 'as_spam', 'as_potential_spam'])) {
					// Fetch all URLs posted by the member, normalised.
					$member_urls_norm = [];
					$query = $db->query("
SELECT          u.url_norm, u.url_term_norm
FROM            {$db->table_prefix}posts p
LEFT OUTER JOIN {$db->table_prefix}post_urls pu
ON              p.pid = pu.pid
LEFT OUTER JOIN {$db->table_prefix}urls u
ON              pu.urlid = u.urlid
WHERE           p.uid = {$mybb->user['uid']}");
					while ($row = $db->fetch_array($query)) {
						$member_urls_norm[] = $row['url_norm'     ];
						$member_urls_norm[] = $row['url_term_norm'];
					}
					$urls_list_esc = "'".implode("', '", array_map([$db, 'escape_string'], $member_urls_norm))."'";
					$class = 'Spam';
					$conds = '';
					if ($mybb->settings[C_LKT.'_anti_link_spam_response_classify_other_posts'] == 'as_spam') {
						$conds = " AND spam_class IN ('Unspecified', 'Potential spam')";
					} else if ($mybb->settings[C_LKT.'_anti_link_spam_response_classify_other_posts'] == 'as_potential_spam') {
						$class = 'Potential spam';
						$conds = " AND spam_class = 'Unspecified'";
					}
					// Classify all links posted by this member except for those in this post.
					$db->update_query('urls', ['spam_class' => $class], "((url_norm IN ({$urls_list_esc}) OR url_term_norm IN ({$urls_list_esc})) AND url_norm NOT IN ({$this_post_urls_list_esc}) AND url_term_norm NOT IN ({$this_post_urls_list_esc})){$conds}");
				}

				// Now take the applicable action.
				switch ($mybb->settings[C_LKT.'_anti_link_spam_response_action']) {
					case 'purge_delete_spammer':
						lkt_purge_spammer($uid, 'delete');
						$posthandler->set_error($lang->lkt_err_purged_del_for_link_spam);
						$took_action = true;
						break;
					case 'purge_ban_spammer':
						lkt_purge_spammer($uid, 'ban');
						$posthandler->set_error($lang->lkt_err_purged_banned_for_link_spam);
						$took_action = true;
						break;
					case 'reject_post_or_edit':
						$lang_key = $qual_action_type == 'edit_post' ? 'lkt_err_invalid_edit_due_to_link_spam' : 'lkt_err_invalid_post_due_to_link_spam';
						$posthandler->set_error($lang->$lang_key);
						$took_action = true;
						break;
					case 'moderate_post':
						$g_lkt_moderate_post = true;
						// Don't set $took_action to true because we're deferring any action
						// to a later hook, after saving of the post/edit.
						break;
				}
			}
		}

		// If we didn't take action based on the anti-link spam policy, then...
		if (!$took_action) {
			// ...check whether we need to take some sort of action based on the
			// link limit policy.

			$num_submitted_links = count($g_lkt_links_incl_vids);
			$prefix = TABLE_PREFIX;

			// The effective user is the user who originally posted the post if it's being
			// edited, and the current user otherwise (i.e., for new posts).
			//
			// We handle the editing of the posts of others by treating the effective user
			// as the original poster, not the (current) editing poster. This may limit
			// moderators at times - e.g., by preventing them from adding explanatory links
			// for their actions to offending posts - but it's a (the most?) straightforward
			// approach.
			$eff_user = $posthandler->data['uid'] ? get_user($posthandler->data['uid']) : $mybb->user;

			$groups = array_merge(array($eff_user['usergroup']), explode(',', $eff_user['additionalgroups']));

			// If the user is editing a post, find out how many links are in the pre-edited post.
			$num_existing_links_in_post = 0;
			if (!empty($posthandler->data['pid'])) {
				$query = $db->query("
  SELECT          COUNT(*) AS num_links
  FROM            {$prefix}urls urls
  LEFT OUTER JOIN {$prefix}post_urls pu
  ON              urls.urlid = pu.urlid
  LEFT OUTER JOIN {$prefix}posts p
  ON              pu.pid = p.pid
  WHERE           pu.pid = {$posthandler->data['pid']}");
				$num_existing_links_in_post = $db->fetch_field($query, 'num_links');
				$db->free_result($query);
			}

			if (!empty($posthandler->data['pid'])) {
				$post = get_post($posthandler->data['pid']);
				$post_dateline = $post['dateline'];
			} else	$post_dateline = TIME_NOW;
			$query1 = $db->simple_select('link_limits', '*');
			while ($row = $db->fetch_array($query1)) {
				$common_groups = array_intersect($groups, explode(',', $row['gids']));
				if ($common_groups && in_array($posthandler->data['fid'], explode(',', $row['fids']))) {
					$limit_period_secs = $row['days'] * 24 * 60 * 60;
					$datelines = array();
					if ($posthandler->data['pid']) {
						// The user is editing a post, so try to find out whether, if the post were to be saved,
						// it would cause the effective user's link count to be exceeded for any relevant period.
						$start = $post_dateline - $limit_period_secs;
						$end   = $post_dateline + $limit_period_secs;
						$query2 = $db->simple_select('posts', 'dateline', "uid='{$eff_user['uid']}' AND dateline >= '{$start}' AND dateline <= '{$end}'", array('order_by' => 'dateline', 'order_dir' => 'DESC'));
						while ($dateline = $db->fetch_field($query2, 'dateline')) {
							$datelines[] = $dateline;
						}
						$db->free_result($query2);
						// Sample at most 10 periods based on datelines of posts made during the overall interval of interest.
						// If we don't sample, we can end up with huge numbers of queries which take a long time to complete.
						// Occasionally, this might miss a period for which the link count would exceed its allowed maximum,
						// but it should for the vast majority of the time be reliable, and otherwise be "close enough".
						$max_dlines = 10;
						$num_dlines = count($datelines);
						if ($num_dlines > $max_dlines) {
							$d2 = array($datelines[0], $datelines[$num_dlines - 1]);
							for ($i = 1; $i <= ($max_dlines - 2); $i++) {
								$idx = ceil($i * $num_dlines / ($max_dlines - 1));
								$d2[] = $datelines[$idx];
							}
							$datelines = $d2;
						}
					} else	$datelines[] = $post_dateline;
					foreach ($datelines as $dateline) {
						if ($dateline >= $post_dateline) {
							$end = $dateline;
							$start = $end - $limit_period_secs;
						} else {
							$start = $dateline;
							$end = $start + $limit_period_secs;
						}
						$interval_cond = "p.dateline >= {$start} AND p.dateline <= {$end}";
						$query3 = $db->query("
  SELECT          COUNT(*) AS num_links
  FROM            {$prefix}urls urls
  LEFT OUTER JOIN {$prefix}post_urls pu
  ON              urls.urlid = pu.urlid
  LEFT OUTER JOIN {$prefix}posts p
  ON              pu.pid = p.pid
  LEFT OUTER JOIN {$prefix}forums f
  ON              f.fid = p.fid
  LEFT OUTER JOIN {$prefix}users u
  ON              p.uid = u.uid
  WHERE           u.uid = {$eff_user['uid']}
                  AND
                  f.fid IN ({$row['fids']})
                  AND
                  {$interval_cond}");
						$num_interval_links = $db->fetch_field($query3, 'num_links');
						$db->free_result($query3);

						$num_net_new_links = $num_submitted_links - $num_existing_links_in_post;
						if ($num_interval_links + $num_net_new_links > $row['maxlinks']) {
							$groups_cache = $cache->read('usergroups');
							$group_links = array();
							foreach ($common_groups as $common_gid) {
								$common_gid = (int)trim($common_gid);
								if (isset($groups_cache[$common_gid])) {
									$group_links[] = format_name(htmlspecialchars_uni($groups_cache[$common_gid]['title']), $common_gid);
								}
							}
							if (!is_array($forum_cache)) {
								$forum_cache = cache_forums();
							}
							$forum_links = array();
							foreach (explode(',', $row['fids']) as $ll_fid) {
								$ll_fid = (int)trim($ll_fid);
								if (isset($forum_cache[$ll_fid]['name'])) {
									$forum_links[] = '<a href="'.get_forum_link($ll_fid).'">'.htmlspecialchars_uni($forum_cache[$ll_fid]['name']).'</a>';
								}
							}

							if (!empty($posthandler->data['pid'])) {
								$posthandler->set_error($lang->sprintf($lang->lkt_err_toomanylinks_prior_period, implode(' | ', $group_links), $row['maxlinks'], $row['days'], implode(' | ', $forum_links), my_date('normal', $start), my_date('normal', $end), $num_interval_links, $num_net_new_links, ($num_interval_links + $num_net_new_links - $row['maxlinks'])));
							} else	$posthandler->set_error($lang->sprintf($lang->lkt_err_toomanylinks, implode(' | ', $group_links), $row['maxlinks'], $row['days'], implode(' | ', $forum_links), $num_interval_links, $num_net_new_links, ($num_interval_links + $num_net_new_links - $row['maxlinks'])));

							// We won't be storing the resolved redirects to the database in
							// a later hook because we're rejecting this new post or edit,
							// so do that here and now (don't provide a pid, *because* we've
							// rejected this new/edited post).
							lkt_store_urls($g_lkt_links_incl_vids, $g_lkt_redirs, $g_lkt_got_terms);
						}
					}
				}
			}
		}
	}
}

function lkt_anti_link_spam_policy_applies($uid, $qualifying_action_type) {
	global $mybb;

	$user = get_user($uid);

	return
	  // Don't mistakenly ban/delete a super-admin.
	  !is_super_admin($uid)
	  &&
	  // Check whether criterion #1 (usergroup membership) applies
	  is_member($mybb->settings[C_LKT.'_anti_link_spam_usergroups'], $uid)
	  &&
	  // Check whether criterion #2 (account age) applies.
	  (
	   $mybb->settings[C_LKT.'_anti_link_spam_max_account_age_days'] == 0 // For a setting value of zero, any account age matches.
	   ||
	   !isset($user['regdate']) // Criterion #2 always applies for guests.
	   ||
	   (TIME_NOW - $user['regdate'])/60/60/24 <= $mybb->settings[C_LKT.'_anti_link_spam_max_account_age_days']
	  )
	  &&
	  // Check whether criterion #3 (post count) applies.
	  (
	   $mybb->settings[C_LKT.'_anti_link_spam_max_post_count'] == 0 // For a setting value of zero, any post count matches.
	   ||
	   !isset($user['postnum']) // Criterion #3 always applies for guests.
	   ||
	   $user['postnum'] <= $mybb->settings[C_LKT.'_anti_link_spam_max_post_count']
	  )
	  &&
	  // Check whether criterion #4 (qualifying action) applies.
	  in_array($mybb->settings[C_LKT.'_anti_link_spam_qualifying_action'], [$qualifying_action_type, 'either']);
}

/**
 * Tests whether any URL in the provided array is classified as spam
 * either as a source or terminating URL, or due to a parallel source URL
 * that terminates in the same terminating URL.
 *
 * @param array $urls An array of raw (i.e., pre-normalised) URLs as strings.
 *
 * @return boolean True if any of the URLs is classified as spam; false otherwise.
 */
function lkt_urls_contain_spam_url($urls) {
	global $db;

	if ($urls) {
		$urls_norm_esc_quoted_list = "'".implode("', '", array_map([$db, 'escape_string'], lkt_normalise_urls($urls)))."'";
		$sql = <<<EOSQL
SELECT          COUNT(*) AS spammy_count
FROM            {$db->table_prefix}urls u1
LEFT OUTER JOIN {$db->table_prefix}urls u2
ON              u2.url_term_norm = u1.url_term_norm
WHERE           (
                 (
                  u1.url_norm IN ($urls_norm_esc_quoted_list)
                  OR
                  u1.url_term_norm IN ($urls_norm_esc_quoted_list)
                 )
                 AND
                 (
                  u1.spam_class = 'Spam'
                  OR
                  u2.spam_class = 'Spam'
                 )
                )
EOSQL;
		return $db->fetch_field($db->query($sql), 'spammy_count') > 0;
	} else	return false;
}

function lkt_hookin__modcp_start() {
	global $lang;

	$lang->load(C_LKT);
}

function lkt_hookin__moderation_purgespammer_show() {
	global $lang;

	$lang->load(C_LKT);
}

function lkt_hookin__do_modqueue() {
	global $mybb, $db;

	$threads = $mybb->get_input('threads', MyBB::INPUT_ARRAY);
	$posts   = $mybb->get_input('posts'  , MyBB::INPUT_ARRAY);
	if (!empty($threads)) {
		$tids_for_link_spam = [];
		foreach ($threads as $tid => &$action) {
			if ($action == 'delete_link_spam') {
				$tids_for_link_spam[] = $tid;
				$action = 'delete';
			}
		}
		$mybb->input['threads'] = $threads;

		if ($tids_for_link_spam) {
			$tids_cs = implode(',', $tids_for_link_spam);
			$db->write_query(<<<EOSQL
UPDATE           {$db->table_prefix}urls u
RIGHT OUTER JOIN {$db->table_prefix}post_urls pu
ON               pu.urlid = u.urlid
RIGHT OUTER JOIN {$db->table_prefix}posts p
ON               p.pid = pu.pid
RIGHT OUTER JOIN {$db->table_prefix}threads t
ON               t.firstpost = p.pid
SET              u.spam_class = 'Spam'
WHERE            t.tid IN ($tids_cs)
                 AND
                 u.spam_class IN ('Unspecified', 'Potential spam')
EOSQL
			);
		}
	} else if (!empty($posts)) {
		$pids_for_link_spam = [];
		foreach ($posts as $pid => &$action) {
			if ($action == 'delete_link_spam') {
				$pids_for_link_spam[] = $pid;
				$action = 'delete';
			}
		}
		$mybb->input['posts'] = $posts;

		if ($pids_for_link_spam) {
			$pids_cs = implode(',', $pids_for_link_spam);
			$db->write_query(<<<EOSQL
UPDATE           {$db->table_prefix}urls u
RIGHT OUTER JOIN {$db->table_prefix}post_urls pu
ON               pu.urlid = u.urlid
RIGHT OUTER JOIN {$db->table_prefix}posts p
ON               p.pid = pu.pid
SET              u.spam_class = 'Spam'
WHERE            p.pid IN ($pids_cs)
                 AND
                 u.spam_class IN ('Unspecified', 'Potential spam')
EOSQL
			);
		}
	}
}

function lkt_hookin__moderation_purgespammer_purge() {
	global $mybb, $db, $uid;

	if ($mybb->get_input('classify_links_as_spam')) {
		$db->write_query(<<<EOSQL
UPDATE           {$db->table_prefix}urls u
RIGHT OUTER JOIN {$db->table_prefix}post_urls pu
ON               pu.urlid = u.urlid
RIGHT OUTER JOIN {$db->table_prefix}posts p
ON               p.pid = pu.pid
SET              u.spam_class = 'Spam'
WHERE            p.uid = {$uid}
                 AND
                 u.spam_class IN ('Unspecified', 'Potential spam')
EOSQL
		);
	}
}

function lkt_hookin__global_intermediate() {
	global $mybb, $db, $lang, $templates, $config, $g_lkt_potential_spam_mod_notice;

	$g_lkt_potential_spam_mod_notice = '';

	require_once MYBB_ROOT.$mybb->config['admin_dir'].'/inc/functions.php';
	$can_view_link_listing = is_super_admin($mybb->user['uid']);
	if (!$can_view_link_listing) {
		$adminperms = get_admin_permissions($mybb->user['uid']);
		$can_view_link_listing = !empty($adminperms['forum']['linklisting']);
	}
	if ($can_view_link_listing) {
		$pot_spam_link_count = $db->fetch_field($db->simple_select('urls', 'COUNT(*) AS pot_spam_link_count', "spam_class='Potential spam'"), 'pot_spam_link_count');
		if ($pot_spam_link_count > 0) {
			$lang->load(C_LKT);
			$pot_spam_link_header_msg = $lang->sprintf($lang->lkt_potential_spam_mod_notice, $pot_spam_link_count);
			$g_lkt_potential_spam_mod_notice = eval($templates->render('linktools_potential_spam_mod_notice'));
		}
	}
}

function lkt_hookin__admin_page_output_footer(&$args) {
	global $mybb, $lang, $unapproved_threads, $unapproved_posts;

	if ($args['this']->active_module == 'forum' && $args['this']->active_action == 'moderation_queue') {
		if ($unapproved_threads || $unapproved_posts) {
			$lang->load(C_LKT);
			$type = $unapproved_threads ? 'threads': 'posts';
			$start = strlen($type) + 1;
			$lkt_delete_link_spam_title = addslashes($lang->lkt_delete_link_spam_title);
			$css = lkt_get_modcp_css();
			// There are no feasible hooks to use to intersperse our additions into the output HTML,
			// so instead inject it via Javascript.
			echo <<<EOJS
<script type="text/javascript">
$(function() {
	$('.radio_delete').each(function(i, obj) {
		$(this).parent().after('<label class="label_radio_delete_link_spam" title="{$lkt_delete_link_spam_title}"><input type="radio" class="radio_input radio_delete_link_spam" name="{$type}['+parseInt($(this).attr('name').substr({$start}))+']" value="delete_link_spam" /> {$lang->lkt_delete_link_spam}</label>'+"\\n");
	});
	$('.mass_delete').parent().after('<li><a href="#" class="mass_delete_link_spam" onclick="$(\'input.radio_delete_link_spam\').each(function(){ $(this).prop(\'checked\', true); }); return false;">{$lang->lkt_mark_all_deletion_link_spam}</a></li>'+"\\n");
});
</script>
<style type="text/css">
{$css}
</style>
EOJS;
		}
	}
}

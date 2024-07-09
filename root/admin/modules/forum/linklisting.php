<?php

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

$spam_classes = ['Unspecified', 'Potential spam', 'Not spam', 'Spam'];
$override_policies = ['override', 'conditional', 'ignore'];
$errors = [];

$import_link = 'index.php?module=forum-linklisting&amp;action=import_links';

if ($mybb->request_method == 'post') {
	if ($mybb->get_input('save_spam_classes')) {
		verify_post_check($mybb->get_input('my_post_key'));
		$did_update = false;
		foreach ($mybb->input as $name => $value) {
			if (strpos($name, 'spam_class_') === 0) {
				$urlid = (int)substr($name, strlen('spam_class_'));
				$db->update_query('urls', ['spam_class' => $value], "urlid = {$urlid}");
				$did_update = true;
			}
		}
		flash_message($did_update ? $lang->lkt_spam_class_update_success : $lang->lkt_spam_class_update_fail, $did_update ? 'success' : 'error');
		admin_redirect($current_url_filtered);
	} else if ($mybb->get_input('import_links')) {
		$urls = array_filter(array_map('trim', explode("\n", $mybb->get_input('urls_one_per_line'))));
		$invalid_urls = [];
		foreach ($urls as $url) {
			if (!lkt_is_url($url)) {
				$invalid_urls[] = $url;
			}
		}
		if ($invalid_urls) {
			$errors[] = $lang->lkt_err_invalid_urls.'<br /><br />'.implode('<br />'."\n", $invalid_urls);
		} else {
			$spam_class = $mybb->get_input('spam_class');
			if (!in_array($spam_class, $spam_classes)) {
				$errors[] = $lang->sprintf($lang->lkt_err_invalid_spam_class, htmlspecialchars_uni($spam_class));
			}
			$override_policy = $mybb->get_input('override_policy');
			if (!in_array($override_policy, $override_policies)) {
				$errors[] = $lang->sprintf($lang->lkt_err_invalid_override_policy, htmlspecialchars_uni($override_policy));
			}
			if (!$errors) {
				lkt_resolve_and_store_urls_from_list($urls, /*$pid = */null, $spam_class, $override_policy);
				flash_message($lang->lkt_import_urls_success, 'success');
				admin_redirect($import_link);
			}
		}
	}
}

$lang->load('linktools');

$sub_tabs = array(
	'linklisting' => array(
		'title' => $lang->lkt_linklisting,
		'description' => $lang->lkt_linklistingdesc,
		'link' => 'index.php?module=forum-linklisting',
	),
	'linkimport' => array(
		'title' => $lang->lkt_import_links,
		'description' => $lang->lkt_import_links_desc,
		'link' => $import_link,
	)
);

$page->add_breadcrumb_item($lang->lkt_linklisting_and_import, "index.php?module=forum-linklisting");

$page->output_header($lang->lkt_linklisting_and_import);

if ($mybb->input['action'] == 'import_links') {
	$page->output_nav_tabs($sub_tabs, 'linkimport');

	if ($errors) {
		$page->output_inline_error($errors);
	}

	$form = new Form($import_link, 'post');
	$form_container = new FormContainer($lang->lkt_import_links);

	$cell = '';
	$spam_class_input = $mybb->get_input('spam_class');
	foreach ($spam_classes as $spam_class) {
		if ($cell) $cell .= ' &nbsp; ';
		$options = [];
		if ($spam_class_input == $spam_class || !$spam_class_input && $spam_class == 'Spam') {
			$options['checked'] = true;
		}
		$cell .= $form->generate_radio_button('spam_class', $spam_class, $spam_class, $options);
	}
	$form_container->output_row($lang->lkt_spam_classification, $lang->lkt_spam_classification_desc, $cell);

	$cell = '';
	$override_policy_input = $mybb->get_input('override_policy');
	foreach ($override_policies as $policy) {
		if ($cell) $cell .= '<br />'."\n";
		$options = [];
		if ($override_policy_input == $policy || !$override_policy_input && $policy == 'conditional') {
			$options['checked'] = true;
		}
		$lang_key = "lkt_override_policy_{$policy}";
		$cell .= $form->generate_radio_button('override_policy', $policy, $lang->$lang_key, $options);
	}
	$form_container->output_row($lang->lkt_override_policy, $lang->lkt_override_policy_desc, $cell);
	$form_container->output_row($lang->lkt_links_to_import, $lang->lkt_links_to_import_desc, $form->generate_text_area('urls_one_per_line', $mybb->get_input('urls_one_per_line'), ['rows' => 25, 'cols' => 100, 'style' => 'width: 99% !important;']));

	$form_container->end();

	$buttons = [$form->generate_submit_button($lang->lkt_import_links_submit_caption, ['name' => 'import_links'])];

	$form->output_submit_wrapper($buttons);

	$form->end();
} else {
	$page->output_nav_tabs($sub_tabs, 'linklisting');

	$per_page = $mybb->settings[C_LKT.'_links_per_listing_page'];

	$pgnum = $mybb->get_input('page', MyBB::INPUT_INT);
	if ($pgnum < 1) $pgnum = 1;

	$spam_class_filters = $mybb->get_input('spam_class_filters', MyBB::INPUT_ARRAY);
	$extra_order = $conds = '';
	foreach ($spam_class_filters as $spam_class) {
		if ($conds) $conds .= ' OR ';
		$conds .= "spam_class = '".$db->escape_string($spam_class)."'";
	}
	$searched_link = $mybb->get_input('searched_link');
	if ($searched_link) {
		if ($conds) $conds = "({$conds}) AND";
		$conds .= " url LIKE '%".$db->escape_string_like($searched_link)."%'";
		$extra_order = 'LENGTH(url) ASC, ';
	}

	$tot_rows = $db->fetch_field($db->simple_select('urls', 'COUNT(*) AS tot_links', $conds), 'tot_links');

	$pgmax = ceil($tot_rows/$per_page);
	if ($pgmax < 1) $pgmax = 1;

	if ($pgnum > $pgmax) $pgnum = $pgmax;

	$current_url = "index.php?module=forum-linklisting&amp;page={$pgnum}";
	$current_url_filtered = $current_url;
	if ($spam_class_filters) {
		foreach ($spam_class_filters as $i => $filter) {
			$i = (int)$i;
			$current_url_filtered .= "&amp;spam_class_filters[{$i}]=".urlencode($filter);
		}
	}
	if ($searched_link) {
		$current_url_filtered .= '&amp;searched_link='.urlencode($searched_link);
	}

	$table = new Table;
	$form  = new Form('index.php', 'get', /*$id=*/'', /*$allow_uploads=*/false, /*$name=*/'', /*$return=*/true);
	$html  = $form->construct_return;
	$html .= $form->generate_hidden_field('module', 'forum-linklisting');
	$html .= $form->generate_hidden_field('page', 1);
	foreach ($spam_classes as $i => $spam_class) {
		$options = [];
		if (in_array($spam_class, $spam_class_filters)) {
			$options['checked'] = true;
		}
		$html .= $form->generate_check_box("spam_class_filters[$i]", $spam_class, $spam_class, $options);
	}
	$html .= ' ';
	$html .= $form->generate_submit_button($lang->lkt_filter_go, ['name' => 'filter']);
	$html .= ' ';
	$html .= $form->generate_text_box('searched_link', $searched_link);
	$html .= ' ';
	$html .= $form->generate_submit_button($lang->lkt_search_go, ['name' => 'search']);
	$html .= $form->end();
	$table->construct_cell($html);
	$table->construct_row();
	$table->output();

	$form = new Form($current_url_filtered, 'post');
	$form_container = new FormContainer($lang->lkt_linklisting);
	$form_container->output_row_header($lang->lkt_url);
	$form_container->output_row_header($lang->lkt_url_term);
	$form_container->output_row_header($lang->lkt_date_added, ['style' => 'text-align: center;']);
	$form_container->output_row_header($lang->lkt_posts_count, ['style' => 'text-align: center;']);
	$form_container->output_row_header($lang->lkt_spam_class, ['style' => 'text-align: center;']);

	$where = $conds ? 'WHERE    ('.$conds.')' : '';
	$query = $db->query("
SELECT   urlid,
         url,
         url_term,
         dateline,
         (
          SELECT COUNT(*)
          FROM   {$db->table_prefix}post_urls pu
          WHERE  pu.urlid = u.urlid
         ) AS posts_count,
         spam_class
FROM     {$db->table_prefix}urls u
$where
ORDER BY {$extra_order}dateline DESC
LIMIT    ".(($pgnum - 1) * $per_page).', '.$per_page
	);

	while ($row = $db->fetch_array($query)) {
		$url_enc      = htmlspecialchars_uni($row['url'     ]);
		$url_term_enc = htmlspecialchars_uni($row['url_term']);
		$style_opts = ['style' => 'word-break: break-word;'];
		$form_container->output_cell("<a href=\"{$url_enc}\">$url_enc</a>", $style_opts);
		$form_container->output_cell($url_term_enc == $url_enc ? $lang->lkt_itself : "<a href=\"{$url_term_enc}\">$url_term_enc</a>", $style_opts);
		$form_container->output_cell($row['dateline'] ? my_date('relative', $row['dateline']) : $lang->lkt_date_unknown, ['style' => 'text-align: center;']);
		$form_container->output_cell('<a href="'.$mybb->settings['bburl'].'/search.php?action=do_search&amp;urls='.urlencode($row['url']).'&amp;raw_only=1" title="'.$lang->lkt_containing_post_count_link_title.'">'.my_number_format($row['posts_count']).'</a>', ['style' => 'text-align: center;']);
		$cell = '';
		foreach ($spam_classes as $spam_class) {
			$options = ['class' => $spam_class];
			if ($row['spam_class'] == $spam_class) {
				$options['checked'] = true;
			}
			if ($cell) $cell .= ' &nbsp; ';
			$cell .= $form->generate_radio_button('spam_class_'.$row['urlid'], $spam_class, $spam_class, $options);
		}
		$form_container->output_cell($cell, ['style' => 'text-align: center;']);
		$form_container->construct_row();
	}

	$form_container->output_cell('');
	$form_container->output_cell('');
	$form_container->output_cell('');
	$form_container->output_cell('');
	$mark_all_links = "<a style=\"cursor: pointer;\" onclick=\"$('.Not.spam').prop('checked', true)\">{$lang->lkt_mark_all_not_spam}</a> | <a style=\"cursor: pointer;\" onclick=\"$('.Spam').prop('checked', true)\">{$lang->lkt_mark_all_spam}</a>".PHP_EOL;
	$form_container->output_cell($mark_all_links, ['style' => 'text-align: center;']);
	$form_container->construct_row();

	$form_container->end();

	$buttons = [$form->generate_submit_button($lang->lkt_save_spam_classes, ['name' => 'save_spam_classes'])];

	$form->output_submit_wrapper($buttons);

	$form->end();

	echo draw_admin_pagination($pgnum, $per_page, $tot_rows, $current_url_filtered.'&page={page}');
}

$page->output_footer();

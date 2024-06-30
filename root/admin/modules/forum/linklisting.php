<?php

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

$spam_classes = ['Unspecified', 'Potential spam', 'Not spam', 'Spam'];

$per_page = $mybb->settings[C_LKT.'_links_per_listing_page'];

$pgnum = $mybb->get_input('page', MyBB::INPUT_INT);
if ($pgnum < 1) $pgnum = 1;

$spam_class_filters = $mybb->get_input('spam_class_filters', MyBB::INPUT_ARRAY);
$conds = '';
foreach ($spam_class_filters as $spam_class) {
	if ($conds) $conds .= ' OR ';
	$conds .= "spam_class = '".$db->escape_string($spam_class)."'";
}

$tot_rows = $db->fetch_field($db->simple_select('urls', 'COUNT(*) AS tot_links', $conds), 'tot_links');

$pgmax = ceil($tot_rows/$per_page);
if ($pgnum > $pgmax) $pgnum = $pgmax;

$current_url = "index.php?module=forum-linklisting&amp;page={$pgnum}";
$current_url_filtered = $current_url;
if ($spam_class_filters) {
	foreach ($spam_class_filters as $i => $filter) {
		$i = (int)$i;
		$current_url_filtered .= "&amp;spam_class_filters[{$i}]=".urlencode($filter);
	}
}

if ($mybb->get_input('my_post_key') && !$mybb->get_input('filter')) {
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
}

$lang->load('linktools');

$sub_tabs = array(
	'linklisting' => array(
		'title' => $lang->lkt_linklisting,
		'description' => $lang->lkt_linklistingdesc
	)
);

$page->add_breadcrumb_item($lang->lkt_linklisting, "index.php?module=forum-linklisting");

$page->output_header($lang->lkt_linklisting);

$page->output_nav_tabs($sub_tabs, 'linklisting');

$form  = new Form('index.php', 'get');
$table = new Table;
$html  = $form->generate_hidden_field('module', 'forum-linklisting');
$html .= $form->generate_hidden_field('page', 1);
foreach ($spam_classes as $i => $spam_class) {
	$options = [];
	if (in_array($spam_class, $spam_class_filters)) {
		$options['checked'] = true;
	}
	$html .= $form->generate_check_box("spam_class_filters[$i]", $spam_class, $spam_class, $options);
}
$table->construct_cell("{$html} ".$form->generate_submit_button($lang->lkt_filter_go, ['name' => 'filter']));
$table->construct_row();
$table->output();
$form->end();

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
ORDER BY dateline DESC
LIMIT    ".(($pgnum - 1) * $per_page).', '.$per_page
);

while ($row = $db->fetch_array($query)) {
	$url_enc      = htmlspecialchars_uni($row['url'     ]);
	$url_term_enc = htmlspecialchars_uni($row['url_term']);
	$form_container->output_cell("<a href=\"{$url_enc}\">$url_enc</a>");
	$form_container->output_cell("<a href=\"{$url_term_enc}\">$url_term_enc</a>");
	$form_container->output_cell($row['dateline'] ? my_date('relative', $row['dateline']) : $lang->lkt_date_unknown, ['style' => 'text-align: center;']);
	$form_container->output_cell('<a href="'.$mybb->settings['bburl'].'/search.php?action=do_search&amp;urls='.urlencode($row['url']).'&amp;raw_only=1">'.my_number_format($row['posts_count']).'</a>', ['style' => 'text-align: center;']);
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

$page->output_footer();

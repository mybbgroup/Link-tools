<?php

$l['lkt_post_anyway'                  ] = 'Ignore warning and post anyway';
$l['lkt_started_by'                   ] = 'Thread started by';
$l['lkt_opening_post'                 ] = '<strong>Opening post</strong>';
$l['lkt_non_opening_post'             ] = '<strong>Non-opening post:</strong>';
$l['lkt_post_singular'                ] = 'post';
$l['lkt_posts_plural'                 ] = 'posts';
$l['lkt_found_posts_count'            ] = 'Found {1} existing {2} containing one or more of the URLs in your post. Perhaps you are sharing a resource or resources that have already been shared and discussed?';
$l['lkt_found_more_than_posts_count'  ] = 'Found more than {1} existing {2} containing one or more of the URLs in your post. Perhaps you are sharing a resource or resources that have already been shared and discussed?';
$l['lkt_found_posts_count_undismissed'] = 'Found {1} existing {2} <em>which you had not dismissed whilst editing</em> containing one or more of the URLs in your post. Perhaps you are sharing a resource or resources that have already been shared and discussed?';
$l['lkt_found_more_than_posts_count_undismissed'] = 'Found more than {1} existing {2} <em>which you had not dismissed whilst editing</em> containing one or more of the URLs in your post. Perhaps you are sharing a resource or resources that have already been shared and discussed?';
$l['lkt_posted_by'] = 'by';
$l['lkt_matching_url_singular'        ] = 'Matching URL:';
$l['lkt_matching_urls_plural'         ] = 'Matching URLs:';
$l['lkt_msg_url1_as_url2'             ] = '{1} as {2}';
$l['lkt_btn_toggle_msg_hide'          ] = 'Hide posts within which all matching urls occur in quotes';
$l['lkt_btn_toggle_msg_show'          ] = 'Show posts within which all matching urls occur in quotes';
$l['lkt_err_not_active'               ] = 'Error: The linktools plugin is not active.';
$l['lkt_task_ran'                     ] = 'The Link Tools link extraction and redirect resolution task successfully ran.'; // duplicated in admin/linktools.php
$l['lkt_exist_open_post_contains'     ] = 'An existing <strong>opening post</strong> contains';
$l['lkt_exist_post_contains'          ] = 'An existing post contains';
$l['lkt_more_than'                    ] = 'More than ';
$l['lkt_x_exist_open_posts_contain'   ] = '{1} existing <strong>opening posts</strong> contain';
$l['lkt_x_exist_posts_contain'        ] = '{1} existing posts contain';
$l['lkt_x_of_urls_added'              ] = ' {1} of the URLs added to your draft.';
$l['lkt_a_url_added'                  ] = ' a URL added to your draft.';
$l['lkt_one_is_an_opening_post'       ] = ' One of these is an <strong>opening post</strong>.';
$l['lkt_x_are_opening_posts'          ] = ' {1} of these are <strong>opening posts</strong>.';
$l['lkt_further_results_below'        ] = 'The first {1} matching posts are shown in full below. Click here for <a href="{2}">ALL matching posts</a>.';
$l['lkt_further_results_above'        ] = 'The first {1} matching posts are shown in full above. Click here for <a href="{2}">ALL matching posts</a>.';
$l['lkt_dismiss_warn_for_post'        ] = 'Dismiss warning for this post';
$l['lkt_show_more'                    ] = 'Show more';
$l['lkt_show_less'                    ] = 'Show less';
$l['lkt_dismiss_all_warnings'         ] = 'Dismiss all warnings';
$l['lkt_undismiss_all_warns'          ] = 'Undismiss all duplicate link warnings';
$l['lkt_title_warn_about_links'       ] = 'If existing forum posts contain URLs present in your draft, you will be visibly warned about them in real time if you check this box.';
$l['lkt_warn_about_links'             ] = 'Warn about duplicate links';

$l['lkt_regen_link_preview'           ] = 'Regenerate preview';
$l['lkt_regen_link_previews'          ] = 'Regenerate previews: ';
$l['lkt_all'                          ] = 'all';

$l['lkt_preview_regen_pg_title'       ] = 'Regenerate Link Preview(s)';
$l['lkt_regen_breadcrumb'             ] = 'Regenerate Link Preview(s)';
$l['lkt_err_regen_no_pid_or_url'      ] = 'Neither a link nor a post ID was provided.';
$l['lkt_err_regen_no_post_or_msg'     ] = 'Either the post for the supplied ID was missing or its contents were empty.';
$l['lkt_err_regen_url_not_found_in_db'] = 'The supplied link was not found in the `urls` database table: &lt;{1}&gt;.';
$l['lkt_err_regen_url_no_previewer'   ] = 'The supplied link is not eligible for a preview: &lt;{1}&gt;.';
$l['lkt_err_regen_url_too_soon'       ] = 'The supplied link was last regenerated in fewer than the minimum wait period in seconds, {1}: &lt;{2}&gt;.';
$l['lkt_err_regen_no_preview_returned'] = 'A preview was not returned for the supplied link &lt;{1}&gt;.';
$l['lkt_success_regen_url'            ] = 'Successfully regenerated a preview for the supplied link &lt;{1}&gt;.';
$l['lkt_regen_page_return_link'       ] = 'Return to post';

$l['lkt_linkpreviewoff'               ] = '<strong>Disable Link Previews</strong>: disable link previews from showing in this post.';

$l['lkt_err_toomanylinks'             ] = 'You may not post this content because it contains too many links (for clarity: embedded videos count as links). You are a member of the usergroup(s) {1}, whose members may not post more than {2} link(s) over a continuous period of {3} days to the forum(s) (combined): {4}. You have already posted {5} link(s) within that period to that/those forums. There is/are {6} net new link(s) in the content you are attempting to post. That would put you {7} link(s) over your current limit.';

$l['lkt_err_toomanylinks_prior_period'] = 'You may not post this content because it contains too many links (for clarity: embedded videos count as links). The effective member (the member whose post you are editing, or you if you\'re editing your own post) is a member of the usergroup(s) {1}, whose members may not post more than {2} link(s) over a continuous period of {3} days to the forum(s) (combined): {4}. In the period from {5} to {6}, the effective member had already posted {7} link(s) to that/those forums. There is/are {8} net new link(s) in the post you are attempting to edit. That would put the effective member {9} link(s) over their limit during that period.';

$l['lkt_err_purged_del_for_link_spam'] = 'Your account has been purged (deleted) for posting link spam';
$l['lkt_err_purged_banned_for_link_spam'] = 'Your account has been purged (banned) for posting link spam';
$l['lkt_err_invalid_edit_due_to_link_spam'] = 'Your edit has been rejected because it contains a link classified as spam';
$l['lkt_err_invalid_post_due_to_link_spam'] = 'Your post has been rejected because it contains a link classified as spam';
$l['lkt_purge_link_spammer_modlog'] = 'Link spammer auto-purged by Link Tools';

$l['lkt_redirect_post_link_edit_moderation'] = 'The administrator has specified that all posts with new links edited into them require moderation. You will now be returned to the thread.';
$l['lkt_redirect_newreply_moderation'] = 'The administrator has specified that all new posts with links require moderation. You will now be returned to the thread.';
$l['lkt_redirect_newreply_anti_link_spam_moderation'] = 'Your post is being moderated for link spam. You will now be returned to the thread.';
$l['lkt_redirect_edit_anti_link_spam_moderation'] = 'Your edited post is being moderated for link spam. You will now be returned to the thread.';

$l['lkt_delete_link_spam'] = 'Delete; classify links as spam';
$l['lkt_mark_all_deletion_link_spam'] = 'Mark all for deletion with links classified as spam';
$l['lkt_delete_link_spam_title'] = 'Deletes the content after classifying any links in it as spam (excluding those already classified as \'Not spam\')';

$l['lkt_classify_links_as_spam'] = 'Classify all links in the user\'s posts as spam (except for those already classified as \'Not spam\')';

$l['lkt_potential_spam_mod_notice'] = 'The count of potentially spammy links is {1}.';

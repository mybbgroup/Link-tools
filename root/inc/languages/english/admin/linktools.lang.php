<?php

$l['lkt_name'             ] = 'Link Tools';
$l['lkt_desc'             ] = 'Keeps track of links (URLs) in posts and then provides two link services: (1) seamless link searching and (2) warning a member if a link in a thread they are about to start has already been shared to the forum.';
$l['lkt_all_links_extracted'              ] = ' All {1} links have been successfully extracted from all {2} posts.';
$l['lkt_x_of_y_posts_unextracted'         ] = '{1} of {2} posts have not yet had links extracted from them.';
$l['lkt_to_extract_links_click_here'      ] = ' To extract links from that/those {1} post(s), click {2}here{3} (or simply leave it up to the scheduled Link Tools task, assuming you have not disabled it). Note that at the end of the process, the success message will read "Successfully repopulated the links tables for Link Tools." Rest assured that despite that message, when that function is run from here the links table is not repopulated from scratch: links are extracted only from the aforementioned posts from which links have not yet been extracted; posts from which links have already been extracted are left untouched.';
$l['lkt_all_term_links_resolved'          ] = 'Terminating links have been successfully resolved for all extracted links.';
$l['lkt_x_of_y_links_unresolved'          ] = '{1} of {2} links have not been resolved into their terminating links.';
$l['lkt_attempts_unsuccess_made_all_links'] = ' Attempts have been unsuccessfully made for all {1} of them.';
$l['lkt_attempts_unsuccess_made_x_links'  ] = ' Attempts have been unsuccessfully made for {1} of them.';
$l['lkt_given_up_on_x_links'              ] = ' We\'ve given up on {1} of them.';
$l['lkt_to_resolve_links_click_here'      ] = ' {1} link(s) is/are eligible for a resolution attempt right now by clicking {2}here{3}. Note that at the end of the process, the success message will read "Successfully repopulated the terminating redirects in the links table for Link Tools." Rest assured that despite that message, when that function is run from here the links table is not repopulated from scratch: only the aforementioned eligible unresolved links are resolved; already-resolved links are left untouched.';
$l['lkt_no_links_eligible_for_resolution' ] = ' None of these links is eligible for another resolution attempt right now: reattempts of failed resolutions are subject to staggered delays as there is typically no point in retrying immediately; instead, the need is to wait for any network/server problem(s) to be fixed.';

$l['lkt_rebuild_links'     ] = 'Rebuild Link Tables For Link Tools';
$l['lkt_rebuild_links_desc'] = 'When this is run, the database tables that store the links from within posts are repopulated from scratch. Generally, the only reason to run this rebuild is to initialise the tables immediately after the plugin is first installed, because the plugin also installs a task that periodically "catches up" with any posts whose links have not yet been extracted and stored. After running this task, you will then need to run the subsequent task, "Repopulate Terminating Redirects For Link Tools", since the information about terminating redirects will be deleted when this task runs.';
$l['lkt_admin_log_rebuild_links'] = 'Repopulating the links tables for Link Tools.';
$l['lkt_success_rebuild_links'] = 'Successfully repopulated the links tables for Link Tools.';

$l['lkt_rebuild_terms'     ] = 'Repopulate Terminating Redirects For Link Tools';
$l['lkt_rebuild_terms_desc'] = 'When this is run, the terminating redirects for links in the database are repopulated from scratch. This ensures that any stored terminating redirects that may have changed since the posts were first made are updated. Be cautious about changing the "Data Entries Per Page" setting (to the right of this text), which determines the (maximum) number of web servers queried simultaneously when resolving redirects - setting it too high might cause failures.';
$l['lkt_admin_log_rebuild_terms'] = 'Repopulating the terminating redirects in the links tables for Link Tools.';
$l['lkt_success_rebuild_terms'] = 'Successfully repopulated the terminating redirects in the links table for Link Tools.';

$l['lkt_rebuild_renorm'     ] = 'Renormalise Links For Link Tools';
$l['lkt_rebuild_renorm_desc'] = 'When this is run, stored links are renormalised. This only needs to be run if the "ignored query parameters" array constant is changed.';
$l['lkt_admin_log_rebuild_renorm'] = 'Renormalising links for Link Tools.';
$l['lkt_success_rebuild_renorm'] = 'Successfully renormalised links for Link Tools.';

$l['lkt_task_title'       ] = 'Link Tools Link Extraction And Redirect Resolution';
$l['lkt_task_description' ] = 'Extracts links from any posts from which links have not already been extracted, and stores them into the database. Then resolves ultimate redirect targets for up to '.lkt_default_rebuild_term_items_per_page.' links for which an ultimate redirect target has not yet already been resolved, and stores those targets into the database. The first part of this task is most useful in cases in which the plugin is deactivated for a period, during which new posts will not have their links extracted by the plugin - this task then "catches up" on those posts when the plugin is reactivated. On top of that scenario, the second part of this task is necessary because even though redirect targets are resolved when links are first extracted and stored after being edited into a new or existing post, there can sometimes be network errors or downed sites which prevent proper resolution of any redirect(s) at the time.';
$l['lkt_task_ran'         ] = 'The Link Tools link extraction and redirect resolution task successfully ran.'; // duplicated in ../linktools.php

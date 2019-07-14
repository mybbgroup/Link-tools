<?php

$l['dlw_name'             ] = 'Duplicate link warner';
$l['dlw_desc'             ] = 'Warns a member if a link in a thread they are about to start has already been shared to the forum.';

$l['dlw_rebuild_links'     ] = 'Rebuild Link Tables For The Duplicate Link Warner';
$l['dlw_rebuild_links_desc'] = 'When this is run, the database tables that store the links from within posts are repopulated from scratch. Generally, the only reason to run this rebuild is to initialise the tables immediately after the plugin is first installed, because the plugin also installs a task that periodically "catches up" with any posts whose links have not yet been extracted and stored.';
$l['dlw_admin_log_rebuild_links'] = 'Repopulating the links tables for the Duplicate Link Warner.';
$l['dwl_success_rebuild_links'] = 'Successfully repopulated the links tables for the Duplicate Link Warner.';

$l['dlw_rebuild_terms'     ] = 'Repopulate Terminating Redirects For The Duplicate Link Warner';
$l['dlw_rebuild_terms_desc'] = 'When this is run, the terminating redirects for links in the database are repopulated from scratch. This ensures that any stored terminating redirects that may have changed since the posts were first made are updated. Be cautious about changing the "Data Entries Per Page" setting (to the right of this text), which determines the (maximum) number of web servers queried simultaneously when resolving redirects - setting it too high might cause failures.';
$l['dlw_admin_log_rebuild_terms'] = 'Repopulating the terminating redirects in the links tables for the Duplicate Link Warner.';
$l['dwl_success_rebuild_terms'] = 'Successfully repopulated the terminating redirects in the links table for the Duplicate Link Warner.';

$l['dlw_rebuild_renorm'     ] = 'Renormalise Links For The Duplicate Link Warner';
$l['dlw_rebuild_renorm_desc'] = 'When this is run, stored links are renormalised. This only needs to be run if the "ignored query parameters" array constant is changed.';
$l['dlw_admin_log_rebuild_renorm'] = 'Renormalising links for the Duplicate Link Warner.';
$l['dwl_success_rebuild_renorm'] = 'Successfully renormalised links for the Duplicate Link Warner.';

$l['dlw_task_title'       ] = 'Duplicate Link Warner Link Extraction And Redirect Resolution';
$l['dlw_task_description' ] = 'Extracts links from any posts from which links have not already been extracted, and stores them into the database. Then resolves ultimate redirect targets for up to '.dlw_default_rebuild_term_items_per_page.' links for which an ultimate redirect target has not yet already been resolved, and stores those targets into the database. The first part of this task is most useful in cases in which the plugin is deactivated for a period, during which new posts will not have their links extracted by the plugin - this task then "catches up" on those posts when the plugin is reactivated. On top of that scenario, the second part of this task is necessary because even though redirect targets are resolved when links are first extracted and stored after being edited into a new or existing post, there can sometimes be network errors or downed sites which prevent proper resolution of any redirect(s) at the time.';
$l['dlw_task_ran'         ] = 'The duplicate link warner link extraction and redirect resolution task successfully ran.'; // duplicated in ../duplicate_link_warner.php

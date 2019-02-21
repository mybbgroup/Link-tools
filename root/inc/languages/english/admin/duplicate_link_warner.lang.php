<?php

$l['dlw_name'             ] = 'Duplicate link warner';
$l['dlw_desc'             ] = 'Warns a member if a link in a thread they are about to start has already been shared to the forum.';
$l['dlw_rebuild'          ] = 'Rebuild Link Tables for the Duplicate Link Warner';
$l['dlw_rebuild_desc'     ] = 'When this is run, the database tables that store the links from within posts are repopulated from scratch. This ensures that any stored redirects that may have changed since the posts were first made are updated.';
$l['dlw_admin_log_rebuild'] = 'Repopulating the links tables for the duplicate links warner.';
$l['dwl_success_rebuild'  ] = 'Successfully repopulated the links tables for the duplicate links warner.';
$l['dlw_task_title'       ] = 'Duplicate Link Warner Redirect Resolution';
$l['dlw_task_description' ] = 'Resolves and stores the ultimate redirect target of links in posts for those posts for which this hasn\'t yet been done (this process is deferred to this task when a new post is posted or an existing post is edited).';
$l['dlw_task_ran'         ] = 'The duplicate link warner redirect resolution task successfully ran.'; // duplicated in ../duplicate_link_warner.php

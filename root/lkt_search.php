<?php

define('IN_MYBB', 1);
define('IGNORE_CLEAN_VARS', 'sid');
define('THIS_SCRIPT', 'lkt_search.php');

require_once './global.php';

lkt_search($mybb->input['urls']);

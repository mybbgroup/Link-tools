<?php

define('IN_MYBB', 1);
define('IGNORE_CLEAN_VARS', 'sid');
define('THIS_SCRIPT', 'dlw_search.php');

require_once './global.php';

dlw_search($mybb->input['urls']);

<?php

/**
 * Configure additional cURL options below as required for your host.
 * Typically, these will be proxy-related. The example provided was supposed
 * to work for GoDaddy, however, on testing, it seems not to work there.
 * Feel free to uncomment it anyway or to create an alternative array of options
 * as necessary.
 *
 * If you find that such additional cURL options as these _are_ necessary, then
 * after copying the files for Link Tools into your MyBB installation, you
 * should create the file inc/plugins/linktools/extra-curl-opts.php, which
 * should simply return an array of options as below (see the example file in
 * the same directory).
 */
// $extra_opts = array(
// 	CURLOPT_PROXYTYPE => CURLPROXY_HTTP                       ,
// 	CURLOPT_PROXY  => 'http://proxy.shr.secureserver.net:3128',
// 	CURLOPT_SSL_VERIFYPEER => false                           ,
// );


header('Content-Type: text/plain');

echo 'Checking whether the cURL extension is loaded...';
if (extension_loaded('curl')) {
	echo 'PASS'.PHP_EOL.PHP_EOL;
} else {
	echo 'FAIL'.PHP_EOL.PHP_EOL;
	echo get_limited_func_str('installed');
	exit;
}

echo 'Checking for the existence of all required cURL functions...';
$funcs = array(
	'curl_init',
	'curl_setopt_array',
	'curl_exec',
	'curl_getinfo',
	'curl_multi_init',
	'curl_multi_add_handle',
	'curl_multi_exec',
	'curl_multi_select',
	'curl_multi_getcontent',
	'curl_multi_remove_handle',
	'curl_close',
);
$funcs_exist = array();
$funcs_not_exist = array();
foreach ($funcs as $func) {
	if (function_exists($func)) {
		$funcs_exist[] = $func;
	} else	$funcs_not_exist[] = $func;
}
if (!$funcs_not_exist) {
	echo 'PASS'.PHP_EOL.PHP_EOL;
} else {
	echo 'FAIL'.PHP_EOL.PHP_EOL;
	echo 'Link Tools functionality will be limited because not all of the required functions from the Client URL Library (cURL) are available.'.PHP_EOL.PHP_EOL;
	if ($funcs_exist) {
		echo 'The following cURL functions are available: '.implode('(), ', $funcs_exist).'().'.PHP_EOL.PHP_EOL;
	}
	echo 'The following cURL functions are not available: '.implode('(), ', $funcs_not_exist).'().'.PHP_EOL.PHP_EOL;
	echo 'Probably, these unavailable functions have been disabled by your host, in which case, you could contact your host to have them enabled if possible.'.PHP_EOL.PHP_EOL;
	echo 'For more details on the cURL library, please see here: <https://www.php.net/manual/en/book.curl.php>.';
	exit;
}

$test_results = array();

$base_opts = array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => true,
	CURLOPT_NOBODY         => false,
	CURLOPT_TIMEOUT        => 10,
	CURLOPT_USERAGENT      => 'The MyBB Link Tools plugin',
);

if (!empty($extra_opts)) foreach ($extra_opts as $k => $v) {
	$base_opts[$k] = $v;
}

echo 'Checking for the successful functioning of single-page, HTTP (non-secure) cURL downloads...';

$opts = $base_opts;
$opts[CURLOPT_URL] = 'http://example.com/';

if (($ch = curl_init()) === false) {
	do_failure($ch, 'The curl_init() function returned false.');
} else if (!curl_setopt_array($ch, $opts)) {
	do_failure($ch, 'The curl_setopt_array() function returned false.');
} else if (($content = curl_exec($ch)) === false) {
	do_failure($ch, 'The curl_exec() function returned false.');
} else if ($content == '') {
	do_failure($ch, 'The curl_exec() function returned an empty string or equivalent (but not false).');
} else if (curl_getinfo($ch, CURLINFO_HEADER_SIZE) == false) {
	do_failure($ch, 'The curl_getinfo() function for CURLINFO_HEADER_SIZE returned false or equivalent.');
} else if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == false) {
	do_failure($ch, 'The curl_getinfo() function for CURLINFO_HTTP_CODE returned false or equivalent.');
} else	$test_results[] = true;

if ($test_results[count($test_results) - 1]) {
	echo 'PASS'.PHP_EOL.PHP_EOL;
}

// Be considerate of others' servers: don't hit example.com again without waiting for 3 seconds.
sleep(3);

echo 'Checking for the successful functioning of single-page, HTTPS (secure) cURL downloads...';

if (!curl_setopt($ch, CURLOPT_URL, 'https://example.com/')) {
	do_failure($ch, 'The curl_setopt() function returned false.');
} else if (($content = curl_exec($ch)) === false) {
	do_failure($ch, 'The curl_exec() function returned false.');
} else if ($content == '') {
	do_failure($ch, 'The curl_exec() function returned an empty string or equivalent (but not false).');
} else if (curl_getinfo($ch, CURLINFO_HEADER_SIZE) == false) {
	do_failure($ch, 'The curl_getinfo() function for CURLINFO_HEADER_SIZE returned false or equivalent.');
} else if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == false) {
	do_failure($ch, 'The curl_getinfo() function for CURLINFO_HTTP_CODE returned false or equivalent.');
} else	$test_results[] = true;

if ($test_results[count($test_results) - 1]) {
	echo 'PASS'.PHP_EOL.PHP_EOL;
}

curl_close($ch);

// Be considerate of others' servers: don't hit example.com again without waiting for 3 seconds.
sleep(3);

echo 'Checking for the successful functioning of multi-page, HTTP (non-secure) cURL downloads...';

$urls = array(
	'http://example.com/',
	'http://example.net/',
);

if (do_curl_multi_test($urls, $base_opts)) {
	$test_results[] = true;
	echo 'PASS'.PHP_EOL.PHP_EOL;
}

// Be considerate of others' servers: don't hit example.com and example.net again without waiting for 3 seconds.
sleep(3);

echo 'Checking for the successful functioning of multi-page, HTTPS (secure) cURL downloads...';

$urls = array(
	'https://example.com/',
	'https://example.net/',
);

if (do_curl_multi_test($urls, $base_opts)) {
	$test_results[] = true;
	echo 'PASS'.PHP_EOL.PHP_EOL;
}

if (in_array(false, $test_results)) {
	echo 'At least one of the tests failed. '.get_limited_func_str();
	if ($test_results == array(true, false, true, false)) {
		echo PHP_EOL.'Due to the pattern of results, it is likely that following the steps of this Stack Overflow answer will resolve the problem: <https://stackoverflow.com/a/43492865>.'.PHP_EOL;
	}
} else	echo 'All tests passed. All of the cURL functionality required by Link Tools appears to be present.'.PHP_EOL;

function do_curl_multi_test($urls, $base_opts) {
	if (($mh = curl_multi_init()) === false) {
		do_failure($ch, 'The curl_multi_init() function returned false.');
		return false;
	}

	$curl_handles = array();
	foreach ($urls as $url) {
		if (($ch = curl_init()) === false) {
			do_failure($ch, 'The curl_init() function returned false.');
			return false;
		} else {
			$opts = $base_opts;
			$opts[CURLOPT_URL] = $url;

			if (!curl_setopt_array($ch, $opts)) {
				do_failure($ch, 'The curl_setopt_array() function returned false (for URL <'.$url.'>).');
				return false;
			}
			if (curl_multi_add_handle($mh, $ch) !== CURLM_OK/*==0*/) {
				do_failure($ch, 'The curl_multi_add_handle() function returned a value not equal to CURLM_OK (for URL <'.$url.'>).');
				return false;
			} else	$curl_handles[$url] = $ch;
		}
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

	foreach ($curl_handles as $url => $ch) {
		$content = curl_multi_getcontent($ch);
		if ($content === false) {
			do_failure($ch, 'The curl_multi_getcontent() function returned false (for URL <'.$url.'>).');
			return false;
		} else if ($content == '') {
			do_failure($ch, 'The curl_multi_getcontent() function returned an empty string or equivalent (but not false) (for URL <'.$url.'>).');
			return false;
		} else if (curl_getinfo($ch, CURLINFO_HEADER_SIZE) == false) {
			do_failure($ch, 'The curl_getinfo() function for CURLINFO_HEADER_SIZE returned false or equivalent (for URL <'.$url.'>).');
			return false;
		} else if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == false) {
			do_failure($ch, 'The curl_getinfo() function for CURLINFO_HTTP_CODE returned false or equivalent (for URL <'.$url.'>).');
			return false;
		}

		curl_multi_remove_handle($mh, $ch);
	}

	return true;
}

function do_failure($ch, $err_msgs) {
	global $test_results;

	$test_results[] = false;

	echo 'FAIL'.PHP_EOL.PHP_EOL;
	echo $err_msgs.PHP_EOL;
	echo 'The curl_error() function reports: '.curl_error($ch).PHP_EOL.PHP_EOL;
}

function get_limited_func_str($error_wording = 'functioning correctly') {
	return 'Link Tools functionality will be limited because the Client URL Library (cURL) does not appear to be '.$error_wording.'. For more details on this library, please see here: <https://www.php.net/manual/en/book.curl.php>.'.PHP_EOL;
}

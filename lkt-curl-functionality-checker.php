<?php

header('Content-Type: text/plain');

echo 'Checking for the existence of the curl_init() function...';
if (function_exists('curl_init')) {
	echo 'PASS'.PHP_EOL.PHP_EOL;
} else {
	echo 'FAIL'.PHP_EOL.PHP_EOL;
	echo get_limited_func_str(true);
	exit;
}

$test_results = array();

echo 'Checking for the successful functioning of single-page, HTTP (non-HTTPS) curl downloads...';

if (($ch = curl_init()) === false) {
	do_failure($ch, 'The curl_init() function returned false.');
} else if (!curl_setopt_array($ch, array(
	CURLOPT_URL            => 'http://example.com/',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => true,
	CURLOPT_NOBODY         => false,
	CURLOPT_TIMEOUT        => 10,
	CURLOPT_USERAGENT      => 'The MyBB Link Tools plugin',
))) {
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

echo 'Checking for the successful functioning of single-page, HTTPS curl downloads...';

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

echo 'Checking for the successful functioning of multi-page, HTTP (non-HTTPS) curl downloads...';

$urls = array(
	'http://example.com/',
	'http://example.net/',
);

if (do_curl_multi_test($urls)) {
	$test_results[] = true;
	echo 'PASS'.PHP_EOL.PHP_EOL;
}

// Be considerate of others' servers: don't hit example.com and example.net again without waiting for 3 seconds.
sleep(3);

echo 'Checking for the successful functioning of multi-page, HTTPS curl downloads...';

$urls = array(
	'https://example.com/',
	'https://example.net/',
);

if (do_curl_multi_test($urls)) {
	$test_results[] = true;
	echo 'PASS'.PHP_EOL.PHP_EOL;
}

if (in_array(false, $test_results)) {
	echo 'At least one of the tests failed. '.get_limited_func_str();
} else	echo 'All tests passed. All of the curl functionality required by Link Tools appears to be present.'.PHP_EOL;

function do_curl_multi_test($urls) {
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
			if (!curl_setopt_array($ch, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => true,
				CURLOPT_NOBODY         => false,
				CURLOPT_TIMEOUT        => 10,
				CURLOPT_USERAGENT      => 'The MyBB Link Tools plugin',
			))) {
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

function get_limited_func_str($for_not_installed = false) {
	return 'Link Tools functionality will be limited because the Client URL Library (curl) does not appear to be '.($for_not_installed ? 'installed' : 'functioning correctly').'. For more details on this library, please see here: <https://www.php.net/manual/en/book.curl.php>.';
}

<?php

/**
 * In at least one instance (see the Twitter/X entry below), a URL will
 * be redirected differently depending on whether or not a cookie is supplied
 * to its serving web server. This file directs Link Tools as to when it needs
 * to supply one or more cookies to a web server in order for the desired
 * redirect to occur.
 *
 * This file returns an array. The array is keyed by a regular expression
 * against which URLs are tested. The values of each array entry are themselves
 * an array of cookies, with each key being the cookie's name and each value
 * being the cookie's value. If the URL matches the regular expression, then
 * those cookies are sent when Link Tools queries the URL.
 *
 * Note that only the first match is effective: matching stops once a match
 * is found. For this reason, custom (3rd-party) files (see below) are tested
 * first, in ascending alphabetical order by filename, so that they can
 * override the default cookies supplied in this distributed file as below if
 * desired.
 *
 * DO NOT MODIFY THIS FILE AS ITS CONTENTS WILL BE REPLACED ON UPGRADE.
 * Instead, add one or more PHP files under the site-specific-cookies-3rd-party/
 * subdirectory. Note that filenames MUST end in .php (case-insensitive) and
 * the file MUST return an array as below and as described above.
 */

return [
	// For some odd reason, Twitter/X URLs - or at least those of tweets -
	// redirect to https://twitter.com?mx=1 without this cookie, at least
	// as of September 2024.
	'(^(http(s)?://)?(www\\.)?(twitter|x)\\.com($|/))' => ['night_mode' => '0'],
];
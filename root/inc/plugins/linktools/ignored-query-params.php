<?php

/**
 * For purposes of correctly determining which links are identical to
 * one another, Link Tools "normalises" each link (URL). Part of the
 * normalisation process is the alphabetical sorting of any query
 * parameters.
 *
 * Some query parameters, however, are redundant, and are thus removed
 * in the process of normalisation. An example of a redundant query
 * parameter is that added by Facebook to outgoing links:
 *  fbclid=[token].
 * Whether or not this parameter is present, and no matter its value,
 * the same page is shown. Thus, all variants of the URL with and
 * without this query should be treated as equal, which is ensured by
 * this aspect of Link Tools' link (URL) "normalisation" process.
 * 
 * The purpose of this file is to specify those parameters which should
 * be ignored (removed from links) as part of normalisation.
 * See further below for customisation instructions.
 *
 * Supported array entry formats:
 *
 * 1. 'param'
 * 2. 'param=value'
 * 3. 'param' => 'domain'
 * 4. 'param' => array('domain1', 'domain2', ...)
 * 5. 'param=value' => 'domain'
 * 6. 'param=value' => array('domain1', 'domain2', ...)
 *
 * 'domain' can be '*' in which case it matches all domains. This is
 * implicit for formats #1 and #2.
 *
 * DO NOT MODIFY THIS FILE AS ITS CONTENTS WILL BE REPLACED ON UPGRADE.
 * Instead, to add entries to the array, create in the same directory
 * the file ignored-query-params-custom.php with the same format as this
 * file (to ensure the correct format, it is safest to copy this file to
 * it before editing it). Its array will be merged with that of this
 * file. For any key in that custom file that duplicates a key in the
 * array in this file, the value in the custom file overrides that in
 * this file.
 *
 * ENSURE THAT YOUR CUSTOM FILE IS READABLE BY YOUR WEB SERVER, otherwise
 * it will simply be ignored.
 */
return array(
	'fbclid',
	'feature=youtu.be',
	'time_continue' => 'youtube.com',
	't' => 'youtube.com',
	'start' => 'youtube.com',
	'CMP',
	'utm_medium',
	'utm_source',
	'utm_campaign',
	'utm_term',
	'utm_content',
	'akid',
	'email_work_card',
	'showFullText',
);

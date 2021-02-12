<?php

/**
 * Link Tools resolves each link (URL) in each post into its "terminating
 * redirect", which it does by querying the link and determining any HTTP
 * or HTML redirect, as well as any "canonical" link in the returned HTML,
 * and repeating this process until it encounters either a loop or a
 * final URL which is neither an HTTP nor a HTML redirect, and does not
 * have a canonical link which points elsewhere than itself.
 *
 * As this involves a lot of web traffic, Link Tools provides a means to
 * circumvent this lookup process for those links whose terminating
 * redirect can be determined in advance because it is standard, such as
 * the redirect from youtu.be links which terminate in youtube.com video
 * links.
 *
 * The purpose of this file is to specify those links and their mappings.
 * See further below for customisation instructions.
 *
 * The keys of this array represent regular expressions which are matched
 * against links (URLs) during the process of determining the terminating
 * redirect of each link (URL) in each post.
 *
 * If a link (URL) matches the array key, then the terminating redirect for
 * that link (URL) is set to the array's corresponding value (the regular
 * expression's matches can be used as substitutions - e.g., \\1 will be
 * substituted for the first match), and the link is not queried online.
 *
 * DO NOT MODIFY THIS FILE AS ITS CONTENTS WILL BE REPLACED ON UPGRADE.
 * Instead, to add entries to, or to replace entries in, the array, create
 * in the same directory the file auto-term-links-custom.php with the same
 * format as this file (to ensure the correct format, it is safest to copy
 * this file to it before editing it). Its array will be merged with that
 * of this file. For any key in that custom file that duplicates a key in
 * the array in this file, the value in the custom file overrides that in
 * this file.
 *
 * ENSURE THAT YOUR CUSTOM FILE IS READABLE BY YOUR WEB SERVER, otherwise
 * it will simply be ignored.
 */
return array(
	'(^http(?:s)?://(?:www.)?youtube\\.com/watch\\?v=([^&]+)(?:&feature=youtu\\.be)?$)'
					=> 'https://www.youtube.com/watch?v=\\1',
	'(^http(?:s)?://(?:www.)?youtube\\.com/watch\\?(t=([^&]+)&)?v=([^&]+)(?:&feature=youtu\\.be)?$)'
					=> 'https://www.youtube.com/watch?t=\\2&v=\\3',
	'(^http(?:s)?://(?:www.)?youtube\\.com/watch\\?v=([^&]+)(&t=([^&]+))?(?:&feature=youtu\\.be)?$)'
					=> 'https://www.youtube.com/watch?t=\\3&v=\\1',
	'(^http(?:s)?://youtu\\.be/([^\\?/]+)$)'
					=> 'https://www.youtube.com/watch?v=\\1',
	'(^http(?:s)?://youtu\\.be/([^\\?/]+)\\?t=([\\d]+))'
					=> 'https://www.youtube.com/watch?t=\\2&v=\\1',

	'(^http(?:s)?://(?:(?:www|en)\\.)wikipedia.org/wiki/(.*)$)'
					=> 'https://en.wikipedia.org/wiki/\\1',
	'(^http(?:s)?://(?:(?:www|en)\\.)wikipedia.org/w/index.php\\?title=([^&]+)$)'
					=> 'https://en.wikipedia.org/wiki/\\1',
);

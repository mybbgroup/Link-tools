<?php

/**
 *  Part of the Link Tools plugin for MyBB 1.8.
 *  Copyright (C) 2021 Laird Shaw
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

class LinkHelperTwitterStatus extends LinkHelper {
	/**
	 * Support only Twitter status links (as detected after URL
	 * normalisation).
	 */
	static protected $supported_norm_links_regex = '(^http\\(s\\)://twitter.com/.*/status/\\d+$)';

	/**
	 * Set a neutral priority for this Helper (priorities may be negative).
	 */
	static protected $priority = 0;

	/**
	 * A change in this version number signals that link previews generated
	 * by this class should be expired and regenerated on-the-fly when the
	 * relevant Link Tools ACP setting is enabled, potentially because the
	 * template has changed or because the variables supplied to it have
	 * changed.
	 */
	static protected $version = '1.0.0';

	/**
	 * A friendly name for this helper (localisation not supported), to be
	 * shown in the ACP Config's Link Helpers module at:
	 * admin/index.php?module=config-linkhelpers
	 */
	protected $friendly_name = 'Twitter status';

	/**
	 * This Helper does not need the page's content and/or content-type at
	 * all: neither to determine whether it supports the page nor to
	 * generate a preview of the page (all it needs is the page's URL). In
	 * addition, if it is the highest-priority Helper among those which do
	 * not require content, it should be treated as the final Helper; other
	 * Helpers for which content would need to be downloaded to determine
	 * their support/preview should be ignored.
	 */
	static protected $needs_content_for = LinkHelper::NC_NEVER_AND_FINAL;

	/**
	 * The contents of this template are stored to the auto-generated (from
	 * this class's name) template 'linktools_linkpreview_twitterstatus'
	 * where they can be modified as usual via the MyBB ACP's Templates &
	 * Style tools. Those potentially modified template contents are then
	 * pulled out in the call to $this->get_template_for_eval() as in
	 * get_preview_contents() below.
	 */
	protected $template =<<<'EOT'
<div id="link-preview-twitter-{$tweet_id}-{$rand}"></div>
<script>
$('document').ready(function() {
	if (!window.twttr) {
		window.twttr = (function(d, s, id) {
			var js, fjs = d.getElementsByTagName(s)[0],
			t = window.twttr || {};
			if (d.getElementById(id)) return t;
			js = d.createElement(s);
			js.id = id;
			js.src = 'https://platform.twitter.com/widgets.js';
			fjs.parentNode.insertBefore(js, fjs);

			t._e = [];
			t.ready = function(f) {
				t._e.push(f);
			};

			return t;
		}(document, 'script', 'twitter-wjs'));
	}
	twttr.ready(function(twttr) {
		twttr.widgets.createTweet(
			'{$tweet_id}',
			document.getElementById('link-preview-twitter-{$tweet_id}-{$rand}'),
			{
				theme       : 'light',
				conversation: 'none',
				dnt         : true
			}
		);
	});
});
</script>
EOT;

	/**
	 * The heart of the class. Generates the HTML for the link preview.
	 * Does not need (ignores) $content and $content_type.
	 */
	protected function get_preview_contents($link, $content, $content_type) {
		$link_safe = $this->make_safe($link);

		preg_match('(/(\\d+)$)', $link, $matches);
		$tweet_id = $matches[1];
		if (strlen($tweet_id) < 1024) {
			$rand = mt_rand();
			eval('$preview_contents = "'.$this->get_template_for_eval().'";');
		} else	$preview_contents = '';

		return $preview_contents;
	}
}

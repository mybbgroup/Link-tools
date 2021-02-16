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

class LinkHelperYouTubeVideo extends LinkHelper {
	/**
	 * Support only YouTube video links (as detected after URL
	 * normalisation for the terminating link).
	 */
	static protected $supported_norm_links_regex = '(^http\\(s\\)://youtube\\.com/watch\\?)';

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
	protected $friendly_name = 'YouTube video';

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
	 * this class's name) template 'linktools_linkpreview_youtubevideo'
	 * where they can be modified as usual via the MyBB ACP's Templates &
	 * Style tools. Those potentially modified template contents are then
	 * pulled out in the call to $this->get_template_for_eval() as in
	 * get_preview_contents() below.
	 */
	protected $template = '<div style="margin-top: 7px;"><iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/{$youtube_id}?start={$start}" frameborder="0" allowfullscreen></iframe></div>';

	/**
	 * The heart of the class. Generates the HTML for the link preview.
	 * Does not need (ignores) $content and $content_type.
	 */
	protected function get_preview_contents($link, $content, $content_type) {
		$youtube_id = '';
		$start = '';
		$parsed_url = lkt_parse_url($link);
		if (isset($parsed_url['query'])) {
			parse_str($parsed_url['query'], $query);
			if (!empty($query['v'])) {
				$youtube_id = $query['v'];
			}
			if (!empty($query['start'])) {
				$start = $query['start'];
			}
			// 'time_continue' takes precedence over 'start'
			if (!empty($query['time_continue'])) {
				$start = $query['time_continue'];
			}
			// 't' takes precedence over 'time_continue'
			if (!empty($query['t'])) {
				$start = $query['t'];
			}
		}

		if ($youtube_id) {
			if (preg_match('([^\\d]+)', $start)) {
				$start = $this->get_secs_from_timecode($start);
			}
			eval('$preview_contents = "'.$this->get_template_for_eval().'";');
		} else	$preview_contents = '';

		return $preview_contents;
	}

	public function get_secs_from_timecode($tcode) {
		$d = $h = $m = $s = 0;
		$num = '';
		foreach (str_split($tcode) as $c) {
			if (ctype_digit($c)) {
				$num .= $c;
			} else {
				if ($c == 'd') {
					$d = $num;
				} else if ($c == 'h') {
					$h = $num;
				} else if ($c == 'm') {
					$m = $num;
				} else if ($c == 's') {
					$s = $num;
				}
				$num = '';
			}
		}

		$ret = $d;
		$ret = $ret * 24 + $h;
		$ret = $ret * 60 + $m;
		$ret = $ret * 60 + $s;

		return $ret;
	}

}

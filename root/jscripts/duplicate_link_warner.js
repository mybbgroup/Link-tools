var DLW = {
	// Should semantically match the equivalent variable in ../inc/plugins/duplicate_link_warner.php
	valid_schemes: ['http', 'https', 'ftp', 'sftp', ''],

	matching_posts: {},
	checked_urls: {},
	further_results: false,

	link: document.createElement('a'),

	// Based on the corresponding function in ../inc/plugins/duplicate_link_warner.php
	extract_url_from_mycode_tag: function(text, urls, re, indexes_to_use = [1]) {
		var match;
		while ((match = re.exec(text)) !== null) {
			var url = '';
			for (i = 0; i < indexes_to_use.length; i++) {
				var index = indexes_to_use[i];
				url += match[index];
			}
			urls = DLW.test_add_url(url, urls);
		}
		text = text.replace(re, ' ');

		return [urls, text];
	},

	// Based on the corresponding function in ../inc/plugins/duplicate_link_warner.php
	has_valid_scheme: function(url) {
		var scheme = /^[a-z]+(?=:)/.exec(url);
		scheme = (scheme === null ? '' : scheme[0]);
		for (var i = 0; i < DLW.valid_schemes.length; i++) {
			if (scheme == DLW.valid_schemes[i]) {
				return true;
			}
		}

		return false;
	},

	// Based on the corresponding function in ../inc/plugins/duplicate_link_warner.php
	test_add_url: function(url, urls) {
		if (DLW.has_valid_scheme(url) && $.inArray(url, urls) <= -1) {
			urls.push(url);
		}

		return urls;
	},

	// From https://stackoverflow.com/a/6969486
	escapeRegExp: function(string) {
		return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
	},

	// Based on the corresponding function in ../inc/plugins/duplicate_link_warner.php
	extract_bare_urls: function(text, urls) {
		var re1, re2, match;
		var supports_unicode = true;
		var urls_matched = [];
		text = ' ' + text;
		var text_new = text;

		re1_noflags = '\\[([^\\]]+)(?:=[^\\]]+)?\\](http|https|ftp|news|irc|ircs|irc6){1}(://)([^\\/"\\s\\<\\[\\.]+\\.([^\\/"\\s\\<\\[\\.]+\\.)*[\\w]+(:[0-9]+)?(/([^"\\s<\\[]|\\[\\])*)?)\\[/\\1\\]';
		re2_noflags = '([\\s\\(\\)\\[\\>])(http|https|ftp|news|irc|ircs|irc6){1}(://)([^\\/\\"\\s\\<\\[\\.]+\.([^\\/\\"\\s\\<\\[\\.]+\\.)*[\\w]+(:[0-9]+)?(/([^\\"\\s<\\[]|\\[\\])*)?([\\w\\/\\)]))';
		re3_noflags = '\\[([^\\]]+)(?:=[^\\]]+)?\\](www|ftp)(\.)(([^\\/"\\s\\<\\[\\.]+\\.)*[\\w]+(:[0-9]+)?(/([^"\\s<\\[]|\\[\\])*)?)\\[/\\1\\]';
		re4_noflags = '([\\s\\(\\)\\[\\>])(www|ftp)(\\.)(([^\\/\\"\\s\\<\\[\\.]+\\.)*[\\w]+(:[0-9]+)?(/([^\\"\\s<\\[]|\\[\\])*)?([\\w\\/\\)]))';
		try {
			// First, try the Unicode-aware regex.
			re1 = new RegExp(re1_noflags, 'giu');
			re2 = new RegExp(re2_noflags, 'giu');
			re3 = new RegExp(re3_noflags, 'giu');
			re4 = new RegExp(re4_noflags, 'giu');
		} catch (err) {
			supports_unicode = false;
			re1 = new RegExp(re1_noflags, 'gi');
			re2 = new RegExp(re2_noflags, 'gi');
			re3 = new RegExp(re3_noflags, 'gi');
			re4 = new RegExp(re4_noflags, 'gi');
		}

		var r = [re1, re2, re3];
		for (var i = 0; i < r.length; i++) {
			var re = r[i];
			while ((match = re.exec(text)) !== null) {
				url = match[2] + match[3] + DLW.strip_unmatched_closing_parens(match[4]);
				urls_matched.push(url);
				urls = DLW.test_add_url(url, urls);
				// Blank out the matched URLs.
				var start = re.lastIndex - match[0].length + match[1].length;
				text_new = text_new.substring(0, start) + ' '.repeat(url.length) + text_new.substring(start + url.length);
			}
		}

		text_new = text_new.substring(1);

		return [urls, text_new];
	},

	substr_count: function(string, substr) {
		var res = string.match(new RegExp(DLW.escapeRegExp(substr), 'g'));
		return res ? res.length : 0;
	},

	// Based on the corresponding function in ../inc/plugins/duplicate_link_warner.php
	strip_unmatched_closing_parens: function(url) {
		// Allow links like http://en.wikipedia.org/wiki/PHP_(disambiguation) but detect mismatching braces
		while (url.substring(url.length - 1) == ')') {
			if (DLW.substr_count(url, ')') > DLW.substr_count(url, '(')) {
				url = url.substring(0, url.length - 1);
			} else {
				break;
			}

			// Example: ([...] http://en.wikipedia.org/Example_(disambiguation).)
			var last_char = url.substring(url.length - 1);
			while (last_char == '.' || last_char == ',' || last_char == '?' || last_char == '!') {
				url = url.substring(0, url.length - 1);
				last_char = url.substr(url.length - 1);
			}
		}

		return url;
	},

	// Based on the corresponding function in ../inc/plugins/duplicate_link_warner.php
	extract_urls: function(text) {
		var urls = [];

		// First, strip out all [img] tags.
		// [img] tag regexes from postParser::parse_mycode() in ../inc/class_parser.php.
		text = text.replace(/\[img\](\r\n?|\n?)(https?:\/\/([^<>\"']+?))\[\/img\]/i, ' ');
		text = text.replace(/\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?:\/\/([^<>\"']+?))\[\/img\]/i, ' ');
		text = text.replace(/\[img align=(left|right)\](\r\n?|\n?)(https?:\/\/([^<>\"']+?))\[\/img\]/i, ' ');
		text = text.replace(/\[img=([1-9][0-9]*)x([1-9][0-9]*) align=(left|right)\](\r\n?|\n?)(https?:\/\/([^<>\"']+?))\[\/img\]/i, ' ');

		// [url] tag regexes from postParser::cache_mycode() in ../inc/class_parser.php.
		var res = DLW.extract_url_from_mycode_tag(text, urls, /\[url\]((?!javascript)[a-z]+?:\/\/)([^\r\n\"<]+?)\[\/url\]/gi, [1, 2]);
		urls = res[0];
		text = res[1];
		res = DLW.extract_url_from_mycode_tag(text, urls, /\[url\]((?!javascript:)[^\r\n\"<]+?)\[\/url\]/gi, [1]);
		urls = res[0];
		text = res[1];
		res = DLW.extract_url_from_mycode_tag(text, urls, /\[url=((?!javascript)[a-z]+?:\/\/)([^\r\n\"<]+?)\](.+?)\[\/url\]/gi, [1, 2]);
		urls = res[0];
		text = res[1];
		res = DLW.extract_url_from_mycode_tag(text, urls, /\[url=((?!javascript:)[^\r\n\"<]+?)\](.+?)\[\/url\]/gi, [1]);
		urls = res[0];
		text = res[1];

		// [video] tag regex from postParser::parse_mycode() in ../inc/class_parser.php.
		res = DLW.extract_url_from_mycode_tag(text, urls, /\[video=(.*?)\](.*?)\[\/video\]/gi, [2]);
		urls = res[0];
		text = res[1];

		res = DLW.extract_bare_urls(text, urls);
		urls = res[0];
		text = res[1];

		return urls;
	},

	mybbeditor_valuechanged_hook: function(e, previously_dismissed = {}) {
		// Find any new URLs in the edit pane contents, in the process adding them to the DLW.checked_urls list -
		// the latter because:
		// #1 The subsequent code section assumes that this has been done, and,
		// #2 Adding them before the async request to the server rather than after ensures
		//    that while the async request executes, another request is not sent for the
		//    same URL(s).
		var val = MyBBEditor.val();
		var urls = DLW.extract_urls(val);
		if (!urls) urls = []; // If null, convert to empty array so .length works.
		var new_urls = [];
		var old_urls = [];
		for (var i = 0; i < urls.length; i++) {
			var already_checked = DLW.checked_urls[urls[i]];
			if (!already_checked) {
				new_urls.push(urls[i]);
				DLW.checked_urls[urls[i]] = {'post_urls': {}, 'active': true};
			} else {
				old_urls.push(urls[i]);
				console.debug('Already checked <'+urls[i]+'>.');
			}
			DLW.checked_urls[urls[i]].active = true;
		}

		// Reinsert as required into the matching_urls and undismissed_urls lists of DLW.matching_posts entries
		// any URLs that were deleted from the edit pane contents and have now been reinserted (we don't
		// requery the server for those URLs).
		var readded_undismissed = false;
		for (var i = 0; i < old_urls.length; i++) {
			if (DLW.checked_urls[old_urls[i]] && DLW.checked_urls[old_urls[i]].active) {
				for (var pid in DLW.checked_urls[old_urls[i]].post_urls) {
					DLW.checked_urls[old_urls[i]].post_urls[pid];
					if ($.inArray(old_urls[i], DLW.matching_posts[pid].matching_urls) <= -1) {
						console.debug('Adding <'+old_urls[i]+'> back onto the matching_urls list of the post with id ' +
						pid + ' as it has been reinserted into the edit pane contents. Also adding <'+DLW.checked_urls[old_urls[i]].post_urls[pid]+'> correspondingly back onto the matching_urls_in_post list of that same post.');
						DLW.matching_posts[pid].matching_urls.push(old_urls[i]);
						DLW.matching_posts[pid].matching_urls_in_post.push(DLW.checked_urls[old_urls[i]].post_urls[pid]);
					}
					if ($.inArray(old_urls[i], DLW.matching_posts[pid].dismissed_urls) <= -1
					    &&
					    $.inArray(old_urls[i], DLW.matching_posts[pid].undismissed_urls) <= -1
					) {
						console.debug('Adding <'+old_urls[i]+'> back onto the undismissed_urls list of the post with id ' +
						              DLW.matching_posts[pid].pid +
						              ' as it has been reinserted into the edit pane contents and not yet dismissed for this post.');
						DLW.matching_posts[pid].undismissed_urls.push(old_urls[i]);
						readded_undismissed = true;
					}
				}
			}
		}

		// Remove from the matching_urls and undismissed_urls arrays of all existing DLW.matching_posts
		// any URLs that the user has deleted or edited out of the edit pane contents
		// since last check.
		var removed_undismissed = false;
		for (var checked_url in DLW.checked_urls) {
			if ($.inArray(checked_url, urls) <= -1) {
				DLW.checked_urls[checked_url].active = false;
				for (var pid in DLW.matching_posts) {
					do {
						var idx = DLW.matching_posts[pid].matching_urls.indexOf(checked_url);
						if (idx >= 0) {
							console.debug('Removing <' + checked_url + '> from the matching_urls list ' +
								'of the post with id ' + pid +
								' because it appears to no longer exist in the edit pane contents.');
							DLW.matching_posts[pid].matching_urls.splice(idx, 1);
						}
					} while (idx >= 0);
					do {
						idx = DLW.matching_posts[pid].undismissed_urls.indexOf(checked_url);
						if (idx >= 0) {
							console.debug('Removing <' + checked_url + '> from the undismissed_urls list ' +
								'of the post with id ' + pid +
								' because it appears to no longer exist in the edit pane contents.');
							DLW.matching_posts[pid].undismissed_urls.splice(idx, 1);
							removed_undismissed = true;
						}
					} while (idx >= 0);
				}
			}
		}

		// If new, unchecked URLs have been edited into the post, then query the server for matching posts.
		if (new_urls.length > 0) {
			var data_to_send = {};
			data_to_send.urls  = new_urls;
			data_to_send.pids  = [];
			data_to_send.edtms = [];
			for (var pid in DLW.matching_posts) {
				data_to_send.pids .push(pid);
				data_to_send.edtms.push(DLW.matching_posts[pid].edittime);
			}

			// Let the server know which posts we've already got and their last edit time
			// so that it doesn't return the contents (or any other redundant properties) of
			// any of those posts which match and which have not been edited since last check.
			// Use the "post" method rather than the "get" method because it supports
			// a greater maximum limit on the data payload.
			$.post(rootpath + '/duplicate_link_warner.php', data_to_send, function(data) {
				if (data.error) {
					console.debug('Server responded with this error message: "' + data.error + '"');
					return;
				}
				if (!data.matching_posts) {
					console.debug('Server responded that no posts match the new links.');
					if (removed_undismissed || readded_undismissed) {
						DLW.warn_summ_box(false);
					}
					return;
				}
				if (data.further_results) {
					DLW.further_results = true;
				}
				var urls_in_edit_pane = new_urls.concat(old_urls);
				var added_matching_post = false;
				for (var pid_in in data.matching_posts) {
					for (var k = 0; k < data.matching_posts[pid_in].matching_urls.length; k++) {
						var url_res         = data.matching_posts[pid_in].matching_urls[k];
						var url_res_in_post = data.matching_posts[pid_in].matching_urls_in_post[k];
						DLW.checked_urls[url_res].post_urls[pid_in] = url_res_in_post;
					}

					var already_downloaded = false;
					for (var pid in DLW.matching_posts) {
						if (pid_in == pid) {
							already_downloaded = true;
							var has_been_edited = (data.matching_posts[pid_in].edittime != DLW.matching_posts[pid].edittime);
							console.debug('The post with id ' + pid + ' has ' + (has_been_edited ? '' : 'NOT ') + 'been edited since we last queried the server.');
							if (has_been_edited) {
								// Post has been edited since last checking - update in our local array
								// the (some only potentially) changed properties of the post.
								DLW.matching_posts[pid].edittime = data.matching_posts[pid_in].edittime;
								DLW.matching_posts[pid].subject = data.matching_posts[pid_in].subject;
								DLW.matching_posts[pid].message = data.matching_posts[pid_in].message;
								var edited_in_urls = [];
							}

							DLW.matching_posts[pid].matching_urls = DLW.matching_posts[pid].matching_urls.concat(data.matching_posts[pid_in].matching_urls);
							DLW.matching_posts[pid].matching_urls_in_post = DLW.matching_posts[pid].matching_urls_in_post.concat(data.matching_posts[pid_in].matching_urls_in_post);

							// If necessary, add any URLs to the post's undismissed_urls and matching_urls lists.
							for (var k = 0; k < data.matching_posts[pid_in].matching_urls.length; k++) {
								var url = data.matching_posts[pid_in].matching_urls[k];
								if ($.inArray(url, DLW.matching_posts[pid].dismissed_urls) <= -1
								    &&
								    $.inArray(url, DLW.matching_posts[pid].undismissed_urls) <= -1
								) {
									DLW.matching_posts[pid].undismissed_urls.push(url);
									console.debug('(1) Adding <' + url + '> to the undismissed_urls list for the post with pid ' + DLW.matching_posts[pid].pid);
								}
							}
						}
					}
					if (!already_downloaded) {
						var num_matching_urls = data.matching_posts[pid_in].matching_urls.length;
						if (typeof previously_dismissed === 'object' && previously_dismissed[pid_in] && data.matching_posts[pid_in]) {
							DLW.matching_posts[pid_in] = data.matching_posts[pid_in];
							DLW.matching_posts[pid_in].undismissed_urls = [];
							DLW.matching_posts[pid_in].dismissed_urls   = previously_dismissed[pid_in].slice();
							var was_dismissed = true;
							for (var idx in data.matching_posts[pid_in].matching_urls) {
								var url = data.matching_posts[pid_in].matching_urls[idx];
								if (previously_dismissed[pid_in].indexOf(url) < 0) {
									DLW.matching_posts[pid_in].matching_urls.push(url);
									DLW.matching_posts[pid_in].undismissed_urls.push(url);
									added_matching_post = true;
									was_dismissed = false;
									break;
								}
							}
							if (was_dismissed) {
								num_matching_urls--;
								DLW.update_hidden_input();
								DLW.show_undismiss_button();
								
							}
						} else {
							data.matching_posts[pid_in].undismissed_urls = data.matching_posts[pid_in].matching_urls.slice();
							data.matching_posts[pid_in].dismissed_urls = [];
							DLW.matching_posts[pid_in] = data.matching_posts[pid_in];
							added_matching_post = true;
						}
						if (added_matching_post) console.debug('Added post with pid ' + pid_in + ' and ' + num_matching_urls + ' matching URLs.');
					}
				}

				DLW.warn_summ_box(added_matching_post);
			}, 'json');
		} else {
			console.debug('Edit pane contents changed but no new URLs found: not querying the server.');
			if (removed_undismissed || readded_undismissed) {
				DLW.warn_summ_box(false);
			}
		}
	},

	get_summ_box_contents: function(ext, enc_summ = true) {
		var non_op_post_ids = [];
		var op_post_ids = [];
		var urls_uniq = [];
		for (var pid in DLW.matching_posts) {
			if (DLW.matching_posts[pid].undismissed_urls.length > 0) {
				if (pid == DLW.matching_posts[pid].firstpost) {
					op_post_ids.push(pid);
				} else	non_op_post_ids.push(pid);
		    
				var undis_urls = DLW.matching_posts[pid].undismissed_urls;
				for (var k = 0; k < undis_urls.length; k++) {
					if ($.inArray(undis_urls[k], urls_uniq) <= -1) {
						urls_uniq.push(undis_urls[k]);
					}
				}
			}
		}

		var cnt = op_post_ids.length + non_op_post_ids.length;
		var msg = '';
		if (cnt > 0) {
			if (enc_summ) msg += '<div id="dlw-warn-summ-box-contents-summ">';
			if (cnt == 1) {
				if (op_post_ids.length == 1) {
					msg += dlw_exist_open_post_contains;
				} else {
					msg += dlw_exist_post_contains;
				}
			} else {
				if (DLW.further_results) {
					msg += dlw_more_than;
				}
				if (op_post_ids.length == cnt) {
					msg += dlw_x_exist_open_post_contain.replace('{1}', cnt);
				} else {
					msg += dlw_x_exist_posts_contain    .replace('{1}', cnt);
				}
			}
			if (urls_uniq.length > 1) {
				msg += dlw_x_of_urls_added.replace('{1}', urls_uniq.length);
			} else {
				msg += dlw_a_url_added;
			}
			if (op_post_ids.length > 0 && op_post_ids.length != cnt) {
				if (op_post_ids.length == 1) {
					msg += dlw_one_is_an_opening_post;
				} else {
					msg += dlw_x_are_opening_posts.replace('{1}', op_post_ids.length);
				}
			}
			if (enc_summ) msg += '</div>';
			if (ext) {
				var radius = 10;
				msg += '<div id="dlw-extra-info" style="max-height: '+($(window).height() - 50)+'px;">';
				if (DLW.further_results) {
					var urls_enc = '';
					for (var checked_url in DLW.checked_urls) {
						if (DLW.checked_urls[checked_url].active) {
							if (urls_enc) urls_enc += ',';
							urls_enc += encodeURIComponent(checked_url);
						}
					}
					var div_open = '<div class="further-results">';
					var url_esc = DLW.htmlspecialchars('dlw_search.php?urls='+urls_enc+'&resulttype=posts');
					var further_results_below = dlw_further_results_below.replace('{1}', cnt);
					further_results_below     = further_results_below    .replace('{2}', url_esc);
					further_results_below     = div_open+further_results_below+'</div>';
					var further_results_above = dlw_further_results_above.replace('{1}', cnt);
					further_results_above     = further_results_above    .replace('{2}', url_esc)+'</div>';
					further_results_above     = div_open+further_results_above+'</div>';
					msg += further_results_below;
				}
				var ids = op_post_ids.concat(non_op_post_ids);
				for (var i = 0; i < ids.length; i++) {
					var pid = ids[i];
					if (DLW.matching_posts[pid].undismissed_urls.length > 0) {
						var post = DLW.matching_posts[pid];
						var is_first_post = (post['firstpost'] == post['pid']);
						msg += '<div id="dlw-post-outer-'+pid+'">'+"\n";
						msg += '<div>'+post['flinks']+'<br />'+post['nav_bit_img']+post['tlink']+'</div>'+"\n";
						msg += '<div>'+dlw_msg_started_by+' '+post['ulink_t']+', '+post['dtlink_t']+'</div>'+"\n";
						msg += '<div>'+(is_first_post ? '<span class="first-post">'+dlw_msg_opening_post+'</span>' : dlw_msg_non_opening_post+' '+post['plink']+' '+dlw_msg_posted_by+' '+post['ulink_p']+', '+post['dtlink_p'])+'</div>'+"\n";
						msg += '<button id="dlw-btn-dismiss-'+pid+'" type="button" class="btn-dismiss">'+dlw_dismiss_warn_for_post+'</button>';
						msg += '<div>'+(post.undismissed_urls.length == 1 ? dlw_msg_matching_url_singular : dlw_msg_matching_urls_plural)+"\n";
						msg += '<ul class="url-list">'+"\n";
						for (var j in post.undismissed_urls) {
							var url = post.undismissed_urls[j];
							var url_esc = DLW.htmlspecialchars(url);
							var link = '<a href="'+url_esc+'">'+url_esc+'</a>';
							msg += '<li class="url-list-item">';
							var idx = $.inArray(url, post.matching_urls);
							if (idx >= 0 && post.matching_urls_in_post[idx] != url) {
								var url2 = post.matching_urls_in_post[idx];
								var url2_esc = DLW.htmlspecialchars(url2);
								var link2 = '<a href="'+url2_esc+'">'+url2_esc+'</a>';
								var tmp = dlw_msg_url1_as_url2.replace('{1}', link);
								var tmp = tmp.replace('{2}', link2);
								msg += tmp;
							} else	msg += link;
							msg += '</li>'+"\n";
						}
						msg += '</ul></div>'+"\n";
						msg += '<div id="dlw-post-inner-'+pid+'" class="dlw-post-inner">'+post['message']+'</div>'+"\n";
						msg += '</div>'+"\n";
					}
				}
				if (DLW.further_results) {
					msg += further_results_above;
				}
				msg += '</div>';
			}
		}

		return msg; // Empty string if there are no matching posts.
	},

	add_dismiss_btn_event_handlers: function() {
		for (var pid in DLW.matching_posts) {
			if (DLW.matching_posts[pid].undismissed_urls.length > 0) {
				$('#dlw-btn-dismiss-'+pid).bind('click', function(e) {
					var id = $(this).prop('id');
					var pid = id.substring(id.lastIndexOf('-') + 1);
					console.debug('Dismissing post with id '+pid);
					DLW.matching_posts[pid].dismissed_urls = DLW.matching_posts[pid].dismissed_urls.concat(DLW.matching_posts[pid].undismissed_urls);
					DLW.matching_posts[pid].undismissed_urls = [];
					$('#dlw-post-outer-'+pid).remove();
					DLW.show_undismiss_button();

					DLW.update_hidden_input();

					var empty = true;
					for (var pid2 in DLW.matching_posts) {
						if (DLW.matching_posts[pid2].undismissed_urls.length > 0) {
							empty = false;
							break;
						}
					}
					if (empty) {
						$('#dlw-warn-summ-box').remove();
					} else {
						$('#dlw-warn-summ-box-contents-summ').html(DLW.get_summ_box_contents(false, false))
					}
				});
			}
		}
	},

	flash_entity: function(entity, flash_interval, flash_count) {
		if (!flash_interval) flash_interval = 200;
		if (!flash_count) flash_count = 2;
		while (flash_count-- > 0) {
			entity.fadeOut(flash_interval).fadeIn(flash_interval);
		}
	},

	warn_summ_box: function(flash) {
		if ($('#dlw-cbx-do-warn').length && !($('#dlw-cbx-do-warn').prop('checked'))) {
			return;
		}

		var extra = false;
		if ($('#dlw-extra-info').length) {
			extra = true;
		}
		var msg = DLW.get_summ_box_contents(extra);
		if (!msg) {
			if ($('#dlw-warn-summ-box').length) {
				$('#dlw-warn-summ-box').remove();
			}
		} else if ($('#dlw-warn-summ-box').length) {
			$('#dlw-warn-summ-box-contents').html(msg);
			if (extra) DLW.add_dismiss_btn_event_handlers();
			if (flash) DLW.flash_entity($('#dlw-warn-summ-box-contents'));
		} else {
			var radius = 10;
			var sc_container = $('.sceditor-container:first');
			var summ_box_width = MyBBEditor.width() - 2 * radius + parseInt(sc_container.css('padding-left')) + parseInt(sc_container.css('padding-right'));
			$('body').append(
				'<div id="dlw-warn-summ-box"></div>'
			);
			$('#dlw-warn-summ-box').css({
				'top'                  : (flash ? ($(window).height()/2) : 0) + 'px',
				'left'                 : parseInt(sc_container.offset().left) + 'px',
				'width'                : summ_box_width,
				'max-height'           : $(window).height() + 'px',
			       
			})
			.append('<button id="dlw-btn-dismiss-summ" type="button">'+'Dismiss all warnings'+'</button><button id="dlw-btn-details-summ-on" type="button">'+dlw_show_more+'</button><button id="dlw-btn-details-summ-off" type="button">'+dlw_show_less+'</button>')
			.append('<span id="dlw-warn-summ-box-contents"></span>');
			$('#dlw-warn-summ-box-contents').html(msg);
			if (flash) {
				DLW.flash_entity($('#dlw-warn-summ-box-contents'), 200, 6);
				$('#dlw-warn-summ-box').delay(2000).animate({'top': 0}, 1500);
			}
			// Bind these event after the animation in case the mouse cursor accidentally
			// enters the "Show more" button during the animation.
			$('#dlw-btn-details-summ-off').css('visibility', 'hidden');
			$('#dlw-btn-details-summ-on').bind('click', function() {
				$('#dlw-btn-details-summ-off').css('visibility', 'visible');
				$(this).css('visibility', 'hidden');
				$('#dlw-warn-summ-box-contents').html(DLW.get_summ_box_contents(true));
				DLW.add_dismiss_btn_event_handlers();
			})
			$('#dlw-btn-details-summ-off').bind('click', function() {
				$('#dlw-btn-details-summ-on').css('visibility', 'visible');
				$(this).css('visibility', 'hidden');
				$('#dlw-warn-summ-box-contents').html(DLW.get_summ_box_contents(false));
			});
			$('#dlw-btn-dismiss-summ').bind('click', function(e) {
				for (var pid in DLW.matching_posts) {
					var post = DLW.matching_posts[pid];
					post.dismissed_urls = post.dismissed_urls.concat(post.undismissed_urls);
					post.undismissed_urls = [];
				}
				DLW.update_hidden_input();
				DLW.show_undismiss_button();
				$('#dlw-warn-summ-box').remove();
			});
		}
	},

	update_hidden_input: function() {
		var dlw_dismissed = {};
		for (var pid in DLW.matching_posts) {
			var post = DLW.matching_posts[pid];
			if (post.dismissed_urls.length > 0) {
				dlw_dismissed[pid] = post.dismissed_urls;
			}
		}
		$('#dlw_dismissed').val(JSON.stringify(dlw_dismissed));
		console.debug('Set hidden dlw_dismissed form input value to: ' + $('#dlw_dismissed').val());
	},

	show_undismiss_button: function() {
		if ($('#dlw-btn-undismiss').length) {
			$('#dlw-btn-undismiss').show();
		} else if ($('#dlw-msg-sidebar-div').length) {
			$('#dlw-msg-sidebar-div').append('<button id="dlw-btn-undismiss" type="button">'+dlw_undismiss_all_warns+'</button>');
			$('#dlw-btn-undismiss').bind('click', function(e) {
				for (var pid in DLW.matching_posts) {
					DLW.matching_posts[pid].undismissed_urls = DLW.matching_posts[pid].matching_urls.slice();
					DLW.matching_posts[pid].dismissed_urls = [];
					$(this).hide();
				}
				DLW.update_hidden_input();
				DLW.warn_summ_box(false);
			});
			DLW.flash_entity($('#dlw-btn-undismiss'));
		}
	},

	have_dismissed: function() {
		for (var pid in DLW.matching_posts) {
			if (DLW.matching_posts[pid].dismissed_urls.length > 0) {
				return true;
			}
		}

		return false;
	},

	// From <http://www.falsepositives.com/index.php/2009/12/01/javascript-function-to-get-the-intersect-of-2-arrays/>
	get_intersect: function(arr1, arr2) {
		var r = [], o = {}, l = arr2.length, i, v;
		for (var i = 0; i < l; i++) {
			o[arr2[i]] = true;
		}
		l = arr1.length;
		for (var i = 0; i < l; i++) {
			v = arr1[i];
			if (v in o) {
				r.push(v);
			}
		}
		return r;
	},

	// From https://stackoverflow.com/a/4835406
	htmlspecialchars: function(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		
		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	},

	init: function() {
		console.debug('Entered DLW initialisation function ...');

		var dlw_sidebar_div = $('#dlw-msg-sidebar-div');
		if (dlw_sidebar_div.length) {
			dlw_sidebar_div.append('<input type="checkbox" id="dlw-cbx-do-warn" name="dlw-cbx-do-warn" checked="checked" /> <span title="' + dlw_title_warn_about_links + '">' + dlw_warn_about_links + '</span>');
			$('#dlw-cbx-do-warn').bind('click', function(e) {
				if (!$('#dlw-cbx-do-warn').prop('checked')) {
					if ($('#dlw-warn-summ-box').length) {
						$('#dlw-warn-summ-box').remove();
					}
					$('#dlw-btn-undismiss').hide();
				} else {
					if (DLW.have_dismissed()) {
						$('#dlw-btn-undismiss').show();
					}
					DLW.warn_summ_box(false);
				}
			});
		} else	console.debug('Could not locate the duplicate link warner sidebar div. The "Warn about duplicate links" checkbox and "Undismiss all duplicate link warnings" button will be unavailable.');

		$('<input>').attr({
			type: 'hidden',
			id  : 'dlw_dismissed',
			name: 'dlw_dismissed',
			value: ''
		}).appendTo($('form[name="input"]'));

		MyBBEditor.valueChanged(DLW.mybbeditor_valuechanged_hook);
		DLW.mybbeditor_valuechanged_hook(null, dlw_previously_dismissed);

		console.debug('...leaving DLW initialisation function.');
	}
};

$('document').ready(function() {
	console.debug('Calling DLW initialisation function ...');
	DLW.init();
	console.debug('...returned from DLW initialisation function.');
});

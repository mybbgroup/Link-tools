var DLW = {
	// Should semantically match the equivalent variable in ../inc/plugins/duplicate_link_warner.php
	valid_schemes: ['http', 'https', 'ftp', 'sftp'],

	matching_posts: {},
	checked_urls: [],

	link: document.createElement('a'),

	mybbeditor_valuechanged_hook: function(e, previously_dismissed = {}) {
		// Find any new URLs in the edit pane contents, in the process adding them to the DLW.checked_urls list -
		// the latter because:
		// #1 The subsequent code section assumes that this has been done, and,
		// #2 Adding them before the async request to the server rather than after ensures
		//    that while the async request executes, another request is not sent for the
		//    same URL(s).
		var val = MyBBEditor.val();
		var urls = val.match(DLW.uriRegex);
		if (!urls) urls = []; // If null, convert to empty array so .length works.
		var new_urls = [];
		var old_urls = [];
		for (var i = 0; i < urls.length; i++) {
			DLW.link.href = urls[i];
			for (var j = 0; j < DLW.valid_schemes.length; j++) {
				if (DLW.link.protocol == DLW.valid_schemes[j]+':') {
					var already_checked = ($.inArray(urls[i], DLW.checked_urls) >= 0);
					if (!already_checked) {
						new_urls.push(urls[i]);
						DLW.checked_urls.push(urls[i]);
						break;
					} else {
						old_urls.push(urls[i]);
						console.debug('Already checked <'+urls[i]+'>.');
					}
				}
			}
		}

		// Reinsert as required into the matching_urls and undismissed_urls lists of DLW.matching_posts entries
		// any URLs that were deleted from the edit pane contents and have now been reinserted (we don't
		// requery the server for those URLs).
		var readded_undismissed = false;
		for (var i = 0; i < old_urls.length; i++) {
			for (var pid in DLW.matching_posts) {
				if ($.inArray(old_urls[i], DLW.matching_posts[pid].all_urls) >= 0) {
					if ($.inArray(old_urls[i], DLW.matching_posts[pid].matching_urls) <= -1) {
						console.debug('Adding <'+old_urls[i]+'> back onto the matching_urls list of the post with id ' +
						pid + ' as it has been reinserted into the edit pane contents.');
						DLW.matching_posts[pid].matching_urls.push(old_urls[i]);
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
		for (var i = 0; i < DLW.checked_urls.length; i++) {
			if ($.inArray(DLW.checked_urls[i], urls) <= -1) {
				for (var pid in DLW.matching_posts) {
					var idx = DLW.matching_posts[pid].matching_urls.indexOf(DLW.checked_urls[i]);
					if (idx >= 0) {
						console.debug('Removing <' + DLW.checked_urls[i] + '> from the matching_urls list ' +
						'of the post with id ' + pid +
						' because it appears to no longer exist in the edit pane contents.');
						DLW.matching_posts[pid].matching_urls.splice(idx, 1);
					}
					idx = DLW.matching_posts[pid].undismissed_urls.indexOf(DLW.checked_urls[i]);
					if (idx >= 0) {
						console.debug('Removing <' + DLW.checked_urls[i] + '> from the undismissed_urls list ' +
						              'of the post with id ' + pid +
						              ' because it appears to no longer exist in the edit pane contents.');
						DLW.matching_posts[pid].undismissed_urls.splice(idx, 1);
						removed_undismissed = true;
					}
				}
			}
		}

		// If new, unchecked URLs have been edited into the post, then query the server for matching posts.
		if (new_urls.length > 0) {
			var data_to_send = {};
			data_to_send.url = new_urls;
			data_to_send.pid = [];
			data_to_send.edtm = [];
			for (var i = 0; i < DLW.matching_posts.length; i++) {
				data_to_send.pid [i] = DLW.matching_posts[i].pid;
				data_to_send.edtm[i] =  DLW.matching_posts[i].edittime;
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
				var urls_in_edit_pane = new_urls.concat(old_urls);
				var added_matching_post = false;
				for (var pid_in in data.matching_posts) {
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
								for (var i = 0; i < data.matching_posts[pid_in].all_urls.length; i++) {
									if ($.inArray(data.matching_posts[pid_in].all_urls[i], DLW.matching_posts[pid].all_urls) <= -1) {
										edited_in_urls.push(data.matching_posts[pid_in].all_urls[i]);
									}
								}
								DLW.matching_posts[pid].all_urls = data.matching_posts[pid_in].all_urls.slice();
								DLW.matching_posts[pid].matching_urls = DLW.matching_posts[pid].matching_urls.concat(data.matching_posts[pid_in].matching_urls);
							}

							// If necessary, add any URLs to the post's undismissed_urls and matching_urls lists.
							for (var k = 0; k < DLW.matching_posts[pid].all_urls.length; k++) {
								var url = DLW.matching_posts[pid].all_urls[k];
								if ($.inArray(url, urls_in_edit_pane) >= 0) {
									if ($.inArray(url, DLW.matching_posts[pid].dismissed_urls) <= -1
									    &&
									    $.inArray(url, DLW.matching_posts[pid].undismissed_urls) <= -1
									    &&
									    // Don't add a URL to the undismissed_urls list of a post
									    // that has been edited since last checking unless there are
									    // no URLs in the dismissed_urls list (i.e., the pre-edited
									    // post has not already been "checked" by the user) or
									    // the URL was edited in during the edit (i.e. any
									    // user-"checked" version of the post did not already
									    // contain the URL - it was added since last user "check").
									    //
									    // "Check" and "checked" are quoted because posts can be
									    // bulk-dismissed, in which case the user might not have
									    // actually sighted this individual post's contents.
									    (!has_been_edited
									     || DLW.matching_posts[pid].dismissed_urls.length <= 0
									     || $.inArray(url, edited_in_urls) >= 0)
									) {
										DLW.matching_posts[pid].undismissed_urls.push(url);
										console.debug('Adding <' + url + '> to the undismissed_urls list for the post with pid ' + DLW.matching_posts[pid].pid);
									}
									if ($.inArray(url, DLW.matching_posts[pid].matching_urls) <= -1) {
										DLW.matching_posts[pid].matching_urls.push(url);
										console.debug('Adding <' + url + '> to the matching_urls list for the post with pid ' + pid);
									}
								}
							}
							break;
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
			/** @todo Move constant strings such as the below into the language file. */
			if (cnt == 1) {
				if (op_post_ids.length == 1) {
					msg += 'An existing <strong>opening post</strong> contains';
				} else {
					msg += 'An existing post contains';
				}
			} else {
				if (op_post_ids.length == cnt) {
					msg += cnt + ' existing <strong>opening posts</strong> contain';
				} else {
					msg += cnt + ' existing posts contain';
				}
			}
			msg += ' ' + (urls_uniq.length > 1 ? urls_uniq.length + ' ' + 'of the URLs' : 'a URL') + ' added to your draft.';
			if (op_post_ids.length > 0 && op_post_ids.length != cnt) {
				if (op_post_ids.length == 1) {
					msg += ' ' + op_post_ids.length + ' of these is an <strong>opening post</strong>.';
				} else {
					msg += ' ' + op_post_ids.length + ' of these are <strong>opening posts</strong>.';
				}
			}
			if (enc_summ) msg += '</div>';
			if (ext) {
				var radius = 10;
				/** @todo Perhaps split this out into a stylesheet that can be customised. */
				var css_obj = {
					'position'             : 'static',
					'background-color'     : 'white',
					'color'                : 'black',
					'border'               : '1px solid black',
					'border-radius'        : radius + 'px',
					'-moz-border-radius'   : radius + 'px',
					'-webkit-border-radius': radius + 'px',
					'padding-left'         : radius + 'px',
					'padding-right'        : radius + 'px',
					'margin'               : '20px auto',
					'white-space'          : 'pre-wrap',      /* CSS 3 */
					'white-space'          : '-moz-pre-wrap', /* Mozilla, since 1999 */
					'white-space'          : '-pre-wrap',     /* Opera 4-6 */
					'white-space'          : '-o-pre-wrap',   /* Opera 7 */
					'word-wrap'            : 'break-word'     /* Internet Explorer 5.5+ */
				};
				var css = '';
				for (var a in css_obj) css += a+':'+css_obj[a]+';';
				msg += '<div id="dlw-extra-info" style="'+css+'max-height: '+($(window).height() - 50)+'px; text-align: left; overflow-y: scroll;">';
				var ids = op_post_ids.concat(non_op_post_ids);
				for (var i = 0; i < ids.length; i++) {
					var pid = ids[i];
					if (DLW.matching_posts[pid].undismissed_urls.length > 0) {
						var post = DLW.matching_posts[pid];
						var is_first_post = (post['firstpost'] == post['pid']);
						msg += '<div id="dlw-post-outer-'+pid+'">'+"\n";
						msg += '<div>'+post['flinks']+'<br />'+post['nav_bit_img']+post['tlink']+'</div>'+"\n";
						msg += '<div>'+dlw_msg_started_by+' '+post['ulink_t']+', '+post['dtlink_t']+'</div>'+"\n";
						/** @todo Perhaps split this out into a stylesheet that can be customised. */
						msg += '<div>'+(is_first_post ? '<span style="border: 1px solid #a5161a; background-color: #fbe3e4; color: #a5161a; border-radius: 10px; -moz-border-radius: 10px; -webkit-border-radius: 10px; padding-left: 10px; padding-right: 10px;">'+dlw_msg_opening_post+'</span>' : dlw_msg_non_opening_post+' '+post['plink']+' '+dlw_msg_posted_by+' '+post['ulink_p']+', '+post['dtlink_p'])+'</div>'+"\n";
						msg += '<button id="dlw-btn-dismiss-'+pid+'" type="button" style="float: right;">'+'Dismiss warning for this post'+'</button>';
						msg += '<div>'+(post.undismissed_urls.length == 1 ? dlw_msg_matching_url_singular : dlw_msg_matching_urls_plural)+"\n";
						msg += '<ul style="padding: 0 auto; margin: 0;">'+"\n";
						for (var j in post.undismissed_urls) {
							var url = post.undismissed_urls[j];
							var url_esc = DLW.htmlspecialchars(url);
							msg += '<li style="padding: 0; margin: 0;"><a href="'+url_esc+'">'+url_esc+'</a></li>'+"\n";
						}
						msg += '</ul></div>'+"\n";
						msg += '<div id="dlw-post-inner-'+pid+'" style="'+css+'">'+post['message']+'</div>'+"\n";
						msg += '</div>'+"\n";
					}
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
					var pid = id.substr(id.lastIndexOf('-') + 1);
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
			/** @todo Perhaps split this out into a stylesheet that can be customised. */
			$('#dlw-warn-summ-box').css({
				'position'             : 'fixed',
				'top'                  : (flash ? ($(window).height()/2) : 0) + 'px',
				'z-index'              : 999,
				'y-overflow'           : 'scroll',
				'left'                 : parseInt(sc_container.offset().left) + 'px',
				'width'                : summ_box_width,
				'max-height'           : $(window).height() + 'px',
				'border'               : '1px solid #a5161a',
				'background-color'     : '#fbe3e4',
				'color'                : '#a5161a',
				'border-radius'        : radius + 'px',
				'-moz-border-radius'   : radius + 'px',
				'-webkit-border-radius': radius + 'px',
				'padding-left'         : radius + 'px',
				'padding-right'        : radius + 'px',
				'margin'               : 0,
				'white-space'          : 'pre-wrap',      // CSS
				'white-space'          : '-moz-pre-wrap', // Mozilla, since 1999
				'white-space'          : '-pre-wrap',     // Opera 4-6
				'white-space'          : '-o-pre-wrap',   // Opera 7
				'word-wrap'            : 'break-word'     // Internet Explorer 5.5+
			       
			})
			.append('<button id="dlw-btn-dismiss-summ" type="button">'+'Dismiss all warnings'+'</button><button id="dlw-btn-details-summ-on" type="button">'+'Show more'+'</button><button id="dlw-btn-details-summ-off" type="button">'+'Show less'+'</button>')
			.append('<span id="dlw-warn-summ-box-contents"></span>');
			$('#dlw-btn-dismiss-summ, #dlw-btn-details-summ-on, #dlw-btn-details-summ-off').css('float', 'right');
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
			$('#dlw-msg-sidebar-div').append('<button id="dlw-btn-undismiss" type="button">'+'Undismiss all duplicate link warnings'+'</button>');
			$('#dlw-btn-undismiss').bind('click', function(e) {
				for (var pid in DLW.matching_posts) {
					DLW.matching_posts[pid].undismissed_urls = DLW.get_intersect(DLW.matching_posts[pid].matching_urls, DLW.matching_posts[pid].all_urls);
					DLW.matching_posts[pid].dismissed_urls = [];
					$(this).hide();
				}
				DLW.warn_summ_box(false);
			});
			DLW.flash_entity($('#dlw-btn-undismiss'));
		}
	},

	have_dismissed: function() {
		for (var pid in DLW.matching_posts) {
			if (DLW.get_intersect(DLW.matching_posts[pid].dismissed_urls, DLW.matching_posts[pid].all_urls).length > 0) {
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

		try {
			// First, try the full Unicode-aware regex.
			// Should semantically match the equivalent variable in ../inc/plugins/duplicate_link_warner.php EXCEPT that
			// the Unicode ranges starting from {00A0} are here started instead from {00A1}, because Unicode character {00A1}
			// is a non-breaking space, which the editor sometimes uses in place of a regular space, and which can be mistakenly
			// captured at the end of a URI.
			DLW.uriRegex = new RegExp(
			  '[a-z](?:[-a-z0-9\\+\\.])*:(?:\\/\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:])*@)?(?:\\[(?:(?:(?:[0-9a-f]{1,4}:){6}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|::(?:[0-9a-f]{1,4}:){5}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){4}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4}:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){3}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,2}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){2}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,3}[0-9a-f]{1,4})?::[0-9a-f]{1,4}:(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,4}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,5}[0-9a-f]{1,4})?::[0-9a-f]{1,4}|(?:(?:[0-9a-f]{1,4}:){0,6}[0-9a-f]{1,4})?::)|v[0-9a-f]+[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:]+)\\]|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}|(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=@])*)(?::[0-9]*)?(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|\\/(?:(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*)?|(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|(?!(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])))(?:\\?(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])|[\\u{E000}-\\u{F8FF}\\u{F0000}-\\u{FFFFD}|\\u{100000}-\\u{10FFFD}\\/\\?])*)?(?:#(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~\\u{00A1}-\\u{D7FF}\\u{F900}-\\u{FDCF}\\u{FDF0}-\\u{FFEF}\\u{10000}-\\u{1FFFD}\\u{20000}-\\u{2FFFD}\\u{30000}-\\u{3FFFD}\\u{40000}-\\u{4FFFD}\\u{50000}-\\u{5FFFD}\\u{60000}-\\u{6FFFD}\\u{70000}-\\u{7FFFD}\\u{80000}-\\u{8FFFD}\\u{90000}-\\u{9FFFD}\\u{A0000}-\\u{AFFFD}\\u{B0000}-\\u{BFFFD}\\u{C0000}-\\u{CFFFD}\\u{D0000}-\\u{DFFFD}\\u{E1000}-\\u{EFFFD}!\\$&\'\\(\\)\\*\\+,;=:@])|[\\/\\?])*)?(?!\\[\\/img\\])',
			  'igu');
		} catch (err) {
			// But if that doesn't work (e.g. for Internet Explorer and older versions of Firefox, and at least some versions of Safari)
			// then strip out Unicode identities and try the non-Unicode-aware regex to accommodate those browsers.
			console.debug('Caught exception when trying Unicode-aware regex. Now trying non-Unicode-aware regex.');
			DLW.uriRegex = new RegExp(
			  '[a-z](?:[-a-z0-9\\+\\.])*:(?:\\/\\/(?:\\[(?:(?:(?:[0-9a-f]{1,4}:){6}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|::(?:[0-9a-f]{1,4}:){5}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){4}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:[0-9a-f]{1,4}:[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){3}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,2}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:){2}(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,3}[0-9a-f]{1,4})?::[0-9a-f]{1,4}:(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,4}[0-9a-f]{1,4})?::(?:[0-9a-f]{1,4}:[0-9a-f]{1,4}|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3})|(?:(?:[0-9a-f]{1,4}:){0,5}[0-9a-f]{1,4})?::[0-9a-f]{1,4}|(?:(?:[0-9a-f]{1,4}:){0,6}[0-9a-f]{1,4})?::)|v[0-9a-f]+[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:]+)\\]|(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(?:\\.(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}|(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=@])*)(?::[0-9]*)?(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|\\/(?:(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:@]))*)*)?|(?:(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:@]))+)(?:\\/(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:@]))*)*|(?!(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~!\$&\'\\(\\)\\*\\+,;=:@])))(?:\\?(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:@])|[\\/\\?])*)?(?:#(?:(?:%[0-9a-f][0-9a-f]|[-a-z0-9\\._~!\\$&\'\\(\\)\\*\\+,;=:@])|[\\/\\?])*)?(?!\\[\\/img\])',
			  'ig');
		}

		var dlw_sidebar_div = $('#dlw-msg-sidebar-div');
		if (dlw_sidebar_div.length) {
			dlw_sidebar_div.append('<input type="checkbox" id="dlw-cbx-do-warn" name="dlw-cbx-do-warn" checked="checked" /> <span title="' + 'If existing forum posts contain URLs present in your draft, you will be visibly warned about them in real time if you check this box.' + '">' + 'Warn about duplicate links' + '</span>');
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

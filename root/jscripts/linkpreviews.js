$('document').ready(function() {
	$('.quick_edit_button').each(function() {
		id = $(this).attr('id');
		pid = id.replace( /[^\d.]/g, '');
		const targetNode = $('#edited_by_' + pid)[0];
		const config = {childList: true, attributes: false, subtree: false};
		const callback = function(mutationsList, observer) {
			// Use traditional 'for loops' for IE 11
			for(const mutation of mutationsList) {
				if (mutation.type === 'childList') {
					console.log('Post '+pid+' was edited in page.');
					$.get('xmlhttp.php?action=lkt_get_post_regen_cont&pid=' + pid + '&my_post_key=' + my_post_key, function(data) {
						$('#lkt_regen_cont_'+pid).replaceWith(data);
					})
				}
			}
		};
		const observer = new MutationObserver(callback);
		observer.observe(targetNode, config);
	});
});

jQuery(document).ready(function($) {
	$('textarea.icf-quicktag').each(function(i) {
		var id = $(this).attr('id');
		var buttons = $(this).data('buttons');

		if (!id) {
			id = 'icf-quicktag-' + i;
			$(this).attr('id', id);
		}

		if (!buttons) {
			buttons = 'strong,em,link';

		} else {
			buttons = buttons.replace(/^\s+|\s+$/g, '').split(/[\s,]+/).join(',');
		}

		settings = {
			id : id,
			buttons: buttons
		}

		quicktags(settings);
	});
});

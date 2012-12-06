(function($) {
	$(function() {
		$('[class*=w_], [class*=w-], [class*=h-], [class*=h_]').each(function() {
			var classes = $(this).attr('class').split(' ');

			for (var i in classes) {
				if (match = classes[i].match(/^([wh])[_\-](\d+)(px|p)$/)) {
					var key = '';
					var value = '';

					switch (match[1]) {
						case 'w':
							key = 'width';
							break;
						case 'h':
							key = 'height';
							break;
					}

					switch (match[3]) {
						case 'p':
							value = match[2] + '%';
							break;
						case 'px':
							value = match[2] + 'px';
							break;
					}

					$(this).removeClass(match[0]).css(key, value);
				}
			}
		})
	});
})(jQuery);
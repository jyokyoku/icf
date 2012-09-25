/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

(function($, window) {
	$(function() {
		var default_send_to_editor = window.send_to_editor;

		$('button.media_button').live('click', function() {
			var field = $(this).data('for'),
				$element = $('input[name="' + field + '"], textarea[name="' + field + '"]');

			if ($element) {
				var insertAtCaret;

				if (window.getSelection) { // modern browser
					insertAtCaret = function(value) {
						$element.each(function() {
							var current = this.value,
								start = this.selectionStart,
								end = start + value.length;

							this.value = current.substr(0, start) + value + current.substr(start);
							this.setSelectionRange(end, end);
						});
					}

				} else if (document.selection) { // IE
					var ranges = [];

					$element.each(function(){
						this.focus();
						range = document.selection.createRange();
						ranges.push(range);
					});

					insertAtCaret = function(value) {
						$element.each(function(i) {
							ranges[i].text = value;
							this.focus();
						});
					}

				} else {
					return;
				}

				var type  = $(this).data('type'),
					value = $(this).data('value') || 'url',
					mode  = $(this).data('mode') || 'replace';

				type = type ? 'type=' + type + '&amp;' : '';
				tb_show('', 'media-upload.php?post_id=0&amp;' + type + 'TB_iframe=true');

				$('#TB_iframeContent').load(function() {
					var iframe_window = $('#TB_iframeContent')[0].contentWindow;
					rewrite_button();

					if (typeof iframe_window.prepareMediaItemInit == 'function') {
						var old_prepare_media_item_init = iframe_window.prepareMediaItemInit;

						iframe_window.prepareMediaItemInit = function(fileObj) {
							old_prepare_media_item_init(fileObj);
							rewrite_button();
						}
					}
				});

				window.send_to_editor = function(html) {
					var html = '<div>' + html + '</div>', data = '';

					switch (value) {
						case 'tag':
							data = $(html).html();
							break;

						case 'url':
						default:
							if ($(html).find('img').length > 0) {
								data = $(html).find('img').attr('src');

							} else if ($(html).find('a').length > 0) {
								data = $(html).find('a').attr('href');

							} else {
								data = $(html).html();
							}
					}

					switch (mode) {
						case 'insert':
							insertAtCaret(data);
							break;

						case 'append':
							$element.val($element.val() + data);
							break;

						case 'replace':
						default:
							$element.val(data);
					}

					tb_remove();
				}

				$('#TB_window').bind('tb_unload', function() {
					window.send_to_editor = default_send_to_editor;
					$('#TB_iframeContent').unbind('load');
				});
			}
		});

		$('button.reset_button').live('click', function() {
			var field = $(this).data('for');

			if (field) {
				$('input[name=' + field + ']').each(function() {
					if ($(this).is(':checkbox') || $(this).is(':radio')) {
						$(this).attr('checked', false);

					} else {
						$(this).val('');
					}
				});

				$('select[name=' + field + ']').attr('selected', false);
				$('textarea[name=' + field + ']').val('');
			}
		});

		$('input[type=text].date_field, button.date_picker').each(function() {
			var $self;

			if ($(this).is('input:text')) {
				$self = $(this);

			} else if ($(this).is('button.date_picker')) {
				var field = $(this).data('for');
				$self = $('input[name=' + field + ']');

				if (!$self) {
					return;
				}

				$(this).click(function() {
					$self.trigger('focus');
				});

			} else {
				return;
			}

			var settings = $.extend({}, {
				ampm           : false,
				cancelText     : icfCommonL10n.cancelText,
				dateFormat     : icfCommonL10n.dateFormat,
				dateOrder      : icfCommonL10n.dateOrder,
				dayNames       : [
					icfCommonL10n.sunday, icfCommonL10n.monday, icfCommonL10n.tuesday,
					icfCommonL10n.wednesday, icfCommonL10n.thursday, icfCommonL10n.friday, icfCommonL10n.saturday
				],
				dayNamesShort  : [
					icfCommonL10n.sundayShort, icfCommonL10n.mondayShort, icfCommonL10n.tuesdayShort,
					icfCommonL10n.wednesdayShort, icfCommonL10n.thursdayShort, icfCommonL10n.fridayShort, icfCommonL10n.saturdayShort
				],
				dayText        : icfCommonL10n.dayText,
				hourText       : icfCommonL10n.hourText,
				minuteText     : icfCommonL10n.minuteText,
				mode           : 'mixed',
				monthNames     : [
					icfCommonL10n.january, icfCommonL10n.february, icfCommonL10n.march, icfCommonL10n.april,
					icfCommonL10n.may, icfCommonL10n.june, icfCommonL10n.july, icfCommonL10n.august,
					icfCommonL10n.september, icfCommonL10n.october, icfCommonL10n.november, icfCommonL10n.december
				],
				monthNamesShort: [
					icfCommonL10n.januaryShort, icfCommonL10n.februaryShort, icfCommonL10n.marchShort, icfCommonL10n.aprilShort,
					icfCommonL10n.mayShort, icfCommonL10n.juneShort, icfCommonL10n.julyShort, icfCommonL10n.augustShort,
					icfCommonL10n.septemberShort, icfCommonL10n.octoberShort, icfCommonL10n.november, icfCommonL10n.decemberShort
				],
				monthText      : icfCommonL10n.monthText,
				secText        : icfCommonL10n.secText,
				setText        : icfCommonL10n.setText,
				timeFormat     : icfCommonL10n.timeFormat,
				yearText       : icfCommonL10n.yearText
			}, $self.data());

			$self.scroller(settings);
			var date_value = $self.val();

			if (date_value.match(/^\d+$/)) {
				var date = new Date(),
					format = '';

				date.setTime(date_value * 1000);

				if (settings.preset == 'time') {
					format = settings.timeFormat;

				} else if (settings.preset == 'datetime') {
					format = settings.dateFormat + ' ' + settings.timeFormat;

				} else {
					format = settings.dateFormat;
				}

				$self.val($.scroller.formatDate(format, date, settings));
			}
		});

		function rewrite_button() {
			$('#TB_iframeContent').contents().find('tr.submit input[type=submit]').val(icfCommonL10n.insertToField);
		}
	})
})(jQuery, window);

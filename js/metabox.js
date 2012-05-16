/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

(function($) {
	$(function() {
		$('form#post').validation({
			errHoverHide: true,
			errTipCloseBtn: false,
			stepValidation: true
		});
	});
})(jQuery, window);

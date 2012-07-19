if (wpActiveEditor == undefined) {
	wpActiveEditor;

	jQuery('.wp-editor-wrap').mousedown(function(e){
		wpActiveEditor = this.id.slice(3, -5);
	});
}

(function() {
	var old_is_mce = null;

	var recover_is_mce = function() {
		if (wpLink !== undefined && old_is_mce && wpLink.isMCE != old_is_mce) {
			wpLink.isMCE = old_is_mce;
		}
	}

	jQuery('.wp-editor-wrap').mousedown(function(e){
		if (wpLink !== undefined && wpActiveEditor && wpActiveEditor.match(/^icf-/)) {
			if (!old_is_mce) {
				old_is_mce = wpLink.isMCE;
			}

			wpLink.isMCE = function() {
				return false;
			}
		}

		var $mce = jQuery('.mceIframeContainer iframe').contents().find('#tinymce');

		if ($mce.length > 0) {
			$mce.unbind('mousedown', recover_is_mce);
			$mce.bind('mousedown', recover_is_mce);
		}
	});
})();
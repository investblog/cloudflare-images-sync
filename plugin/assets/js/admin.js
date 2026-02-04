/**
 * Cloudflare Images Sync — Admin scripts.
 */
(function ($) {
	'use strict';

	// Copy URL to clipboard on click.
	$(document).on('click', '.cfi-copy-url', function () {
		var $el = $(this);
		var url = $el.text();

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(function () {
				$el.addClass('copied');
				setTimeout(function () {
					$el.removeClass('copied');
				}, 1500);
			});
		}
	});
})(jQuery);

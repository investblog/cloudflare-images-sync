/**
 * Cloudflare Images Sync — Admin scripts.
 */
(function ($) {
	'use strict';

	// ── Copy URL to clipboard ──────────────────────────────────────────
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

	// ── Mapping form logic ─────────────────────────────────────────────
	var $form = $('#cfi-mapping-form');
	if (!$form.length) {
		return;
	}

	var config = window.cfiMapping || {};
	var sourceKeyConfig = config.sourceKeyConfig || {};
	var i18n = config.i18n || {};
	var ajax = window.cfiAdmin || {};

	var $sourceType = $('#source_type');
	var $sourceKeyRow = $('#cfi-source-key-row');
	var $sourceKey = $('#source_key');
	var $sourceKeyLabel = $('#cfi-source-key-label');
	var $postType = $('#cfi_post_type');
	var $metaKeysDatalist = $('#cfi-meta-keys');

	// Dynamic source key visibility and label.
	function updateSourceKeyRow() {
		var type = $sourceType.val();
		var cfg = sourceKeyConfig[type];

		if (!cfg) {
			$sourceKeyRow.show();
			return;
		}

		if (cfg.hidden) {
			$sourceKeyRow.hide();
			$sourceKey.val('').removeAttr('required');
			return;
		}

		$sourceKeyRow.show();

		if (cfg.label) {
			$sourceKeyLabel.text(cfg.label);
		}
		if (cfg.placeholder) {
			$sourceKey.attr('placeholder', cfg.placeholder);
		}
		if (cfg.required) {
			$sourceKey.attr('required', 'required');
		}

		// Switch datalist: ACF fields for acf_field, meta keys for others.
		if (type === 'acf_field' && $('#cfi-acf-fields').length) {
			$sourceKey.attr('list', 'cfi-acf-fields');
		} else {
			$sourceKey.attr('list', 'cfi-meta-keys');
		}
	}

	$sourceType.on('change', updateSourceKeyRow);
	updateSourceKeyRow();

	// AJAX: fetch meta keys when post type changes.
	function fetchMetaKeys() {
		var postType = $postType.val();
		$metaKeysDatalist.empty();

		if (!postType || !ajax.ajaxUrl) {
			return;
		}

		$.get(ajax.ajaxUrl, {
			action: 'cfi_meta_keys',
			nonce: ajax.nonce,
			post_type: postType
		}).done(function (response) {
			if (response.success && response.data) {
				$.each(response.data, function (_, key) {
					$metaKeysDatalist.append(
						$('<option>').val(key)
					);
				});
			}
		});
	}

	$postType.on('change', fetchMetaKeys);
	// Fetch on load if post type is pre-selected (edit mode).
	if ($postType.val()) {
		fetchMetaKeys();
	}

	// ── Client-side validation ─────────────────────────────────────────
	var keyPattern = /^[A-Za-z0-9_\-:.]+$/;

	function showError($field, message) {
		var id = $field.attr('id') + '_error';
		var $error = $('#' + id);

		if (!$error.length) {
			$error = $('<p class="cfi-field-error" id="' + id + '"></p>');
			$field.after($error);
		}

		$error.text(message).addClass('visible');
		$field.addClass('form-invalid');
	}

	function clearErrors() {
		$form.find('.cfi-field-error').removeClass('visible');
		$form.find('.form-invalid').removeClass('form-invalid');
	}

	function validateKey($field, required) {
		var val = $.trim($field.val());

		if (required && val === '') {
			showError($field, i18n.required || 'This field is required.');
			return false;
		}

		if (val === '') {
			return true; // Optional and empty is OK.
		}

		if (val.length > 191) {
			showError($field, i18n.keyTooLong || 'Maximum 191 characters.');
			return false;
		}

		if (!keyPattern.test(val)) {
			showError($field, i18n.invalidKey || 'Invalid characters.');
			return false;
		}

		return true;
	}

	$form.on('submit', function (e) {
		clearErrors();
		var valid = true;

		// Post type.
		if (!$postType.val()) {
			showError($postType, i18n.selectPostType || 'Please select a post type.');
			valid = false;
		}

		// Source type.
		if (!$sourceType.val()) {
			showError($sourceType, i18n.required || 'This field is required.');
			valid = false;
		}

		// Source key (only if visible and required).
		var type = $sourceType.val();
		var cfg = sourceKeyConfig[type];
		if (cfg && !cfg.hidden && cfg.required) {
			if (!validateKey($sourceKey, true)) {
				valid = false;
			}
		}

		// Target URL meta (required).
		if (!validateKey($('#target_url_meta'), true)) {
			valid = false;
		}

		// Optional target keys.
		if (!validateKey($('#target_id_meta'), false)) {
			valid = false;
		}
		if (!validateKey($('#target_sig_meta'), false)) {
			valid = false;
		}

		if (!valid) {
			e.preventDefault();
			// Scroll to first error.
			var $firstError = $form.find('.form-invalid').first();
			if ($firstError.length) {
				$('html, body').animate({
					scrollTop: $firstError.offset().top - 50
				}, 300);
			}
		}
	});
})(jQuery);

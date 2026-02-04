/**
 * Cloudflare Images Sync — Admin scripts.
 */
(function ($) {
	'use strict';

	// ── Copy to clipboard (universal) ─────────────────────────────────

	function copyToClipboard(text, $el) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				showCopied($el);
			}).catch(function () {
				fallbackCopy(text, $el);
			});
		} else {
			fallbackCopy(text, $el);
		}
	}

	function fallbackCopy(text, $el) {
		var $textarea = $('<textarea>')
			.val(text)
			.css({ position: 'fixed', left: '-9999px', opacity: 0 })
			.appendTo('body');
		$textarea[0].select();
		try {
			document.execCommand('copy');
			showCopied($el);
		} catch (e) {
			// Silent fail.
		}
		$textarea.remove();
	}

	function showCopied($el) {
		$el.addClass('copied');
		setTimeout(function () {
			$el.removeClass('copied');
		}, 1500);
	}

	// Copy URL (preset grid cards, preview page).
	$(document).on('click', '.cfi-copy-url', function () {
		var $el = $(this);
		var url = $.trim($el.text());
		if (url !== '') {
			copyToClipboard(url, $el);
		}
	});

	// Copy button (data-copy-from attribute).
	$(document).on('click', '.cfi-copy-btn', function () {
		var $btn = $(this);
		var selector = $btn.data('copyFrom');
		if (!selector) {
			return;
		}
		var value = $.trim($(selector).val());
		if (value !== '') {
			copyToClipboard(value, $btn);
		}
	});

	// Copy all targets button.
	$(document).on('click', '#cfi-copy-all-targets', function () {
		var $btn = $(this);
		var json = JSON.stringify({
			url_meta: $.trim($('#target_url_meta').val()),
			id_meta: $.trim($('#target_id_meta').val()),
			sig_meta: $.trim($('#target_sig_meta').val())
		}, null, 2);
		copyToClipboard(json, $btn);
	});

	// Enable/disable copy buttons based on input value.
	function updateCopyBtnState($input) {
		var selector = '#' + $input.attr('id');
		var $btn = $('.cfi-copy-btn[data-copy-from="' + selector + '"]');
		if ($btn.length) {
			$btn.prop('disabled', $.trim($input.val()) === '');
		}
	}

	function updateCopyAllState() {
		var allEmpty =
			$.trim($('#target_url_meta').val()) === '' &&
			$.trim($('#target_id_meta').val()) === '' &&
			$.trim($('#target_sig_meta').val()) === '';
		$('#cfi-copy-all-targets').prop('disabled', allEmpty);
	}

	$(document).on('input change', '#target_url_meta, #target_id_meta, #target_sig_meta', function () {
		updateCopyBtnState($(this));
		updateCopyAllState();
	});

	// Initialize copy button states on page load (edit mode with pre-filled values).
	$(function () {
		$('#target_url_meta, #target_id_meta, #target_sig_meta').each(function () {
			updateCopyBtnState($(this));
		});
		updateCopyAllState();
	});

	// ── Flexible Variants Test / Enable ───────────────────────────────

	var ajax = window.cfiAdmin || {};

	$(document).on('click', '#cfi-flex-test', function () {
		cfiFlexAction('cfi_flex_test');
	});

	$(document).on('click', '#cfi-flex-enable', function () {
		if (!confirm('This enables Flexible Variants account-wide on your Cloudflare account. Continue?')) {
			return;
		}
		cfiFlexAction('cfi_flex_enable', true);
	});

	function cfiFlexAction(action, reloadOnSuccess) {
		var $spinner = $('#cfi-flex-spinner');
		var $result = $('#cfi-flex-result');
		$spinner.addClass('is-active');
		$result.text('');

		$.post(ajax.ajaxUrl, {
			action: action,
			_ajax_nonce: ajax.nonce
		}, function (response) {
			$spinner.removeClass('is-active');
			if (response.success) {
				updateFlexUI(response.data.status, response.data.message, response.data.checked_at);
				// Reload page on enable success to refresh UI state (non-settings pages).
				if (reloadOnSuccess && response.data.status === 'enabled' && !$('#cfi-status-box').length) {
					setTimeout(function () {
						window.location.reload();
					}, 1000);
				}
			} else {
				$result.text(response.data.message || 'Error').css('color', '#d63638');
			}
		}).fail(function () {
			$spinner.removeClass('is-active');
			$result.text('Request failed.').css('color', '#d63638');
		});
	}

	function updateFlexUI(status, message, checkedAt) {
		var $badge = $('#cfi-flex-badge');
		var $enable = $('#cfi-flex-enable');
		var $result = $('#cfi-flex-result');
		var labels = ajax.flexLabels || {};

		$badge.removeClass('cfi-flex--enabled cfi-flex--disabled cfi-flex--unknown');
		if (status === 'enabled') {
			$badge.addClass('cfi-flex--enabled').text(labels.enabled || 'Enabled');
			$enable.hide();
			$result.text(message).css('color', '#00a32a');
		} else if (status === 'disabled') {
			$badge.addClass('cfi-flex--disabled').text(labels.disabled || 'Disabled');
			$enable.show();
			$result.text(message).css('color', '#d63638');
		} else {
			$badge.addClass('cfi-flex--unknown').text(labels.unknown || 'Unknown');
			$enable.show();
			$result.text(message).css('color', '#646970');
		}

		// Update status box if present (Settings page).
		updateStatusBox(status, checkedAt);
	}

	function updateStatusBox(flexStatus, checkedAt) {
		var $statusFlex = $('#cfi-status-flex');
		var $timestamp = $('#cfi-status-timestamp');

		if (!$statusFlex.length) {
			return;
		}

		// Update FV status indicator.
		var statusClass, statusText;
		if (flexStatus === 'enabled') {
			statusClass = 'cfi-status--ok';
			statusText = 'Enabled';
		} else if (flexStatus === 'disabled') {
			statusClass = 'cfi-status--error';
			statusText = 'Disabled';
		} else {
			statusClass = 'cfi-status--pending';
			statusText = 'Unknown';
		}
		$statusFlex.html('<span class="cfi-status-indicator ' + statusClass + '">' + statusText + '</span>');

		// Update timestamp.
		if (checkedAt && $timestamp.length) {
			$timestamp.attr('data-timestamp', checkedAt).text('Last checked: just now');
		}
	}

	// ── Install Recommended Presets (blocked when FV not enabled) ─────

	$(document).on('click', '#cfi-install-recommended-btn', function () {
		var status = $(this).data('flexStatus');
		var msg;

		if (status === 'disabled') {
			msg = 'Flexible Variants are disabled on your Cloudflare account. ' +
				'Recommended presets use flexible syntax and will not render correctly.\n\n' +
				'Options:\n' +
				'• Go to Settings to enable Flexible Variants first\n' +
				'• Click OK to install anyway (presets will be broken until FV enabled)\n' +
				'• Click Cancel to abort';
		} else {
			msg = 'Flexible Variants status is unknown. Recommended presets may not work.\n\n' +
				'Go to Settings to test the connection first, or click OK to install anyway.';
		}

		if (confirm(msg)) {
			// Submit the actual form.
			$('#cfi-install-recommended-form').find('button[type="button"]')
				.attr('type', 'submit')
				.attr('name', 'cfi_install_recommended');
			$('#cfi-install-recommended-form').submit();
		}
	});

	// ── Autocomplete component ─────────────────────────────────────────

	/**
	 * Lightweight autocomplete dropdown for text inputs.
	 *
	 * @param {jQuery} $input The text input to attach to.
	 */
	function CfiAutocomplete($input) {
		this.$input = $input;
		this.items = [];
		this.filtered = [];
		this.open = false;
		this.activeIndex = -1;
		this._debounceTimer = null;

		if (!$input.length) {
			return;
		}

		this._init();
	}

	CfiAutocomplete.prototype._init = function () {
		var self = this;

		// Wrap input in a relative container.
		this.$wrap = $('<div class="cfi-autocomplete"></div>');
		this.$input.wrap(this.$wrap);
		this.$wrap = this.$input.parent();

		// Create dropdown panel.
		this.$panel = $('<div class="cfi-autocomplete__panel"></div>');
		this.$wrap.append(this.$panel);

		// Remove native autocomplete.
		this.$input.attr('autocomplete', 'off');

		// Events.
		this.$input.on('input', function () {
			self._debounce(function () {
				self._filter();
				self._show();
			}, 200);
		});

		this.$input.on('focus', function () {
			if (self.items.length > 0) {
				self._filter();
				self._show();
			}
		});

		this.$panel.on('mousedown', '.cfi-autocomplete__item', function (e) {
			e.preventDefault(); // Prevent blur before click.
			self._select($(this).data('value'));
		});

		this.$input.on('keydown', function (e) {
			if (!self.open) {
				return;
			}

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				self._move(1);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				self._move(-1);
			} else if (e.key === 'Enter' && self.activeIndex >= 0) {
				e.preventDefault();
				self._select(self.filtered[self.activeIndex].name);
			} else if (e.key === 'Escape') {
				self._hide();
			}
		});

		this.$input.on('blur', function () {
			// Delay hide so mousedown on panel item fires first.
			setTimeout(function () {
				self._hide();
			}, 150);
		});
	};

	CfiAutocomplete.prototype.setItems = function (items) {
		this.items = items || [];
		this.activeIndex = -1;
		this._filter();

		// If the input already has focus (AJAX arrived after focus), show panel.
		if (this.items.length > 0 && this.$input.is(':focus')) {
			this._show();
		}
	};

	CfiAutocomplete.prototype._debounce = function (fn, delay) {
		clearTimeout(this._debounceTimer);
		this._debounceTimer = setTimeout(fn, delay);
	};

	CfiAutocomplete.prototype._filter = function () {
		var query = $.trim(this.$input.val()).toLowerCase();
		if (query === '') {
			this.filtered = this.items.slice(0, 200);
		} else {
			var matches = [];
			for (var i = 0; i < this.items.length && matches.length < 200; i++) {
				var item = this.items[i];
				if (
					item.name.toLowerCase().indexOf(query) !== -1 ||
					item.label.toLowerCase().indexOf(query) !== -1
				) {
					matches.push(item);
				}
			}
			this.filtered = matches;
		}
		this.activeIndex = -1;
		this._render();
	};

	CfiAutocomplete.prototype._render = function () {
		this.$panel.empty();

		if (this.filtered.length === 0) {
			this.$panel.html(
				'<div class="cfi-autocomplete__empty">No matching fields</div>'
			);
			return;
		}

		for (var i = 0; i < this.filtered.length; i++) {
			var item = this.filtered[i];
			var $item = $(
				'<div class="cfi-autocomplete__item" data-index="' + i + '"></div>'
			);
			$item.data('value', item.name);
			$item.append(
				'<span class="cfi-autocomplete__item-name">' +
					this._esc(item.name) +
					'</span>'
			);
			if (item.label && item.label !== item.name) {
				$item.append(
					'<span class="cfi-autocomplete__item-label">' +
						this._esc(item.label) +
						'</span>'
				);
			}
			if (item.group) {
				$item.append(
					'<span class="cfi-autocomplete__item-group">' +
						this._esc(item.group) +
						'</span>'
				);
			}
			$item.append(
				'<span class="cfi-autocomplete__item-type">' +
					this._esc(item.type === 'acf_image' ? 'image' : item.type) +
					'</span>'
			);
			this.$panel.append($item);
		}
	};

	CfiAutocomplete.prototype._esc = function (str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	};

	CfiAutocomplete.prototype._show = function () {
		if (this.items.length === 0) {
			this._hide();
			return;
		}
		this.$panel.addClass('is-open');
		this.open = true;
	};

	CfiAutocomplete.prototype._hide = function () {
		this.$panel.removeClass('is-open');
		this.open = false;
		this.activeIndex = -1;
	};

	CfiAutocomplete.prototype._move = function (direction) {
		var count = this.filtered.length;
		if (count === 0) {
			return;
		}

		this.activeIndex += direction;
		if (this.activeIndex < 0) {
			this.activeIndex = count - 1;
		}
		if (this.activeIndex >= count) {
			this.activeIndex = 0;
		}

		this.$panel.find('.cfi-autocomplete__item').removeClass('is-active');
		var $active = this.$panel
			.find('.cfi-autocomplete__item[data-index="' + this.activeIndex + '"]')
			.addClass('is-active');

		// Scroll into view.
		if ($active.length) {
			var panel = this.$panel[0];
			var el = $active[0];
			if (el.offsetTop < panel.scrollTop) {
				panel.scrollTop = el.offsetTop;
			} else if (el.offsetTop + el.offsetHeight > panel.scrollTop + panel.clientHeight) {
				panel.scrollTop = el.offsetTop + el.offsetHeight - panel.clientHeight;
			}
		}
	};

	CfiAutocomplete.prototype._select = function (value) {
		this.$input.val(value).trigger('change');
		this._hide();
	};

	// ── Mapping form logic ─────────────────────────────────────────────
	var $form = $('#cfi-mapping-form');
	if (!$form.length) {
		return;
	}

	var config = window.cfiMapping || {};
	var sourceKeyConfig = config.sourceKeyConfig || {};
	var i18n = config.i18n || {};
	var hasAcf = !!config.hasAcf;

	var $sourceType = $('#source_type');
	var $sourceKeyRow = $('#cfi-source-key-row');
	var $sourceKey = $('#source_key');
	var $sourceKeyLabel = $('#cfi-source-key-label');
	var $postType = $('#cfi_post_type');

	// Autocomplete instances.
	var sourceAutocomplete = new CfiAutocomplete($sourceKey);
	var targetAutocompletes = [];
	$('#target_url_meta, #target_id_meta, #target_sig_meta').each(function () {
		targetAutocompletes.push(new CfiAutocomplete($(this)));
	});

	// Client-side AJAX cache: key = "action:post_type", value = items array.
	var suggestionsCache = {};

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
	}

	// Fetch cached items or make AJAX request, then call callback with items.
	function fetchCached(action, postType, callback) {
		var cacheKey = action + ':' + postType;
		if (suggestionsCache[cacheKey]) {
			callback(suggestionsCache[cacheKey]);
			return;
		}

		$.get(ajax.ajaxUrl, {
			action: action,
			nonce: ajax.nonce,
			post_type: postType
		}).done(function (response) {
			if (response.success && response.data) {
				suggestionsCache[cacheKey] = response.data;
				callback(response.data);
			}
		});
	}

	// WordPress internal meta prefixes — unsafe to overwrite.
	var internalPrefixes = ['_wp_', '_edit_', '_oembed_', '_pingme', '_encloseme', '_thumbnail'];

	function filterSafeTargetKeys(items) {
		return items.filter(function (item) {
			for (var i = 0; i < internalPrefixes.length; i++) {
				if (item.name.indexOf(internalPrefixes[i]) === 0) {
					return false;
				}
			}
			return true;
		});
	}

	// Update destination autocompletes with meta keys for current post type.
	function fetchTargetSuggestions(reset) {
		var postType = $postType.val();

		if (reset) {
			for (var i = 0; i < targetAutocompletes.length; i++) {
				targetAutocompletes[i].setItems([]);
			}
		}

		if (!postType || !ajax.ajaxUrl) {
			return;
		}

		fetchCached('cfi_meta_keys', postType, function (items) {
			var safe = filterSafeTargetKeys(items);
			for (var i = 0; i < targetAutocompletes.length; i++) {
				targetAutocompletes[i].setItems(safe);
			}
		});
	}

	// Lazy-fetch: when a destination input is focused and has no items yet, fetch them.
	$('#target_url_meta, #target_id_meta, #target_sig_meta').on('focus', function () {
		if ($postType.val() && targetAutocompletes.length > 0 && targetAutocompletes[0].items.length === 0) {
			fetchTargetSuggestions(false);
		}
	});

	// Smart AJAX fetching for source key: picks endpoint based on source type.
	function fetchSourceSuggestions() {
		var postType = $postType.val();
		var sourceType = $sourceType.val();

		sourceAutocomplete.setItems([]);

		if (!postType || !ajax.ajaxUrl) {
			return;
		}

		var action;
		if (sourceType === 'acf_field') {
			if (!hasAcf) {
				return;
			}
			action = 'cfi_acf_fields';
		} else if (sourceType === 'post_meta_attachment_id' || sourceType === 'post_meta_url') {
			action = 'cfi_meta_keys';
		} else {
			return; // No suggestions for featured_image, attachment_id.
		}

		fetchCached(action, postType, function (items) {
			sourceAutocomplete.setItems(items);
		});
	}

	$sourceType.on('change', function () {
		updateSourceKeyRow();
		fetchSourceSuggestions();
	});
	$postType.on('change', function () {
		suggestionsCache = {};
		fetchSourceSuggestions();
		fetchTargetSuggestions(true);
	});

	// Smart defaults: auto-fill destination keys based on source key.
	$sourceKey.on('change', function () {
		var key = $.trim($sourceKey.val());
		if (key === '') {
			return;
		}

		var $urlMeta = $('#target_url_meta');
		var $idMeta = $('#target_id_meta');
		var $sigMeta = $('#target_sig_meta');

		if ($.trim($urlMeta.val()) === '') {
			$urlMeta.val('_' + key + '_cdn_url').trigger('change');
		}
		if ($.trim($idMeta.val()) === '') {
			$idMeta.val('_' + key + '_cf_id').trigger('change');
		}
		if ($.trim($sigMeta.val()) === '') {
			$sigMeta.val('_' + key + '_cf_sig').trigger('change');
		}
	});

	updateSourceKeyRow();
	// Fetch on load if post type is pre-selected (edit mode).
	if ($postType.val()) {
		fetchSourceSuggestions();
		fetchTargetSuggestions(false);
	}

	// ── Test Mapping dry-run ───────────────────────────────────────────

	$('#cfi-test-btn').on('click', function () {
		var postId = $.trim($('#cfi_test_post_id').val());
		if (postId === '' || parseInt(postId, 10) <= 0) {
			$('#cfi-test-results')
				.html('<p class="cfi-test-error">Please enter a valid post ID.</p>')
				.show();
			return;
		}

		var $btn = $(this);
		var $spinner = $('#cfi-test-spinner');
		var $results = $('#cfi-test-results');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$results.hide();

		$.post(ajax.ajaxUrl, {
			action: 'cfi_test_mapping',
			nonce: ajax.nonce,
			post_id: postId,
			source_type: $sourceType.val(),
			source_key: $sourceKey.val(),
			target_url_meta: $('#target_url_meta').val(),
			target_id_meta: $('#target_id_meta').val(),
			target_sig_meta: $('#target_sig_meta').val(),
			preset_id: $('#preset_id').val(),
			upload_if_missing: $('input[name="upload_if_missing"]').is(':checked') ? 1 : 0,
			reupload_if_changed: $('input[name="reupload_if_changed"]').is(':checked') ? 1 : 0
		}).always(function () {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
		}).done(function (response) {
			if (!response.success) {
				$results
					.html('<p class="cfi-test-error">' + escHtml(response.data || 'Unknown error.') + '</p>')
					.show();
				return;
			}

			var d = response.data;
			var html = '<dl>';

			// Post info.
			html += '<dt>Post</dt><dd>' + escHtml(d.post_title) + ' <code>#' + d.post_type + '</code></dd>';

			// Source status.
			if (d.source_found) {
				html += '<dt>Source</dt><dd><span class="cfi-test-status cfi-test-status--found">Found</span>';
				if (d.attachment_id > 0) {
					html += ' Attachment #' + d.attachment_id;
				}
				if (d.file_name) {
					html += ' <code>' + escHtml(d.file_name) + '</code>';
				}
				html += '</dd>';
			} else {
				html += '<dt>Source</dt><dd><span class="cfi-test-status cfi-test-status--missing">Not found</span></dd>';
			}

			// Upload decision.
			if (d.source_found) {
				var uploadClass = d.would_upload ? 'cfi-test-status--upload' : 'cfi-test-status--skip';
				var uploadLabel = d.would_upload ? 'Will upload' : 'Skip';
				html += '<dt>Upload</dt><dd><span class="cfi-test-status ' + uploadClass + '">' + uploadLabel + '</span> ' + escHtml(d.upload_reason) + '</dd>';
			} else {
				html += '<dt>Upload</dt><dd>' + escHtml(d.upload_reason) + '</dd>';
			}

			// URLs.
			if (d.current_url) {
				html += '<dt>Current URL</dt><dd><code>' + escHtml(d.current_url) + '</code></dd>';
			}
			if (d.preview_url) {
				html += '<dt>Preview URL</dt><dd><code>' + escHtml(d.preview_url) + '</code></dd>';
			}

			html += '</dl>';
			$results.html(html).show();
		}).fail(function () {
			$results
				.html('<p class="cfi-test-error">Request failed. Check your connection.</p>')
				.show();
		});
	});

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	// ── Client-side validation ─────────────────────────────────────────
	var keyPattern = /^[A-Za-z0-9_\-:.]+$/;

	function isReservedKey(val) {
		for (var i = 0; i < internalPrefixes.length; i++) {
			if (val.indexOf(internalPrefixes[i]) === 0) {
				return true;
			}
		}
		return false;
	}

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
		} else if ($sourceType.val() === 'acf_field' && !hasAcf) {
			showError($sourceType, 'ACF is not installed. This source type requires Advanced Custom Fields.');
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

		// Reserved key check for all target fields.
		$('#target_url_meta, #target_id_meta, #target_sig_meta').each(function () {
			var val = $.trim($(this).val());
			if (val !== '' && isReservedKey(val)) {
				showError($(this), 'This key is reserved by WordPress and cannot be used as a destination.');
				valid = false;
			}
		});

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

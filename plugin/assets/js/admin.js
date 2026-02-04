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
	var ajax = window.cfiAdmin || {};
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

	// Update destination autocompletes with meta keys for current post type.
	function fetchTargetSuggestions() {
		var postType = $postType.val();

		for (var i = 0; i < targetAutocompletes.length; i++) {
			targetAutocompletes[i].setItems([]);
		}

		if (!postType || !ajax.ajaxUrl) {
			return;
		}

		fetchCached('cfi_meta_keys', postType, function (items) {
			for (var i = 0; i < targetAutocompletes.length; i++) {
				targetAutocompletes[i].setItems(items);
			}
		});
	}

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
		fetchTargetSuggestions();
	});

	updateSourceKeyRow();
	// Fetch on load if post type is pre-selected (edit mode).
	if ($postType.val()) {
		fetchSourceSuggestions();
		fetchTargetSuggestions();
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

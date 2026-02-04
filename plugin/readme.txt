=== Images Sync for Cloudflare ===
Contributors: investblog
Tags: cloudflare, images, sync, cdn, optimization
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.6-beta
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress images to Cloudflare Images with flexible mappings, presets, and variant delivery.

== Description ==

Images Sync for Cloudflare lets you automatically upload WordPress media attachments and post-related images to Cloudflare Images, then serve optimized variants via Cloudflare's CDN.

**Key Features:**

* **Flexible Mappings** — Map any post type + source field (ACF, featured image, meta) to a Cloudflare Images upload with customizable delivery URL storage.
* **Presets** — Define reusable image variant presets (dimensions, fit, quality, format) for consistent delivery URLs.
* **Auto-Sync on Save** — Hooks into `save_post` and `acf/save_post` to sync images automatically when content is updated.
* **Bulk Sync** — Process existing posts in chunks via Action Scheduler.
* **Change Detection** — Signature-based change detection avoids redundant uploads.
* **Preview / Variant Studio** — Preview how images look with different presets before going live.
* **WP-CLI Support** — `wp cfi sync` and `wp cfi test` commands for scripted workflows.
* **Logging** — Ring-buffer log with configurable max entries for debugging.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Navigate to **CF Images > Settings** and enter your Cloudflare Account ID, Account Hash, and API Token.
4. Create presets under **CF Images > Presets**.
5. Set up mappings under **CF Images > Mappings** to define which post types and fields to sync.

== Frequently Asked Questions ==

= What Cloudflare plan do I need? =

Cloudflare Images is a paid product available on all Cloudflare plans. You need an active Cloudflare Images subscription.

= Does this plugin modify my original images? =

No. The plugin uploads a copy to Cloudflare Images and stores the delivery URL in post meta. Your original WordPress media files remain unchanged.

= Can I use this with ACF? =

Yes. The plugin supports ACF image fields (ID, array, and URL return formats) as source types.

== Changelog ==

= 0.1.6-beta =
* Smart ACF field suggestions: only image fields assigned to selected post type (via ACF location rules).
* Recursive subfield traversal for repeater, group, and flexible_content ACF fields.
* Custom autocomplete dropdown replacing native datalist — styled, filterable, keyboard-navigable.
* Unified suggestion response format {name, label, type} for both ACF and meta key endpoints.
* Meta key AJAX results cached via 5-minute transient.
* Client-side suggestion cache by (action, post_type) to avoid redundant AJAX calls.

= 0.1.5-beta =
* Fix: safe re-upload skips CF image deletion when shared via attachment cache.
* Fix: Settings page links to Cloudflare dashboard now clickable (wp_kses instead of esc_html__).
* Fix: empty delivery URL no longer written to meta on build failure.
* Attachment cache meta keys moved to OptionKeys constants.
* BulkEnqueuer validates mapping ID format before processing.
* Readme changelog corrected.

= 0.1.4-beta =
* Attachment-level CF image cache — avoids duplicate uploads across mappings.
* Safe re-upload: skip CF image deletion when shared by other mappings.
* Mapping form UX: sections, descriptions, dynamic fields, client-side validation.
* Settings page: clickable help links to Cloudflare dashboard.
* AJAX meta key suggestions for mapping form.
* Card-style mappings list, log count badge.
* Fix: 500 error on mapping save (post_type field name conflict).

= 0.1.0-beta =
* Initial beta release.
* Settings, Presets, Mappings CRUD.
* Auto-sync via save_post and acf/save_post hooks.
* Bulk sync via Action Scheduler.
* Preview / Variant Studio.
* WP-CLI commands.
* Ring-buffer logging.

=== Images Sync for Cloudflare ===
Contributors: investblog
Tags: cloudflare, images, sync, cdn, optimization
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.2.0
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

= 0.2.0 =
* New: Universal "public" preset that works without Flexible Variants.
* New: "Universal" badge for presets that don't require Flexible Variants.
* New: Attachment ID validation with nearby ID suggestions in Preview.
* New: Loading spinners on all Preview page forms.
* New: Detailed sync status badges in Test Mapping (New upload, Re-upload, Cached, Unchanged).
* Improved: Settings page redesigned with collapsible sections (Delivery, API Access, Advanced).
* Improved: Connection Status box with visual indicators for API and Flexible Variants status.
* Improved: Trimmed recommended presets to 7 core variants.
* Fixed: Reserved WordPress meta keys filtered from autocomplete.
* Fixed: Plugin translations now load correctly via load_plugin_textdomain().

= 0.1.10-beta =
* New: Flexible Variants detection — automatically check if FV is enabled on your Cloudflare account.
* New: Enable Flexible Variants with one click from Settings, Presets, or Preview pages.
* New: Smart status callout banners with Test/Enable buttons on Presets and Preview pages.
* Improved: Badge logic on Presets page — "Flexible" (enabled), "Needs Flexible Variants" (disabled), "Flexible (status unknown)".
* Improved: Install Recommended Presets shows confirm dialog when FV not enabled.
* Improved: Preview page shows placeholder instead of broken images for flex presets.
* Improved: Copy URL disabled for flex presets when FV not enabled.

= 0.1.9-beta =
* Mapping card: arrow between Source and Target, preset name links to edit page.
* Settings: disable "Use queue" checkbox and show warning when Action Scheduler is not installed.
* Preview: inline descriptions for Attachment and Post + Mapping modes.
* Mapping form: placeholder templates for destination meta keys, note that keys are created automatically.

= 0.1.8-beta =
* Fix: Destination autocomplete lazy-fetches meta keys on focus when cache is empty.

= 0.1.7-beta =
* Destination field autocomplete: meta key suggestions for Delivery URL, CF Image ID, and Change Signature fields.
* Autocomplete constructor guard for empty jQuery selections.
* Version bump to bust browser cache for JS/CSS assets.

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

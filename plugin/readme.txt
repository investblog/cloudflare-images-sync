=== Images Sync for Cloudflare ===
Contributors: investblog
Tags: cloudflare, images, sync, cdn, optimization
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
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

= 1.0.0 =
* Initial release.
* Settings, Presets, Mappings CRUD.
* Auto-sync via save_post and acf/save_post hooks.
* Bulk sync via Action Scheduler.
* Preview / Variant Studio.
* WP-CLI commands.
* Ring-buffer logging.

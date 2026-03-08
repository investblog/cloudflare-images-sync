=== Images Sync for Cloudflare ===
Contributors: 301st
Tags: cloudflare, images, cdn, acf, headless
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.8
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-sync WordPress images to Cloudflare Images. Stores optimized CDN URLs in post meta — ready for headless, ACF, or classic themes.

== Description ==

**Images Sync for Cloudflare** automatically uploads your WordPress images to Cloudflare Images and stores optimized delivery URLs directly in post meta. Perfect for headless setups, JAMstack sites, or anyone who wants fast, globally-distributed images without changing their workflow.

= Why Use This Plugin? =

Headless frontends need stable, cacheable CDN URLs. This plugin makes WordPress the source of truth while Cloudflare Images handles delivery and optimization. No custom resolvers needed — just query the meta field.

= Features =

* **Flexible Mappings** — Map any image source (Featured Image, ACF fields, post meta) to Cloudflare Images with customizable delivery URL storage
* **Preset System** — Define reusable presets for OG images, thumbnails, heroes, squares — consistent URLs across your site
* **Preview Studio** — Visually test how images look with different presets before going live
* **Auto-Sync** — Images sync automatically on post save, or bulk-process existing content via Action Scheduler
* **Flexible Variants** — Smart detection prevents broken images and 9429 errors
* **Headless-Ready** — URLs stored in post meta, perfect for GraphQL/REST API consumption
* **WP-CLI Support** — `wp cfimg sync` commands for scripted workflows
* **Secure Storage** — API token encrypted with libsodium (AES-256 fallback)
* **No Lock-in** — Your images stay in WordPress Media Library; Cloudflare URLs are plain meta values

= Security =

Your Cloudflare API token is encrypted at rest using modern cryptography (libsodium or AES-256-CBC with HMAC). The token is never stored in plain text and is only decrypted when making API requests.

= Requirements =

* Cloudflare account with [Cloudflare Images](https://www.cloudflare.com/products/cloudflare-images/) subscription
* API Token with "Cloudflare Images: Edit" permission

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via WordPress admin
2. Activate the plugin through the **Plugins** menu
3. Go to **CF Images → Settings** and enter your Cloudflare credentials
4. Create presets under **CF Images → Presets** (or install recommended presets)
5. Set up mappings under **CF Images → Mappings** to define sync rules

= Quick Start =

1. **Credentials** — Get your Account ID, Account Hash, and API Token from Cloudflare dashboard
2. **Preset** — Create a preset like `og_1200x630` with variant `w=1200,h=630,fit=cover,f=auto`
3. **Mapping** — Map your CPT's featured image to a meta field like `_og_image_url`
4. **Done** — Images sync on save, URLs available via `get_post_meta()`

== Frequently Asked Questions ==

= What Cloudflare plan do I need? =

Cloudflare Images is a paid add-on available on all Cloudflare plans. Pricing is based on images stored and served.

= Does this modify my original images? =

No. The plugin uploads a copy to Cloudflare Images. Your WordPress media files remain unchanged.

= Can I use this with ACF? =

Yes. The plugin supports ACF image fields with ID, array, and URL return formats.

= What are Flexible Variants? =

Flexible Variants allow on-the-fly image transformations via URL parameters (width, height, quality, format). The plugin detects if this feature is enabled on your account and adjusts the UI accordingly.

= What fit modes are available? =

Cloudflare Images supports several fit modes for resizing:

* **scale-down** — Shrinks to fit within dimensions but never upscales. Best for content images where originals may be smaller than the target.
* **cover** — Crops to fill exact dimensions. Best for OG images, thumbnails, and heroes where consistent sizing matters.
* **contain** — Scales to fit within dimensions, adding letterboxing if needed. Preserves the full image.
* **crop** — Crops to exact dimensions from the center (or custom gravity).

Most presets use `fit=cover` for consistent sizing. Use `fit=scale-down` when you want to limit max width without stretching small images.

= Do I need Action Scheduler? =

Action Scheduler is optional but recommended for bulk syncing. It offloads image uploads to background jobs, avoiding timeouts on large sites. Action Scheduler is bundled with WooCommerce, or you can install it as a [standalone plugin](https://wordpress.org/plugins/action-scheduler/).

= Is this good for headless WordPress? =

Absolutely. Delivery URLs are stored as standard post meta, so they're immediately available via REST API or WPGraphQL without any custom resolvers.

= What happens if I deactivate the plugin? =

Your Cloudflare Images remain on Cloudflare, and the delivery URLs stay in post meta. You can continue using them or migrate to another solution.

== Screenshots ==

1. Dashboard widget showing connection status at a glance
2. Settings page with API configuration and Flexible Variants status
3. Presets page with recommended presets and Universal/Flexible badges
4. Mapping configuration with source, target, and preset selection
5. Preview Studio for testing presets with live images

== External services ==

This plugin optionally connects to external Cloudflare services to upload, manage, and deliver images. No connection is made until the user configures API credentials and explicitly triggers a sync, test, or preview action.

= Cloudflare Images API =

This plugin sends requests to the [Cloudflare API](https://api.cloudflare.com/) (`api.cloudflare.com`).

Data sent to Cloudflare (only when the user configures credentials and triggers actions):

* API token — for authentication (sent as a Bearer token header, never logged or stored in plain text)
* Image files — binary content of WordPress media attachments
* Image metadata — WordPress attachment ID and a purpose label (e.g. "preview")
* Configuration updates — Flexible Variants enable/disable flag

* [Cloudflare Terms of Service](https://www.cloudflare.com/terms/)
* [Cloudflare Privacy Policy](https://www.cloudflare.com/privacypolicy/)
* [Cloudflare API Documentation](https://developers.cloudflare.com/api/)

= Cloudflare Image Delivery =

Delivery URLs use the [Cloudflare Image Delivery](https://imagedelivery.net/) CDN (`imagedelivery.net`). These URLs are stored in post meta and served directly to site visitors by their browsers. The plugin itself makes one request to this service to detect Flexible Variants support (canary check).

No visitor data, IP addresses, cookies, or personal information is ever sent to Cloudflare by this plugin.

* [Cloudflare Terms of Service](https://www.cloudflare.com/terms/)
* [Cloudflare Privacy Policy](https://www.cloudflare.com/privacypolicy/)
* [Cloudflare Images Documentation](https://developers.cloudflare.com/images/)

== Privacy Policy ==

This plugin does not collect, store, or transmit any personal user data. Only image files and technical metadata (attachment IDs, image hashes) are sent to Cloudflare.

== Changelog ==

= 1.0.8 =
* Added: Developer filters `cfimg_delivery_url` and `cfimg_resolve_source` for customizing sync behavior
* Added: PHPUnit test suite — 35 tests covering admin security, sync engine, and validators
* Added: SECURITY.md with vulnerability reporting policy
* Improved: Plugin description updated for clarity
* Tested: WordPress 7.0 compatibility verified

= 1.0.7 =
* Changed: Internal prefix renamed from `cfi` to `cfimg` (4+ chars required by WP.org)
* Changed: External services section restructured with inline ToS/Privacy links per service
* Added: Automatic database migration from `cfi_` to `cfimg_` option/meta keys
* Fixed: Uninstall cleans up both old (`cfi_`) and new (`cfimg_`) prefixed data
* Fixed: Asset enqueue guard referenced stale prefix, preventing CSS/JS on subpages

= 1.0.5 =
* Changed: Text domain and plugin file renamed to match WP.org slug

= 1.0.0 – 1.0.4 =
First stable release. Encrypted API token storage (libsodium/AES-256), Dashboard widget, Settings link, WP.org Plugin Check compliance, uninstall cleanup, content_1200w preset, FAQ sections.

= 0.1.0-beta – 0.2.5 =
Pre-release development. Flexible Variants detection, auto-sync engine, ACF support, Preview Studio, Universal presets, Dashboard widget.

== Upgrade Notice ==

= 1.0.8 =
Developer filters, test suite, and WordPress 7.0 compatibility.

= 1.0.7 =
Prefix renamed from `cfi` to `cfimg` per WP.org review. Database keys migrate automatically.

= 1.0.5 =
Text domain and plugin file renamed to match WP.org assigned slug `images-sync-for-cloudflare`.

== Credits ==

Developed by [301st](https://301.st) with [Claude AI](https://claude.ai).

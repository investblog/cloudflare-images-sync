=== Images Sync for Cloudflare ===
Contributors: 301st
Tags: cloudflare, images, cdn, acf, headless
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.6
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress images to Cloudflare Images — store CDN URLs in post meta, ready for headless or classic themes.

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

= 1.0.6 =
* Changed: Internal prefix renamed from `cfi` to `cfimg` (4+ chars required by WP.org)
* Changed: External services section restructured with inline ToS/Privacy links per service
* Added: Automatic database migration from `cfi_` to `cfimg_` option/meta keys
* Fixed: Uninstall cleans up both old (`cfi_`) and new (`cfimg_`) prefixed data

= 1.0.5 =
* Changed: Text domain renamed to `images-sync-for-cloudflare` to match WP.org slug
* Changed: Main plugin file renamed to `images-sync-for-cloudflare.php`

= 1.0.4 =
* Fixed: readme.txt uses required == External services == section heading (WP.org review)
* Fixed: Uninstall SQL uses $wpdb->prepare() with %i placeholder
* Fixed: Release ZIP filename without version suffix
* Removed: GitHub Plugin URI headers (not needed for WP.org)
* Added: acf tag for better discoverability
* Added: == Upgrade Notice == section

= 1.0.3 =
* Fixed: Uninstall now removes encrypted API token and migration version
* Fixed: Uninstall cleans up all plugin post meta from database

= 1.0.2 =
* New: content_1200w recommended preset (fit=scale-down, never upscales small images)
* New: FAQ — available fit modes (scale-down, cover, contain, crop)
* New: FAQ — Action Scheduler as optional dependency
* Improved: Settings page UX — API Access section moved above Delivery for logical onboarding flow

= 1.0.1 =
* Improved: WordPress.org Plugin Check compliance (inline styles → enqueued CSS)
* Improved: SQL queries use %i placeholder for table names (WP 6.2+)
* Improved: Third-party service disclosure per Guideline 7
* Removed: load_plugin_textdomain (automatic since WP 4.6 for .org plugins)
* Changed: Minimum WordPress version raised to 6.2

= 1.0.0 =
* New: Encrypted API token storage (libsodium/AES-256)
* New: Settings link on Plugins page
* New: Translation template (.pot file) for localization
* New: WordPress.org assets (icons and banners)
* Improved: Dashboard widget with connection status
* First stable release

= 0.2.5 =
* New: Dashboard widget on main WP Dashboard with connection status
* Improved: Plugin descriptions and documentation
* Improved: Author info updated for WordPress.org

= 0.2.4 =
* Fixed: Dashboard widget error — added missing LogsRepo::recent() method

= 0.2.3 =
* New: Dashboard widget with Connection Status, quick stats, and links
* New: Cloudflare icon in widget header

= 0.2.2 =
* Fixed: Auto-sync URL persistence when ACF clears target field
* Added: wp_after_insert_post fallback for maximum compatibility
* Improved: Hook priorities increased to 999

= 0.2.1 =
* Fixed: Auto-sync URL write on ACF save
* Improved: Debug logging verifies meta writes

= 0.2.0 =
* New: Universal "public" preset (works without Flexible Variants)
* New: Attachment ID validation with suggestions in Preview
* Improved: Settings page with collapsible sections
* Improved: Connection Status indicators

= 0.1.10-beta =
* New: Flexible Variants detection and one-click enable
* New: Smart UI gating — no broken previews

= 0.1.0-beta =
* Initial release

== Upgrade Notice ==

= 1.0.6 =
Prefix renamed from `cfi` to `cfimg` per WP.org review. Database keys migrate automatically.

= 1.0.5 =
Text domain and plugin file renamed to match WP.org assigned slug `images-sync-for-cloudflare`.

= 1.0.4 =
WP.org review compliance: external services section, prepared SQL, ACF tag.

= 1.0.3 =
Complete uninstall cleanup — encrypted token and migration version removed.

= 1.0.2 =
New content_1200w preset, fit modes FAQ, improved settings UX.

= 1.0.1 =
WordPress.org Plugin Check compliance, %i placeholders, external services disclosure.

= 1.0.0 =
First stable release with encrypted token storage and WordPress.org assets.

== Credits ==

Developed by [301st](https://301.st) with [Claude AI](https://claude.ai).

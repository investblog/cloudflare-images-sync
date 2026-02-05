# Images Sync for Cloudflare

A WordPress plugin that syncs images to Cloudflare Images with flexible mappings, presets, and variant delivery.

## Features

- **Automatic Sync** — Upload images to Cloudflare Images on post save
- **Flexible Mappings** — Map any image source (Featured Image, ACF fields, post meta) to Cloudflare
- **Preset System** — Define reusable variant presets (dimensions, quality, format)
- **Flexible Variants** — Use Cloudflare's on-the-fly image transformations
- **Preview Studio** — Test presets with live image previews
- **WP-CLI Support** — Bulk sync via command line
- **Action Scheduler** — Background processing for large sites

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Cloudflare account with Images enabled

## Installation

1. Download the latest release from [Releases](https://github.com/investblog/cloudflare-images-sync/releases)
2. Upload to `/wp-content/plugins/`
3. Activate the plugin
4. Go to **CF Images → Settings** and enter your Cloudflare credentials

## Configuration

### Cloudflare Credentials

You'll need from your Cloudflare dashboard:
- **Account ID** — Found in the URL or dashboard sidebar
- **Account Hash** — Found in Images → Overview → "Your account hash"
- **API Token** — Create one with `Cloudflare Images:Edit` permission

### Flexible Variants

For presets with custom dimensions (w=, h=, f=auto), enable Flexible Variants in your Cloudflare Images settings. The plugin can detect and enable this for you.

## Usage

### 1. Create Presets

Go to **CF Images → Presets** and create variant presets:
- `public` — Universal, works without Flexible Variants
- `og_1200x630` — Open Graph images (1200×630)
- `thumb_400x300` — Thumbnails

### 2. Create Mappings

Go to **CF Images → Mappings** and define sync rules:
- **Source** — Where to get the image (Featured Image, ACF field, meta key)
- **Target** — Where to store the Cloudflare URL (post meta keys)
- **Preset** — Which variant to use
- **Triggers** — When to sync (on save, ACF save)

### 3. Preview & Test

Use **CF Images → Preview** to:
- Test presets with any attachment
- Preview delivery URLs before going live
- Verify Flexible Variants are working

## WP-CLI Commands

```bash
# Sync all posts for a mapping
wp cfi sync --mapping=map_abc12345

# Sync specific post
wp cfi sync --post_id=123 --mapping=map_abc12345

# List mappings
wp cfi mappings list
```

## Hooks & Filters

```php
// Modify delivery URL before storing
add_filter('cfi_delivery_url', function($url, $cf_image_id, $preset) {
    return $url;
}, 10, 3);

// Custom source resolver
add_filter('cfi_resolve_source', function($resolved, $post_id, $source) {
    return $resolved;
}, 10, 3);
```

## Development

```bash
# Clone repository
git clone https://github.com/investblog/cloudflare-images-sync.git

# Install dependencies
composer install

# Run linting
composer run phpcs

# Local development with wp-env
npm install
npx wp-env start
```

## License

GPL-2.0+

## Credits

Developed by [investblog](https://github.com/investblog)

![Images Sync for Cloudflare](assets/banner-1544x500.png)

# Images Sync for Cloudflare

Sync WordPress images to Cloudflare Images — store CDN URLs in post meta, ready for headless or classic themes.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-1.0.0-orange.svg)](https://github.com/investblog/cloudflare-images-sync/releases/tag/v1.0.0)

## Why?

Headless frontends need stable, cacheable CDN URLs. This plugin makes WordPress the source of truth while Cloudflare Images handles delivery and optimization. No custom resolvers needed — just query the meta field.

## Features

- **Flexible Mappings** — Map any image source (Featured Image, ACF fields, post meta) to Cloudflare Images
- **Preset System** — Reusable presets for OG images, thumbnails, heroes — consistent URLs across your site
- **Preview Studio** — Visually test presets with live images before going live
- **Auto-Sync** — Images sync on post save, or bulk-process via Action Scheduler
- **Flexible Variants** — Smart detection prevents broken images and 9429 errors
- **Headless-Ready** — URLs in post meta, perfect for GraphQL/REST API
- **WP-CLI Support** — `wp cfi sync` for scripted workflows
- **Secure Storage** — API token encrypted with libsodium (AES-256 fallback)
- **No Lock-in** — Images stay in Media Library; URLs are plain meta values

## Security

Your Cloudflare API token is encrypted at rest using modern cryptography:

- **Primary**: libsodium `crypto_secretbox` (XSalsa20-Poly1305)
- **Fallback**: OpenSSL AES-256-CBC with HMAC-SHA256
- **Key derivation**: WordPress authentication salts (`AUTH_KEY` + `SECURE_AUTH_KEY`)

The token is never stored in plain text and is only decrypted when making API requests.

## Quick Start

```bash
# 1. Install
composer create-project your-vendor/your-plugin  # or download from Releases

# 2. Configure (WP Admin → CF Images → Settings)
Account ID:    ← from Cloudflare dashboard
Account Hash:  ← from Images → Overview
API Token:     ← with "Cloudflare Images: Edit" permission

# 3. Create preset
Name: og_1200x630
Variant: w=1200,h=630,fit=cover,f=auto

# 4. Create mapping
Post Type: post
Source: Featured Image
Target Meta: _og_image_url
Preset: og_1200x630

# 5. Done — URLs sync on save
get_post_meta($post_id, '_og_image_url', true);
// → https://imagedelivery.net/{hash}/{id}/w=1200,h=630,fit=cover,f=auto
```

## Requirements

- WordPress 6.0+
- PHP 8.0+
- [Cloudflare Images](https://www.cloudflare.com/products/cloudflare-images/) subscription
- API Token with `Cloudflare Images: Edit` permission

## Installation

### From GitHub Releases

1. Download the latest `.zip` from [Releases](https://github.com/investblog/cloudflare-images-sync/releases)
2. Upload via **Plugins → Add New → Upload Plugin**
3. Activate and configure at **CF Images → Settings**

### Manual

```bash
cd wp-content/plugins/
git clone https://github.com/investblog/cloudflare-images-sync.git
```

## Usage

### Presets

Define image variants at **CF Images → Presets**:

| Preset | Variant | Use Case |
|--------|---------|----------|
| `public` | `public` | Universal (no Flexible Variants needed) |
| `og_1200x630` | `w=1200,h=630,fit=cover,f=auto` | Open Graph / Social |
| `thumb_400` | `w=400,quality=80,f=auto` | Thumbnails |
| `hero_1920` | `w=1920,quality=85,f=auto` | Hero images |

### Mappings

Connect sources to destinations at **CF Images → Mappings**:

- **Source**: Featured Image, ACF field, or meta key containing attachment ID
- **Target**: Meta keys for delivery URL, CF Image ID, and change signature
- **Preset**: Which variant to use for the delivery URL
- **Triggers**: `save_post`, `acf/save_post`, or both

### Preview Studio

Test presets before deploying:

1. Go to **CF Images → Preview**
2. Enter an attachment ID or select Post + Mapping
3. See live previews with all your presets
4. Copy URLs directly to clipboard

## WP-CLI

```bash
# Sync single post
wp cfi sync --post_id=123 --mapping=map_abc12345

# Bulk sync all posts for a mapping
wp cfi sync --mapping=map_abc12345

# Test connection
wp cfi test
```

## For Developers

### Hooks

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

### GraphQL Example

With WPGraphQL, the meta field is immediately queryable:

```graphql
query {
  post(id: "123") {
    ogImageUrl: metaValue(key: "_og_image_url")
  }
}
```

## Development

```bash
git clone https://github.com/investblog/cloudflare-images-sync.git
cd cloudflare-images-sync

# Install dependencies
composer install

# Run linting
composer run phpcs

# Local dev with wp-env
npm install && npx wp-env start
```

## License

GPL-2.0+

## Credits

Developed by [301st](https://301.st) with [Claude AI](https://claude.ai).

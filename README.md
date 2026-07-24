# ClickPress WP

[![PHP Syntax](https://github.com/nikoyap/clickup-featured-image-importer/actions/workflows/php-lint.yml/badge.svg)](https://github.com/nikoyap/clickup-featured-image-importer/actions/workflows/php-lint.yml)
[![Release ZIP](https://github.com/nikoyap/clickup-featured-image-importer/actions/workflows/release.yml/badge.svg)](https://github.com/nikoyap/clickup-featured-image-importer/actions/workflows/release.yml)
[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL_v2_or_later-blue.svg)](LICENSE)

**ClickPress WP** automatically imports ClickUp attachment images into the WordPress Media Library and assigns them as featured images for posts created through the WordPress REST API.

## Why this project exists

Zapier can create WordPress posts from ClickUp, but ClickUp attachment URLs cannot be assigned directly as WordPress featured images. ClickPress WP bridges that gap.

It captures the attachment URL from the incoming REST request, downloads and validates the file, imports it into WordPress, assigns it as the featured image, sets accessible alt text, and cleans up the temporary transport data.

## Features

- One-Zap publishing workflow
- Supports ClickUp AI-generated attachments
- Tries the original tokenized ClickUp URL first
- Falls back to the clean URL when necessary
- Validates the response before importing
- Imports images into the WordPress Media Library
- Sets the featured image automatically
- Uses the post title as image alt text
- Does not overwrite an existing featured image
- Retries once after 20 seconds
- Cleans the temporary excerpt after success

## Workflow

```text
ClickUp AI
    |
    v
ClickUp Task
    |
    v
Zapier Create Post
    |
    +-- Title   -> WordPress Title
    +-- Content -> WordPress Content
    +-- Image URL -> WordPress Excerpt
    |
    v
WordPress REST API
    |
    v
ClickPress WP
    |
    +-- Capture attachment URL
    +-- Download and validate image
    +-- Import to Media Library
    +-- Set featured image
    +-- Set alt text
    +-- Clear temporary excerpt
    |
    v
Published WordPress Article
```

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer
- ClickUp
- Zapier
- A WordPress connection in Zapier

## Installation

### From a release ZIP

1. Download the latest `clickpress-wp.zip` file from GitHub Releases.
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and activate **ClickPress WP**.

### From source

1. Copy the `clickpress-wp` folder into `wp-content/plugins/`.
2. Activate **ClickPress WP** from the WordPress Plugins screen.

## Zapier setup

In the WordPress **Create Post** action, use this mapping:

| ClickUp value | WordPress field |
|---|---|
| Article title | Title |
| Article body | Content |
| Complete ClickUp attachment URL | Excerpt |

The attachment URL should include its `authz_token` query parameter. The Excerpt acts only as a temporary transport field and is cleared after a successful import.

## How it works

1. `rest_pre_dispatch` examines the incoming REST request.
2. The plugin finds the first supported ClickUp attachment URL.
3. The URL is stored temporarily in post metadata.
4. `rest_after_insert_post` starts the image import.
5. The complete tokenized URL is attempted first.
6. The response is verified using `getimagesize()`.
7. WordPress imports the image using `media_handle_sideload()`.
8. The imported image becomes the post's featured image.
9. The post title becomes the image alt text.
10. Temporary metadata and the transport excerpt are removed.

## Troubleshooting

### The post is created without a featured image

Check that:

- The full ClickUp attachment URL is mapped to Excerpt.
- The URL still contains the `authz_token`.
- The WordPress server can make outbound HTTPS requests.
- The post did not already have a featured image.
- The image format is supported by WordPress.

### The ClickUp URL opens as HTML instead of an image

Do not remove the query string manually. ClickPress WP tries the original tokenized URL before attempting a clean fallback URL.

### The image appears twice in the Media Library

The current release does not perform duplicate-image detection. This is planned for a future version.

## Security

The plugin accepts only URLs hosted on `clickup-attachments.com`. Temporary source data is deleted after a successful import.

Never publish or log live ClickUp `authz_token` values.

## Documentation

See the [`docs`](docs/) folder for architecture, maintenance, release, and troubleshooting documentation.

## Contributing

Contributions are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

## Roadmap

- Custom post type support
- Configurable transport field
- Duplicate media detection
- Admin settings page
- Structured diagnostic logging
- WP-CLI command
- Automated WordPress coding-standard checks

## License

ClickPress WP is licensed under the GNU General Public License v2.0 or later.

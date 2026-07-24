=== ClickPress WP ===
Contributors: nikoyap
Tags: clickup, zapier, featured image, automation, media library
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically imports ClickUp attachment images and assigns them as featured images for WordPress posts created through the REST API.

== Description ==

ClickPress WP connects a ClickUp-to-WordPress Zapier workflow with the WordPress Media Library.

The plugin captures a ClickUp attachment URL from an incoming REST post request, downloads and validates the image, imports it into the Media Library, sets it as the post's featured image, and removes the temporary URL from the post excerpt.

== Installation ==

1. Upload the `clickpress-wp` folder to `/wp-content/plugins/`.
2. Activate ClickPress WP.
3. In Zapier, map the complete ClickUp attachment URL to the WordPress Excerpt field.
4. Create the WordPress post through Zapier.

== Changelog ==

= 1.0.0 =
* Initial public release.

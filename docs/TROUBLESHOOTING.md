# Troubleshooting Guide

## Post created, but no featured image

1. Confirm the complete ClickUp attachment URL is mapped to Excerpt.
2. Confirm the attachment URL contains `authz_token`.
3. Test whether the WordPress server can make outbound HTTPS requests.
4. Confirm the post does not already have a featured image.
5. Confirm the attachment is a supported image type.

## HTML downloaded instead of an image

A stripped ClickUp URL may return an HTML page. Keep the complete tokenized URL in Zapier.

## Server timeout

Increase the WordPress/PHP HTTP timeout only after confirming that network latency is the problem. The plugin currently allows 45 seconds.

## Retry behavior

The plugin retries once after 20 seconds when the first attempt does not set a featured image.

## Safe debugging

Do not publish logs containing the full `authz_token`. Redact query-string values before sharing logs publicly.

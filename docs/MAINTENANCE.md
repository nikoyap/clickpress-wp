# Maintenance Guide

## Versioning

Use Semantic Versioning:

- Patch: bug fixes, for example `1.0.1`
- Minor: backward-compatible features, for example `1.1.0`
- Major: breaking changes, for example `2.0.0`

## Standard update workflow

```bash
git status
git add .
git commit -m "Describe the change"
git push
```

## Release workflow

1. Update the plugin version header.
2. Update `clickpress-wp/readme.txt`.
3. Update `CHANGELOG.md`.
4. Commit and push.
5. Create a Git tag:

```bash
git tag v1.0.1
git push origin v1.0.1
```

6. GitHub Actions packages `clickpress-wp.zip` and attaches it to a GitHub Release.

## Testing checklist

- Create a new WordPress post through Zapier.
- Confirm the image imports into Media Library.
- Confirm it becomes the featured image.
- Confirm alt text matches the post title.
- Confirm the temporary excerpt is removed.
- Confirm existing featured images are not overwritten.
- Test both JPEG and PNG attachments.

# Changelog

## 1.0.1

- Prevented unmanaged pages from falling back to the primary organization during schema context resolution.
- Added static and live WordPress regressions for disabled homepage and unmanaged-page injection.

## 1.0.0

- Created the separate SMC Organization Profile Integration plugin.
- Established HWS ownership for Organization, Testimonial, and Team Member content types.
- Added the complete organization ACF model with attachment-ID media.
- Added idempotent migration from legacy SFPF organization fields.
- Added normalized data, media, and relationship repositories.
- Added deterministic Organization, WebSite, page, and related-entity graphs.
- Added frontend JSON-LD injection and Rank Math deduplication.
- Added organization REST endpoints and a standard REST schema field.
- Added SMC field, gallery, schema, and legacy organization shortcodes.
- Added a structured admin dashboard, settings, field inventory, and live schema preview.
- Added standalone architecture, schema, integration, lint, and ownership tests.

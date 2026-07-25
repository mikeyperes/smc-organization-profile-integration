# Architecture

## Boundaries

`initialization.php` defines metadata and constants, registers the PSR-4 loader, participates in the shared Hexa Core resolver, and delegates to `Plugin`. It contains no feature implementation.

`src/Plugin.php` is the composition root. Feature modules own their hooks:

- `Acf/`: generic field factory and organization field group
- `Admin/`: settings page, status, field inventory, shortcode examples, and schema preview
- `Core/`: Hexa Core context and GitHub updater
- `Data/`: all field, value, media, and entity normalization
- `Dependencies/`: HWS and ACF contracts
- `Http/`: REST routes and WordPress REST field
- `Migration/`: idempotent legacy data conversion
- `Schema/`: IDs, node factories, Organization node, graph, injection, and SEO coexistence
- `Shortcodes/`: field, gallery, and schema presentation
- `Support/`: prefixed settings contract

## Content-Type Ownership

HWS Base Tools is the only owner of the shared Organization, Testimonial, and Team Member post-type definitions. SMC enables and consumes those contracts. There is no fallback definition in SMC.

SFPF Person retains Book ownership and may delegate its former organization helpers to SMC. SMC preserves the legacy organization shortcode as a configurable adapter.

## Data Flow

1. ACF and legacy post metadata are read through `FieldReader`.
2. `Normalizer` and `MediaNormalizer` produce typed values and attachment records.
3. `OrganizationData` exposes one compatibility-aware entity contract.
4. Schema, REST, shortcodes, migration, and admin previews consume that contract.
5. No generated schema payload is stored or edited.

## Extension Points

- `smc_organization_schema_node`
- `smc_organization_schema_graph`
- `smc_organization_profile_booted`
- `smc_organization_profile_core_booted`
- `smc_dashboard_tabs`
- `smc_dashboard_render_tab`

## Security

- Public REST requests return only published organizations.
- Draft and private records require `edit_post`.
- Admin updates require `manage_options` and a nonce.
- Shortcodes expose an explicit public-field allowlist.
- URLs, emails, dates, numbers, IDs, HTML, JSON, and media are normalized at output boundaries.
- Schema JSON uses WordPress encoding with tag and ampersand escaping.

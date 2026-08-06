# Architecture

## Boundaries

`initialization.php` defines metadata and constants, registers the PSR-4 loader, participates in the shared Hexa Core resolver, and delegates to `Plugin`. It contains no feature implementation.

`src/Plugin.php` is the composition root. Feature modules own their hooks:

- `Acf/`: Organization field definitions and the SMC key-policy adapter over Core's ACF field factory
- `Admin/`: settings page, status, Core-rendered field inventory, shortcode examples, and schema preview
- `Core/`: Hexa Core context and GitHub updater
- `Data/`: Organization-specific entity/repository contracts plus compatibility facades over Core normalization
- `Dependencies/`: HWS and ACF contracts registered through Core plugin checks
- `Http/`: REST routes and WordPress REST field
- `Migration/`: idempotent legacy data conversion
- `Schema/`: IDs, node factories, Organization node, graph, injection, and SEO coexistence
- `Presentation/`: the canonical Organization profile renderer and shared frontend asset loader
- `Shortcodes/`: SMC-owned field, profile, gallery, schema, and legacy `organization` alias presentation
- `Compatibility/`: read-only adapters for historical SFPF callback consumers
- `Support/`: prefixed settings contract

## Content-Type Ownership

HWS Base Tools is the only owner of the shared Organization, Testimonial, and Team Member post-type definitions. SMC enables and consumes those contracts. There is no fallback definition in SMC.

SFPF Person retains Book and Person ownership and delegates its former Organization callbacks to SMC. SMC alone registers the legacy `organization` shortcode alias. The SFPF-facing adapter reads canonical SMC data but never registers fields, emits schema, changes settings, or selects fallback records.

Shortcode entity resolution is explicit ID, current Organization single, then canonical primary Organization. The legacy alias retains its own `shortcode_atts_organization` filter and empty-field default so existing integrations do not silently switch records or output.

## Core Boundary

Hexa WordPress Plugin Core owns generic mechanics: plugin context/bootstrap, ACF field construction, cached field/media/value normalization, plugin checks, shortcode and admin-tab catalogs, field-structure rendering, updater plumbing, AJAX guards, and schema document normalization. SMC supplies Organization policy, field names, schema mappings, presentation, routes, and compatibility decisions.

The SMC facades in `Acf/FieldRegistry.php` and `Data/` intentionally preserve the plugin's public signatures. They contain only SMC key/default policy or direct delegation, preventing downstream templates and schema code from coupling to a vendor implementation detail.

## Data Flow

1. ACF and legacy post metadata are read through the SMC facade over Core `FieldReader`.
2. SMC `Normalizer` and `MediaNormalizer` facades delegate generic conversion and attachment work to Core.
3. `OrganizationData` exposes one compatibility-aware entity contract.
4. Schema, REST, shortcodes, migration, and admin previews consume that contract.
5. Repeated field, media, entity, and schema reads are cached only for the current request.
6. Every graph is normalized and deduplicated by Core before reaching REST, shortcode, admin, or frontend consumers.
7. No generated schema payload is stored or edited.

## Admin Surface

SMC supplies `TabDefinition` records to the Core tab registry and panels to the shared grouped sidebar. Core renders the field structure and guards the lazy AJAX endpoint with capability and nonce checks. Dashboard styling is attached to WordPress's existing admin stylesheet instead of requiring a separate plugin asset request.

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

# SMC Organization Profile Integration

Canonical Organization fields, schema graphs, REST output, and presentation helpers for HWS-managed company websites.

> Feature base for HWS Skills. Read before building on this plugin; use or
> extend these features instead of rebuilding them.

**Purpose:** the canonical Organization record of a company website, its schema graph and display helpers. **Admin:** WP Admin → Organization settings (the plugin's dashboard). **Depends on:** ACF Pro; HWS Base Tools primary entity.

## Features

### Organization fields
- **Does:** prints any field of the site's Organization (or a given one).
- **Use:** `[organization field="" id="" format="html" link="false" target="" size="large" columns="3" pretty="false" action=""]`; `[smc_organization field="name" ...]` takes the same attributes with `name` as the default field.
- **Code:** `src/Shortcodes/ShortcodeRegistrar.php`

### Organization gallery
- **Use:** `[smc_organization_gallery id="" field="gallery" size="large" columns="3"]`.

### Organization schema
- **Does:** outputs the Organization JSON-LD graph; also added to pages automatically.
- **Use:** `[smc_organization_schema]`.
- **Extend:** `smc_organization_schema_graph`, `smc_organization_schema_node`.

### Extension points
- `smc_dashboard_tabs`, `smc_dashboard_render_tab`.

## Identity

- Repository: `mikeyperes/smc-organization-profile-integration`
- Plugin slug: `smc-organization-profile-integration`
- Namespace: `SMC\OrganizationProfile`
- Version: `3.1.0`

## Ownership

SMC owns:

- Canonical Organization ACF fields.
- Organization data normalization and repositories.
- Organization/WebSite/ProfilePage schema construction.
- Organization REST endpoints and shortcodes.

HWS Base Tools owns the immutable `organization` custom post type and the optional canonical website/entity selection. SMC extends that post type and does not register a competing CPT or duplicate HWS field group.

## Canonical Entity

SMC consumes the optional HWS primary entity when its semantic type is Organization. The source may be an Organization post or a WordPress user. For post-backed records, Hexa WP Core resolves and displays the bound WordPress author and all available fields.

No primary entity is required. Unmanaged frontend requests do not silently fall back to a global Organization.

## Organization Model

The normalized model covers:

- Legal and alternate names, slogan, descriptions, dates, and locations.
- Contact points, area served, identity links, NAICS/ISIC identifiers, and employee counts.
- Founders, team members, parent/sub-organizations, services, products, and brands.
- Credentials, awards, press mentions, milestones, testimonials, ratings, logos, and galleries.

Media values are stored as attachment IDs and normalized for REST, shortcodes, and schema output.

## Schema

SMC builds deterministic schema graphs with stable IDs. Graphs may include `WebSite`, `ProfilePage`, `Organization` subtypes, `ImageObject`, `Offer`, `EducationalOccupationalCredential`, `Article`, and `Review` nodes.

Schema encoding, deduplication, and frontend injection use `Hexa\PluginCore\SchemaTools`. Rank Math coexistence is guarded so unrelated nodes are preserved.

## REST API

| Route | Purpose |
| --- | --- |
| `/wp-json/smc/v1/organizations` | List public Organization records. |
| `/wp-json/smc/v1/organizations/primary` | Return the configured canonical Organization. |
| `/wp-json/smc/v1/organizations/{id}` | Return one normalized Organization. |
| `/wp-json/smc/v1/organizations/{id}/schema` | Return one Organization schema graph. |

## Shortcodes

- `[smc_organization]`
- `[smc_organization_gallery]`
- `[smc_organization_schema]`
- `[organization]` compatibility alias

Public field access uses an explicit allowlist.

## Architecture

`initialization.php` is a thin composition root. Namespaced implementation code lives under `src/`; architecture and schema contracts are documented in [docs/architecture.md](docs/architecture.md) and [docs/schema-contract.md](docs/schema-contract.md).

Reusable CPT, entity, ACF settings, dashboard, updater, and schema infrastructure comes from Hexa WP Core 3.4.5.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 6.0 |
| PHP | 8.1 |
| HWS Base Tools | 13.0.3 |
| Hexa WP Core bundle | 3.4.5 |

ACF Pro is not required: the Organization field model runs on Hexa WP Core custom fields, which use ACF when it is active and store the same data natively when it is not.

## Installation

Install the repository as `wp-content/plugins/smc-organization-profile-integration`. Activate HWS Base Tools first, then activate SMC. Existing legacy Organization values are migrated idempotently and are never overwritten by empty values.

## Development

Run static, architecture, lint, and schema tests with:

```bash
php tests/run.php
```

Run `tests/wordpress-integration.php` in a bootstrapped WordPress environment for live hook and REST verification.

## Changelog

### 3.1.0

- ACF Pro is no longer required: the Organization field model, its migrator hook and diagnostics run on Hexa WP Core 3.4.5 `Fields` (ACF when active, native storage otherwise). The ACF Pro activation check, admin notice and required-plugin entry are removed.
- Synchronized the bundled Hexa WordPress Plugin Core to 3.4.5.

### 3.0.1

- Synchronized the bundled Hexa WordPress Plugin Core to 3.0.1 and the HWS Base Tools dependency to 13.0.3.

### 3.0.0

- Coordinated major release for the consolidated Organization field, schema, REST, presentation, and HWS dependency infrastructure, synchronized with HWS Base Tools 13.0.0 and Hexa WordPress Plugin Core 3.0.0.

### 2.0.0

- Established the stable major baseline for canonical Organization fields, schema, REST output, and presentation helpers.
- Updated shared CPT, ACF, entity, schema, updater, rendering, and admin UI infrastructure to Hexa WP Core 1.0.0.
- Set HWS Base Tools 11.0.0 as the canonical Organization CPT and optional entity-source dependency while preserving existing Organization data.

### 1.0.3

- Delegated canonical entity resolution, schema injection, and Organization CPT/ACF settings infrastructure to Hexa WP Core.
- Preserved user-backed and post-backed Organization field/schema behavior.
- Updated the bundled Hexa WordPress Plugin Core to 0.19.78.
- Added complete installation, requirements, development, support, and license documentation.

## Support

Report issues at <https://github.com/mikeyperes/smc-organization-profile-integration/issues>.

## License

GPL-2.0-or-later.

# SMC Organization Profile Integration

Canonical Organization fields, schema graphs, REST output, and presentation helpers for HWS-managed company websites.

## Identity

- Repository: `mikeyperes/smc-organization-profile-integration`
- Plugin slug: `smc-organization-profile-integration`
- Namespace: `SMC\OrganizationProfile`
- Version: `1.0.3`

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

Reusable CPT, entity, ACF settings, dashboard, updater, and schema infrastructure comes from Hexa WP Core 0.19.78.

## Requirements

| Requirement | Minimum |
| --- | --- |
| WordPress | 6.0 |
| PHP | 8.1 |
| HWS Base Tools | 10.18.143 |
| Hexa WP Core bundle | 0.19.78 |

ACF Pro is required for the Organization field model.

## Installation

Install the repository as `wp-content/plugins/smc-organization-profile-integration`. Activate HWS Base Tools first, then activate SMC. Existing legacy Organization values are migrated idempotently and are never overwritten by empty values.

## Development

Run static, architecture, lint, and schema tests with:

```bash
php tests/run.php
```

Run `tests/wordpress-integration.php` in a bootstrapped WordPress environment for live hook and REST verification.

## Changelog

### 1.0.3

- Delegated canonical entity resolution, schema injection, and Organization CPT/ACF settings infrastructure to Hexa WP Core.
- Preserved user-backed and post-backed Organization field/schema behavior.
- Updated the bundled Hexa WordPress Plugin Core to 0.19.78.
- Added complete installation, requirements, development, support, and license documentation.

## Support

Report issues at <https://github.com/mikeyperes/smc-organization-profile-integration/issues>.

## License

GPL-2.0-or-later.

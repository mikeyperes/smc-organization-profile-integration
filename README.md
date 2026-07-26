# SMC Organization Profile Integration

A company-profile integration for WordPress sites managed by Hexa Web Systems.

## Naming

- Plugin: SMC Organization Profile Integration
- Repository: `mikeyperes/smc-organization-profile-integration`
- Slug: `smc-organization-profile-integration`
- Namespace: `SMC\OrganizationProfile`
- Runtime prefix: `smc_`
- Constants: `SMC_*`

## Ownership

SMC does not register shared content types.

- HWS Base Tools owns `organization`, `testimonial`, and `team-member`.
- SFPF Person Profile Integration owns only `book`.
- SMC owns organization ACF fields, migration, normalized reads, schema, REST output, shortcodes, and organization admin controls.

SMC requires HWS Base Tools 10.18.143 or newer and Advanced Custom Fields Pro.

## Organization Model

The local ACF group stores structured organization data:

- identity, legal names, alternate names, slogan, mission, and descriptions
- founding and dissolution dates
- founding location, multiple operating locations, postal addresses, and geo coordinates
- areas served and multiple contact points
- consolidated identity links and typed identifiers
- NAICS, ISIC, LEI, tax, VAT, DUNS, and Google Knowledge Graph identifiers
- founders, team members, employee counts, parent organizations, sub-organizations, departments, and memberships
- services, products, brands, credentials, awards, and demands
- press mentions, milestones, testimonials, reviews, and aggregate ratings when ratings exist
- logo, primary image, Knowledge Graph images, and public gallery

Media fields store WordPress attachment IDs. Schema is generated live and is never an editable ACF value.

## Schema

SMC builds a deterministic `@graph` from the current WordPress state:

- `WebSite`
- `WebPage`, `AboutPage`, or `ProfilePage`
- the selected Organization subtype
- related Place, Person, Offer, Product, Service, Brand, Credential, Demand, Article, Event, Review, and ImageObject nodes

Press mentions become `subjectOf` Article nodes. They are never added to `sameAs`.

Schema is injected on:

- organization single pages
- the homepage when enabled
- configured About and Profile pages

On managed pages, SMC can remove only the graph types it owns from Rank Math output while preserving unrelated nodes such as breadcrumbs.

## REST API

- `GET /wp-json/smc/v1/organizations`
- `GET /wp-json/smc/v1/organizations/primary`
- `GET /wp-json/smc/v1/organizations/{id}`
- `GET /wp-json/smc/v1/organizations/{id}/schema`

The standard WordPress organization endpoint also exposes a read-only `smc_schema` field.

## Shortcodes

```text
[smc_organization field="legal_name"]
[smc_organization field="url" link="true" target="_blank"]
[smc_organization field="gallery" format="json"]
[smc_organization_gallery columns="3" size="large"]
[smc_organization_schema format="json"]
[smc_organization_schema format="script"]
```

The legacy `[organization]` alias is enabled by default and can be disabled in Settings.

## Migration

The migrator preserves all legacy metadata and fills canonical fields only when their target is empty. It converts:

- SFPF subtitle, summary, and company-description values
- text alternate names into repeater rows
- individual social fields and URL textareas into identity-link rows
- legacy headquarters, address, and secondary locations into location rows
- one contact group into contact-point rows
- comma-separated brands, awards, and demands into structured rows
- old image arrays into attachment IDs
- free-form founding dates into typed dates

Migration reports are available in the SMC Overview tab.

## Admin and Runtime

The settings dashboard uses the grouped Hexa Core sidebar and loads tab panels through guarded AJAX requests. Plugin CSS is delivered through WordPress's existing style pipeline, and normalized organization, media, field, and schema values are cached for the duration of each request.

## Development

```bash
php tests/run.php
php tests/schema-unit.php
find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Run `tests/wordpress-integration.php` with `wp eval-file` after deployment.

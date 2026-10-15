<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Schema;

use Hexa\PluginCore\SchemaTools\SchemaPage;
use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Decides which pages show an organization graph and adds it to HexaWP
 * Core's shared schema block: each organization's own page, plus the
 * homepage, About and profile pages for the site's primary organization.
 */
final class SchemaInjector {
    public const SOURCE = 'smc-organization';
    public const PRIORITY = 10;

    public static function register(): void {
        SchemaPage::register( self::SOURCE, [ self::class, 'nodes' ], self::PRIORITY );
    }

    /** @return list<array<string,mixed>> */
    public static function nodes(): array {
        $context = self::current_context();
        return null === $context ? [] : OrganizationGraph::nodes( $context['organization_id'], $context['page_id'] );
    }

    /**
     * @return array{organization_id:int,page_id:int}|null
     */
    public static function current_context(): ?array {
        if ( is_admin() || is_feed() || ! Settings::enabled( Settings::SCHEMA_ENABLED ) ) {
            return null;
        }

        $page_id = absint( get_queried_object_id() );
        $organization_id = 0;

        if ( is_singular( 'organization' ) ) {
            $organization_id = $page_id;
        } elseif ( is_front_page() && Settings::enabled( Settings::INJECT_HOMEPAGE ) ) {
            $organization_id = OrganizationRepository::primary_id();
        } elseif (
            is_page()
            && in_array(
                $page_id,
                array_filter(
                    [
                        Settings::integer( Settings::ABOUT_PAGE ),
                        Settings::integer( Settings::PROFILE_PAGE ),
                    ]
                ),
                true
            )
        ) {
            $organization_id = OrganizationRepository::primary_id();
        }

        if ( $organization_id <= 0 ) {
            return null;
        }

        $organization = OrganizationRepository::data( $organization_id );
        if ( ! $organization || ! $organization->schema_enabled() ) {
            return null;
        }

        return [
            'organization_id' => $organization_id,
            'page_id'         => $page_id > 0 ? $page_id : $organization_id,
        ];
    }
}

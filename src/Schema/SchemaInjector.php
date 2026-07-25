<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Schema;

use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class SchemaInjector {
    private const OWNED_TYPES = [
        'Organization',
        'Corporation',
        'LocalBusiness',
        'ProfessionalService',
        'NGO',
        'NonprofitOrganization',
        'EducationalOrganization',
        'GovernmentOrganization',
        'MedicalOrganization',
        'SportsOrganization',
        'WebSite',
        'WebPage',
        'AboutPage',
        'ProfilePage',
    ];

    public static function register(): void {
        add_action( 'wp_head', [ self::class, 'render' ], 2 );
        add_filter( 'rank_math/json_ld', [ self::class, 'filter_rank_math' ], 1000 );
    }

    public static function render(): void {
        $context = self::current_context();
        if ( null === $context ) {
            return;
        }

        $graph = OrganizationGraph::build( $context['organization_id'], $context['page_id'] );
        if ( [] === $graph ) {
            return;
        }

        $json = wp_json_encode(
            $graph,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );

        if ( false === $json ) {
            return;
        }

        echo "\n<script type=\"application/ld+json\" id=\"smc-organization-schema\">"
            . $json
            . "</script>\n";
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

        $organization = OrganizationRepository::data( $organization_id );
        if ( ! $organization || ! $organization->schema_enabled() ) {
            return null;
        }

        return [
            'organization_id' => $organization_id,
            'page_id'         => $page_id > 0 ? $page_id : $organization_id,
        ];
    }

    public static function filter_rank_math( mixed $data ): mixed {
        if ( ! Settings::enabled( Settings::RANK_MATH_DEDUPE ) || null === self::current_context() || ! is_array( $data ) ) {
            return $data;
        }

        $filtered = [];
        foreach ( $data as $key => $node ) {
            if ( is_array( $node ) && isset( $node['@graph'] ) && is_array( $node['@graph'] ) ) {
                $node['@graph'] = self::filter_nodes( $node['@graph'] );
                if ( [] === $node['@graph'] ) {
                    continue;
                }
            } elseif ( is_array( $node ) && self::is_owned_node( $node ) ) {
                continue;
            }

            $filtered[ $key ] = $node;
        }

        return $filtered;
    }

    /**
     * @param array<int|string,mixed> $nodes
     * @return array<int|string,mixed>
     */
    private static function filter_nodes( array $nodes ): array {
        return array_values(
            array_filter(
                $nodes,
                static fn ( mixed $node ): bool => ! is_array( $node ) || ! self::is_owned_node( $node )
            )
        );
    }

    /** @param array<string,mixed> $node */
    private static function is_owned_node( array $node ): bool {
        $types = $node['@type'] ?? [];
        if ( ! is_array( $types ) ) {
            $types = [ $types ];
        }

        return [] !== array_intersect( self::OWNED_TYPES, array_map( 'strval', $types ) );
    }
}

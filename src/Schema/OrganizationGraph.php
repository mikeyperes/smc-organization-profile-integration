<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Schema;

use Hexa\PluginCore\SchemaTools\SchemaDocumentRenderer;
use Hexa\PluginCore\SchemaTools\SchemaNodes;
use Hexa\PluginCore\SchemaTools\SchemaSources;
use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One organization's page graph: the site, the page about the organization,
 * the organization and its related nodes. Built with HexaWP Core.
 */
final class OrganizationGraph {
    /** @return list<array<string,mixed>> */
    public static function nodes( int $organization_id, int $page_id = 0 ): array {
        $bundle = OrganizationSchema::bundle( $organization_id );
        if ( empty( $bundle['organization'] ) ) {
            return [];
        }

        $page_id = $page_id > 0 ? $page_id : $organization_id;
        $organization_ref = SchemaNodes::ref( SchemaIds::organization( $organization_id ) );
        $page = get_post( $page_id );
        $page_node = $page instanceof \WP_Post
            ? SchemaSources::post_page( $page, self::page_type( $organization_id, $page_id ), [ 'about' => $organization_ref, 'mainEntity' => $organization_ref ] )
            : [];

        $related = [];
        foreach ( $bundle['nodes'] as $node ) {
            if ( isset( $node['@id'] ) ) {
                $related[ (string) $node['@id'] ] = $node;
            }
        }
        ksort( $related );

        $nodes = [
            SchemaSources::website( SchemaIds::organization( $organization_id ) ),
            $page_node,
            $bundle['organization'],
            ...array_values( $related ),
        ];

        $filtered = apply_filters( 'smc_organization_schema_graph', [ '@context' => 'https://schema.org', '@graph' => $nodes ], $organization_id, $page_id );
        $graph = is_array( $filtered ) && isset( $filtered['@graph'] ) && is_array( $filtered['@graph'] ) ? $filtered['@graph'] : $nodes;
        return array_values( array_filter( $graph ) );
    }

    /**
     * The same graph as one document, for the shortcode, REST and admin previews.
     *
     * @return array<string,mixed>
     */
    public static function build( int $organization_id, int $page_id = 0 ): array {
        $nodes = self::nodes( $organization_id, $page_id );
        return [] === $nodes ? [] : ( new SchemaDocumentRenderer() )->normalize( [ '@context' => 'https://schema.org', '@graph' => $nodes ] );
    }

    private static function page_type( int $organization_id, int $page_id ): string {
        if ( $page_id === Settings::integer( Settings::ABOUT_PAGE ) ) {
            return 'AboutPage';
        }

        if ( $page_id === Settings::integer( Settings::PROFILE_PAGE ) || $page_id === $organization_id ) {
            return 'ProfilePage';
        }

        return 'WebPage';
    }
}

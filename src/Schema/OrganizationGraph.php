<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Schema;

use Hexa\PluginCore\SchemaTools\SchemaDocumentRenderer;
use SMC\OrganizationProfile\Data\Normalizer;
use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class OrganizationGraph {
    /** @return array<string,mixed> */
    public static function build( int $organization_id, int $page_id = 0 ): array {
        $bundle = OrganizationSchema::bundle( $organization_id );
        if ( empty( $bundle['organization'] ) ) {
            return [];
        }

        $page_id = $page_id > 0 ? $page_id : $organization_id;
        $organization_ref = [ '@id' => SchemaIds::organization( $organization_id ) ];
        $website = NodeFactory::clean(
            [
                '@type'     => 'WebSite',
                '@id'       => SchemaIds::website(),
                'url'       => trailingslashit( home_url( '/' ) ),
                'name'      => Normalizer::text( get_bloginfo( 'name' ) ),
                'publisher' => $organization_ref,
                'inLanguage'=> Normalizer::text( get_bloginfo( 'language' ) ),
            ]
        );

        $page_url = Normalizer::url( get_permalink( $page_id ) );
        if ( '' === $page_url ) {
            $page_url = Normalizer::url( home_url( '/' ) );
        }

        $page = NodeFactory::clean(
            [
                '@type'      => self::page_type( $organization_id, $page_id ),
                '@id'        => SchemaIds::webpage( $page_id ),
                'url'        => $page_url,
                'name'       => Normalizer::text( get_the_title( $page_id ) ?: get_bloginfo( 'name' ) ),
                'isPartOf'   => [ '@id' => SchemaIds::website() ],
                'about'      => $organization_ref,
                'mainEntity' => $organization_ref,
                'inLanguage' => Normalizer::text( get_bloginfo( 'language' ) ),
            ]
        );

        $related = [];
        foreach ( $bundle['nodes'] as $node ) {
            if ( isset( $node['@id'] ) ) {
                $related[ (string) $node['@id'] ] = $node;
            }
        }
        ksort( $related );

        $graph = [
            '@context' => 'https://schema.org',
            '@graph'   => [
                $website,
                $page,
                $bundle['organization'],
                ...array_values( $related ),
            ],
        ];

        $filtered = apply_filters( 'smc_organization_schema_graph', $graph, $organization_id, $page_id );

        return is_array( $filtered )
            ? ( new SchemaDocumentRenderer() )->normalize( $filtered )
            : [];
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

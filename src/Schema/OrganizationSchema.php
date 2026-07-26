<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Schema;

use SMC\OrganizationProfile\Data\MediaNormalizer;
use SMC\OrganizationProfile\Data\Normalizer;
use SMC\OrganizationProfile\Data\OrganizationData;
use SMC\OrganizationProfile\Data\OrganizationRepository;

defined( 'ABSPATH' ) || exit;

final class OrganizationSchema {
    private const TYPES = [
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
    ];

    /** @var array<int,array{organization:array<string,mixed>,nodes:list<array<string,mixed>>}> */
    private static array $bundle_cache = [];

    public static function clear_cache( int $post_id = 0 ): void {
        if ( $post_id > 0 ) {
            unset( self::$bundle_cache[ $post_id ] );
            return;
        }

        self::$bundle_cache = [];
    }

    /**
     * Compatibility entry point used by SFPF Person.
     *
     * @return array<string,mixed>
     */
    public static function build( int $post_id, bool $with_context = true ): array {
        $bundle = self::bundle( $post_id );
        $node = $bundle['organization'] ?? [];

        if ( $with_context && [] !== $node ) {
            $node = [ '@context' => 'https://schema.org' ] + $node;
        }

        return $node;
    }

    /**
     * @return array{organization:array<string,mixed>,nodes:list<array<string,mixed>>}
     */
    public static function bundle( int $post_id ): array {
        if ( array_key_exists( $post_id, self::$bundle_cache ) ) {
            return self::$bundle_cache[ $post_id ];
        }

        $data = OrganizationRepository::data( $post_id );
        if ( ! $data || ! $data->schema_enabled() ) {
            self::$bundle_cache[ $post_id ] = [ 'organization' => [], 'nodes' => [] ];
            return self::$bundle_cache[ $post_id ];
        }

        $organization_id = SchemaIds::organization( $post_id );
        $nodes = [];
        $references = [
            'location'      => [],
            'founder'       => [],
            'employee'      => [],
            'brand'         => [],
            'makesOffer'    => [],
            'hasCredential' => [],
            'seeks'         => [],
            'subjectOf'     => [],
            'review'        => [],
            'image'         => [],
        ];

        self::append_locations( $data, $organization_id, $nodes, $references );
        self::append_people( $data, $organization_id, $nodes, $references );
        self::append_commercial_nodes( $data, $organization_id, $nodes, $references );
        self::append_reputation_nodes( $data, $organization_id, $nodes, $references );
        self::append_media_nodes( $data, $organization_id, $nodes, $references );

        $organization = self::organization_node( $data, $organization_id, $references );

        self::$bundle_cache[ $post_id ] = [
            'organization' => $organization,
            'nodes'        => array_values( $nodes ),
        ];

        return self::$bundle_cache[ $post_id ];
    }

    /**
     * @param array<string,list<array<string,string>>> $references
     * @return array<string,mixed>
     */
    private static function organization_node( OrganizationData $data, string $id, array $references ): array {
        $type = $data->text( 'organization_type' );
        if ( ! in_array( $type, self::TYPES, true ) ) {
            $type = 'Organization';
        }

        $description = $data->text( 'short_description' );
        if ( '' === $description ) {
            $description = $data->text( 'company_description' );
        }
        if ( '' === $description ) {
            $description = Normalizer::text(
                get_post_field( 'post_excerpt', $data->id() ) ?: get_post_field( 'post_content', $data->id() )
            );
        }

        $identifiers = [];
        foreach ( $data->rows( 'identifiers' ) as $row ) {
            $identifier = NodeFactory::identifier( $row );
            if ( count( $identifier ) > 1 ) {
                $identifiers[] = $identifier;
            }
        }

        $kgid = $data->text( 'knowledge_graph_id' );
        if ( '' !== $kgid ) {
            $identifiers[] = [
                '@type'      => 'PropertyValue',
                'propertyID' => 'googleKgMID',
                'value'      => $kgid,
            ];
        }

        foreach ( [ 'lei_code' => 'LEI', 'tax_id' => 'Tax ID', 'vat_id' => 'VAT ID', 'duns_number' => 'DUNS' ] as $field => $label ) {
            $value = $data->text( $field );
            if ( '' !== $value ) {
                $identifiers[] = [ '@type' => 'PropertyValue', 'propertyID' => $label, 'value' => $value ];
            }
        }

        $contact_points = [];
        foreach ( $data->contact_points() as $row ) {
            $node = NodeFactory::contact_point( $row );
            if ( count( $node ) > 1 ) {
                $contact_points[] = $node;
            }
        }

        $areas = [];
        foreach ( $data->rows( 'area_served' ) as $row ) {
            $area = NodeFactory::area( $row );
            if ( count( $area ) > 1 ) {
                $areas[] = $area;
            }
        }

        $founding_location = NodeFactory::place(
            Normalizer::rows( $data->raw( 'founding_location' ) )[0] ?? [],
            SchemaIds::related( $id, 'place', 'founding' )
        );
        if ( count( $founding_location ) <= 2 ) {
            $founding_location = [];
        }

        $employee_count = self::employee_count( $data );
        $awards = Normalizer::strings( $data->raw( 'award_entries' ), [ 'name', 'value' ] );
        if ( [] === $awards ) {
            foreach ( $data->rows( 'award_entries' ) as $row ) {
                $name = Normalizer::text( $row['name'] ?? '' );
                if ( '' !== $name ) {
                    $awards[] = $name;
                }
            }
        }

        $node = NodeFactory::clean(
            [
                '@type'             => $type,
                '@id'               => $id,
                'name'              => $data->name(),
                'legalName'         => $data->text( 'legal_name' ) ?: $data->name(),
                'alternateName'     => $data->alternate_names(),
                'url'               => $data->url( 'url' ) ?: $data->permalink(),
                'mainEntityOfPage'  => [ '@id' => SchemaIds::webpage( $data->id() ) ],
                'description'       => $description,
                'slogan'            => $data->text( 'slogan' ),
                'foundingDate'      => $data->date( 'founding_date' ),
                'dissolutionDate'   => $data->date( 'dissolution_date' ),
                'foundingLocation'  => $founding_location,
                'location'          => $references['location'],
                'address'           => self::primary_address( $data ),
                'areaServed'        => $areas,
                'email'             => Normalizer::email( $data->raw( 'email' ) ),
                'telephone'         => $data->text( 'telephone' ),
                'contactPoint'      => $contact_points,
                'sameAs'            => array_column( $data->identity_links(), 'url' ),
                'identifier'        => $identifiers,
                'naics'             => $data->text( 'naics_code' ),
                'isicV4'            => $data->text( 'isic_code' ),
                'leiCode'           => $data->text( 'lei_code' ),
                'taxID'             => $data->text( 'tax_id' ),
                'vatID'             => $data->text( 'vat_id' ),
                'duns'              => $data->text( 'duns_number' ),
                'numberOfEmployees' => $employee_count,
                'founder'           => $references['founder'],
                'employee'          => $references['employee'],
                'parentOrganization'=> self::organization_reference( $data->relation_ids( 'parent_organization' )[0] ?? 0 ),
                'subOrganization'   => self::organization_references( $data->relation_ids( 'sub_organizations' ) ),
                'department'        => self::organization_references( $data->relation_ids( 'departments' ) ),
                'memberOf'          => self::organization_references( $data->relation_ids( 'member_of' ) ),
                'brand'             => $references['brand'],
                'makesOffer'        => $references['makesOffer'],
                'hasCredential'     => $references['hasCredential'],
                'award'             => array_values( array_unique( $awards ) ),
                'seeks'             => $references['seeks'],
                'subjectOf'         => $references['subjectOf'],
                'review'            => $references['review'],
                'aggregateRating'   => self::aggregate_rating( $data->relation_ids( 'testimonials' ) ),
                'logo'              => $references['logo'][0] ?? null,
                'image'             => $references['image'],
            ]
        );

        return apply_filters( 'smc_organization_schema_node', $node, $data->id(), $data );
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     * @param array<string,list<array<string,string>>> $references
     */
    private static function append_locations( OrganizationData $data, string $organization_id, array &$nodes, array &$references ): void {
        foreach ( $data->locations() as $index => $row ) {
            $id = SchemaIds::related( $organization_id, 'place', $index + 1 );
            $node = NodeFactory::place( $row, $id );
            if ( count( $node ) <= 2 ) {
                continue;
            }
            $nodes[ $id ] = $node;
            $references['location'][] = [ '@id' => $id ];
        }
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     * @param array<string,list<array<string,string>>> $references
     */
    private static function append_people( OrganizationData $data, string $organization_id, array &$nodes, array &$references ): void {
        foreach ( $data->relation_ids( 'founder_users' ) as $user_id ) {
            $id = SchemaIds::related( $organization_id, 'founder-user', $user_id );
            $node = NodeFactory::user_person( $user_id, $id );
            if ( [] !== $node ) {
                $nodes[ $id ] = $node;
                $references['founder'][] = [ '@id' => $id ];
            }
        }

        foreach ( $data->relation_ids( 'founders' ) as $post_id ) {
            $id = SchemaIds::related( $organization_id, 'founder', $post_id );
            $node = NodeFactory::team_person( $post_id, $id );
            if ( [] !== $node ) {
                $nodes[ $id ] = $node;
                $references['founder'][] = [ '@id' => $id ];
            }
        }

        foreach ( $data->relation_ids( 'team_members' ) as $post_id ) {
            $id = SchemaIds::related( $organization_id, 'employee', $post_id );
            $node = NodeFactory::team_person( $post_id, $id );
            if ( [] !== $node ) {
                $nodes[ $id ] = $node;
                $references['employee'][] = [ '@id' => $id ];
            }
        }
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     * @param array<string,list<array<string,string>>> $references
     */
    private static function append_commercial_nodes( OrganizationData $data, string $organization_id, array &$nodes, array &$references ): void {
        foreach ( [ 'services' => 'service', 'products' => 'product' ] as $field => $kind ) {
            foreach ( $data->rows( $field ) as $index => $row ) {
                $id = SchemaIds::related( $organization_id, $kind, $index + 1 );
                $node = NodeFactory::offer( $row, $kind, $id );
                if ( [] !== $node ) {
                    $nodes[ $id ] = $node;
                    $references['makesOffer'][] = [ '@id' => $id ];
                }
            }
        }

        foreach ( $data->rows( 'brand_entries' ) as $index => $row ) {
            $id = SchemaIds::related( $organization_id, 'brand', $index + 1 );
            $node = NodeFactory::brand( $row, $id );
            if ( [] !== $node ) {
                $nodes[ $id ] = $node;
                $references['brand'][] = [ '@id' => $id ];
            }
        }

        foreach ( $data->rows( 'credentials' ) as $index => $row ) {
            $id = SchemaIds::related( $organization_id, 'credential', $index + 1 );
            $node = NodeFactory::credential( $row, $id );
            if ( [] !== $node ) {
                $nodes[ $id ] = $node;
                $references['hasCredential'][] = [ '@id' => $id ];
            }
        }

        foreach ( $data->rows( 'seek_entries' ) as $index => $row ) {
            $id = SchemaIds::related( $organization_id, 'demand', $index + 1 );
            $node = NodeFactory::demand( $row, $id );
            if ( [] !== $node ) {
                $nodes[ $id ] = $node;
                $references['seeks'][] = [ '@id' => $id ];
            }
        }
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     * @param array<string,list<array<string,string>>> $references
     */
    private static function append_reputation_nodes( OrganizationData $data, string $organization_id, array &$nodes, array &$references ): void {
        foreach ( $data->rows( 'press_mentions' ) as $index => $row ) {
            $id = SchemaIds::related( $organization_id, 'article', $index + 1 );
            $node = NodeFactory::article( $row, $id, $organization_id );
            if ( [] !== $node ) {
                $nodes[ $id ] = $node;
                $references['subjectOf'][] = [ '@id' => $id ];
            }
        }

        foreach ( $data->rows( 'milestones' ) as $index => $row ) {
            $id = SchemaIds::related( $organization_id, 'milestone', $index + 1 );
            $node = NodeFactory::milestone( $row, $id, $organization_id );
            if ( [] !== $node ) {
                $nodes[ $id ] = $node;
                $references['subjectOf'][] = [ '@id' => $id ];
            }
        }

        foreach ( $data->relation_ids( 'testimonials' ) as $post_id ) {
            $id = SchemaIds::related( $organization_id, 'review', $post_id );
            $node = NodeFactory::review( $post_id, $id, $organization_id );
            if ( [] !== $node ) {
                $nodes[ $id ] = $node;
                $references['review'][] = [ '@id' => $id ];
            }
        }
    }

    /**
     * @param array<string,array<string,mixed>> $nodes
     * @param array<string,list<array<string,string>>> $references
     */
    private static function append_media_nodes( OrganizationData $data, string $organization_id, array &$nodes, array &$references ): void {
        $logo = $data->logo();
        if ( [] !== $logo ) {
            $id = SchemaIds::related( $organization_id, 'image', 'logo' );
            $nodes[ $id ] = MediaNormalizer::schema_image( $logo, $id );
            $references['logo'][] = [ '@id' => $id ];
            $references['image'][ $id ] = [ '@id' => $id ];
        }

        $images = [ $data->primary_image(), ...$data->gallery( 'knowledge_graph_images' ), ...$data->gallery() ];
        foreach ( $images as $index => $image ) {
            if ( [] === $image ) {
                continue;
            }
            $value = $image['id'] ?? $index + 1;
            $id = SchemaIds::related( $organization_id, 'image', $value );
            $nodes[ $id ] = MediaNormalizer::schema_image( $image, $id );
            $references['image'][ $id ] = [ '@id' => $id ];
        }

        $references['image'] = array_values( $references['image'] );
    }

    /** @return array<string,mixed>|int|null */
    private static function employee_count( OrganizationData $data ): array|int|null {
        $minimum = $data->number( 'employee_count_min' );
        $maximum = $data->number( 'employee_count_max' );
        $count = $data->number( 'number_of_employees' );

        if ( null !== $minimum || null !== $maximum ) {
            return NodeFactory::clean(
                [
                    '@type'    => 'QuantitativeValue',
                    'minValue' => $minimum,
                    'maxValue' => $maximum,
                    'value'    => $count,
                ]
            );
        }

        return is_int( $count ) ? $count : ( null !== $count ? (int) $count : null );
    }

    /** @return array<string,mixed> */
    private static function primary_address( OrganizationData $data ): array {
        $locations = $data->locations();
        foreach ( $locations as $location ) {
            if ( 'headquarters' === ( $location['location_type'] ?? '' ) ) {
                return NodeFactory::address( $location );
            }
        }

        return [] !== $locations ? NodeFactory::address( $locations[0] ) : [];
    }

    /** @return array<string,string>|null */
    private static function organization_reference( int $post_id ): ?array {
        return OrganizationRepository::is_public_organization( $post_id )
            ? [ '@id' => SchemaIds::organization( $post_id ) ]
            : null;
    }

    /** @param list<int> $post_ids @return list<array<string,string>> */
    private static function organization_references( array $post_ids ): array {
        return array_values(
            array_filter(
                array_map( [ self::class, 'organization_reference' ], $post_ids )
            )
        );
    }

    /** @param list<int> $testimonial_ids @return array<string,mixed> */
    private static function aggregate_rating( array $testimonial_ids ): array {
        $ratings = [];
        foreach ( $testimonial_ids as $post_id ) {
            $rating = Normalizer::number( get_post_meta( $post_id, 'rating', true ) );
            if ( null !== $rating ) {
                $ratings[] = (float) $rating;
            }
        }

        if ( [] === $ratings ) {
            return [];
        }

        return [
            '@type'       => 'AggregateRating',
            'ratingValue' => round( array_sum( $ratings ) / count( $ratings ), 2 ),
            'ratingCount' => count( $ratings ),
            'bestRating'  => 5,
            'worstRating' => 1,
        ];
    }
}

<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Acf;

defined( 'ABSPATH' ) || exit;

final class OrganizationFields {
    public const GROUP_KEY = 'group_smc_organization_profile';

    /** @var list<array<string,mixed>>|null */
    private static ?array $fields = null;

    public static function register_hooks(): void {
        \Hexa\PluginCore\Fields\Hooks::on( 'update_value/name=knowledge_graph_id', [ self::class, 'sanitize_kgid' ], 10, 3 );
    }

    /** @return array<string,mixed> */
    public static function definition(): array {
        return [
            'key'                   => self::GROUP_KEY,
            'title'                 => 'SMC Organization Profile',
            'fields'                => self::fields(),
            'location'              => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'organization' ] ] ],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
            'show_in_rest'          => 1,
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function fields(): array {
        if ( null !== self::$fields ) {
            return self::$fields;
        }

        self::$fields = [
            FieldRegistry::tab( 'tab_identity', 'Identity' ),
            FieldRegistry::toggle( 'schema_enabled', 'Include in Schema', 'schema_enabled', [
                'instructions' => 'Controls live SMC schema output for this organization.',
            ] ),
            FieldRegistry::select( 'organization_type', 'Schema Type', 'organization_type', self::organization_types(), [
                'default_value' => 'Organization',
                'wrapper' => [ 'width' => '33' ],
            ] ),
            FieldRegistry::text( 'legal_name', 'Legal Name', 'legal_name', [
                'wrapper' => [ 'width' => '67' ],
            ] ),
            FieldRegistry::repeater( 'alternate_names', 'Alternate Names', 'alternate_name_entries', [
                FieldRegistry::text( 'alternate_name_value', 'Name', 'name' ),
            ], [ 'layout' => 'table', 'button_label' => 'Add Name' ] ),
            FieldRegistry::text( 'slogan', 'Slogan', 'slogan', [
                'instructions' => '[smc_organization field="slogan"]',
            ] ),
            FieldRegistry::textarea( 'short_description', 'Short Description', 'short_description', [
                'rows' => 3,
                'instructions' => 'Plain-language summary used in schema and previews.',
            ] ),
            FieldRegistry::wysiwyg( 'mission_statement', 'Mission Statement', 'mission_statement' ),
            FieldRegistry::wysiwyg( 'company_description', 'Company Description', 'company_description', [
                'media_upload' => 1,
            ] ),
            FieldRegistry::url( 'url', 'Canonical Website URL', 'url' ),

            FieldRegistry::tab( 'tab_footprint', 'Dates and Footprint' ),
            FieldRegistry::date( 'founding_date', 'Founding Date', 'founding_date', [
                'wrapper' => [ 'width' => '50' ],
            ] ),
            FieldRegistry::date( 'dissolution_date', 'Dissolution Date', 'dissolution_date', [
                'wrapper' => [ 'width' => '50' ],
            ] ),
            FieldRegistry::group( 'founding_location', 'Founding Location', 'founding_location', self::place_fields( 'founding' ) ),
            FieldRegistry::repeater( 'locations', 'Locations', 'locations', self::location_fields(), [
                'button_label' => 'Add Location',
            ] ),
            FieldRegistry::repeater( 'area_served', 'Areas Served', 'area_served', [
                FieldRegistry::select( 'area_type', 'Type', 'type', [
                    'Country'            => 'Country',
                    'State'              => 'State',
                    'City'               => 'City',
                    'AdministrativeArea' => 'Administrative Area',
                    'Place'              => 'Place',
                ], [ 'wrapper' => [ 'width' => '25' ] ] ),
                FieldRegistry::text( 'area_name', 'Name', 'name', [ 'wrapper' => [ 'width' => '45' ] ] ),
                FieldRegistry::url( 'area_same_as', 'Reference URL', 'same_as', [ 'wrapper' => [ 'width' => '30' ] ] ),
            ], [ 'layout' => 'table', 'button_label' => 'Add Area' ] ),

            FieldRegistry::tab( 'tab_contact', 'Contact' ),
            FieldRegistry::email( 'email', 'Primary Email', 'email', [ 'wrapper' => [ 'width' => '50' ] ] ),
            FieldRegistry::text( 'telephone', 'Primary Telephone', 'telephone', [ 'wrapper' => [ 'width' => '50' ] ] ),
            FieldRegistry::repeater( 'contact_points', 'Contact Points', 'contact_points', [
                FieldRegistry::text( 'contact_type', 'Contact Type', 'contact_type', [ 'wrapper' => [ 'width' => '25' ] ] ),
                FieldRegistry::email( 'contact_email', 'Email', 'email', [ 'wrapper' => [ 'width' => '25' ] ] ),
                FieldRegistry::text( 'contact_telephone', 'Telephone', 'telephone', [ 'wrapper' => [ 'width' => '25' ] ] ),
                FieldRegistry::url( 'contact_url', 'URL', 'url', [ 'wrapper' => [ 'width' => '25' ] ] ),
                FieldRegistry::text( 'contact_product', 'Product Supported', 'product_supported', [ 'wrapper' => [ 'width' => '34' ] ] ),
                FieldRegistry::text( 'contact_hours', 'Hours Available', 'hours_available', [ 'wrapper' => [ 'width' => '33' ] ] ),
                FieldRegistry::text( 'contact_languages', 'Languages', 'available_languages', [
                    'instructions' => 'Comma-separated language names.',
                    'wrapper' => [ 'width' => '33' ],
                ] ),
            ], [ 'button_label' => 'Add Contact Point' ] ),

            FieldRegistry::tab( 'tab_identifiers', 'Links and Identifiers' ),
            FieldRegistry::repeater( 'identity_links', 'Identity Links', 'identity_links', [
                FieldRegistry::select( 'identity_platform', 'Platform', 'platform', self::identity_platforms(), [
                    'wrapper' => [ 'width' => '25' ],
                ] ),
                FieldRegistry::text( 'identity_label', 'Label', 'label', [ 'wrapper' => [ 'width' => '25' ] ] ),
                FieldRegistry::url( 'identity_url', 'URL', 'url', [ 'wrapper' => [ 'width' => '50' ] ] ),
            ], [
                'instructions' => 'Only URLs that identify the same organization become schema sameAs values.',
                'button_label' => 'Add Identity Link',
                'layout' => 'table',
            ] ),
            FieldRegistry::text( 'knowledge_graph_id', 'Google Knowledge Graph ID', 'knowledge_graph_id', [
                'placeholder' => '/g/11abc123',
            ] ),
            FieldRegistry::repeater( 'identifiers', 'Identifiers', 'identifiers', [
                FieldRegistry::text( 'identifier_property', 'Property', 'property_id', [ 'wrapper' => [ 'width' => '30' ] ] ),
                FieldRegistry::text( 'identifier_value', 'Value', 'value', [ 'wrapper' => [ 'width' => '40' ] ] ),
                FieldRegistry::url( 'identifier_url', 'Reference URL', 'url', [ 'wrapper' => [ 'width' => '30' ] ] ),
            ], [ 'layout' => 'table', 'button_label' => 'Add Identifier' ] ),
            FieldRegistry::text( 'naics_code', 'NAICS Code', 'naics_code', [ 'wrapper' => [ 'width' => '33' ] ] ),
            FieldRegistry::text( 'isic_code', 'ISIC Code', 'isic_code', [ 'wrapper' => [ 'width' => '33' ] ] ),
            FieldRegistry::text( 'lei_code', 'Legal Entity Identifier', 'lei_code', [ 'wrapper' => [ 'width' => '34' ] ] ),
            FieldRegistry::text( 'tax_id', 'Tax ID', 'tax_id', [ 'wrapper' => [ 'width' => '33' ] ] ),
            FieldRegistry::text( 'vat_id', 'VAT ID', 'vat_id', [ 'wrapper' => [ 'width' => '33' ] ] ),
            FieldRegistry::text( 'duns_number', 'DUNS Number', 'duns_number', [ 'wrapper' => [ 'width' => '34' ] ] ),

            FieldRegistry::tab( 'tab_people', 'People and Relationships' ),
            FieldRegistry::number( 'number_of_employees', 'Employee Count', 'number_of_employees', [
                'min' => 0,
                'step' => 1,
                'wrapper' => [ 'width' => '34' ],
            ] ),
            FieldRegistry::number( 'employee_count_min', 'Employee Count Minimum', 'employee_count_min', [
                'min' => 0,
                'step' => 1,
                'wrapper' => [ 'width' => '33' ],
            ] ),
            FieldRegistry::number( 'employee_count_max', 'Employee Count Maximum', 'employee_count_max', [
                'min' => 0,
                'step' => 1,
                'wrapper' => [ 'width' => '33' ],
            ] ),
            FieldRegistry::user( 'founder_users', 'Founder Users', 'founder_users' ),
            FieldRegistry::relationship( 'founders', 'Founder Team Members', 'founders', [ 'team-member' ] ),
            FieldRegistry::relationship( 'team_members', 'Team Members', 'team_members', [ 'team-member' ] ),
            FieldRegistry::relationship( 'parent_organization', 'Parent Organization', 'parent_organization', [ 'organization' ], [
                'max' => 1,
            ] ),
            FieldRegistry::relationship( 'sub_organizations', 'Sub-Organizations', 'sub_organizations', [ 'organization' ] ),
            FieldRegistry::relationship( 'departments', 'Departments', 'departments', [ 'organization' ] ),
            FieldRegistry::relationship( 'member_of', 'Member Of', 'member_of', [ 'organization' ] ),

            FieldRegistry::tab( 'tab_offerings', 'Offerings' ),
            FieldRegistry::repeater( 'services', 'Services', 'services', self::offer_fields( 'service' ), [
                'button_label' => 'Add Service',
            ] ),
            FieldRegistry::repeater( 'products', 'Products', 'products', self::offer_fields( 'product' ), [
                'button_label' => 'Add Product',
            ] ),
            FieldRegistry::repeater( 'brands', 'Brands', 'brand_entries', [
                FieldRegistry::text( 'brand_name', 'Name', 'name', [ 'wrapper' => [ 'width' => '40' ] ] ),
                FieldRegistry::url( 'brand_url', 'URL', 'url', [ 'wrapper' => [ 'width' => '40' ] ] ),
                FieldRegistry::image( 'brand_logo', 'Logo', 'logo', [ 'wrapper' => [ 'width' => '20' ] ] ),
            ], [ 'layout' => 'table', 'button_label' => 'Add Brand' ] ),
            FieldRegistry::repeater( 'credentials', 'Credentials', 'credentials', [
                FieldRegistry::text( 'credential_name', 'Name', 'name', [ 'wrapper' => [ 'width' => '35' ] ] ),
                FieldRegistry::text( 'credential_category', 'Category', 'category', [ 'wrapper' => [ 'width' => '25' ] ] ),
                FieldRegistry::text( 'credential_recognized_by', 'Recognized By', 'recognized_by', [ 'wrapper' => [ 'width' => '40' ] ] ),
                FieldRegistry::date( 'credential_issued', 'Issued Date', 'issued_date', [ 'wrapper' => [ 'width' => '25' ] ] ),
                FieldRegistry::date( 'credential_expires', 'Expiration Date', 'expires_date', [ 'wrapper' => [ 'width' => '25' ] ] ),
                FieldRegistry::url( 'credential_url', 'Reference URL', 'url', [ 'wrapper' => [ 'width' => '50' ] ] ),
            ], [ 'button_label' => 'Add Credential' ] ),
            FieldRegistry::repeater( 'awards', 'Awards', 'award_entries', [
                FieldRegistry::text( 'award_name', 'Name', 'name', [ 'wrapper' => [ 'width' => '40' ] ] ),
                FieldRegistry::text( 'award_issuer', 'Issuer', 'issuer', [ 'wrapper' => [ 'width' => '30' ] ] ),
                FieldRegistry::date( 'award_date', 'Date', 'date', [ 'wrapper' => [ 'width' => '15' ] ] ),
                FieldRegistry::url( 'award_url', 'URL', 'url', [ 'wrapper' => [ 'width' => '15' ] ] ),
            ], [ 'layout' => 'table', 'button_label' => 'Add Award' ] ),
            FieldRegistry::repeater( 'seeks', 'Seeks', 'seek_entries', [
                FieldRegistry::text( 'seek_name', 'Name', 'name', [ 'wrapper' => [ 'width' => '30' ] ] ),
                FieldRegistry::textarea( 'seek_description', 'Description', 'description', [ 'rows' => 2, 'wrapper' => [ 'width' => '45' ] ] ),
                FieldRegistry::url( 'seek_url', 'URL', 'url', [ 'wrapper' => [ 'width' => '25' ] ] ),
            ], [ 'button_label' => 'Add Demand' ] ),

            FieldRegistry::tab( 'tab_reputation', 'Press and Reputation' ),
            FieldRegistry::repeater( 'press_mentions', 'Press Mentions', 'press_mentions', [
                FieldRegistry::text( 'press_headline', 'Headline', 'headline', [ 'wrapper' => [ 'width' => '40' ] ] ),
                FieldRegistry::url( 'press_url', 'Article URL', 'url', [ 'wrapper' => [ 'width' => '40' ] ] ),
                FieldRegistry::date( 'press_date', 'Published Date', 'date_published', [ 'wrapper' => [ 'width' => '20' ] ] ),
                FieldRegistry::text( 'press_publisher', 'Publisher', 'publisher', [ 'wrapper' => [ 'width' => '50' ] ] ),
                FieldRegistry::text( 'press_author', 'Author', 'author', [ 'wrapper' => [ 'width' => '50' ] ] ),
            ], [
                'instructions' => 'Press becomes schema subjectOf Article nodes, never sameAs.',
                'button_label' => 'Add Press Mention',
            ] ),
            FieldRegistry::repeater( 'milestones', 'Milestones', 'milestones', [
                FieldRegistry::text( 'milestone_name', 'Name', 'name', [ 'wrapper' => [ 'width' => '35' ] ] ),
                FieldRegistry::date( 'milestone_date', 'Date', 'date', [ 'wrapper' => [ 'width' => '20' ] ] ),
                FieldRegistry::textarea( 'milestone_description', 'Description', 'description', [ 'rows' => 2, 'wrapper' => [ 'width' => '30' ] ] ),
                FieldRegistry::url( 'milestone_url', 'URL', 'url', [ 'wrapper' => [ 'width' => '15' ] ] ),
            ], [ 'button_label' => 'Add Milestone' ] ),
            FieldRegistry::relationship( 'testimonials', 'Testimonials', 'testimonials', [ 'testimonial' ] ),

            FieldRegistry::tab( 'tab_media', 'Media' ),
            FieldRegistry::image( 'logo', 'Logo', 'logo', [
                'instructions' => 'Use the official uncropped or square logo. Stored as an attachment ID.',
                'wrapper' => [ 'width' => '50' ],
            ] ),
            FieldRegistry::image( 'primary_image', 'Primary Image', 'primary_image', [
                'instructions' => 'Primary representative organization image. Stored as an attachment ID.',
                'wrapper' => [ 'width' => '50' ],
            ] ),
            FieldRegistry::gallery( 'knowledge_graph_images', 'Knowledge Graph Images', 'knowledge_graph_images', [
                'max' => 20,
            ] ),
            FieldRegistry::gallery( 'gallery', 'Public Gallery', 'gallery', [
                'max' => 60,
                'instructions' => '[smc_organization_gallery] or [smc_organization field="gallery" format="json"]',
            ] ),
        ];

        return self::$fields;
    }

    /** @return list<string> */
    public static function field_labels(): array {
        $labels = [];
        foreach ( self::fields() as $field ) {
            if ( 'tab' === (string) ( $field['type'] ?? '' ) || empty( $field['label'] ) ) {
                continue;
            }
            $labels[] = (string) $field['label'];
        }
        return $labels;
    }

    public static function sanitize_kgid( mixed $value, mixed $post_id, mixed $field ): mixed {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        $value = trim( $value );
        if ( preg_match( '/(?:[?&]kgmid=)([^&]+)/i', $value, $matches ) ) {
            $value = rawurldecode( $matches[1] );
        }

        if ( preg_match( '#^/?([gm])/([A-Za-z0-9_-]+)$#', $value, $matches ) ) {
            return '/' . $matches[1] . '/' . $matches[2];
        }

        return sanitize_text_field( $value );
    }

    /** @return array<string,string> */
    private static function organization_types(): array {
        return [
            'Organization'            => 'Organization',
            'Corporation'             => 'Corporation',
            'LocalBusiness'           => 'Local Business',
            'ProfessionalService'     => 'Professional Service',
            'NGO'                     => 'NGO',
            'NonprofitOrganization'   => 'Nonprofit Organization',
            'EducationalOrganization' => 'Educational Organization',
            'GovernmentOrganization'  => 'Government Organization',
            'MedicalOrganization'     => 'Medical Organization',
            'SportsOrganization'      => 'Sports Organization',
        ];
    }

    /** @return array<string,string> */
    private static function identity_platforms(): array {
        return [
            'linkedin'   => 'LinkedIn',
            'facebook'   => 'Facebook',
            'instagram'  => 'Instagram',
            'x'          => 'X',
            'youtube'    => 'YouTube',
            'tiktok'     => 'TikTok',
            'github'     => 'GitHub',
            'wikipedia'  => 'Wikipedia',
            'wikidata'   => 'Wikidata',
            'crunchbase' => 'Crunchbase',
            'other'      => 'Other',
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function place_fields( string $prefix, bool $include_name = true ): array {
        return [
            ...(
                $include_name
                    ? [ FieldRegistry::text( $prefix . '_name', 'Name', 'name' ) ]
                    : []
            ),
            FieldRegistry::text( $prefix . '_street', 'Street Address', 'street_address' ),
            FieldRegistry::text( $prefix . '_city', 'City', 'address_locality', [ 'wrapper' => [ 'width' => '30' ] ] ),
            FieldRegistry::text( $prefix . '_region', 'State / Region', 'address_region', [ 'wrapper' => [ 'width' => '25' ] ] ),
            FieldRegistry::text( $prefix . '_postal', 'Postal Code', 'postal_code', [ 'wrapper' => [ 'width' => '20' ] ] ),
            FieldRegistry::text( $prefix . '_country', 'Country', 'address_country', [ 'wrapper' => [ 'width' => '25' ] ] ),
            FieldRegistry::number( $prefix . '_latitude', 'Latitude', 'latitude', [ 'step' => 'any', 'wrapper' => [ 'width' => '25' ] ] ),
            FieldRegistry::number( $prefix . '_longitude', 'Longitude', 'longitude', [ 'step' => 'any', 'wrapper' => [ 'width' => '25' ] ] ),
            FieldRegistry::url( $prefix . '_same_as', 'Reference URL', 'same_as', [ 'wrapper' => [ 'width' => '50' ] ] ),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function location_fields(): array {
        return [
            FieldRegistry::text( 'location_name', 'Location Name', 'name', [ 'wrapper' => [ 'width' => '40' ] ] ),
            FieldRegistry::select( 'location_type', 'Location Type', 'location_type', [
                'headquarters' => 'Headquarters',
                'office'       => 'Office',
                'store'        => 'Store',
                'facility'     => 'Facility',
                'other'        => 'Other',
            ], [ 'wrapper' => [ 'width' => '30' ] ] ),
            FieldRegistry::url( 'location_url', 'URL', 'url', [ 'wrapper' => [ 'width' => '30' ] ] ),
            ...self::place_fields( 'location', false ),
            FieldRegistry::email( 'location_email', 'Email', 'email', [ 'wrapper' => [ 'width' => '50' ] ] ),
            FieldRegistry::text( 'location_phone', 'Telephone', 'telephone', [ 'wrapper' => [ 'width' => '50' ] ] ),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function offer_fields( string $kind ): array {
        return [
            FieldRegistry::text( $kind . '_name', 'Name', 'name', [ 'wrapper' => [ 'width' => '35' ] ] ),
            FieldRegistry::text( $kind . '_category', 'Category', 'category', [ 'wrapper' => [ 'width' => '25' ] ] ),
            FieldRegistry::url( $kind . '_url', 'URL', 'url', [ 'wrapper' => [ 'width' => '40' ] ] ),
            FieldRegistry::textarea( $kind . '_description', 'Description', 'description', [ 'rows' => 2 ] ),
            FieldRegistry::text( $kind . '_sku', 'SKU', 'sku', [
                'wrapper' => [ 'width' => '20' ],
                'conditional_logic' => $kind === 'product' ? 0 : [ [ [ 'field' => 'field_smc_org_' . $kind . '_name', 'operator' => '==', 'value' => '__never__' ] ] ],
            ] ),
            FieldRegistry::text( $kind . '_brand', 'Brand', 'brand', [ 'wrapper' => [ 'width' => '20' ] ] ),
            FieldRegistry::text( $kind . '_area', 'Area Served', 'area_served', [ 'wrapper' => [ 'width' => '20' ] ] ),
            FieldRegistry::number( $kind . '_price', 'Price', 'price', [ 'step' => 'any', 'wrapper' => [ 'width' => '15' ] ] ),
            FieldRegistry::text( $kind . '_currency', 'Currency', 'price_currency', [ 'placeholder' => 'USD', 'wrapper' => [ 'width' => '10' ] ] ),
            FieldRegistry::select( $kind . '_availability', 'Availability', 'availability', [
                'InStock'     => 'In Stock',
                'PreOrder'    => 'Pre-order',
                'OnlineOnly'  => 'Online Only',
                'SoldOut'     => 'Sold Out',
                'Discontinued'=> 'Discontinued',
            ], [ 'wrapper' => [ 'width' => '15' ] ] ),
        ];
    }
}

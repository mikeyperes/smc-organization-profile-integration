<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Shortcodes;

use Hexa\PluginCore\SchemaTools\SchemaDocumentRenderer;
use SMC\OrganizationProfile\Data\Normalizer;
use SMC\OrganizationProfile\Data\OrganizationData;
use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Presentation\OrganizationProfileRenderer;
use SMC\OrganizationProfile\Schema\OrganizationGraph;
use SMC\OrganizationProfile\Schema\OrganizationSchema;

defined( 'ABSPATH' ) || exit;

final class OrganizationShortcode {
    private const PUBLIC_FIELDS = [
        'name',
        'title',
        'permalink',
        'organization_type',
        'legal_name',
        'alternate_names',
        'slogan',
        'short_description',
        'mission_statement',
        'company_description',
        'url',
        'founding_date',
        'dissolution_date',
        'locations',
        'area_served',
        'email',
        'telephone',
        'contact_points',
        'identity_links',
        'knowledge_graph_id',
        'identifiers',
        'naics_code',
        'isic_code',
        'lei_code',
        'number_of_employees',
        'founder_users',
        'founders',
        'team_members',
        'parent_organization',
        'sub_organizations',
        'departments',
        'member_of',
        'services',
        'products',
        'brand_entries',
        'credentials',
        'award_entries',
        'seek_entries',
        'press_mentions',
        'milestones',
        'testimonials',
        'logo',
        'primary_image',
        'knowledge_graph_images',
        'gallery',
        'schema',
        'graph',
    ];

    private const LEGACY_FIELDS = [
        'sub_title',
        'subtitle',
        'short_summary',
        'company_info',
        'headquarters_location',
        'headquarters_wikipedia',
        'secondary_location',
        'url_facebook',
        'url_instagram',
        'url_linkedin',
        'url_x',
        'url_youtube',
        'url_tiktok',
        'url_github',
        'url_wikipedia',
        'url_crunchbase',
        'social_urls',
        'sameas_urls',
        'brands',
        'awards',
        'seeks',
        'address_street',
        'address_city',
        'address_region',
        'address_postal',
        'address_country',
        'image_cropped',
    ];

    /** @param array<string,mixed>|string $attributes */
    public static function render( array|string $attributes = [], ?string $content = null, string $tag = 'smc_organization' ): string {
        unset( $content );
        $tag = 'organization' === $tag ? 'organization' : 'smc_organization';
        $attributes = shortcode_atts(
            [
                'id'      => 0,
                'field'   => 'organization' === $tag ? '' : 'name',
                'format'  => 'html',
                'link'    => 'false',
                'target'  => '',
                'size'    => 'large',
                'columns' => 3,
                'pretty'  => 'false',
                'action'  => '',
                'show_title' => 'true',
                'show_logo'  => 'true',
                'heading'    => 'h1',
            ],
            is_array( $attributes ) ? $attributes : [],
            $tag
        );

        $organization_id = OrganizationRepository::resolve_id( $attributes['id'] );
        $data = $organization_id > 0 ? OrganizationRepository::data( $organization_id ) : null;
        if ( ! $data ) {
            return '';
        }

        if ( 'display_profile' === $attributes['action'] ) {
            return OrganizationProfileRenderer::render( $data, $attributes );
        }

        $field = sanitize_key( (string) $attributes['field'] );
        if ( 'display_gallery' === $attributes['action'] ) {
            $field = 'gallery';
        }
        if (
            ! in_array( $field, self::PUBLIC_FIELDS, true )
            && ! in_array( $field, self::LEGACY_FIELDS, true )
        ) {
            return '';
        }

        if ( in_array( $field, [ 'gallery', 'knowledge_graph_images' ], true ) ) {
            $format = sanitize_key( (string) $attributes['format'] );
            if ( 'html' !== $format ) {
                return self::format(
                    $data->gallery( $field, sanitize_key( (string) $attributes['size'] ) ),
                    $field,
                    $attributes
                );
            }

            return GalleryRenderer::render(
                $data,
                $field,
                sanitize_key( (string) $attributes['size'] ),
                absint( $attributes['columns'] )
            );
        }

        $value = self::value( $data, $field );
        return self::format( $value, $field, $attributes );
    }

    /** @param array<string,mixed>|string $attributes */
    public static function render_gallery( array|string $attributes = [] ): string {
        $attributes = shortcode_atts(
            [ 'id' => 0, 'field' => 'gallery', 'size' => 'large', 'columns' => 3 ],
            is_array( $attributes ) ? $attributes : [],
            'smc_organization_gallery'
        );

        $organization_id = OrganizationRepository::resolve_id( $attributes['id'] );
        $data = $organization_id > 0 ? OrganizationRepository::data( $organization_id ) : null;
        if ( ! $data ) {
            return '';
        }

        $field = in_array( $attributes['field'], [ 'gallery', 'knowledge_graph_images' ], true )
            ? $attributes['field']
            : 'gallery';

        return GalleryRenderer::render(
            $data,
            $field,
            sanitize_key( (string) $attributes['size'] ),
            absint( $attributes['columns'] )
        );
    }

    /** @param array<string,mixed>|string $attributes */
    public static function render_schema( array|string $attributes = [] ): string {
        $attributes = shortcode_atts(
            [ 'id' => 0, 'format' => 'json' ],
            is_array( $attributes ) ? $attributes : [],
            'smc_organization_schema'
        );

        $organization_id = OrganizationRepository::resolve_id( $attributes['id'] );
        $data = $organization_id > 0 ? OrganizationRepository::data( $organization_id ) : null;
        if ( ! $data ) {
            return '';
        }

        $graph = OrganizationGraph::build( $data->id(), get_queried_object_id() ?: $data->id() );
        $renderer = new SchemaDocumentRenderer();
        $json = $renderer->json( $graph );
        if ( '' === $json ) {
            return '';
        }

        if ( 'script' === $attributes['format'] ) {
            return $renderer->script( $graph, '', 'smc-organization-schema-shortcode' );
        }

        return esc_html( $json );
    }

    private static function value( OrganizationData $data, string $field ): mixed {
        return match ( $field ) {
            'name', 'title' => $data->name(),
            'permalink' => $data->permalink(),
            'alternate_names' => $data->alternate_names(),
            'locations' => $data->locations(),
            'contact_points' => $data->contact_points(),
            'identity_links' => $data->identity_links(),
            'logo' => $data->logo(),
            'primary_image' => $data->primary_image(),
            'gallery', 'knowledge_graph_images' => $data->gallery( $field ),
            'schema' => OrganizationSchema::build( $data->id(), true ),
            'graph' => OrganizationGraph::build( $data->id(), get_queried_object_id() ?: $data->id() ),
            default => in_array( $field, self::LEGACY_FIELDS, true )
                ? self::legacy_value( $data, $field )
                : $data->raw( $field ),
        };
    }

    private static function legacy_value( OrganizationData $data, string $field ): mixed {
        $locations = $data->locations();
        $primary = $locations[0] ?? [];

        if ( str_starts_with( $field, 'url_' ) ) {
            $platform = substr( $field, 4 );
            foreach ( $data->identity_links() as $link ) {
                if ( $platform === $link['platform'] ) {
                    return $link['url'];
                }
            }
            return '';
        }

        return match ( $field ) {
            'sub_title', 'subtitle' => $data->text( 'slogan' ),
            'short_summary' => $data->text( 'short_description' ),
            'company_info' => $data->raw( 'company_description' ),
            'headquarters_location' => $primary['name'] ?? $primary['address_locality'] ?? '',
            'headquarters_wikipedia' => $primary['same_as'] ?? $primary['wikipedia_url'] ?? '',
            'secondary_location' => $locations[1]['name'] ?? $locations[1]['address_locality'] ?? '',
            'social_urls', 'sameas_urls' => array_column( $data->identity_links(), 'url' ),
            'brands' => Normalizer::strings( $data->raw( 'brand_entries' ), [ 'name', 'value' ] ),
            'awards' => Normalizer::strings( $data->raw( 'award_entries' ), [ 'name', 'value' ] ),
            'seeks' => Normalizer::strings( $data->raw( 'seek_entries' ), [ 'name', 'value' ] ),
            'address_street' => $primary['street_address'] ?? '',
            'address_city' => $primary['address_locality'] ?? '',
            'address_region' => $primary['address_region'] ?? '',
            'address_postal' => $primary['postal_code'] ?? '',
            'address_country' => $primary['address_country'] ?? '',
            'image_cropped' => $data->logo(),
            default => $data->raw( $field ),
        };
    }

    /** @param array<string,mixed> $attributes */
    private static function format( mixed $value, string $field, array $attributes ): string {
        $format = sanitize_key( (string) $attributes['format'] );

        if ( 'json' === $format ) {
            $json = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            return false === $json ? '' : esc_html( $json );
        }

        if ( 'count' === $format ) {
            return is_countable( $value ) ? (string) count( $value ) : ( Normalizer::present( $value ) ? '1' : '0' );
        }

        if ( 'urls' === $format ) {
            if ( ! is_array( $value ) ) {
                return '';
            }

            $urls = [];
            foreach ( $value as $item ) {
                $url = Normalizer::url( is_array( $item ) ? ( $item['url'] ?? '' ) : $item );
                if ( '' !== $url ) {
                    $urls[] = $url;
                }
            }

            return esc_html( implode( "\n", array_values( array_unique( $urls ) ) ) );
        }

        if ( is_array( $value ) ) {
            if ( isset( $value['url'] ) ) {
                $value = $value['url'];
            } else {
                $value = Normalizer::strings( $value );
                $value = implode( ' | ', $value );
            }
        }

        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = (string) $value;
        if ( '' === trim( $value ) ) {
            return '';
        }

        $url = Normalizer::url( $value );
        $label = $url;
        $pretty = filter_var( $attributes['pretty'], FILTER_VALIDATE_BOOLEAN );
        $link = filter_var( $attributes['link'], FILTER_VALIDATE_BOOLEAN );

        if ( '' !== $url && $pretty ) {
            $label = preg_replace( '#^https?://#', '', $url ) ?: $url;
            $label = rtrim( $label, '/' );
        }
        if ( '' !== $url && ( $link || 'link' === $format ) ) {
            $target = '_blank' === $attributes['target'] ? ' target="_blank" rel="noopener"' : '';
            return '<a href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( $label ) . '</a>';
        }

        if ( '' !== $url ) {
            return esc_html( $label );
        }

        if ( in_array( $field, [ 'mission_statement', 'company_description', 'company_info' ], true ) && 'html' === $format ) {
            return wp_kses_post( $value );
        }

        return esc_html( Normalizer::text( $value ) );
    }
}

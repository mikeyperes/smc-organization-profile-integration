<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Compatibility;

use SMC\OrganizationProfile\Data\OrganizationData;
use SMC\OrganizationProfile\Data\OrganizationRepository;

defined( 'ABSPATH' ) || exit;

/** Read-only compatibility contract for historical SFPF Organization callbacks. */
final class SfpfOrganizationAdapter {
    public static function resolve_id( mixed $requested_id = '' ): int {
        return OrganizationRepository::resolve_id( $requested_id );
    }

    public static function primary_post(): ?\WP_Post {
        $data = OrganizationRepository::data();
        if ( ! $data || $data->is_user() || ! OrganizationRepository::is_public_organization( $data->id() ) ) {
            return null;
        }

        $post = get_post( $data->id() );
        return $post instanceof \WP_Post ? $post : null;
    }

    public static function field( string $field, int $organization_id ): mixed {
        if ( $organization_id <= 0 ) {
            return null;
        }

        $data = OrganizationRepository::data( $organization_id );
        if ( ! $data ) {
            return null;
        }

        $locations = $data->locations();
        $primary   = $locations[0] ?? [];

        return match ( $field ) {
            'name', 'title' => $data->name(),
            'permalink' => $data->permalink(),
            'headquarters_location' => self::first_text( $primary, 'name', 'address_locality' ),
            'headquarters_wikipedia' => self::first_text( $primary, 'same_as', 'wikipedia_url' ),
            'headquarters' => self::legacy_headquarters( $data, $primary ),
            'logo', 'image_cropped' => $data->logo(),
            'gallery', 'knowledge_graph_images' => $data->gallery( $field ),
            default => $data->raw( $field ),
        };
    }

    /** @return array<string,mixed>|null */
    public static function primary_info(): ?array {
        $data = OrganizationRepository::data();
        if ( ! $data ) {
            return null;
        }

        $logo      = $data->logo();
        $locations = $data->locations();
        $primary   = $locations[0] ?? [];
        $edit_url  = $data->is_user() ? get_edit_user_link( $data->id() ) : get_edit_post_link( $data->id() );

        return [
            'ID'                       => $data->id(),
            'title'                    => $data->name(),
            'url'                      => $data->url( 'url' ),
            'logo_url'                 => (string) ( $logo['url'] ?? '' ),
            'short_summary'             => $data->text( 'short_description' ),
            'headquarters_location'      => self::first_text( $primary, 'name', 'address_locality' ),
            'headquarters_wikipedia'     => self::first_text( $primary, 'same_as', 'wikipedia_url' ),
            'founding_date'              => $data->date( 'founding_date' ),
            'edit_url'                   => $edit_url,
            'view_url'                   => $data->permalink(),
        ];
    }

    /** @param array<string,mixed> $primary @return array<string,mixed> */
    private static function legacy_headquarters( OrganizationData $data, array $primary ): array {
        $legacy = $data->raw( 'headquarters' );
        if ( is_array( $legacy ) && [] !== $legacy ) {
            return $legacy;
        }

        return [
            'location'      => self::first_text( $primary, 'name', 'address_locality' ),
            'wikipedia_url' => self::first_text( $primary, 'same_as', 'wikipedia_url' ),
        ];
    }

    /** @param array<string,mixed> $values */
    private static function first_text( array $values, string ...$keys ): string {
        foreach ( $keys as $key ) {
            $value = trim( (string) ( $values[ $key ] ?? '' ) );
            if ( '' !== $value ) {
                return $value;
            }
        }

        return '';
    }
}

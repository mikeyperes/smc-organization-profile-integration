<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class OrganizationRepository {
    private static ?int $primary_id = null;

    /** @var array<int,OrganizationData|null> */
    private static array $data = [];

    public static function primary_id(): int {
        if ( null !== self::$primary_id ) {
            return self::$primary_id;
        }

        foreach ( [ Settings::PRIMARY_ORGANIZATION, 'sfpf_primary_organization' ] as $option ) {
            $id = absint( get_option( $option, 0 ) );
            if ( self::is_public_organization( $id ) ) {
                self::$primary_id = $id;
                return self::$primary_id;
            }
        }

        $posts = get_posts(
            [
                'post_type'      => 'organization',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC' ],
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        self::$primary_id = isset( $posts[0] ) ? absint( $posts[0] ) : 0;
        return self::$primary_id;
    }

    public static function is_organization( int $post_id ): bool {
        return $post_id > 0 && 'organization' === get_post_type( $post_id );
    }

    public static function is_public_organization( int $post_id ): bool {
        return self::is_organization( $post_id ) && 'publish' === get_post_status( $post_id );
    }

    /** @return list<int> */
    public static function published_ids(): array {
        return array_map(
            'absint',
            get_posts(
                [
                    'post_type'      => 'organization',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC', 'ID' => 'ASC' ],
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                ]
            )
        );
    }

    public static function data( int $post_id = 0 ): ?OrganizationData {
        $post_id = $post_id > 0 ? $post_id : self::primary_id();
        if ( array_key_exists( $post_id, self::$data ) ) {
            return self::$data[ $post_id ];
        }

        self::$data[ $post_id ] = self::is_organization( $post_id ) ? new OrganizationData( $post_id ) : null;
        return self::$data[ $post_id ];
    }

    public static function clear_cache( int $post_id = 0 ): void {
        if ( $post_id > 0 ) {
            unset( self::$data[ $post_id ] );
        } else {
            self::$data = [];
        }

        self::$primary_id = null;
    }
}

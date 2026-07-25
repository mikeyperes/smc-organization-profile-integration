<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class OrganizationRepository {
    public static function primary_id(): int {
        foreach ( [ Settings::PRIMARY_ORGANIZATION, 'sfpf_primary_organization' ] as $option ) {
            $id = absint( get_option( $option, 0 ) );
            if ( self::is_public_organization( $id ) ) {
                return $id;
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

        return isset( $posts[0] ) ? absint( $posts[0] ) : 0;
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
        return self::is_organization( $post_id ) ? new OrganizationData( $post_id ) : null;
    }
}

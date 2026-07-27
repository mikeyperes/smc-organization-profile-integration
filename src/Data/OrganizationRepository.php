<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

use Hexa\PluginCore\EntitySources\CanonicalEntityResolver;
use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class OrganizationRepository {
    private static ?int $primary_id = null;
    /** @var array<string,mixed>|null|false */
    private static array|null|false $primary_entity = false;

    /** @var array<int,OrganizationData|null> */
    private static array $data = [];

    public static function primary_id(): int {
        if ( null !== self::$primary_id ) {
            return self::$primary_id;
        }

        $canonical = self::primary_entity();
        if ( $canonical ) {
            self::$primary_id = (int) $canonical['id'];
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

    /** @return array<string,mixed>|null */
    public static function primary_entity(): ?array {
        if ( false !== self::$primary_entity ) {
            return self::$primary_entity;
        }

        $entity = class_exists( CanonicalEntityResolver::class ) ? CanonicalEntityResolver::resolve() : null;
        self::$primary_entity = is_array( $entity ) && 'organization' === (string) ( $entity['entity_type'] ?? '' )
            ? $entity
            : null;
        return self::$primary_entity;
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

    public static function data( int $object_id = 0, string $kind = '' ): ?OrganizationData {
        if ( $object_id <= 0 ) {
            $entity = self::primary_entity();
            $object_id = $entity ? (int) $entity['id'] : self::primary_id();
            $kind = $entity ? (string) $entity['kind'] : 'post';
        } elseif ( '' === $kind ) {
            $entity = self::primary_entity();
            $kind = $entity && (int) $entity['id'] === $object_id ? (string) $entity['kind'] : 'post';
        }

        $cache_key = $kind . ':' . $object_id;
        if ( array_key_exists( $cache_key, self::$data ) ) {
            return self::$data[ $cache_key ];
        }

        $valid = 'user' === $kind ? (bool) get_userdata( $object_id ) : self::is_organization( $object_id );
        self::$data[ $cache_key ] = $valid ? new OrganizationData( $object_id, $kind ) : null;
        return self::$data[ $cache_key ];
    }

    public static function clear_cache( int $post_id = 0 ): void {
        if ( $post_id > 0 ) {
            unset( self::$data[ 'post:' . $post_id ], self::$data[ 'user:' . $post_id ] );
        } else {
            self::$data = [];
        }

        self::$primary_id = null;
        self::$primary_entity = false;
    }
}

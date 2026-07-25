<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Schema;

use SMC\OrganizationProfile\Data\OrganizationRepository;

defined( 'ABSPATH' ) || exit;

final class SchemaIds {
    public static function organization( int $post_id ): string {
        if ( $post_id === OrganizationRepository::primary_id() ) {
            return trailingslashit( home_url( '/' ) ) . '#organization';
        }

        return untrailingslashit( get_permalink( $post_id ) ) . '#organization';
    }

    public static function website(): string {
        return trailingslashit( home_url( '/' ) ) . '#website';
    }

    public static function webpage( int $page_id ): string {
        $url = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/' );
        return untrailingslashit( $url ) . '#webpage';
    }

    public static function related( string $organization_id, string $kind, string|int $value ): string {
        return untrailingslashit( $organization_id ) . '/' . sanitize_title( $kind . '-' . (string) $value );
    }
}

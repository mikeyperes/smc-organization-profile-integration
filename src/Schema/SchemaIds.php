<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Schema;

use Hexa\PluginCore\SchemaTools\SchemaIds as CoreSchemaIds;
use SMC\OrganizationProfile\Data\OrganizationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Organization @ids on top of HexaWP Core's shared conventions: the primary
 * organization is the site organization; every other one is its own page's.
 */
final class SchemaIds {
    public static function organization( int $post_id ): string {
        if ( $post_id === OrganizationRepository::primary_id() ) {
            return CoreSchemaIds::organization();
        }

        return CoreSchemaIds::for_url( (string) get_permalink( $post_id ), 'organization' );
    }

    public static function website(): string {
        return CoreSchemaIds::website();
    }

    public static function webpage( int $page_id ): string {
        return CoreSchemaIds::webpage( $page_id > 0 ? (string) get_permalink( $page_id ) : CoreSchemaIds::home() );
    }

    public static function related( string $organization_id, string $kind, string|int $value ): string {
        return untrailingslashit( $organization_id ) . '/' . sanitize_title( $kind . '-' . (string) $value );
    }
}

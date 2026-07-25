<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

defined( 'ABSPATH' ) || exit;

final class FieldReader {
    public function __construct( private readonly int $post_id ) {
    }

    public function post_id(): int {
        return $this->post_id;
    }

    public function first( string ...$names ): mixed {
        foreach ( $names as $name ) {
            $value = $this->read( $name );
            if ( Normalizer::present( $value ) ) {
                return $value;
            }
        }

        return null;
    }

    public function read( string $name ): mixed {
        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $name, $this->post_id );
            if ( Normalizer::present( $value ) ) {
                return $value;
            }
        }

        return get_post_meta( $this->post_id, $name, true );
    }
}

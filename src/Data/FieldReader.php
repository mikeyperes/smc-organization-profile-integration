<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

defined( 'ABSPATH' ) || exit;

final class FieldReader {
    /** @var array<string,mixed> */
    private array $cache = [];

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
        if ( array_key_exists( $name, $this->cache ) ) {
            return $this->cache[ $name ];
        }

        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $name, $this->post_id );
            if ( Normalizer::present( $value ) ) {
                $this->cache[ $name ] = $value;
                return $this->cache[ $name ];
            }
        }

        $this->cache[ $name ] = get_post_meta( $this->post_id, $name, true );
        return $this->cache[ $name ];
    }
}

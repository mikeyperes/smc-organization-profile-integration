<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

use Hexa\PluginCore\DataNormalization\FieldReader as CoreFieldReader;

defined( 'ABSPATH' ) || exit;

/** Backward-compatible SMC facade for Core's cached ACF-first field reader. */
final class FieldReader {
    private readonly CoreFieldReader $reader;

    public function __construct( int $object_id, string $kind = 'post' ) {
        $this->reader = new CoreFieldReader( $object_id, $kind );
    }

    public function post_id(): int {
        return $this->reader->post_id();
    }

    public function object_id(): int {
        return $this->reader->object_id();
    }

    public function kind(): string {
        return $this->reader->kind();
    }

    public function context(): string|int {
        return $this->reader->context();
    }

    public function first( string ...$names ): mixed {
        return $this->reader->first( ...$names );
    }

    public function read( string $name ): mixed {
        return $this->reader->read( $name );
    }

    public function clear_cache( ?string $name = null ): void {
        $this->reader->clear_cache( $name );
    }
}

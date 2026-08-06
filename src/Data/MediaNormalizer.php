<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

use Hexa\PluginCore\DataNormalization\MediaNormalizer as CoreMediaNormalizer;

defined( 'ABSPATH' ) || exit;

/** Backward-compatible SMC facade for Core media normalization. */
final class MediaNormalizer {
    public static function clear_cache(): void {
        CoreMediaNormalizer::clear_cache();
    }

    public static function attachment_id( mixed $value ): int {
        return CoreMediaNormalizer::attachment_id( $value );
    }

    /** @return array<string,mixed> */
    public static function image( mixed $value, string $size = 'full' ): array {
        return CoreMediaNormalizer::image( $value, $size );
    }

    /** @return list<array<string,mixed>> */
    public static function gallery( mixed $value, string $size = 'full' ): array {
        return CoreMediaNormalizer::gallery( $value, $size );
    }

    /** @return array<string,mixed> */
    public static function schema_image( array $image, string $id ): array {
        return CoreMediaNormalizer::schema_image( $image, $id );
    }
}

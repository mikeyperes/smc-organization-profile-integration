<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

use Hexa\PluginCore\DataNormalization\ValueNormalizer;

defined( 'ABSPATH' ) || exit;

/** Backward-compatible SMC facade for Core value normalization. */
final class Normalizer {
    public static function present( mixed $value ): bool {
        return ValueNormalizer::present( $value );
    }

    public static function text( mixed $value ): string {
        return ValueNormalizer::text( $value );
    }

    public static function url( mixed $value ): string {
        return ValueNormalizer::url( $value );
    }

    public static function email( mixed $value ): string {
        return ValueNormalizer::email( $value );
    }

    public static function date( mixed $value ): string {
        return ValueNormalizer::date( $value );
    }

    public static function number( mixed $value ): int|float|null {
        return ValueNormalizer::number( $value );
    }

    /** @return list<array<string,mixed>> */
    public static function rows( mixed $value ): array {
        return ValueNormalizer::rows( $value );
    }

    /** @return list<string> */
    public static function strings( mixed $value, string|array $row_keys = 'name' ): array {
        return ValueNormalizer::strings( $value, $row_keys );
    }

    /** @return list<string> */
    public static function urls( mixed ...$values ): array {
        return ValueNormalizer::urls( ...$values );
    }

    /** @return list<int> */
    public static function ids( mixed $value ): array {
        return ValueNormalizer::ids( $value );
    }
}

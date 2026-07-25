<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Support;

defined( 'ABSPATH' ) || exit;

final class Settings {
    public const PRIMARY_ORGANIZATION = 'smc_primary_organization_id';
    public const INJECT_HOMEPAGE = 'smc_inject_homepage_schema';
    public const ABOUT_PAGE = 'smc_about_page_id';
    public const PROFILE_PAGE = 'smc_profile_page_id';
    public const RANK_MATH_DEDUPE = 'smc_rank_math_dedupe';
    public const LEGACY_SHORTCODE = 'smc_enable_legacy_organization_shortcode';
    public const REST_ENABLED = 'smc_rest_enabled';
    public const SCHEMA_ENABLED = 'smc_schema_enabled';

    private const DEFAULTS = [
        self::PRIMARY_ORGANIZATION => 0,
        self::INJECT_HOMEPAGE      => 1,
        self::ABOUT_PAGE           => 0,
        self::PROFILE_PAGE         => 0,
        self::RANK_MATH_DEDUPE     => 1,
        self::LEGACY_SHORTCODE     => 1,
        self::REST_ENABLED         => 1,
        self::SCHEMA_ENABLED       => 1,
    ];

    public static function install_defaults(): void {
        foreach ( self::DEFAULTS as $name => $value ) {
            add_option( $name, $value, '', false );
        }
    }

    public static function enabled( string $name ): bool {
        $default = self::DEFAULTS[ $name ] ?? 0;
        return (bool) get_option( $name, $default );
    }

    public static function integer( string $name ): int {
        $default = self::DEFAULTS[ $name ] ?? 0;
        return max( 0, (int) get_option( $name, $default ) );
    }

    /** @param array<string,mixed> $input */
    public static function update_from_request( array $input ): void {
        foreach ( [ self::PRIMARY_ORGANIZATION, self::ABOUT_PAGE, self::PROFILE_PAGE ] as $name ) {
            update_option( $name, isset( $input[ $name ] ) ? absint( $input[ $name ] ) : 0, false );
        }

        foreach ( [ self::INJECT_HOMEPAGE, self::RANK_MATH_DEDUPE, self::LEGACY_SHORTCODE, self::REST_ENABLED, self::SCHEMA_ENABLED ] as $name ) {
            update_option( $name, isset( $input[ $name ] ) ? 1 : 0, false );
        }
    }

    /** @return array<string,int> */
    public static function all(): array {
        $values = [];
        foreach ( self::DEFAULTS as $name => $default ) {
            $values[ $name ] = (int) get_option( $name, $default );
        }
        return $values;
    }
}

<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Presentation;

defined( 'ABSPATH' ) || exit;

final class FrontendAssets {
    private static bool $registered = false;
    private static bool $enqueued = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_for_request' ] );
        self::$registered = true;
    }

    public static function enqueue_for_request(): void {
        if ( function_exists( 'is_singular' ) && is_singular( 'organization' ) ) {
            self::enqueue();
        }
    }

    public static function enqueue(): void {
        if ( self::$enqueued ) {
            return;
        }

        self::$enqueued = true;
        wp_register_style( 'smc-organization-profile', false, [], SMC_VERSION );
        wp_enqueue_style( 'smc-organization-profile' );

        $path = SMC_PLUGIN_DIR . 'assets/frontend.css';
        if ( is_readable( $path ) ) {
            wp_add_inline_style( 'smc-organization-profile', (string) file_get_contents( $path ) );
        }
    }
}

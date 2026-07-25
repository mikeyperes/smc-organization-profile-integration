<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile;

defined( 'ABSPATH' ) || exit;

final class Autoloader {
    private const PREFIX = 'SMC\\OrganizationProfile\\';

    private static bool $registered = false;

    public static function register( string $source_directory ): void {
        if ( self::$registered ) {
            return;
        }

        $base = rtrim( $source_directory, '/\\' ) . DIRECTORY_SEPARATOR;

        spl_autoload_register(
            static function ( string $class ) use ( $base ): void {
                if ( ! str_starts_with( $class, self::PREFIX ) ) {
                    return;
                }

                $relative = substr( $class, strlen( self::PREFIX ) );
                $path = $base . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

                if ( is_readable( $path ) ) {
                    require_once $path;
                }
            }
        );

        self::$registered = true;
    }
}

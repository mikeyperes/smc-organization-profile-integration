<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile;

use SMC\OrganizationProfile\Acf\OrganizationFields;
use SMC\OrganizationProfile\Admin\AdminPage;
use SMC\OrganizationProfile\Core\CoreIntegration;
use SMC\OrganizationProfile\Data\MediaNormalizer;
use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Dependencies\HwsContentTypes;
use SMC\OrganizationProfile\Http\RestController;
use SMC\OrganizationProfile\Migration\LegacyFieldMigrator;
use SMC\OrganizationProfile\Presentation\FrontendAssets;
use SMC\OrganizationProfile\Schema\OrganizationSchema;
use SMC\OrganizationProfile\Schema\SchemaInjector;
use SMC\OrganizationProfile\Shortcodes\ShortcodeRegistrar;
use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    private static bool $registered = false;
    private static bool $booted = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        self::$registered = true;

        if ( did_action( 'plugins_loaded' ) ) {
            self::boot();
            return;
        }

        add_action( 'plugins_loaded', [ self::class, 'boot' ], 20 );
    }

    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }

        CoreIntegration::boot();

        OrganizationFields::register_hooks();
        LegacyFieldMigrator::register();
        SchemaInjector::register();
        RestController::register();
        ShortcodeRegistrar::register();
        FrontendAssets::register();
        HwsContentTypes::register();
        self::register_cache_invalidation();

        if ( is_admin() || wp_doing_ajax() ) {
            AdminPage::register();
        }

        self::$booted = true;

        do_action( 'smc_organization_profile_booted', CoreIntegration::context() );
    }

    public static function activate(): void {
        Settings::install_defaults();
        HwsContentTypes::activate();
        LegacyFieldMigrator::schedule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public static function clear_request_caches( mixed ...$ignored ): void {
        unset( $ignored );
        OrganizationRepository::clear_cache();
        OrganizationSchema::clear_cache();
        MediaNormalizer::clear_cache();
    }

    private static function register_cache_invalidation(): void {
        add_action( 'clean_post_cache', [ self::class, 'clear_request_caches' ], 10, 2 );
        add_action( 'added_post_meta', [ self::class, 'clear_request_caches' ], 10, 4 );
        add_action( 'updated_post_meta', [ self::class, 'clear_request_caches' ], 10, 4 );
        add_action( 'deleted_post_meta', [ self::class, 'clear_request_caches' ], 10, 4 );
    }
}

<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile;

use SMC\OrganizationProfile\Acf\OrganizationFields;
use SMC\OrganizationProfile\Admin\AdminPage;
use SMC\OrganizationProfile\Core\CoreIntegration;
use SMC\OrganizationProfile\Dependencies\HwsContentTypes;
use SMC\OrganizationProfile\Http\RestController;
use SMC\OrganizationProfile\Migration\LegacyFieldMigrator;
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
        add_action( 'plugins_loaded', [ self::class, 'boot' ], 20 );
    }

    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }

        self::$booted = true;
        Settings::install_defaults();
        HwsContentTypes::register();
        OrganizationFields::register_hooks();
        LegacyFieldMigrator::register();
        SchemaInjector::register();
        RestController::register();
        ShortcodeRegistrar::register();
        AdminPage::register();
        CoreIntegration::boot();

        do_action( 'smc_organization_profile_booted' );
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
}

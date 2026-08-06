<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Core;

use Hexa\PluginCore\CoreBootstrap\CoreBootstrap;
use Hexa\PluginCore\CoreRuntime\PluginContext;
use Hexa\PluginCore\PluginUpdates\GitHubPluginUpdater;
use Hexa\PluginCore\PluginUpdates\UpdaterAjaxController;
use Hexa\PluginCore\PluginUpdates\UpdaterConfig;
use Hexa\PluginCore\SchemaTools\SchemaInjector as CoreSchemaInjector;
use Hexa\PluginCore\WpAdminTabs\CoreTabConfig;
use Hexa\PluginCore\WpAdminTabs\CoreTabModule;
use SMC\OrganizationProfile\ContentTypes\OrganizationContentType;
use SMC\OrganizationProfile\Schema\SchemaInjector;

defined( 'ABSPATH' ) || exit;

final class CoreIntegration {
    private static ?CoreBootstrap $bootstrap = null;
    private static ?PluginContext $context = null;
    private static ?UpdaterConfig $updater = null;

    public static function boot(): void {
        if ( self::$bootstrap instanceof CoreBootstrap || ! self::classes_available() ) {
            return;
        }

        $context = new PluginContext(
            [
                'slug'        => 'smc-organization-profile-integration',
                'basename'    => SMC_PLUGIN_BASENAME,
                'version'     => SMC_VERSION,
                'path'        => SMC_PLUGIN_DIR,
                'url'         => SMC_PLUGIN_URL,
                'github_repo' => 'mikeyperes/smc-organization-profile-integration',
                'admin_page'  => admin_url( 'options-general.php?page=smc-organization-profile' ),
                'capability'  => 'manage_options',
            ]
        );

        $bootstrap = new CoreBootstrap( $context );
        $updater = self::updater_config();

        $bootstrap
            ->add_module( new GitHubPluginUpdater( $updater ) )
            ->add_module( new UpdaterAjaxController( $updater ) )
            ->add_module( OrganizationContentType::registry( $context ) )
            ->add_module(
                new CoreSchemaInjector(
                    [ SchemaInjector::class, 'schema' ],
                    [
                        'hook'          => 'wp_head',
                        'priority'      => 2,
                        'script_id'     => 'smc-organization-schema',
                        'should_output' => [ SchemaInjector::class, 'should_output' ],
                    ]
                )
            );

        if ( is_admin() || wp_doing_ajax() ) {
            $bootstrap->add_module(
                new CoreTabModule(
                    new CoreTabConfig(
                        [
                            'tabs_filter'   => 'smc_dashboard_tabs',
                            'render_filter' => 'smc_dashboard_render_tab',
                            'capability'    => 'manage_options',
                            'core_root'     => SMC_PLUGIN_DIR . 'lib/hexa-wordpress-plugin-core',
                            'readme_path'   => SMC_PLUGIN_DIR . 'lib/hexa-wordpress-plugin-core/README.md',
                            'library_path'  => SMC_PLUGIN_DIR . 'HEXA_PLUGIN_CORE_LIBRARY.md',
                        ]
                    )
                )
            );
        }

        $bootstrap->boot();
        self::$context = $context;
        self::$bootstrap = $bootstrap;
        do_action( 'smc_organization_profile_core_booted', $context, $bootstrap );
    }

    public static function context(): ?PluginContext {
        self::boot();

        return self::$context;
    }

    public static function bootstrap(): ?CoreBootstrap {
        self::boot();

        return self::$bootstrap;
    }

    public static function updater_config(): UpdaterConfig {
        if ( self::$updater instanceof UpdaterConfig ) {
            return self::$updater;
        }

        self::$updater = UpdaterConfig::from_plugin_file(
            SMC_PLUGIN_FILE,
            'mikeyperes/smc-organization-profile-integration',
            [
                'plugin_slug'               => 'smc-organization-profile-integration',
                'proper_folder_name'        => 'smc-organization-profile-integration',
                'runtime_folder_name'       => dirname( SMC_PLUGIN_BASENAME ),
                'plugin_basename'           => SMC_PLUGIN_BASENAME,
                'canonical_plugin_basename' => 'smc-organization-profile-integration/initialization.php',
                'plugin_starter_file'       => 'initialization.php',
                'github_branch'             => 'main',
                'requires'                  => '6.0',
                'tested'                    => get_bloginfo( 'version' ),
                'requires_php'              => '8.1',
                'nonce_action'              => 'smc_core_updater',
                'nonce_param'               => 'nonce',
                'ajax_action_prefix'        => 'smc_core_updater',
                'progress_key'              => 'smc_core_update_progress',
            ]
        );

        return self::$updater;
    }

    private static function classes_available(): bool {
        return class_exists( PluginContext::class )
            && class_exists( CoreBootstrap::class )
            && class_exists( UpdaterConfig::class );
    }
}

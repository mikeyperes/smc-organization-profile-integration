<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Dependencies;

use Hexa\PluginCore\PluginChecks\PluginCheckService;
use Hexa\PluginCore\PluginChecks\PluginRecommendationRegistry;
use HWS\BaseTools\ContentTypes\SharedContentTypes;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class HwsContentTypes {
    public const MINIMUM_VERSION = '13.0.3';

    private const ENABLED_OPTIONS = [
        'smp_enable_cpt_organization',
        'enable_cpt_testimonial',
        'smp_enable_cpt_teammember',
        'enable_acf_testimonial',
        'smp_enable_acf_teammember',
    ];

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        PluginRecommendationRegistry::register_hexa_plugin(
            [
                'id'          => 'smc-organization-profile-integration',
                'name'        => 'SMC Organization Profile Integration',
                'plugin_file' => SMC_PLUGIN_BASENAME,
                'repo'        => 'mikeyperes/smc-organization-profile-integration',
                'definitions' => self::plugin_definitions(),
            ]
        );

        if ( is_admin() ) {
            add_action( 'admin_notices', [ self::class, 'render_notice' ] );
        }

        self::$registered = true;
    }

    public static function activate(): void {
        if ( ! self::hws_ready() ) {
            self::activation_error(
                sprintf(
                    'SMC Organization Profile Integration requires HWS Base Tools %s or newer.',
                    self::MINIMUM_VERSION
                )
            );
        }

        self::enable_owned_dependencies();

        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            self::activation_error( 'SMC Organization Profile Integration requires Advanced Custom Fields Pro.' );
        }
    }

    public static function enable_owned_dependencies(): void {
        foreach ( self::ENABLED_OPTIONS as $option ) {
            update_option( $option, 1, false );
        }

        update_option( 'smp_enable_acf_organization', 0, false );
    }

    public static function hws_ready(): bool {
        if ( ! class_exists( SharedContentTypes::class ) || ! class_exists( PluginMetadata::class ) ) {
            return false;
        }

        return version_compare( PluginMetadata::VERSION, self::MINIMUM_VERSION, '>=' );
    }

    /** @return array<string,mixed> */
    public static function status(): array {
        return [
            'hws_ready'         => self::hws_ready(),
            'hws_version'       => class_exists( PluginMetadata::class ) ? PluginMetadata::VERSION : '',
            'acf_ready'         => function_exists( 'acf_add_local_field_group' ),
            'organization_type' => post_type_exists( 'organization' ),
            'testimonial_type'  => post_type_exists( 'testimonial' ),
            'team_member_type'  => post_type_exists( 'team-member' ),
            'plugin_checks'     => PluginCheckService::statuses( self::plugin_definitions() ),
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function plugin_definitions(): array {
        return [
            [
                'id'          => 'hws-base-tools',
                'name'        => 'HWS Base Tools',
                'plugin_file' => 'hws-base-tools/hws-base-tools.php',
                'slug'        => 'hws-base-tools',
                'source'      => 'github',
                'github_repo' => 'mikeyperes/hws-base-tools',
                'required'    => true,
                'checks'      => [ 'installed' => true, 'active' => true ],
                'notes'       => 'Owns the shared Organization, Testimonial, and Team Member content types.',
            ],
            [
                'id'          => 'advanced-custom-fields-pro',
                'name'        => 'Advanced Custom Fields Pro',
                'plugin_file' => 'advanced-custom-fields-pro/acf.php',
                'slug'        => 'advanced-custom-fields-pro',
                'source'      => 'pro',
                'required'    => true,
                'checks'      => [ 'installed' => true, 'active' => true ],
                'notes'       => 'Registers the Organization profile field structure.',
            ],
        ];
    }

    public static function render_notice(): void {
        if ( self::hws_ready() && function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        $message = ! self::hws_ready()
            ? sprintf( 'HWS Base Tools %s or newer must be active.', self::MINIMUM_VERSION )
            : 'Advanced Custom Fields Pro must be active.';

        echo '<div class="notice notice-error"><p><strong>SMC Organization Profile:</strong> '
            . esc_html( $message )
            . '</p></div>';
    }

    private static function activation_error( string $message ): void {
        if ( function_exists( 'deactivate_plugins' ) ) {
            deactivate_plugins( SMC_PLUGIN_BASENAME );
        }

        wp_die( esc_html( $message ), 'SMC Organization Profile Integration', [ 'back_link' => true ] );
    }
}

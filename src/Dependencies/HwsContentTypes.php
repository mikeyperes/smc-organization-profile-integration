<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Dependencies;

use HWS\BaseTools\ContentTypes\SharedContentTypes;
use HWS\BaseTools\PluginRuntime\PluginMetadata;

defined( 'ABSPATH' ) || exit;

final class HwsContentTypes {
    public const MINIMUM_VERSION = '10.18.143';

    private const ENABLED_OPTIONS = [
        'smp_enable_cpt_organization',
        'enable_cpt_testimonial',
        'smp_enable_cpt_teammember',
        'enable_acf_testimonial',
        'smp_enable_acf_teammember',
    ];

    public static function register(): void {
        add_action( 'admin_notices', [ self::class, 'render_notice' ] );
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

    /** @return array<string,bool|string> */
    public static function status(): array {
        return [
            'hws_ready'         => self::hws_ready(),
            'hws_version'       => class_exists( PluginMetadata::class ) ? PluginMetadata::VERSION : '',
            'acf_ready'         => function_exists( 'acf_add_local_field_group' ),
            'organization_type' => post_type_exists( 'organization' ),
            'testimonial_type'  => post_type_exists( 'testimonial' ),
            'team_member_type'  => post_type_exists( 'team-member' ),
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

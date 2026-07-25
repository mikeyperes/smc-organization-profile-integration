<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Shortcodes;

use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class ShortcodeRegistrar {
    public static function register(): void {
        add_action( 'init', [ self::class, 'register_shortcodes' ], 20 );
    }

    public static function register_shortcodes(): void {
        add_shortcode( 'smc_organization', [ OrganizationShortcode::class, 'render' ] );
        add_shortcode( 'smc_organization_gallery', [ OrganizationShortcode::class, 'render_gallery' ] );
        add_shortcode( 'smc_organization_schema', [ OrganizationShortcode::class, 'render_schema' ] );

        if ( Settings::enabled( Settings::LEGACY_SHORTCODE ) ) {
            remove_shortcode( 'organization' );
            add_shortcode( 'organization', [ OrganizationShortcode::class, 'render' ] );
        }
    }

    /** @return array<string,array<string,string>> */
    public static function catalog(): array {
        return [
            'smc_organization' => [
                'shortcode'   => '[smc_organization field="legal_name"]',
                'description' => 'Outputs one public organization field from the primary or selected organization.',
            ],
            'smc_gallery' => [
                'shortcode'   => '[smc_organization_gallery columns="3" size="large"]',
                'description' => 'Renders the public organization gallery with normalized attachment metadata.',
            ],
            'smc_schema' => [
                'shortcode'   => '[smc_organization_schema format="json"]',
                'description' => 'Outputs the current deterministic organization graph as escaped JSON.',
            ],
            'legacy_organization' => [
                'shortcode'   => '[organization field="name"]',
                'description' => 'Compatibility alias for existing SFPF and Elementor templates.',
            ],
        ];
    }
}

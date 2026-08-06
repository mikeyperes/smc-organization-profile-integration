<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Shortcodes;

use Hexa\PluginCore\ShortcodeRegistry\ShortcodeDefinition;
use Hexa\PluginCore\ShortcodeRegistry\ShortcodeRegistry;
use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class ShortcodeRegistrar {
    private static ?ShortcodeRegistry $catalog = null;

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
        $catalog = [];
        foreach ( self::registry()->all() as $id => $definition ) {
            $catalog[ $id ] = [
                'shortcode'   => $definition->shortcode(),
                'description' => $definition->description,
            ];
        }

        return $catalog;
    }

    public static function registry(): ShortcodeRegistry {
        if ( self::$catalog instanceof ShortcodeRegistry ) {
            return self::$catalog;
        }

        self::$catalog = new ShortcodeRegistry();
        self::$catalog
            ->add(
                new ShortcodeDefinition(
                    'smc_organization',
                    'Organization field',
                    '[smc_organization field="legal_name"]',
                    'Outputs one public organization field from the primary or selected organization.',
                    'shortcode'
                )
            )
            ->add(
                new ShortcodeDefinition(
                    'smc_gallery',
                    'Organization gallery',
                    '[smc_organization_gallery columns="3" size="large"]',
                    'Renders the public organization gallery with normalized attachment metadata.',
                    'shortcode'
                )
            )
            ->add(
                new ShortcodeDefinition(
                    'smc_schema',
                    'Organization schema',
                    '[smc_organization_schema format="json"]',
                    'Outputs the current deterministic organization graph as escaped JSON.',
                    'shortcode'
                )
            )
            ->add(
                new ShortcodeDefinition(
                    'legacy_organization',
                    'Legacy Organization alias',
                    '[organization field="name"]',
                    'Compatibility alias for existing SFPF and Elementor templates.',
                    'shortcode'
                )
            );

        return self::$catalog;
    }
}

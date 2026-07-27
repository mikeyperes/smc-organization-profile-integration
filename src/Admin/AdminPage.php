<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Admin;

use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;
use Hexa\PluginCore\CoreRuntime\CoreVersion;
use Hexa\PluginCore\SchemaTools\SchemaDocumentRenderer;
use Hexa\PluginCore\WpAdminAjax\AjaxActionRegistry;
use Hexa\PluginCore\WpAdminAjax\AjaxFailure;
use Hexa\PluginCore\WpAdminAjax\AjaxRequest;
use Hexa\PluginCore\WpAdminTabs\HostTabsRenderer;
use SMC\OrganizationProfile\Acf\OrganizationFields;
use SMC\OrganizationProfile\ContentTypes\OrganizationContentType;
use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Dependencies\HwsContentTypes;
use SMC\OrganizationProfile\Http\RestController;
use SMC\OrganizationProfile\Migration\LegacyFieldMigrator;
use SMC\OrganizationProfile\Schema\OrganizationGraph;
use SMC\OrganizationProfile\Shortcodes\OrganizationShortcode;
use SMC\OrganizationProfile\Shortcodes\ShortcodeRegistrar;
use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class AdminPage {
    public const SLUG = 'smc-organization-profile';

    private const AJAX_ACTION = 'smc_load_dashboard_tab';
    private const NONCE_ACTION = 'smc_dashboard_tabs';

    private const TABS = [
        'overview'   => 'Overview',
        'settings'   => 'Settings',
        'content-types' => 'Custom Post Types',
        'fields'     => 'Field Model',
        'shortcodes' => 'Shortcodes',
        'schema'     => 'Live Schema',
    ];

    private const GROUPS = [
        [ 'label' => 'Overview', 'tabs' => [ 'overview' ] ],
        [ 'label' => 'Organization', 'tabs' => [ 'settings', 'content-types', 'fields', 'shortcodes', 'schema' ] ],
        [ 'label' => 'System', 'tabs' => [ 'hexa-core' ] ],
    ];

    public static function register(): void {
        add_action( 'admin_menu', [ self::class, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
        add_action( 'admin_post_smc_save_settings', [ self::class, 'save_settings' ] );
        add_filter( 'plugin_action_links_' . SMC_PLUGIN_BASENAME, [ self::class, 'action_links' ] );

        if ( class_exists( AjaxActionRegistry::class ) ) {
            ( new AjaxActionRegistry(
                [
                    'capability'   => 'manage_options',
                    'nonce_action' => self::NONCE_ACTION,
                    'nonce_field'  => 'nonce',
                ]
            ) )->register(
                [
                    self::AJAX_ACTION => [
                        'callback' => [ self::class, 'load_tab' ],
                    ],
                ]
            );
        }
    }

    public static function add_menu(): void {
        add_options_page(
            'SMC Organization Profile',
            'SMC Organization',
            'manage_options',
            self::SLUG,
            [ self::class, 'render' ]
        );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( 'settings_page_' . self::SLUG !== $hook ) {
            return;
        }

        $css = self::admin_css();
        if ( '' !== $css ) {
            wp_add_inline_style( 'common', $css );
        }
    }

    /** @param array<string,string> $links @return array<string,string> */
    public static function action_links( array $links ): array {
        $settings = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">Settings</a>';
        array_unshift( $links, $settings );
        return $links;
    }

    public static function save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to manage SMC settings.' );
        }

        check_admin_referer( 'smc_save_settings' );
        Settings::update_from_request( wp_unslash( $_POST ) );

        wp_safe_redirect(
            add_query_arg(
                [ 'page' => self::SLUG, 'tab' => 'settings', 'updated' => 1 ],
                admin_url( 'options-general.php' )
            )
        );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $tabs = self::tabs();
        $tab  = self::current_tab( $tabs );

        echo '<div class="wrap smc-admin">';
        self::render_header();

        ( new HostTabsRenderer() )->render(
            [
                'tabs'                => $tabs,
                'active'              => $tab,
                'page_url'            => self::page_url(),
                'ajax_action'         => self::AJAX_ACTION,
                'nonce'               => wp_create_nonce( self::NONCE_ACTION ),
                'nonce_field'         => 'nonce',
                'root_id'             => 'smc-organization-tabs',
                'panel_id'            => 'smc-organization-panel',
                'label'               => 'SMC Organization Profile sections',
                'layout'              => 'sidebar',
                'groups'              => self::GROUPS,
                'sidebar_identity'    => [
                    'plugin_name'     => 'SMC Organization Profile',
                    'current_version' => SMC_VERSION,
                    'github_url'      => 'https://github.com/mikeyperes/smc-organization-profile-integration',
                    'core_name'       => CoreVersion::PACKAGE_NAME,
                    'core_version'    => CoreVersion::current(),
                    'core_github_url' => 'https://github.com/mikeyperes/hexa-wordpress-plugin-core',
                ],
                'sidebar_collapsible' => true,
                'sidebar_collapsed'   => false,
                'sidebar_persist'     => true,
                'render_callback'     => [ self::class, 'render_panel' ],
            ]
        );

        echo '</div>';
    }

    public static function load_tab( AjaxRequest $request ): array {
        $tabs = self::tabs();
        $tab  = $request->key( 'tab', '', 'post' );

        if ( ! isset( $tabs[ $tab ] ) ) {
            throw AjaxFailure::not_found( 'Unknown SMC dashboard tab.', 'unknown_tab' );
        }

        ob_start();
        self::render_panel( $tab );
        $html = (string) ob_get_clean();

        return [
            'html'  => $html,
            'tab'   => $tab,
            'label' => self::tab_label( $tabs[ $tab ] ),
        ];
    }

    public static function render_panel( string $tab ): void {
        if ( apply_filters( 'smc_dashboard_render_tab', false, $tab ) ) {
            return;
        }

        match ( $tab ) {
            'settings'   => self::render_settings(),
            'content-types' => self::render_content_types(),
            'fields'     => self::render_fields(),
            'shortcodes' => self::render_shortcodes(),
            'schema'     => self::render_schema(),
            default      => self::render_overview(),
        };
    }

    private static function render_header(): void {
        echo '<header class="smc-admin__header">';
        echo '<div><p class="smc-admin__eyebrow">SMC</p>';
        echo '<h1>Organization Profile Integration</h1>';
        echo '<p>Organization data, schema, REST output, and template interfaces.</p></div>';
        echo '</header>';

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>SMC settings saved.</p></div>';
        }
    }

    private static function render_overview(): void {
        $status = HwsContentTypes::status();
        $primary = OrganizationRepository::data();
        $counts = wp_count_posts( 'organization' );
        $report = LegacyFieldMigrator::report();

        echo '<section class="smc-admin__section"><div class="smc-admin__section-heading">';
        echo '<h2>System State</h2><p>Runtime ownership and publication readiness.</p></div>';
        echo '<div class="smc-status-grid">';
        self::status_item( 'HWS Base Tools', (bool) $status['hws_ready'], (string) ( $status['hws_version'] ?: 'Not detected' ) );
        self::status_item( 'Advanced Custom Fields', (bool) $status['acf_ready'], $status['acf_ready'] ? 'Ready' : 'Required' );
        self::status_item( 'Organization CPT', (bool) $status['organization_type'], $status['organization_type'] ? 'Owned by HWS' : 'Unavailable' );
        self::status_item( 'Primary Organization', null !== $primary, $primary ? $primary->name() : 'Not selected' );
        echo '</div></section>';

        echo '<section class="smc-admin__section"><div class="smc-admin__section-heading">';
        echo '<h2>Content</h2><p>Manage the HWS-owned records consumed by SMC.</p></div>';
        echo '<div class="smc-admin__actions">';
        echo '<a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=organization' ) ) . '">Manage Organizations</a>';
        echo '<a class="button" href="' . esc_url( admin_url( 'post-new.php?post_type=organization' ) ) . '">Add Organization</a>';
        if ( $primary ) {
            $edit_url = $primary->is_user() ? get_edit_user_link( $primary->id() ) : get_edit_post_link( $primary->id() );
            echo '<a class="button" href="' . esc_url( $edit_url ) . '">Edit Primary</a>';
            if ( $primary->is_user() || 'publish' === get_post_status( $primary->id() ) ) {
                echo '<a class="button" href="' . esc_url( $primary->permalink() ) . '" target="_blank" rel="noopener">View Profile</a>';
            }
        }
        echo '</div>';
        echo '<table class="widefat striped smc-admin__table"><tbody>';
        echo '<tr><th>Published organizations</th><td>' . absint( $counts->publish ?? 0 ) . '</td></tr>';
        echo '<tr><th>Migration scanned</th><td>' . absint( $report['posts_scanned'] ?? 0 ) . ' record(s)</td></tr>';
        echo '<tr><th>Migration changes</th><td>' . absint( $report['fields_migrated'] ?? 0 ) . ' field value(s)</td></tr>';
        echo '<tr><th>REST collection</th><td><a href="' . esc_url( rest_url( RestController::NAMESPACE . '/organizations' ) ) . '" target="_blank" rel="noopener"><code>/wp-json/smc/v1/organizations</code></a></td></tr>';
        echo '</tbody></table></section>';
    }

    private static function render_settings(): void {
        $pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );
        $primary = OrganizationRepository::data();

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="smc-settings-form">';
        wp_nonce_field( 'smc_save_settings' );
        echo '<input type="hidden" name="action" value="smc_save_settings">';
        echo '<section class="smc-admin__section"><div class="smc-admin__section-heading">';
        echo '<h2>Canonical Organization</h2><p>HWS Base Tools is the source of truth for the optional primary entity. SMC consumes it when its semantic type is Organization.</p></div>';
        echo '<p><strong>Current source:</strong> ' . esc_html( $primary ? $primary->name() . ' (' . $primary->kind() . ')' : 'No Organization entity attached' ) . '</p>';
        echo '<p><a class="button" href="' . esc_url( admin_url( 'options-general.php?page=hws-core-tools&tab=website-types' ) ) . '">Manage in HWS Base Tools</a></p>';
        echo '<table class="form-table" role="presentation"><tbody>';
        self::select_row( Settings::ABOUT_PAGE, 'About Page', $pages, Settings::integer( Settings::ABOUT_PAGE ) );
        self::select_row( Settings::PROFILE_PAGE, 'Profile Page', $pages, Settings::integer( Settings::PROFILE_PAGE ) );
        echo '</tbody></table></section>';

        echo '<section class="smc-admin__section"><div class="smc-admin__section-heading">';
        echo '<h2>Output</h2><p>Control live schema, API, and compatibility behavior.</p></div>';
        echo '<fieldset class="smc-toggle-list">';
        self::toggle( Settings::SCHEMA_ENABLED, 'Enable SMC schema output' );
        self::toggle( Settings::INJECT_HOMEPAGE, 'Inject the primary organization graph on the homepage' );
        self::toggle( Settings::RANK_MATH_DEDUPE, 'Remove SMC-owned nodes from Rank Math output on managed pages' );
        self::toggle( Settings::REST_ENABLED, 'Expose public organization REST endpoints' );
        self::toggle( Settings::LEGACY_SHORTCODE, 'Own the legacy [organization] shortcode alias' );
        echo '</fieldset></section>';
        submit_button( 'Save SMC Settings' );
        echo '</form>';
    }

    private static function render_content_types(): void {
        echo ( new ContentTypeRenderer() )->render(
            OrganizationContentType::registry(),
            [
                'title' => 'Custom Post Types',
                'description' => 'SMC extends the HWS-owned Organization content type. Its ACF structure is registered and controlled through Hexa WP Core without duplicating CPT ownership.',
                'persist_prefix' => 'smc',
            ]
        );
    }

    private static function render_fields(): void {
        $fields = OrganizationFields::fields();
        $sections = [];
        $active = 'General';

        foreach ( $fields as $field ) {
            if ( 'tab' === ( $field['type'] ?? '' ) ) {
                $active = (string) $field['label'];
                $sections[ $active ] = [];
                continue;
            }
            $sections[ $active ][] = $field;
        }

        echo '<section class="smc-admin__section"><div class="smc-admin__section-heading">';
        echo '<h2>Organization Field Model</h2><p>' . absint( count( $fields ) ) . ' registered field definitions across ' . absint( count( $sections ) ) . ' sections.</p></div>';
        foreach ( $sections as $section => $section_fields ) {
            echo '<h3>' . esc_html( $section ) . '</h3><table class="widefat striped smc-admin__table"><thead><tr><th>Field</th><th>Name</th><th>Type</th></tr></thead><tbody>';
            foreach ( $section_fields as $field ) {
                echo '<tr><td>' . esc_html( $field['label'] ?? '' ) . '</td>';
                echo '<td><code>' . esc_html( $field['name'] ?? '' ) . '</code></td>';
                echo '<td>' . esc_html( $field['type'] ?? '' ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';
    }

    private static function render_shortcodes(): void {
        $primary = OrganizationRepository::data();

        echo '<section class="smc-admin__section"><div class="smc-admin__section-heading">';
        echo '<h2>Shortcodes</h2><p>All shortcode values are read from the same normalized organization source as REST and schema.</p></div>';
        echo '<table class="widefat striped smc-admin__table"><thead><tr><th>Shortcode</th><th>Description</th><th>Live Value</th></tr></thead><tbody>';
        foreach ( ShortcodeRegistrar::catalog() as $item ) {
            if ( str_contains( $item['shortcode'], 'gallery' ) ) {
                $example = $primary ? count( $primary->gallery() ) . ' image(s)' : '';
            } elseif ( str_contains( $item['shortcode'], '_schema' ) ) {
                $example = $primary ? 'Available in Live Schema' : '';
            } else {
                $example = $primary ? do_shortcode( $item['shortcode'] ) : '';
            }
            echo '<tr><td><code>' . esc_html( $item['shortcode'] ) . '</code></td>';
            echo '<td>' . esc_html( $item['description'] ) . '</td>';
            echo '<td>' . ( '' !== $example ? wp_kses_post( $example ) : '<span class="smc-muted">No value</span>' ) . '</td></tr>';
        }
        echo '</tbody></table></section>';
    }

    private static function render_schema(): void {
        $primary = OrganizationRepository::data();

        echo '<section class="smc-admin__section"><div class="smc-admin__section-heading">';
        echo '<h2>Live Schema Graph</h2><p>Generated at request time. No editable or stored schema payload is used.</p></div>';

        if ( ! $primary ) {
            echo '<div class="notice notice-warning inline"><p>Select or publish a primary organization first.</p></div></section>';
            return;
        }

        $graph = OrganizationGraph::build( $primary->id(), $primary->id() );
        $json = ( new SchemaDocumentRenderer() )->json( $graph );
        echo '<div class="smc-admin__actions">';
        echo '<a class="button" href="' . esc_url( rest_url( RestController::NAMESPACE . '/organizations/primary' ) ) . '" target="_blank" rel="noopener">Open Primary REST Data</a>';
        if ( $primary->is_user() || 'publish' === get_post_status( $primary->id() ) ) {
            echo '<a class="button" href="' . esc_url( $primary->permalink() ) . '" target="_blank" rel="noopener">Open Frontend Profile</a>';
        }
        echo '</div><pre class="smc-schema-preview"><code>' . esc_html( $json ?: '{}' ) . '</code></pre></section>';
    }

    private static function status_item( string $label, bool $ready, string $detail ): void {
        echo '<div class="smc-status"><span class="smc-status__indicator ' . ( $ready ? 'is-ready' : 'is-missing' ) . '"></span>';
        echo '<div><strong>' . esc_html( $label ) . '</strong><span>' . esc_html( $detail ) . '</span></div></div>';
    }

    /** @param list<object> $items */
    private static function select_row( string $name, string $label, array $items, int $selected, bool $posts = false ): void {
        echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
        echo '<select class="regular-text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"><option value="0">Not selected</option>';
        foreach ( $items as $item ) {
            $id = absint( $item->ID );
            $text = (string) $item->post_title;
            if ( $posts ) {
                $text .= ' (' . $item->post_status . ')';
            }
            echo '<option value="' . $id . '" ' . selected( $selected, $id, false ) . '>' . esc_html( $text ) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private static function toggle( string $name, string $label ): void {
        echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" '
            . checked( Settings::enabled( $name ), true, false )
            . '> <span>' . esc_html( $label ) . '</span></label>';
    }

    /** @return array<string,mixed> */
    private static function tabs(): array {
        $tabs = apply_filters( 'smc_dashboard_tabs', self::TABS );
        return is_array( $tabs ) ? $tabs : self::TABS;
    }

    /** @param array<string,mixed> $tabs */
    private static function current_tab( array $tabs ): string {
        $requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        return isset( $tabs[ $requested ] ) ? $requested : 'overview';
    }

    private static function tab_label( mixed $tab ): string {
        if ( is_array( $tab ) && isset( $tab['label'] ) ) {
            return (string) $tab['label'];
        }

        return (string) $tab;
    }

    private static function page_url(): string {
        return add_query_arg( 'page', self::SLUG, admin_url( 'options-general.php' ) );
    }

    private static function admin_css(): string {
        static $css = null;

        if ( is_string( $css ) ) {
            return $css;
        }

        $path = SMC_PLUGIN_DIR . 'assets/admin.css';
        $css  = is_readable( $path ) ? (string) file_get_contents( $path ) : '';

        return $css;
    }
}

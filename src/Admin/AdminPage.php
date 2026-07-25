<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Admin;

use SMC\OrganizationProfile\Acf\OrganizationFields;
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

    public static function register(): void {
        add_action( 'admin_menu', [ self::class, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
        add_action( 'admin_post_smc_save_settings', [ self::class, 'save_settings' ] );
        add_filter( 'plugin_action_links_' . SMC_PLUGIN_BASENAME, [ self::class, 'action_links' ] );
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

        wp_enqueue_style(
            'smc-organization-admin',
            SMC_PLUGIN_URL . 'assets/admin.css',
            [],
            SMC_VERSION
        );
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

        $tabs = apply_filters(
            'smc_dashboard_tabs',
            [
                'overview'   => 'Overview',
                'settings'   => 'Settings',
                'fields'     => 'Field Model',
                'shortcodes' => 'Shortcodes',
                'schema'     => 'Live Schema',
            ]
        );
        $tab = sanitize_key( $_GET['tab'] ?? 'overview' );
        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'overview';
        }

        echo '<div class="wrap smc-admin">';
        self::render_header();
        self::render_tabs( $tabs, $tab );

        echo '<main class="smc-admin__content">';
        if ( ! apply_filters( 'smc_dashboard_render_tab', false, $tab ) ) {
            match ( $tab ) {
                'settings'   => self::render_settings(),
                'fields'     => self::render_fields(),
                'shortcodes' => self::render_shortcodes(),
                'schema'     => self::render_schema(),
                default      => self::render_overview(),
            };
        }
        echo '</main></div>';
    }

    private static function render_header(): void {
        echo '<header class="smc-admin__header">';
        echo '<div><p class="smc-admin__eyebrow">SMC</p>';
        echo '<h1>Organization Profile Integration</h1>';
        echo '<p>Organization data, schema, REST output, and template interfaces.</p></div>';
        echo '<div class="smc-admin__version">Version ' . esc_html( SMC_VERSION ) . '</div>';
        echo '</header>';

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>SMC settings saved.</p></div>';
        }
    }

    /** @param array<string,string> $tabs */
    private static function render_tabs( array $tabs, string $active ): void {
        echo '<nav class="nav-tab-wrapper smc-admin__tabs" aria-label="SMC settings">';
        foreach ( $tabs as $id => $label ) {
            $url = add_query_arg(
                [ 'page' => self::SLUG, 'tab' => $id ],
                admin_url( 'options-general.php' )
            );
            $class = 'nav-tab' . ( $id === $active ? ' nav-tab-active' : '' );
            echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">'
                . esc_html( $label )
                . '</a>';
        }
        echo '</nav>';
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
            echo '<a class="button" href="' . esc_url( get_edit_post_link( $primary->id() ) ) . '">Edit Primary</a>';
            if ( 'publish' === get_post_status( $primary->id() ) ) {
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
        $organizations = get_posts(
            [
                'post_type'      => 'organization',
                'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );
        $pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="smc-settings-form">';
        wp_nonce_field( 'smc_save_settings' );
        echo '<input type="hidden" name="action" value="smc_save_settings">';
        echo '<section class="smc-admin__section"><div class="smc-admin__section-heading">';
        echo '<h2>Primary Entity</h2><p>Select the organization and managed pages used by the graph builder.</p></div>';
        echo '<table class="form-table" role="presentation"><tbody>';
        self::select_row( Settings::PRIMARY_ORGANIZATION, 'Primary Organization', $organizations, Settings::integer( Settings::PRIMARY_ORGANIZATION ), true );
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
            $example = $primary ? do_shortcode( $item['shortcode'] ) : '';
            if ( str_contains( $item['shortcode'], 'gallery' ) ) {
                $example = $primary ? count( $primary->gallery() ) . ' image(s)' : '';
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
        $json = wp_json_encode( $graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        echo '<div class="smc-admin__actions">';
        echo '<a class="button" href="' . esc_url( rest_url( RestController::NAMESPACE . '/organizations/' . $primary->id() . '/schema' ) ) . '" target="_blank" rel="noopener">Open REST Schema</a>';
        if ( 'publish' === get_post_status( $primary->id() ) ) {
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
}

<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$smc_test_core = dirname( __DIR__ ) . '/lib/hexa-wordpress-plugin-core';
require_once $smc_test_core . '/bootstrap.php';
HexaPluginCorePackageRegistry::register_candidate( 'smc-tests', $smc_test_core );
HexaPluginCorePackageRegistry::resolve();

final class WP_User {
    /** @param list<string> $roles */
    public function __construct(
        public int $ID,
        public string $display_name,
        public string $user_email,
        public string $description,
        public array $roles = [ 'administrator' ]
    ) {
    }
}

$GLOBALS['smc_test_options'] = [
    'smc_primary_organization_id' => 10,
];

$GLOBALS['smc_test_posts'] = [
    10 => [
        'type' => 'organization',
        'status' => 'publish',
        'title' => 'Acme Research',
        'excerpt' => 'Acme builds reliable research systems.',
        'content' => 'Long company description.',
        'date' => '2020-02-03 10:00:00',
        'modified' => '2026-07-25 12:00:00',
        'url' => 'https://company.test/organization/acme-research/',
    ],
    20 => [
        'type' => 'team-member',
        'status' => 'publish',
        'title' => 'Ada Founder',
        'excerpt' => 'Founder and chief executive.',
        'content' => '',
        'date' => '2020-02-03 10:00:00',
        'modified' => '2026-07-25 12:00:00',
        'url' => 'https://company.test/team-member/ada-founder/',
    ],
    30 => [
        'type' => 'testimonial',
        'status' => 'publish',
        'title' => 'Casey Customer',
        'excerpt' => '',
        'content' => 'Acme delivered exactly what our team needed.',
        'date' => '2025-05-06 10:00:00',
        'modified' => '2026-07-25 12:00:00',
        'url' => 'https://company.test/testimonial/casey-customer/',
    ],
];

$GLOBALS['smc_test_meta'] = [
    10 => [
        'schema_enabled' => 1,
        'organization_type' => 'Corporation',
        'legal_name' => 'Acme Research, Inc.',
        'alternate_name_entries' => [ [ 'name' => 'Acme Labs' ] ],
        'slogan' => 'Evidence into action',
        'short_description' => 'Acme builds reliable research systems.',
        'company_description' => '<p>Acme serves technical teams.</p>',
        'url' => 'https://acme.example/',
        'founding_date' => '2020-02-03',
        'founding_location' => [
            'name' => 'Boston',
            'address_locality' => 'Boston',
            'address_region' => 'MA',
            'address_country' => 'US',
        ],
        'locations' => [
            [
                'name' => 'Acme Headquarters',
                'location_type' => 'headquarters',
                'street_address' => '100 Main Street',
                'address_locality' => 'Boston',
                'address_region' => 'MA',
                'postal_code' => '02108',
                'address_country' => 'US',
                'latitude' => 42.3601,
                'longitude' => -71.0589,
                'telephone' => '+1-617-555-0100',
            ],
        ],
        'area_served' => [ [ 'type' => 'Country', 'name' => 'United States' ] ],
        'email' => 'hello@acme.example',
        'telephone' => '+1-617-555-0100',
        'contact_points' => [
            [
                'contact_type' => 'sales',
                'email' => 'sales@acme.example',
                'telephone' => '+1-617-555-0101',
                'available_languages' => 'English, Spanish',
            ],
        ],
        'identity_links' => [
            [ 'platform' => 'linkedin', 'label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/acme/' ],
        ],
        'knowledge_graph_id' => '/g/11acme',
        'identifiers' => [ [ 'property_id' => 'Registry', 'value' => 'ACME-100' ] ],
        'naics_code' => '541715',
        'isic_code' => '7210',
        'lei_code' => '549300ACME',
        'number_of_employees' => 42,
        'founder_users' => [ 1 ],
        'founders' => [ 20 ],
        'team_members' => [ 20 ],
        'services' => [
            [ 'name' => 'Research Consulting', 'description' => 'Applied research support.', 'url' => 'https://acme.example/services/research/' ],
        ],
        'products' => [
            [ 'name' => 'Acme Platform', 'sku' => 'ACP-1', 'brand' => 'Acme', 'price' => 99, 'price_currency' => 'USD', 'availability' => 'InStock' ],
        ],
        'brand_entries' => [ [ 'name' => 'Acme', 'url' => 'https://acme.example/' ] ],
        'credentials' => [ [ 'name' => 'ISO 9001', 'category' => 'Quality', 'recognized_by' => 'ISO' ] ],
        'award_entries' => [ [ 'name' => 'Research Award', 'issuer' => 'Example Institute' ] ],
        'seek_entries' => [ [ 'name' => 'Research partners', 'description' => 'University partnerships.' ] ],
        'press_mentions' => [
            [
                'headline' => 'Acme launches a research platform',
                'url' => 'https://news.example/acme-launch/',
                'publisher' => 'Example News',
                'date_published' => '2025-01-02',
                'author' => 'Jordan Writer',
            ],
        ],
        'milestones' => [ [ 'name' => 'Platform launch', 'date' => '2024-04-05' ] ],
        'testimonials' => [ 30 ],
        'logo' => 101,
        'primary_image' => 102,
        'knowledge_graph_images' => [ 103 ],
        'gallery' => [ 104 ],
    ],
    20 => [
        'position' => 'Founder and CEO',
    ],
    30 => [
        'Headline' => 'Excellent research partner',
        'rating' => 4.5,
        'url' => 'https://reviews.example/acme/1',
    ],
    101 => [ '_wp_attachment_image_alt' => 'Acme logo' ],
    102 => [ '_wp_attachment_image_alt' => 'Acme team' ],
    103 => [ '_wp_attachment_image_alt' => 'Acme laboratory' ],
    104 => [ '_wp_attachment_image_alt' => 'Acme office' ],
];

function absint( mixed $value ): int {
    return abs( (int) $value );
}

function sanitize_key( string $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?: '';
}

function sanitize_title( string $value ): string {
    return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $value ) ) ?: '', '-' );
}

function trailingslashit( string $value ): string {
    return rtrim( $value, '/\\' ) . '/';
}

function untrailingslashit( string $value ): string {
    return rtrim( $value, '/\\' );
}

function home_url( string $path = '' ): string {
    return 'https://company.test' . ( '/' === $path ? '/' : $path );
}

function get_option( string $name, mixed $default = false ): mixed {
    return $GLOBALS['smc_test_options'][ $name ] ?? $default;
}

function get_post_type( int $post_id ): string {
    return $GLOBALS['smc_test_posts'][ $post_id ]['type'] ?? '';
}

function get_post_status( int $post_id ): string {
    return $GLOBALS['smc_test_posts'][ $post_id ]['status'] ?? '';
}

function get_posts( array $args = [] ): array {
    $ids = [];
    foreach ( $GLOBALS['smc_test_posts'] as $id => $post ) {
        if ( ( $args['post_type'] ?? '' ) === $post['type'] && ( 'any' === ( $args['post_status'] ?? 'publish' ) || ( $args['post_status'] ?? 'publish' ) === $post['status'] ) ) {
            $ids[] = $id;
        }
    }
    return isset( $args['posts_per_page'] ) && 1 === $args['posts_per_page'] ? array_slice( $ids, 0, 1 ) : $ids;
}

function get_the_title( int $post_id ): string {
    return $GLOBALS['smc_test_posts'][ $post_id ]['title'] ?? ( $post_id >= 100 ? 'Image ' . $post_id : '' );
}

function get_permalink( int $post_id ): string {
    return $GLOBALS['smc_test_posts'][ $post_id ]['url'] ?? 'https://company.test/?p=' . $post_id;
}

function get_post_field( string $field, int $post_id ): string {
    $map = [
        'post_excerpt' => 'excerpt',
        'post_content' => 'content',
        'post_date' => 'date',
        'post_modified' => 'modified',
    ];
    return $GLOBALS['smc_test_posts'][ $post_id ][ $map[ $field ] ?? $field ] ?? '';
}

function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
    return $GLOBALS['smc_test_meta'][ $post_id ][ $key ] ?? '';
}

function get_user_meta( int $user_id, string $key, bool $single = false ): mixed {
    unset( $single );
    return $GLOBALS['smc_test_user_meta'][ $user_id ][ $key ] ?? '';
}

function get_field( string $key, int|string $context ): mixed {
    if ( is_string( $context ) && str_starts_with( $context, 'user_' ) ) {
        return match ( $key ) {
            'schema_enabled' => 1,
            'organization_type' => 'Organization',
            'legal_name' => 'Ada Founder Media LLC',
            'short_description' => 'An author-led media organization.',
            'company_description' => '<p>A canonical user-backed Organization.</p>',
            'url' => 'https://company.test/',
            'founding_date' => '2021-06-10',
            'identity_links' => [ [ 'platform' => 'linkedin', 'url' => 'https://www.linkedin.com/in/ada/' ] ],
            'logo' => 101,
            'urls' => [ 'linkedin' => 'https://www.linkedin.com/in/ada/' ],
            'additional' => [ 'public_email' => 'ada-public@acme.example' ],
            default => null,
        };
    }
    return $GLOBALS['smc_test_meta'][ (int) $context ][ $key ] ?? null;
}

function shortcode_atts( array $defaults, array $attributes, string $shortcode = '' ): array {
    return array_merge( $defaults, array_intersect_key( $attributes, $defaults ) );
}

function maybe_unserialize( mixed $value ): mixed {
    if ( is_string( $value ) && preg_match( '/^[aObisd]:/', $value ) ) {
        $decoded = @unserialize( $value );
        return false === $decoded ? $value : $decoded;
    }
    return $value;
}

function wp_strip_all_tags( string $value, bool $remove_breaks = false ): string {
    return strip_tags( $value );
}

function esc_url_raw( string $value ): string {
    return $value;
}

function sanitize_email( string $value ): string {
    return filter_var( $value, FILTER_SANITIZE_EMAIL );
}

function esc_html( mixed $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_url( mixed $value ): string {
    return (string) $value;
}

function esc_attr( mixed $value ): string {
    return esc_html( $value );
}

function wp_kses_post( mixed $value ): string {
    return (string) $value;
}

function wp_json_encode( mixed $value, int $flags = 0 ): string|false {
    return json_encode( $value, $flags );
}

function get_queried_object_id(): int {
    return 10;
}

function is_admin(): bool {
    return false;
}

function is_feed(): bool {
    return false;
}

function is_singular( string $post_type = '' ): bool {
    return '' === $post_type || $post_type === get_post_type( get_queried_object_id() );
}

function is_front_page(): bool {
    return false;
}

function is_page(): bool {
    return false;
}

function wp_enqueue_style( string $handle, string $source, array $dependencies = [], string|bool|null $version = false ): void {
}

function wp_get_attachment_image_src( int $id, string $size ): array {
    $suffix = 'full' === $size ? 'full' : $size;
    return [ 'https://company.test/uploads/' . $id . '-' . $suffix . '.jpg', 1200, 900, true ];
}

function wp_get_attachment_caption( int $id ): string {
    return 'Caption ' . $id;
}

function attachment_url_to_postid( string $url ): int {
    return 0;
}

function get_post_thumbnail_id( int $post_id ): int {
    return 102;
}

function get_the_post_thumbnail_url( int $post_id, string $size ): string {
    return 'https://company.test/uploads/team-' . $post_id . '.jpg';
}

function get_userdata( int $user_id ): WP_User|false {
    return 1 === $user_id
        ? new WP_User( 1, 'Ada Founder', 'ada@acme.example', 'Founder and publisher.' )
        : false;
}

function get_edit_user_link( int $user_id ): string {
    return 'https://company.test/wp-admin/user-edit.php?user_id=' . $user_id;
}

function get_author_posts_url( int $user_id ): string {
    return 'https://company.test/author/ada/';
}

function get_avatar_url( int $user_id, array $args = [] ): string {
    return 'https://company.test/uploads/ada-avatar.jpg';
}

function get_bloginfo( string $field ): string {
    return match ( $field ) {
        'name' => 'Acme Research',
        'language' => 'en-US',
        default => '',
    };
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
    return $value;
}

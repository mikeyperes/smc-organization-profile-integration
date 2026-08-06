<?php

declare( strict_types=1 );

use HWS\BaseTools\ContentTypes\SharedContentTypes;
use HWS\BaseTools\PluginRuntime\PluginMetadata;
use SMC\OrganizationProfile\Acf\OrganizationFields;
use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Http\RestController;
use SMC\OrganizationProfile\Schema\OrganizationGraph;
use SMC\OrganizationProfile\Schema\OrganizationSchema;
use SMC\OrganizationProfile\Schema\SchemaInjector;

defined( 'ABSPATH' ) || exit;

$checks = 0;
$failures = [];

$expect = static function ( bool $condition, string $message ) use ( &$checks, &$failures ): void {
    ++$checks;
    if ( $condition ) {
        echo "PASS {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL {$message}\n";
};

$expect( defined( 'SMC_VERSION' ) && '3.0.1' === SMC_VERSION, 'SMC 3.0.1 is active' );
$expect( class_exists( SharedContentTypes::class ), 'HWS shared content registry is active' );
$expect( version_compare( PluginMetadata::VERSION, '13.0.3', '>=' ), 'HWS stable ownership release is active' );
$expect( post_type_exists( 'organization' ), 'Organization CPT exists' );
$expect( post_type_exists( 'testimonial' ), 'Testimonial CPT exists' );
$expect( post_type_exists( 'team-member' ), 'Team Member CPT exists' );
$expect( has_action( 'init', [ SharedContentTypes::class, 'register_enabled' ] ) !== false, 'shared CPT registry is hooked by HWS' );
$expect( function_exists( 'acf_add_local_field_group' ), 'ACF Pro is active' );

$field_group = function_exists( 'acf_get_local_field_group' )
    ? acf_get_local_field_group( OrganizationFields::GROUP_KEY )
    : false;
$expect( is_array( $field_group ), 'SMC Organization ACF group is registered' );

$field_names = [];
if ( function_exists( 'acf_get_fields' ) ) {
    foreach ( acf_get_fields( OrganizationFields::GROUP_KEY ) ?: [] as $field ) {
        if ( ! empty( $field['name'] ) ) {
            $field_names[] = $field['name'];
        }
    }
}
foreach ( [ 'identity_links', 'locations', 'press_mentions', 'logo', 'gallery' ] as $name ) {
    $expect( in_array( $name, $field_names, true ), "ACF field is live: {$name}" );
}

foreach ( [ 'smc_organization', 'smc_organization_gallery', 'smc_organization_schema', 'organization' ] as $tag ) {
    $expect( shortcode_exists( $tag ), "shortcode is live: {$tag}" );
}

$server = rest_get_server();
$routes = $server->get_routes();
foreach (
    [
        '/' . RestController::NAMESPACE . '/organizations',
        '/' . RestController::NAMESPACE . '/organizations/primary',
        '/' . RestController::NAMESPACE . '/organizations/(?P<id>\d+)',
        '/' . RestController::NAMESPACE . '/organizations/(?P<id>\d+)/schema',
    ] as $route
) {
    $expect( isset( $routes[ $route ] ), "REST route is live: {$route}" );
}

$primary_id = OrganizationRepository::primary_id();
$expect( $primary_id > 0, 'a published primary organization is available' );
$expect( null === SchemaInjector::current_context(), 'unmanaged requests do not fall back to the primary organization' );

if ( $primary_id > 0 ) {
    $schema = OrganizationSchema::build( $primary_id, true );
    $graph = OrganizationGraph::build( $primary_id, $primary_id );
    $data = OrganizationRepository::data( $primary_id );

    $expect( 'https://schema.org' === ( $schema['@context'] ?? '' ), 'live Organization schema has context' );
    $expect( ! empty( $schema['name'] ), 'live Organization schema has a name' );
    $expect( ! empty( $schema['image'] ), 'live Organization schema has normalized images' );
    $expect( count( $data?->gallery() ?? [] ) > 0, 'live organization has uploaded gallery media' );
    $expect( count( $graph['@graph'] ?? [] ) >= 3, 'live graph has website, page, and organization nodes' );

    $rest_request = new WP_REST_Request( 'GET', '/' . RestController::NAMESPACE . '/organizations/' . $primary_id . '/schema' );
    $rest_response = rest_do_request( $rest_request );
    $rest_data = $rest_response->get_data();
    $expect( 200 === $rest_response->get_status(), 'live schema REST endpoint returns HTTP 200' );
    $expect( ! empty( $rest_data['graph']['@graph'] ), 'live schema REST endpoint returns the graph' );

    $gallery_json = do_shortcode( '[smc_organization id="' . $primary_id . '" field="gallery" format="json"]' );
    $expect( str_contains( html_entity_decode( $gallery_json ), 'uploads' ), 'gallery shortcode returns uploaded media URLs' );
}

$smc_source = '';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( SMC_PLUGIN_DIR . 'src' ) ) as $file ) {
    if ( $file->isFile() && 'php' === $file->getExtension() ) {
        $smc_source .= file_get_contents( $file->getPathname() ) ?: '';
    }
}
$expect( ! str_contains( $smc_source, 'register_' . 'post_type' ), 'deployed SMC source does not register CPTs' );
$expect( ! str_contains( file_get_contents( SMC_PLUGIN_DIR . 'src/Acf/OrganizationFields.php' ) ?: '', 'schema_markup' ), 'deployed ACF model has no editable schema payload' );

if ( defined( 'SFPF_PLUGIN_DIR' ) ) {
    $expect( ! is_file( SFPF_PLUGIN_DIR . 'snippets/register-cpt-organization.php' ), 'SFPF no longer ships Organization registration' );
    $expect( ! is_file( SFPF_PLUGIN_DIR . 'snippets/register-cpt-testimonial.php' ), 'SFPF no longer ships Testimonial registration' );
}

if ( [] !== $failures ) {
    fwrite( STDERR, count( $failures ) . " WordPress integration checks failed.\n" );
    exit( 1 );
}

echo "{$checks} WordPress integration checks passed.\n";

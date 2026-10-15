<?php

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$root = dirname( __DIR__ );
foreach (
    [
        'src/Acf/FieldRegistry.php',
        'src/Acf/OrganizationFields.php',
        'src/Data/Normalizer.php',
        'src/Data/FieldReader.php',
        'src/Data/MediaNormalizer.php',
        'src/Support/Settings.php',
        'src/Data/OrganizationRepository.php',
        'src/Data/OrganizationData.php',
        'src/Schema/SchemaIds.php',
        'src/Schema/OrganizationSchema.php',
        'src/Schema/OrganizationGraph.php',
        'src/Schema/SchemaInjector.php',
        'src/Presentation/FrontendAssets.php',
        'src/Presentation/OrganizationProfileRenderer.php',
        'src/Shortcodes/GalleryRenderer.php',
        'src/Shortcodes/OrganizationShortcode.php',
    ] as $file
) {
    require_once $root . '/' . $file;
}

use SMC\OrganizationProfile\Acf\OrganizationFields;
use SMC\OrganizationProfile\Data\Normalizer;
use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Schema\OrganizationGraph;
use SMC\OrganizationProfile\Schema\OrganizationSchema;
use SMC\OrganizationProfile\Schema\SchemaInjector;
use SMC\OrganizationProfile\Shortcodes\OrganizationShortcode;

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

$field_definitions = OrganizationFields::fields();
$fields = [];
foreach ( $field_definitions as $field ) {
    if ( ! empty( $field['name'] ) ) {
        $fields[ $field['name'] ] = $field;
    }
}
$expect( 1 === ( $fields['company_description']['media_upload'] ?? 0 ), 'ACF options override generic field defaults' );
$expect( 'table' === ( $fields['brand_entries']['layout'] ?? '' ), 'repeater layout options override generic defaults' );

$seek_description = [];
foreach ( $fields['seek_entries']['sub_fields'] ?? [] as $field ) {
    if ( 'description' === ( $field['name'] ?? '' ) ) {
        $seek_description = $field;
    }
}
$expect( 2 === ( $seek_description['rows'] ?? 0 ), 'nested textarea options override generic defaults' );
$expect( '' === Normalizer::date( '2026-13-40' ), 'invalid ISO-like dates are rejected' );
$expect(
    [ 'Legacy Award' ] === Normalizer::strings( [ [ 'value' => 'Legacy Award' ] ], [ 'name', 'value' ] ),
    'legacy repeater value keys normalize generically'
);

$field_keys = [];
$collect_field_keys = static function ( array $definitions ) use ( &$collect_field_keys, &$field_keys ): void {
    foreach ( $definitions as $definition ) {
        if ( ! empty( $definition['key'] ) ) {
            $field_keys[] = $definition['key'];
        }
        if ( ! empty( $definition['sub_fields'] ) && is_array( $definition['sub_fields'] ) ) {
            $collect_field_keys( $definition['sub_fields'] );
        }
    }
};
$collect_field_keys( $field_definitions );
$expect(
    count( $field_keys ) === count( array_unique( $field_keys ) ),
    'all top-level and nested ACF field keys are globally unique'
);

$data = OrganizationRepository::data( 10 );
$expect( null !== $data, 'organization repository resolves a published organization' );
$expect( 1 === count( $data?->gallery() ?? [] ), 'public gallery resolves its attachment record' );

$node = OrganizationSchema::build( 10, false );
$expect( 'Corporation' === ( $node['@type'] ?? '' ), 'configured Organization subtype is retained' );
$expect( 'Acme Research, Inc.' === ( $node['legalName'] ?? '' ), 'legal name is mapped' );
$expect( in_array( 'Acme Labs', $node['alternateName'] ?? [], true ), 'alternate names are mapped' );
$expect( '2020-02-03' === ( $node['foundingDate'] ?? '' ), 'typed founding date is mapped' );
$expect( '541715' === ( $node['naics'] ?? '' ) && '7210' === ( $node['isicV4'] ?? '' ), 'industry identifiers are mapped' );
$expect( 42 === ( $node['numberOfEmployees'] ?? null ), 'employee count is numeric' );
$expect( 1 === count( $node['contactPoint'] ?? [] ), 'contact-point repeater is mapped' );
$expect( 2 === count( $node['founder'] ?? [] ), 'WordPress and team founder relationships are represented without losing references' );
$expect( 2 === count( $node['makesOffer'] ?? [] ), 'services and products become offers' );
$expect( 1 === count( $node['brand'] ?? [] ), 'brands become graph references' );
$expect( 1 === count( $node['hasCredential'] ?? [] ), 'credentials become graph references' );
$expect( ! isset( $node['review'] ) && ! isset( $node['aggregateRating'] ), 'testimonials get no schema' );
$expect( in_array( 'https://www.linkedin.com/company/acme/', $node['sameAs'] ?? [], true ), 'identity URL is emitted as sameAs' );
$expect( ! in_array( 'https://news.example/acme-launch/', $node['sameAs'] ?? [], true ), 'press URL is never emitted as sameAs' );
$expect( 2 === count( $node['subjectOf'] ?? [] ), 'press and milestones are represented through subjectOf' );
$expect( 4 === count( $node['image'] ?? [] ), 'logo, primary, Knowledge Graph, and gallery images are referenced' );

$person_nodes = OrganizationSchema::bundle( 10 )['nodes'];
$person = array_values(
    array_filter(
        $person_nodes,
        static fn ( array $item ): bool => 'Person' === ( $item['@type'] ?? '' )
            && str_contains( $item['@id'] ?? '', 'founder-user' )
    )
)[0] ?? [];
$expect( 'ada-public@acme.example' === ( $person['email'] ?? '' ), 'founder schema uses the explicit public email field' );

$with_context = OrganizationSchema::build( 10, true );
$expect( 'https://schema.org' === ( $with_context['@context'] ?? '' ), 'compatibility schema entry includes context on request' );

$graph = OrganizationGraph::build( 10, 10 );
$types = array_column( $graph['@graph'] ?? [], '@type' );
$expect( in_array( 'WebSite', $types, true ), 'graph includes WebSite' );
$expect( in_array( 'ProfilePage', $types, true ), 'organization single graph includes ProfilePage' );
$expect( in_array( 'Corporation', $types, true ), 'graph includes the Organization node' );
$expect( in_array( 'Article', $types, true ), 'press mention becomes an Article node' );
$expect( ! in_array( 'Review', $types, true ), 'testimonials get no Review node' );
$expect( $graph === OrganizationGraph::build( 10, 10 ), 'graph generation is deterministic' );

$article = array_values(
    array_filter(
        $graph['@graph'] ?? [],
        static fn ( array $item ): bool => 'Article' === ( $item['@type'] ?? '' )
    )
)[0] ?? [];
$expect( [ '@id' => 'https://company.test/#organization' ] === ( $article['about'] ?? [] ), 'press Article points back to the Organization' );

$GLOBALS['smc_test_meta'][10]['schema_enabled'] = 0;
OrganizationRepository::clear_cache( 10 );
OrganizationSchema::clear_cache( 10 );
$expect( [] === OrganizationSchema::build( 10, false ), 'an explicit per-organization schema toggle disables output' );
$GLOBALS['smc_test_meta'][10]['schema_enabled'] = 1;
OrganizationRepository::clear_cache( 10 );
OrganizationSchema::clear_cache( 10 );
$expect( ! method_exists( SchemaInjector::class, 'filter_rank_math' ), 'Rank Math schema output is left to the HWS Base Tools switch' );

$gallery_urls = html_entity_decode(
    OrganizationShortcode::render(
        [ 'id' => 10, 'field' => 'gallery', 'format' => 'urls', 'size' => 'large' ]
    )
);
$expect( str_contains( $gallery_urls, '/104-large.jpg' ), 'legacy gallery URL format preserves the requested image size' );
$expect(
    'acme.example' === OrganizationShortcode::render( [ 'id' => 10, 'field' => 'url', 'pretty' => 'true' ] ),
    'pretty URL formatting remains compatible'
);
$GLOBALS['smc_test_queried_object_id'] = 11;
OrganizationRepository::clear_cache();
$expect(
    'Beta Research' === OrganizationShortcode::render( [ 'field' => 'name' ], null, 'organization' ),
    'legacy Organization shortcode resolves the current non-primary Organization single'
);
$expect(
    'organization' === ( $GLOBALS['smc_test_shortcode_atts_tag'] ?? '' )
        && '' === OrganizationShortcode::render( [], null, 'organization' ),
    'legacy Organization shortcode retains its tag-specific attribute filter and empty-field default'
);
$expect(
    '' === OrganizationShortcode::render( [ 'id' => 999, 'field' => 'name' ], null, 'organization' ),
    'an invalid explicit legacy Organization ID never falls back to the primary record'
);
$GLOBALS['smc_test_queried_object_id'] = 10;
OrganizationRepository::clear_cache();
$expect(
    'Research Award' === OrganizationShortcode::render( [ 'id' => 10, 'field' => 'awards' ] ),
    'legacy award shortcode reads canonical repeater rows'
);
$expect(
    str_contains( OrganizationShortcode::render( [ 'id' => 10, 'field' => 'company_info' ] ), '<p>' ),
    'legacy company information preserves allowed HTML'
);
$profile = OrganizationShortcode::render( [ 'id' => 10, 'action' => 'display_profile' ] );
$expect(
    str_contains( $profile, 'smc-organization-profile' )
        && str_contains( $profile, 'Acme Research' )
        && str_contains( $profile, '<p>Acme serves technical teams.</p>' ),
    'SMC renders the migrated complete Organization profile from canonical data'
);
$expect(
    str_contains( $GLOBALS['smc_test_styles']['smc-organization-profile'] ?? '', '.smc-organization-profile' ),
    'complete Organization profile loads the SMC-owned inline stylesheet'
);
$GLOBALS['smc_test_meta'][10]['sub_title'] = $GLOBALS['smc_test_meta'][10]['slogan'];
unset( $GLOBALS['smc_test_meta'][10]['slogan'] );
$expect(
    'Evidence into action' === OrganizationShortcode::render( [ 'id' => 10, 'field' => 'subtitle' ] ),
    'legacy subtitle alias reads the normalized slogan'
);

$GLOBALS['smc_test_options']['hws_primary_entity'] = [
    'enabled' => 1,
    'source' => 'wordpress-user',
    'kind' => 'user',
    'post_type' => '',
    'object_id' => 1,
    'entity_type' => 'organization',
];
OrganizationRepository::clear_cache();
OrganizationSchema::clear_cache();
$canonical_entity = OrganizationRepository::primary_entity();
$canonical_data = OrganizationRepository::data();
$canonical_schema = OrganizationSchema::build( 1, false );
$expect(
    'user' === ( $canonical_entity['kind'] ?? '' ) && 1 === ( $canonical_entity['id'] ?? 0 ),
    'canonical HWS primary Organization resolves from a WordPress user'
);
$expect(
    $canonical_data?->is_user() && 'Ada Founder Media LLC' === $canonical_data->text( 'legal_name' ),
    'user-backed Organization reads the same canonical ACF field contract'
);
$expect(
    'Organization' === ( $canonical_schema['@type'] ?? '' )
        && 'Ada Founder Media LLC' === ( $canonical_schema['legalName'] ?? '' ),
    'user-backed Organization produces the canonical Organization schema'
);
unset( $GLOBALS['smc_test_options']['hws_primary_entity'] );
OrganizationRepository::clear_cache();
OrganizationSchema::clear_cache();

if ( [] !== $failures ) {
    fwrite( STDERR, count( $failures ) . " schema checks failed.\n" );
    exit( 1 );
}

echo "{$checks} schema checks passed.\n";

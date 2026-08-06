<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
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

$read = static function ( string $path ): string {
    $contents = file_get_contents( $path );
    return false === $contents ? '' : $contents;
};

$initialization = $read( $root . '/initialization.php' );
$plugin = $read( $root . '/src/Plugin.php' );
$dependencies = $read( $root . '/src/Dependencies/HwsContentTypes.php' );
$fields = $read( $root . '/src/Acf/OrganizationFields.php' );
$field_registry = $read( $root . '/src/Acf/FieldRegistry.php' );
$data = $read( $root . '/src/Data/OrganizationData.php' );
$schema = $read( $root . '/src/Schema/OrganizationSchema.php' );
$graph = $read( $root . '/src/Schema/OrganizationGraph.php' );
$injector = $read( $root . '/src/Schema/SchemaInjector.php' );
$rest = $read( $root . '/src/Http/RestController.php' );
$shortcode = $read( $root . '/src/Shortcodes/OrganizationShortcode.php' );
$registrar = $read( $root . '/src/Shortcodes/ShortcodeRegistrar.php' );
$migrator = $read( $root . '/src/Migration/LegacyFieldMigrator.php' );
$admin = $read( $root . '/src/Admin/AdminPage.php' );
$field_reader = $read( $root . '/src/Data/FieldReader.php' );
$media_normalizer = $read( $root . '/src/Data/MediaNormalizer.php' );
$core_media_normalizer = $read( $root . '/lib/hexa-wordpress-plugin-core/src/DataNormalization/MediaNormalizer.php' );
$normalizer = $read( $root . '/src/Data/Normalizer.php' );
$repository = $read( $root . '/src/Data/OrganizationRepository.php' );
$gallery_renderer = $read( $root . '/src/Shortcodes/GalleryRenderer.php' );
$frontend_assets = $read( $root . '/src/Presentation/FrontendAssets.php' );
$profile_renderer = $read( $root . '/src/Presentation/OrganizationProfileRenderer.php' );
$compatibility_adapter = $read( $root . '/src/Compatibility/SfpfOrganizationAdapter.php' );
$core_integration = $read( $root . '/src/Core/CoreIntegration.php' );

preg_match( '/^ \* Version:\s*([^\s]+)/m', $initialization, $header_match );
preg_match( "/define\( 'SMC_VERSION', '([^']+)' \)/", $initialization, $constant_match );
$expect( '3.0.0' === ( $header_match[1] ?? '' ), 'plugin header version is 3.0.0' );
$expect( ( $header_match[1] ?? '' ) === ( $constant_match[1] ?? '' ), 'plugin header and runtime versions match' );
$expect( str_contains( $initialization, 'Plugin Name: SMC Organization Profile Integration' ), 'approved plugin name is used' );
$expect( str_contains( $initialization, 'Text Domain: smc-organization-profile-integration' ), 'approved plugin slug is used' );
$expect( str_contains( $initialization, 'namespace SMC\\OrganizationProfile;' ), 'approved root namespace is used' );
$expect( str_contains( $initialization, "hexa_plugin_core_register_package( 'smc-organization-profile-integration'" ), 'plugin participates in the shared Core resolver' );
$expect( substr_count( $initialization, "\n" ) < 70, 'entry file remains a thin composition bootstrap' );

$source_files = [];
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src' ) );
foreach ( $iterator as $file ) {
    if ( $file->isFile() && 'php' === $file->getExtension() ) {
        $source_files[] = $file->getPathname();
    }
}
sort( $source_files );

$all_source = $initialization;
foreach ( $source_files as $file ) {
    $contents = $read( $file );
    $all_source .= "\n" . $contents;
    $expect(
        1 === preg_match( '/namespace\s+SMC\\\\OrganizationProfile(?:\\\\[A-Za-z0-9_]+)*;/', $contents ),
        'namespaced source: ' . substr( $file, strlen( $root ) + 1 )
    );
}

$expect( ! str_contains( $all_source, 'register_' . 'post_type' ), 'SMC contains no content-type registration call' );
$expect( ! str_contains( $fields, 'schema_markup' ), 'ACF model contains no editable schema payload' );
$expect( str_contains( $field_registry, "'return_format' => 'id'" ), 'media fields store attachment IDs' );
$expect( str_contains( $dependencies, "public const MINIMUM_VERSION = '13.0.0'" ), 'SMC requires the HWS stable ownership release' );
$expect(
    str_contains( $dependencies, "'smp_enable_cpt_organization'" )
    && str_contains( $dependencies, "'enable_cpt_testimonial'" )
    && str_contains( $dependencies, "'smp_enable_cpt_teammember'" ),
    'SMC enables all HWS-owned shared content contracts'
);
$expect( str_contains( $dependencies, "update_option( 'smp_enable_acf_organization', 0" ), 'SMC disables the former HWS organization field group' );

$required_fields = [
    'legal_name',
    'alternate_name_entries',
    'slogan',
    'short_description',
    'company_description',
    'founding_date',
    'dissolution_date',
    'founding_location',
    'locations',
    'area_served',
    'contact_points',
    'identity_links',
    'identifiers',
    'naics_code',
    'isic_code',
    'number_of_employees',
    'founder_users',
    'founders',
    'team_members',
    'parent_organization',
    'sub_organizations',
    'services',
    'products',
    'brand_entries',
    'credentials',
    'award_entries',
    'seek_entries',
    'press_mentions',
    'milestones',
    'testimonials',
    'logo',
    'primary_image',
    'knowledge_graph_images',
    'gallery',
];
foreach ( $required_fields as $field ) {
    $expect( str_contains( $fields, "'{$field}'" ), "ACF model includes {$field}" );
}

$expect(
    str_contains( $data, "'slogan'              => [ 'sub_title' ]" )
    && str_contains( $data, "'logo'                => [ 'image_cropped', '_thumbnail_id' ]" ),
    'normalized reads retain SFPF field compatibility'
);
$expect(
    str_contains( $schema, "'subjectOf'" )
    && str_contains( $schema, 'NodeFactory::article' )
    && str_contains( $schema, "array_column( \$data->identity_links(), 'url' )" ),
    'press is subjectOf while sameAs comes only from identity links'
);
foreach ( [ 'foundingLocation', 'contactPoint', 'numberOfEmployees', 'makesOffer', 'hasCredential', 'aggregateRating', 'ImageObject' ] as $property ) {
    $haystack = 'ImageObject' === $property ? $core_media_normalizer : $schema;
    $expect( str_contains( $haystack, $property ), "schema supports {$property}" );
}
$expect(
    str_contains( $graph, "'WebSite'" )
    && str_contains( $graph, "'AboutPage'" )
    && str_contains( $graph, "'ProfilePage'" ),
    'graph supports website and page nodes'
);
$expect(
    str_contains( $core_integration, 'new CoreSchemaInjector' )
    && str_contains( $injector, "add_filter( 'rank_math/json_ld'" )
    && str_contains( $injector, 'SchemaDocumentRenderer' ),
    'frontend injection and Rank Math coexistence are guarded through Hexa WP Core'
);
$expect( str_contains( $injector, 'if ( $organization_id <= 0 )' ), 'unmanaged frontend requests cannot fall back to the primary organization' );
foreach ( [ '/organizations', '/organizations/primary', '/organizations/(?P<id>\\d+)', '/organizations/(?P<id>\\d+)/schema' ] as $route ) {
    $expect( str_contains( $rest, $route ), "REST route exists: {$route}" );
}
$expect( str_contains( $rest, "'smc_schema'" ), 'standard organization REST records include live schema' );
foreach ( [ 'smc_organization', 'smc_organization_gallery', 'smc_organization_schema', 'organization' ] as $tag ) {
    $expect( str_contains( $registrar, "'{$tag}'" ), "shortcode is registered: {$tag}" );
}
$expect( str_contains( $shortcode, 'PUBLIC_FIELDS' ), 'field shortcode uses a public allowlist' );
$expect(
    str_contains( $repository, 'public static function resolve_id' )
    && str_contains( $shortcode, "'organization' === \$tag" ),
    'canonical shortcode resolution preserves current-record and legacy-tag semantics'
);
$expect( str_contains( $migrator, 'copy_if_empty' ) && str_contains( $migrator, '_smc_migration_version' ), 'legacy migration is idempotent and non-overwriting' );
$expect( str_contains( $admin, "check_admin_referer( 'smc_save_settings' )" ), 'admin settings require a nonce' );
$expect(
    str_contains( $admin, 'HostTabsRenderer' )
    && str_contains( $admin, 'TabRegistry' )
    && str_contains( $admin, 'TabDefinition' )
    && str_contains( $admin, 'FieldStructureRenderer' )
    && 1 === preg_match( "/'layout'\\s*=>\\s*'sidebar'/", $admin )
    && str_contains( $admin, "'groups'" )
    && str_contains( $admin, 'sidebar_identity' )
    && ! str_contains( $admin, 'nav-tab-wrapper' ),
    'admin uses the current grouped Hexa Core sidebar'
);
$expect(
    str_contains( $admin, 'AjaxActionRegistry' )
    && str_contains( $admin, "wp_add_inline_style( 'common'" )
    && ! str_contains( $admin, 'wp_enqueue_style(' ),
    'admin tabs load lazily without a blocking plugin stylesheet request'
);
$expect(
    ! str_contains( $admin, "self::select_row( Settings::PRIMARY_ORGANIZATION" )
    && str_contains( $admin, 'tab=website-types' ),
    'admin delegates canonical entity selection to HWS Base Tools'
);
$expect(
    str_contains( $field_reader, 'CoreFieldReader' )
    && ! str_contains( $field_reader, 'private array $cache' )
    && str_contains( $media_normalizer, 'CoreMediaNormalizer' )
    && str_contains( $normalizer, 'ValueNormalizer' )
    && str_contains( $repository, 'private static array $data' )
    && str_contains( $schema, 'private static array $bundle_cache' ),
    'generic normalization delegates to Core while domain repositories and schema retain request caches'
);
$expect(
    ! str_contains( $dependencies, "add_action( 'init', [ self::class, 'ensure_registered' ]" )
    && 1 === substr_count( $plugin, 'Settings::install_defaults();' )
    && str_contains( $plugin, "add_action( 'updated_post_meta'" )
    && str_contains( $plugin, 'clear_request_caches' ),
    'runtime avoids repeated setup and invalidates request caches after writes'
);
$expect(
    str_contains( $gallery_renderer, 'FrontendAssets::enqueue()' )
    && str_contains( $frontend_assets, "wp_register_style( 'smc-organization-profile', false" )
    && str_contains( $frontend_assets, 'wp_add_inline_style' )
    && str_contains( $frontend_assets, "is_singular( 'organization' )" )
    && str_contains( $plugin, 'FrontendAssets::register()' ),
    'shared frontend presentation preserves Organization-page loading without a blocking stylesheet request'
);
$expect( '3.0.0' === trim( $read( $root . '/lib/hexa-wordpress-plugin-core/VERSION' ) ), 'bundled Core is synchronized to canonical 3.0.0' );
$expect( is_file( $root . '/docs/architecture.md' ) && is_file( $root . '/docs/schema-contract.md' ), 'architecture and schema contracts are documented' );

// Architecture regressions: SMC owns Organization behavior; generic mechanics stay in Core.
$expect(
    str_contains( $field_registry, 'AcfFieldFactory' )
    && ! str_contains( $field_registry, 'private static function field(' ),
    'SMC retains only its ACF key policy and delegates generic field construction to Core'
);
$expect(
    str_contains( $dependencies, 'PluginRecommendationRegistry' )
    && str_contains( $dependencies, 'PluginCheckService' ),
    'dependency discovery and status use the Core plugin-check contracts'
);
$expect(
    str_contains( $registrar, 'ShortcodeRegistry' )
    && str_contains( $registrar, 'ShortcodeDefinition' ),
    'shortcode documentation is described through the Core registry'
);
$expect(
    str_contains( $graph, 'SchemaDocumentRenderer' )
    && str_contains( $graph, '->normalize(' ),
    'all Organization graph consumers receive Core-normalized and deduplicated documents'
);
$expect(
    str_contains( $profile_renderer, 'final class OrganizationProfileRenderer' )
    && str_contains( $profile_renderer, 'smc-organization-profile' )
    && str_contains( $shortcode, "'display_profile'" )
    && str_contains( $shortcode, 'OrganizationProfileRenderer::render' ),
    'SMC owns the complete Organization profile presentation path'
);
$expect(
    str_contains( $compatibility_adapter, 'Read-only compatibility contract' )
    && ! str_contains( $compatibility_adapter, 'update_' . 'option' )
    && ! str_contains( $compatibility_adapter, 'update_' . 'post_meta' ),
    'the historical SFPF Organization adapter is read-only'
);
$expect(
    str_contains( $registrar, "add_shortcode( 'organization'" )
    && ! str_contains( $profile_renderer, 'add_shortcode(' ),
    'SMC is the sole host owner of the legacy Organization shortcode alias'
);
$expect(
    str_contains( $core_integration, 'PluginContext' )
    && str_contains( $core_integration, 'OrganizationContentType::registry( $context )' ),
    'SMC passes its Core plugin context into the domain content-type registry'
);
$expect(
    str_contains( $read( $root . '/assets/frontend.css' ), '.smc-organization-profile' )
    && ! str_contains( $read( $root . '/assets/frontend.css' ), '.sfpf-organization-profile' ),
    'SMC owns Organization profile styling without an SFPF stylesheet dependency'
);

$root_php = array_map( 'basename', glob( $root . '/*.php' ) ?: [] );
sort( $root_php );
$expect( [ 'initialization.php', 'uninstall.php' ] === $root_php, 'root PHP surface is limited to bootstrap and uninstall' );

$lint_files = [ $root . '/initialization.php', $root . '/uninstall.php', ...$source_files ];
foreach ( glob( $root . '/tests/*.php' ) ?: [] as $test_file ) {
    $lint_files[] = $test_file;
}
foreach ( $lint_files as $file ) {
    $command = escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file ) . ' 2>&1';
    exec( $command, $output, $status );
    $expect( 0 === $status, 'PHP lint: ' . substr( $file, strlen( $root ) + 1 ) );
    $output = [];
}

if ( [] !== $failures ) {
    fwrite( STDERR, count( $failures ) . " static checks failed.\n" );
    exit( 1 );
}

$unit_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/schema-unit.php' );
passthru( $unit_command, $unit_status );
if ( 0 !== $unit_status ) {
    exit( $unit_status );
}

echo "{$checks} static checks passed; 0 failed.\n";

<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Migration;

use SMC\OrganizationProfile\Data\FieldReader;
use SMC\OrganizationProfile\Data\MediaNormalizer;
use SMC\OrganizationProfile\Data\Normalizer;
use SMC\OrganizationProfile\Data\OrganizationData;
use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class LegacyFieldMigrator {
    public const VERSION = '1.0.0';

    private const COMPLETED_OPTION = 'smc_organization_migration_version';
    private const REPORT_OPTION = 'smc_organization_migration_report';
    private const PENDING_OPTION = 'smc_organization_migration_pending';

    public static function register(): void {
        add_action( 'admin_init', [ self::class, 'maybe_run' ], 2 );
        \Hexa\PluginCore\Fields\Hooks::on( 'save_post', [ self::class, 'migrate_saved_post' ], 30 );
    }

    public static function schedule(): void {
        update_option( self::PENDING_OPTION, 1, false );
    }

    public static function maybe_run(): void {
        if (
            self::VERSION === get_option( self::COMPLETED_OPTION, '' )
            && ! get_option( self::PENDING_OPTION, false )
        ) {
            return;
        }

        $post_ids = get_posts(
            [
                'post_type'      => 'organization',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ]
        );

        $report = [ 'posts_scanned' => 0, 'posts_changed' => 0, 'fields_migrated' => 0 ];
        foreach ( $post_ids as $post_id ) {
            ++$report['posts_scanned'];
            $changed = self::migrate_post( absint( $post_id ) );
            if ( $changed > 0 ) {
                ++$report['posts_changed'];
                $report['fields_migrated'] += $changed;
            }
        }

        $legacy_primary = absint( get_option( 'sfpf_primary_organization', 0 ) );
        if ( 0 === Settings::integer( Settings::PRIMARY_ORGANIZATION ) && OrganizationRepository::is_organization( $legacy_primary ) ) {
            update_option( Settings::PRIMARY_ORGANIZATION, $legacy_primary, false );
        }

        update_option( self::REPORT_OPTION, $report, false );
        update_option( self::COMPLETED_OPTION, self::VERSION, false );
        delete_option( self::PENDING_OPTION );
    }

    public static function migrate_saved_post( mixed $post_id ): void {
        $post_id = absint( $post_id );
        if ( OrganizationRepository::is_organization( $post_id ) ) {
            self::migrate_post( $post_id );
        }
    }

    public static function migrate_post( int $post_id ): int {
        if ( ! OrganizationRepository::is_organization( $post_id ) ) {
            return 0;
        }

        $fields = new FieldReader( $post_id );
        $data = new OrganizationData( $post_id );
        $changed = 0;

        $changed += self::copy_if_empty( $post_id, 'slogan', $fields->read( 'sub_title' ) );
        $changed += self::copy_if_empty( $post_id, 'short_description', $fields->read( 'short_summary' ) );
        $changed += self::copy_if_empty( $post_id, 'company_description', $fields->read( 'company_info' ) );

        if ( ! Normalizer::present( $fields->read( 'alternate_name_entries' ) ) ) {
            $names = Normalizer::strings( $fields->read( 'alternate_names' ) );
            $changed += self::write_if_present(
                $post_id,
                'alternate_name_entries',
                array_map( static fn ( string $name ): array => [ 'name' => $name ], $names )
            );
        }

        if ( ! Normalizer::present( $fields->read( 'identity_links' ) ) ) {
            $changed += self::write_if_present( $post_id, 'identity_links', $data->identity_links() );
        }

        if ( ! Normalizer::present( $fields->read( 'locations' ) ) ) {
            $changed += self::write_if_present( $post_id, 'locations', $data->locations() );
        }

        if ( ! Normalizer::present( $fields->read( 'contact_points' ) ) ) {
            $changed += self::write_if_present( $post_id, 'contact_points', $data->contact_points() );
        }

        $changed += self::migrate_simple_rows( $post_id, $fields, 'brands', 'brand_entries' );
        $changed += self::migrate_simple_rows( $post_id, $fields, 'awards', 'award_entries' );
        $changed += self::migrate_simple_rows( $post_id, $fields, 'seeks', 'seek_entries' );

        if ( ! Normalizer::present( $fields->read( 'logo' ) ) ) {
            $logo_id = MediaNormalizer::attachment_id( $fields->read( 'image_cropped' ) );
            $changed += self::write_if_present( $post_id, 'logo', $logo_id );
        }

        if ( ! Normalizer::present( $fields->read( 'primary_image' ) ) ) {
            $changed += self::write_if_present( $post_id, 'primary_image', get_post_thumbnail_id( $post_id ) );
        }

        $founding_date = $fields->read( 'founding_date' );
        if ( is_string( $founding_date ) && preg_match( '/^\d{4}$/', trim( $founding_date ) ) ) {
            self::write( $post_id, 'founding_date', trim( $founding_date ) . '-01-01' );
            update_post_meta( $post_id, '_smc_founding_date_precision', 'year' );
            ++$changed;
        } elseif ( Normalizer::present( $founding_date ) ) {
            $normalized = Normalizer::date( $founding_date );
            if ( '' !== $normalized && $normalized !== $founding_date ) {
                self::write( $post_id, 'founding_date', $normalized );
                ++$changed;
            }
        }

        update_post_meta( $post_id, '_smc_migration_version', self::VERSION );

        return $changed;
    }

    /** @return array<string,int> */
    public static function report(): array {
        $report = get_option( self::REPORT_OPTION, [] );
        return is_array( $report ) ? $report : [];
    }

    private static function migrate_simple_rows( int $post_id, FieldReader $fields, string $legacy, string $canonical ): int {
        if ( Normalizer::present( $fields->read( $canonical ) ) ) {
            return 0;
        }

        $rows = array_map(
            static fn ( string $name ): array => [ 'name' => $name ],
            Normalizer::strings( $fields->read( $legacy ), [ 'name', 'value' ] )
        );

        return self::write_if_present( $post_id, $canonical, $rows );
    }

    private static function copy_if_empty( int $post_id, string $target, mixed $source ): int {
        $fields = new FieldReader( $post_id );
        if ( Normalizer::present( $fields->read( $target ) ) ) {
            return 0;
        }

        return self::write_if_present( $post_id, $target, $source );
    }

    private static function write_if_present( int $post_id, string $field, mixed $value ): int {
        if ( ! Normalizer::present( $value ) ) {
            return 0;
        }

        self::write( $post_id, $field, $value );
        return 1;
    }

    private static function write( int $post_id, string $field, mixed $value ): void {
        if ( \Hexa\PluginCore\Fields\Field::available() ) {
            \Hexa\PluginCore\Fields\Field::update( $field, $value, $post_id );
            return;
        }

        update_post_meta( $post_id, $field, $value );
    }
}

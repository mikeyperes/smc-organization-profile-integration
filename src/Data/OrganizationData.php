<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

defined( 'ABSPATH' ) || exit;

final class OrganizationData {
    private const SIMPLE_ROW_FIELDS = [ 'brand_entries', 'award_entries', 'seek_entries' ];

    private const ALIASES = [
        'slogan'              => [ 'sub_title' ],
        'short_description'   => [ 'short_summary' ],
        'company_description' => [ 'company_info' ],
        'logo'                => [ 'image_cropped', '_thumbnail_id' ],
        'primary_image'       => [ '_thumbnail_id' ],
        'brand_entries'       => [ 'brands' ],
        'award_entries'       => [ 'awards' ],
        'seek_entries'        => [ 'seeks' ],
    ];

    private readonly FieldReader $fields;

    public function __construct( private readonly int $object_id, private readonly string $kind = 'post' ) {
        $this->fields = new FieldReader( $object_id, $kind );
    }

    public function id(): int {
        return $this->object_id;
    }

    public function kind(): string {
        return $this->kind;
    }

    public function is_user(): bool {
        return 'user' === $this->kind;
    }

    public function name(): string {
        if ( $this->is_user() ) {
            $user = get_userdata( $this->object_id );
            return $user ? Normalizer::text( $user->display_name ) : '';
        }
        return Normalizer::text( get_the_title( $this->object_id ) );
    }

    public function permalink(): string {
        return Normalizer::url( $this->is_user() ? get_author_posts_url( $this->object_id ) : get_permalink( $this->object_id ) );
    }

    public function description(): string {
        if ( $this->is_user() ) {
            $user = get_userdata( $this->object_id );
            return $user ? Normalizer::text( $user->description ) : '';
        }
        return Normalizer::text( get_post_field( 'post_excerpt', $this->object_id ) ?: get_post_field( 'post_content', $this->object_id ) );
    }

    public function raw( string $name ): mixed {
        return $this->fields->first( $name, ...( self::ALIASES[ $name ] ?? [] ) );
    }

    public function text( string $name ): string {
        return Normalizer::text( $this->raw( $name ) );
    }

    public function url( string $name ): string {
        return Normalizer::url( $this->raw( $name ) );
    }

    public function date( string $name ): string {
        return Normalizer::date( $this->raw( $name ) );
    }

    public function number( string $name ): int|float|null {
        return Normalizer::number( $this->raw( $name ) );
    }

    /** @return list<array<string,mixed>> */
    public function rows( string $name ): array {
        $value = $this->raw( $name );
        $rows = Normalizer::rows( $value );

        if ( ! in_array( $name, self::SIMPLE_ROW_FIELDS, true ) ) {
            return $rows;
        }

        if ( [] === $rows ) {
            return array_map(
                static fn ( string $item ): array => [ 'name' => $item ],
                Normalizer::strings( $value, [ 'name', 'value' ] )
            );
        }

        return array_map(
            static function ( array $row ): array {
                if ( ! Normalizer::present( $row['name'] ?? null ) && Normalizer::present( $row['value'] ?? null ) ) {
                    $row['name'] = $row['value'];
                }
                return $row;
            },
            $rows
        );
    }

    /** @return list<int> */
    public function relation_ids( string $name ): array {
        return Normalizer::ids( $this->raw( $name ) );
    }

    /** @return list<string> */
    public function alternate_names(): array {
        $canonical = Normalizer::strings( $this->fields->read( 'alternate_name_entries' ) );
        $legacy = Normalizer::strings( $this->fields->read( 'alternate_names' ) );

        return array_values( array_unique( [ ...$canonical, ...$legacy ] ) );
    }

    /** @return list<array{platform:string,label:string,url:string}> */
    public function identity_links(): array {
        $links = [];

        foreach ( Normalizer::rows( $this->fields->read( 'identity_links' ) ) as $row ) {
            $url = Normalizer::url( $row['url'] ?? '' );
            if ( '' === $url ) {
                continue;
            }

            $links[ $url ] = [
                'platform' => Normalizer::text( $row['platform'] ?? 'other' ),
                'label'    => Normalizer::text( $row['label'] ?? '' ),
                'url'      => $url,
            ];
        }

        foreach ( [ 'facebook', 'instagram', 'linkedin', 'x', 'youtube', 'tiktok', 'github', 'wikipedia', 'crunchbase' ] as $platform ) {
            $url = Normalizer::url( $this->fields->read( 'url_' . $platform ) );
            if ( '' !== $url ) {
                $links[ $url ] = [ 'platform' => $platform, 'label' => '', 'url' => $url ];
            }
        }

        foreach ( Normalizer::urls( $this->fields->read( 'sameas_urls' ), $this->fields->read( 'social_urls' ) ) as $url ) {
            $links[ $url ] ??= [ 'platform' => 'other', 'label' => '', 'url' => $url ];
        }

        ksort( $links );
        return array_values( $links );
    }

    /** @return list<array<string,mixed>> */
    public function locations(): array {
        $rows = Normalizer::rows( $this->fields->read( 'locations' ) );
        if ( [] !== $rows ) {
            return $rows;
        }

        $headquarters = $this->fields->read( 'headquarters' );
        $address = $this->fields->read( 'address' );
        $row = [ 'location_type' => 'headquarters' ];

        if ( is_array( $headquarters ) ) {
            $row['name'] = $headquarters['location'] ?? '';
            $row['same_as'] = $headquarters['wikipedia_url'] ?? '';
        } else {
            $row['name'] = $headquarters;
        }

        if ( is_array( $address ) ) {
            $row = array_merge( $row, $address );
        }

        $rows = Normalizer::present( $row ) ? [ $row ] : [];
        $secondary = Normalizer::text( $this->fields->read( 'secondary_location' ) );
        if ( '' !== $secondary ) {
            $rows[] = [ 'name' => $secondary, 'location_type' => 'office' ];
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public function contact_points(): array {
        $rows = Normalizer::rows( $this->fields->read( 'contact_points' ) );
        if ( [] !== $rows ) {
            return $rows;
        }

        return Normalizer::rows( $this->fields->read( 'contact_point' ) );
    }

    /** @return array<string,mixed> */
    public function logo(): array {
        $value = $this->raw( 'logo' );
        if ( ! Normalizer::present( $value ) && $this->is_user() ) {
            $value = get_avatar_url( $this->object_id, [ 'size' => 512 ] );
        }
        return MediaNormalizer::image( $value );
    }

    /** @return array<string,mixed> */
    public function primary_image(): array {
        $value = $this->raw( 'primary_image' );
        if ( ! Normalizer::present( $value ) && $this->is_user() ) {
            $value = get_avatar_url( $this->object_id, [ 'size' => 1200 ] );
        }
        return MediaNormalizer::image( $value );
    }

    /** @return list<array<string,mixed>> */
    public function gallery( string $field = 'gallery', string $size = 'full' ): array {
        return MediaNormalizer::gallery( $this->raw( $field ), $size );
    }

    public function schema_enabled(): bool {
        $value = $this->fields->read( 'schema_enabled' );
        if ( null === $value || '' === $value ) {
            return true;
        }

        return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    /** @return array<string,mixed> */
    public function export(): array {
        return [
            'id'                  => $this->id(),
            'source_kind'         => $this->kind(),
            'name'                => $this->name(),
            'legal_name'          => $this->text( 'legal_name' ),
            'alternate_names'     => $this->alternate_names(),
            'slogan'              => $this->text( 'slogan' ),
            'short_description'   => $this->text( 'short_description' ),
            'company_description' => $this->text( 'company_description' ),
            'url'                 => $this->url( 'url' ),
            'permalink'           => $this->permalink(),
            'founding_date'       => $this->date( 'founding_date' ),
            'dissolution_date'    => $this->date( 'dissolution_date' ),
            'locations'           => $this->locations(),
            'area_served'         => $this->rows( 'area_served' ),
            'contact_points'      => $this->contact_points(),
            'identity_links'      => $this->identity_links(),
            'identifiers'         => $this->rows( 'identifiers' ),
            'logo'                => $this->logo(),
            'primary_image'       => $this->primary_image(),
            'gallery'             => $this->gallery(),
        ];
    }
}

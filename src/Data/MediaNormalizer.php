<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

defined( 'ABSPATH' ) || exit;

final class MediaNormalizer {
    /** @var array<string,array<string,mixed>> */
    private static array $image_cache = [];

    public static function clear_cache(): void {
        self::$image_cache = [];
    }

    public static function attachment_id( mixed $value ): int {
        if ( is_object( $value ) && isset( $value->ID ) ) {
            return absint( $value->ID );
        }

        if ( is_array( $value ) ) {
            return absint( $value['ID'] ?? $value['id'] ?? 0 );
        }

        if ( is_numeric( $value ) ) {
            return absint( $value );
        }

        $url = Normalizer::url( $value );
        if ( '' !== $url && function_exists( 'attachment_url_to_postid' ) ) {
            return absint( attachment_url_to_postid( $url ) );
        }

        return 0;
    }

    /** @return array<string,mixed> */
    public static function image( mixed $value, string $size = 'full' ): array {
        $id = self::attachment_id( $value );
        $cache_key = $id > 0 ? $id . ':' . $size : '';
        if ( '' !== $cache_key && array_key_exists( $cache_key, self::$image_cache ) ) {
            return self::$image_cache[ $cache_key ];
        }

        $fallback_url = is_array( $value ) ? Normalizer::url( $value['url'] ?? '' ) : Normalizer::url( $value );
        $source = $id > 0 && function_exists( 'wp_get_attachment_image_src' )
            ? wp_get_attachment_image_src( $id, $size )
            : false;
        $full = $id > 0 && function_exists( 'wp_get_attachment_image_src' )
            ? wp_get_attachment_image_src( $id, 'full' )
            : false;

        $url = is_array( $source ) ? Normalizer::url( $source[0] ?? '' ) : $fallback_url;
        if ( '' === $url ) {
            if ( '' !== $cache_key ) {
                self::$image_cache[ $cache_key ] = [];
            }
            return [];
        }

        $alt = $id > 0 ? Normalizer::text( get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
        $title = $id > 0 && function_exists( 'get_the_title' ) ? Normalizer::text( get_the_title( $id ) ) : '';
        $caption = $id > 0 && function_exists( 'wp_get_attachment_caption' )
            ? Normalizer::text( wp_get_attachment_caption( $id ) )
            : '';

        $image = array_filter(
            [
                'id'       => $id,
                'url'      => $url,
                'full_url' => is_array( $full ) ? Normalizer::url( $full[0] ?? '' ) : $url,
                'width'    => is_array( $source ) ? absint( $source[1] ?? 0 ) : 0,
                'height'   => is_array( $source ) ? absint( $source[2] ?? 0 ) : 0,
                'alt'      => $alt,
                'title'    => $title,
                'caption'  => $caption,
            ],
            static fn ( mixed $item ): bool => Normalizer::present( $item )
        );

        if ( '' !== $cache_key ) {
            self::$image_cache[ $cache_key ] = $image;
        }

        return $image;
    }

    /** @return list<array<string,mixed>> */
    public static function gallery( mixed $value, string $size = 'full' ): array {
        if ( function_exists( 'maybe_unserialize' ) ) {
            $value = maybe_unserialize( $value );
        }

        if ( ! is_array( $value ) ) {
            $urls = Normalizer::urls( $value );
            $value = [] !== $urls ? $urls : [ $value ];
        }

        $images = [];
        foreach ( $value as $item ) {
            $image = self::image( $item, $size );
            if ( [] !== $image ) {
                $images[ $image['url'] ] = $image;
            }
        }

        return array_values( $images );
    }

    /** @return array<string,mixed> */
    public static function schema_image( array $image, string $id ): array {
        if ( empty( $image['url'] ) ) {
            return [];
        }

        return array_filter(
            [
                '@type'      => 'ImageObject',
                '@id'        => $id,
                'url'        => $image['full_url'] ?? $image['url'],
                'contentUrl' => $image['full_url'] ?? $image['url'],
                'width'      => $image['width'] ?? null,
                'height'     => $image['height'] ?? null,
                'caption'    => $image['caption'] ?? $image['title'] ?? null,
                'description'=> $image['alt'] ?? null,
            ],
            static fn ( mixed $item ): bool => Normalizer::present( $item )
        );
    }
}

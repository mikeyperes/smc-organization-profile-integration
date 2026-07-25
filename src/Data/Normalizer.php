<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Data;

defined( 'ABSPATH' ) || exit;

final class Normalizer {
    public static function present( mixed $value ): bool {
        if ( null === $value || false === $value || '' === $value ) {
            return false;
        }

        return ! is_array( $value ) || [] !== array_filter(
            $value,
            static fn ( mixed $item ): bool => self::present( $item )
        );
    }

    public static function text( mixed $value ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }

        return function_exists( 'wp_strip_all_tags' )
            ? trim( wp_strip_all_tags( $value, true ) )
            : trim( strip_tags( $value ) );
    }

    public static function url( mixed $value ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $url = trim( (string) $value );
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return '';
        }

        return function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
    }

    public static function email( mixed $value ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $email = trim( (string) $value );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            return '';
        }

        return function_exists( 'sanitize_email' ) ? sanitize_email( $email ) : $email;
    }

    public static function date( mixed $value ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $date = trim( (string) $value );
        if ( preg_match( '/^\d{4}$/', $date ) ) {
            return $date;
        }

        if ( preg_match( '/^\d{4}-\d{2}$/', $date ) ) {
            $parsed = \DateTimeImmutable::createFromFormat( '!Y-m', $date );
            return $parsed && $parsed->format( 'Y-m' ) === $date ? $date : '';
        }

        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            $parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
            return $parsed && $parsed->format( 'Y-m-d' ) === $date ? $date : '';
        }

        if ( preg_match( '/^\d{8}$/', $date ) ) {
            $parsed = \DateTimeImmutable::createFromFormat( '!Ymd', $date );
            return $parsed && $parsed->format( 'Ymd' ) === $date
                ? $parsed->format( 'Y-m-d' )
                : '';
        }

        $timestamp = strtotime( $date );
        return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
    }

    public static function number( mixed $value ): int|float|null {
        if ( ! is_numeric( $value ) ) {
            return null;
        }

        $number = (float) $value;
        return floor( $number ) === $number ? (int) $number : $number;
    }

    /** @return list<array<string,mixed>> */
    public static function rows( mixed $value ): array {
        if ( function_exists( 'maybe_unserialize' ) ) {
            $value = maybe_unserialize( $value );
        }

        if ( ! is_array( $value ) || [] === $value ) {
            return [];
        }

        if ( self::is_associative( $value ) ) {
            return [ $value ];
        }

        return array_values(
            array_filter(
                $value,
                static fn ( mixed $row ): bool => is_array( $row )
            )
        );
    }

    /**
     * @param string|list<string> $row_keys Repeater value keys in priority order.
     * @return list<string>
     */
    public static function strings( mixed $value, string|array $row_keys = 'name' ): array {
        $strings = [];
        $row_keys = is_array( $row_keys ) ? $row_keys : [ $row_keys ];

        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                if ( is_array( $item ) ) {
                    $candidate = '';
                    foreach ( $row_keys as $row_key ) {
                        if ( isset( $item[ $row_key ] ) && self::present( $item[ $row_key ] ) ) {
                            $candidate = $item[ $row_key ];
                            break;
                        }
                    }
                    $item = $candidate;
                }
                $text = self::text( $item );
                if ( '' !== $text ) {
                    $strings[] = $text;
                }
            }
        } elseif ( is_scalar( $value ) ) {
            foreach ( preg_split( '/[\r\n,]+/', (string) $value ) ?: [] as $item ) {
                $text = self::text( $item );
                if ( '' !== $text ) {
                    $strings[] = $text;
                }
            }
        }

        return array_values( array_unique( $strings ) );
    }

    /** @return list<string> */
    public static function urls( mixed ...$values ): array {
        $urls = [];

        foreach ( $values as $value ) {
            if ( is_array( $value ) ) {
                foreach ( $value as $item ) {
                    if ( is_array( $item ) ) {
                        $item = $item['url'] ?? '';
                    }
                    $url = self::url( $item );
                    if ( '' !== $url ) {
                        $urls[] = $url;
                    }
                }
                continue;
            }

            if ( is_scalar( $value ) ) {
                preg_match_all( '#https?://[^\s,<>]+#i', (string) $value, $matches );
                foreach ( $matches[0] ?? [] as $item ) {
                    $url = self::url( rtrim( $item, '.,;:)\]' ) );
                    if ( '' !== $url ) {
                        $urls[] = $url;
                    }
                }
            }
        }

        return array_values( array_unique( $urls ) );
    }

    /** @return list<int> */
    public static function ids( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            $value = [ $value ];
        }

        $ids = [];
        foreach ( $value as $item ) {
            if ( is_object( $item ) && isset( $item->ID ) ) {
                $item = $item->ID;
            } elseif ( is_array( $item ) ) {
                $item = $item['ID'] ?? $item['id'] ?? 0;
            }

            $id = absint( $item );
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }

        return array_values( array_unique( $ids ) );
    }

    /** @param array<mixed> $value */
    private static function is_associative( array $value ): bool {
        return array_keys( $value ) !== range( 0, count( $value ) - 1 );
    }
}

<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Schema;

use Hexa\PluginCore\SchemaTools\SchemaGraph;
use SMC\OrganizationProfile\Data\MediaNormalizer;
use SMC\OrganizationProfile\Data\Normalizer;

defined( 'ABSPATH' ) || exit;

final class NodeFactory {
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function place( array $row, string $id ): array {
        $address = self::address( $row );
        $latitude = Normalizer::number( $row['latitude'] ?? null );
        $longitude = Normalizer::number( $row['longitude'] ?? null );
        $geo = null;

        if ( null !== $latitude && null !== $longitude ) {
            $geo = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $latitude,
                'longitude' => $longitude,
            ];
        }

        $name = Normalizer::text( $row['name'] ?? $row['location'] ?? '' );
        if ( '' === $name && isset( $address['addressLocality'] ) ) {
            $name = Normalizer::text( $address['addressLocality'] );
        }

        return self::clean(
            [
                '@type'     => 'Place',
                '@id'       => $id,
                'name'      => $name,
                'address'   => $address,
                'geo'       => $geo,
                'url'       => Normalizer::url( $row['url'] ?? '' ),
                'sameAs'    => Normalizer::url( $row['same_as'] ?? $row['wikipedia_url'] ?? '' ),
                'email'     => Normalizer::email( $row['email'] ?? '' ),
                'telephone' => Normalizer::text( $row['telephone'] ?? '' ),
            ]
        );
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function address( array $row ): array {
        $address = self::clean(
            [
                '@type'          => 'PostalAddress',
                'streetAddress'  => Normalizer::text( $row['street_address'] ?? '' ),
                'addressLocality'=> Normalizer::text( $row['address_locality'] ?? '' ),
                'addressRegion'  => Normalizer::text( $row['address_region'] ?? '' ),
                'postalCode'     => Normalizer::text( $row['postal_code'] ?? '' ),
                'addressCountry' => Normalizer::text( $row['address_country'] ?? '' ),
            ]
        );

        return count( $address ) > 1 ? $address : [];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function contact_point( array $row ): array {
        return self::clean(
            [
                '@type'             => 'ContactPoint',
                'contactType'       => Normalizer::text( $row['contact_type'] ?? '' ),
                'email'             => Normalizer::email( $row['email'] ?? '' ),
                'telephone'         => Normalizer::text( $row['telephone'] ?? '' ),
                'url'               => Normalizer::url( $row['url'] ?? '' ),
                'productSupported'  => Normalizer::text( $row['product_supported'] ?? '' ),
                'hoursAvailable'    => Normalizer::text( $row['hours_available'] ?? '' ),
                'availableLanguage' => Normalizer::strings( $row['available_languages'] ?? '' ),
            ]
        );
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function area( array $row ): array {
        $allowed = [ 'Country', 'State', 'City', 'AdministrativeArea', 'Place' ];
        $type = Normalizer::text( $row['type'] ?? 'Place' );
        if ( ! in_array( $type, $allowed, true ) ) {
            $type = 'Place';
        }

        return self::clean(
            [
                '@type'  => $type,
                'name'   => Normalizer::text( $row['name'] ?? '' ),
                'sameAs' => Normalizer::url( $row['same_as'] ?? '' ),
            ]
        );
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function identifier( array $row ): array {
        return self::clean(
            [
                '@type'      => 'PropertyValue',
                'propertyID' => Normalizer::text( $row['property_id'] ?? '' ),
                'value'      => Normalizer::text( $row['value'] ?? '' ),
                'url'        => Normalizer::url( $row['url'] ?? '' ),
            ]
        );
    }

    /** @return array<string,mixed> */
    public static function user_person( int $user_id, string $id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }

        $same_as = [];
        if ( \Hexa\PluginCore\Fields\Field::available() ) {
            $urls = \Hexa\PluginCore\Fields\Field::get( 'urls', 'user_' . $user_id );
            $same_as = Normalizer::urls( is_array( $urls ) ? array_values( $urls ) : [] );
        }

        $additional = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get( 'additional', 'user_' . $user_id ) : [];
        $public_email = is_array( $additional )
            ? Normalizer::email( $additional['public_email'] ?? '' )
            : '';

        return self::clean(
            [
                '@type' => 'Person',
                '@id'   => $id,
                'name'  => Normalizer::text( $user->display_name ),
                'url'   => Normalizer::url( get_author_posts_url( $user_id ) ),
                'email' => $public_email,
                'sameAs'=> $same_as,
                'image' => Normalizer::url( get_avatar_url( $user_id, [ 'size' => 512 ] ) ),
            ]
        );
    }

    /** @return array<string,mixed> */
    public static function team_person( int $post_id, string $id ): array {
        if ( 'team-member' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
            return [];
        }

        $position = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get( 'position', $post_id ) : get_post_meta( $post_id, 'position', true );
        $image = get_the_post_thumbnail_url( $post_id, 'full' );

        return self::clean(
            [
                '@type'      => 'Person',
                '@id'        => $id,
                'name'       => Normalizer::text( get_the_title( $post_id ) ),
                'url'        => Normalizer::url( get_permalink( $post_id ) ),
                'jobTitle'   => Normalizer::text( $position ),
                'description'=> Normalizer::text( get_post_field( 'post_excerpt', $post_id ) ?: get_post_field( 'post_content', $post_id ) ),
                'image'      => Normalizer::url( $image ),
            ]
        );
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function offer( array $row, string $kind, string $id ): array {
        $name = Normalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }

        $item_type = 'product' === $kind ? 'Product' : 'Service';
        $item = self::clean(
            [
                '@type'       => $item_type,
                'name'        => $name,
                'description' => Normalizer::text( $row['description'] ?? '' ),
                'url'         => Normalizer::url( $row['url'] ?? '' ),
                'category'    => Normalizer::text( $row['category'] ?? '' ),
                'sku'         => 'Product' === $item_type ? Normalizer::text( $row['sku'] ?? '' ) : '',
                'brand'       => Normalizer::text( $row['brand'] ?? '' ),
                'areaServed'  => Normalizer::text( $row['area_served'] ?? '' ),
            ]
        );

        $availability = Normalizer::text( $row['availability'] ?? '' );
        if ( '' !== $availability ) {
            $availability = 'https://schema.org/' . $availability;
        }

        return self::clean(
            [
                '@type'         => 'Offer',
                '@id'           => $id,
                'url'           => Normalizer::url( $row['url'] ?? '' ),
                'price'         => Normalizer::number( $row['price'] ?? null ),
                'priceCurrency' => strtoupper( Normalizer::text( $row['price_currency'] ?? '' ) ),
                'availability'  => $availability,
                'itemOffered'   => $item,
            ]
        );
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function brand( array $row, string $id ): array {
        $name = Normalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }

        $image = MediaNormalizer::image( $row['logo'] ?? null );
        return self::clean(
            [
                '@type' => 'Brand',
                '@id'   => $id,
                'name'  => $name,
                'url'   => Normalizer::url( $row['url'] ?? '' ),
                'logo'  => $image['url'] ?? '',
            ]
        );
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function credential( array $row, string $id ): array {
        $name = Normalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }

        $recognized_by = Normalizer::text( $row['recognized_by'] ?? '' );

        return self::clean(
            [
                '@type'          => 'EducationalOccupationalCredential',
                '@id'            => $id,
                'name'           => $name,
                'credentialCategory' => Normalizer::text( $row['category'] ?? '' ),
                'recognizedBy'   => '' !== $recognized_by ? [ '@type' => 'Organization', 'name' => $recognized_by ] : null,
                'dateCreated'    => Normalizer::date( $row['issued_date'] ?? '' ),
                'expires'        => Normalizer::date( $row['expires_date'] ?? '' ),
                'url'            => Normalizer::url( $row['url'] ?? '' ),
            ]
        );
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function demand( array $row, string $id ): array {
        $name = Normalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }

        return self::clean(
            [
                '@type'      => 'Demand',
                '@id'        => $id,
                'name'       => $name,
                'description'=> Normalizer::text( $row['description'] ?? '' ),
                'url'        => Normalizer::url( $row['url'] ?? '' ),
            ]
        );
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function article( array $row, string $id, string $organization_id ): array {
        $url = Normalizer::url( $row['url'] ?? '' );
        $headline = Normalizer::text( $row['headline'] ?? '' );
        if ( '' === $url && '' === $headline ) {
            return [];
        }

        $publisher = Normalizer::text( $row['publisher'] ?? '' );
        $author = Normalizer::text( $row['author'] ?? '' );

        return self::clean(
            [
                '@type'         => 'Article',
                '@id'           => $id,
                'headline'      => $headline,
                'url'           => $url,
                'datePublished' => Normalizer::date( $row['date_published'] ?? '' ),
                'publisher'     => '' !== $publisher ? [ '@type' => 'Organization', 'name' => $publisher ] : null,
                'author'        => '' !== $author ? [ '@type' => 'Person', 'name' => $author ] : null,
                'about'         => [ '@id' => $organization_id ],
            ]
        );
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function milestone( array $row, string $id, string $organization_id ): array {
        $name = Normalizer::text( $row['name'] ?? '' );
        if ( '' === $name ) {
            return [];
        }

        return self::clean(
            [
                '@type'      => 'Event',
                '@id'        => $id,
                'name'       => $name,
                'startDate'  => Normalizer::date( $row['date'] ?? '' ),
                'description'=> Normalizer::text( $row['description'] ?? '' ),
                'url'        => Normalizer::url( $row['url'] ?? '' ),
                'about'      => [ '@id' => $organization_id ],
                'organizer'  => [ '@id' => $organization_id ],
            ]
        );
    }

    /** @return array<string,mixed> */
    public static function review( int $post_id, string $id, string $organization_id ): array {
        if ( 'testimonial' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
            return [];
        }

        $rating = Normalizer::number( get_post_meta( $post_id, 'rating', true ) );
        $source_url = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get( 'url', $post_id ) : get_post_meta( $post_id, 'url', true );

        return self::clean(
            [
                '@type'         => 'Review',
                '@id'           => $id,
                'name'          => Normalizer::text( get_post_meta( $post_id, 'Headline', true ) ?: get_the_title( $post_id ) ),
                'reviewBody'    => Normalizer::text( get_post_field( 'post_content', $post_id ) ),
                'datePublished' => Normalizer::date( get_post_field( 'post_date', $post_id ) ),
                'url'           => Normalizer::url( $source_url ?: get_permalink( $post_id ) ),
                'author'        => [ '@type' => 'Person', 'name' => Normalizer::text( get_the_title( $post_id ) ) ],
                'itemReviewed'  => [ '@id' => $organization_id ],
                'reviewRating'  => null !== $rating ? [
                    '@type'      => 'Rating',
                    'ratingValue'=> $rating,
                    'bestRating' => 5,
                    'worstRating'=> 1,
                ] : null,
            ]
        );
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    public static function clean( array $values ): array {
        return SchemaGraph::clean( $values );
    }
}

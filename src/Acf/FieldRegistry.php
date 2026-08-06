<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Acf;

use Hexa\PluginCore\AcfFieldFactory\AcfFieldFactory;

defined( 'ABSPATH' ) || exit;

/**
 * SMC key-policy adapter for the generic Core ACF field factory.
 *
 * Callers retain the established compact SMC signature while Core owns field
 * construction and type defaults.
 */
final class FieldRegistry {
    public static function text( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::text( self::args( $id, $label, $name, $options ) );
    }

    public static function textarea( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::textarea( self::args( $id, $label, $name, array_replace( [ 'rows' => 4 ], $options ) ) );
    }

    public static function wysiwyg( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::wysiwyg(
            self::args( $id, $label, $name, array_replace( [ 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 0, 'delay' => 1 ], $options ) )
        );
    }

    public static function url( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::url( self::args( $id, $label, $name, $options ) );
    }

    public static function email( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::email( self::args( $id, $label, $name, $options ) );
    }

    public static function number( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::number( self::args( $id, $label, $name, $options ) );
    }

    public static function date( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::date(
            self::args( $id, $label, $name, array_replace( [ 'display_format' => 'F j, Y', 'return_format' => 'Y-m-d', 'first_day' => 1 ], $options ) )
        );
    }

    public static function select( string $id, string $label, string $name, array $choices, array $options = [] ): array {
        return AcfFieldFactory::select(
            self::args( $id, $label, $name, array_replace( [ 'choices' => $choices, 'allow_null' => 1, 'ui' => 1, 'return_format' => 'value' ], $options ) )
        );
    }

    public static function toggle( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::toggle(
            self::args( $id, $label, $name, array_replace( [ 'ui' => 1, 'default_value' => 1 ], $options ) )
        );
    }

    public static function image( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::image(
            self::args( $id, $label, $name, array_replace( [ 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all' ], $options ) )
        );
    }

    public static function gallery( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::gallery(
            self::args(
                $id,
                $label,
                $name,
                array_replace(
                    [
                        'return_format' => 'id',
                        'preview_size'  => 'thumbnail',
                        'library'       => 'all',
                        'insert'        => 'append',
                        'mime_types'    => 'jpg,jpeg,png,webp,avif',
                    ],
                    $options
                )
            )
        );
    }

    public static function group( string $id, string $label, string $name, array $sub_fields, array $options = [] ): array {
        return AcfFieldFactory::group(
            self::args( $id, $label, $name, array_replace( [ 'layout' => 'block', 'sub_fields' => $sub_fields ], $options ) )
        );
    }

    public static function repeater( string $id, string $label, string $name, array $sub_fields, array $options = [] ): array {
        return AcfFieldFactory::repeater(
            self::args(
                $id,
                $label,
                $name,
                array_replace( [ 'layout' => 'block', 'button_label' => 'Add Row', 'min' => 0, 'sub_fields' => $sub_fields ], $options )
            )
        );
    }

    public static function relationship( string $id, string $label, string $name, array $post_types, array $options = [] ): array {
        return AcfFieldFactory::relationship(
            self::args(
                $id,
                $label,
                $name,
                array_replace(
                    [
                        'post_type'     => $post_types,
                        'post_status'   => [ 'publish', 'draft', 'pending', 'private' ],
                        'filters'       => [ 'search', 'post_type' ],
                        'return_format' => 'id',
                        'elements'      => [ 'featured_image' ],
                    ],
                    $options
                )
            )
        );
    }

    public static function user( string $id, string $label, string $name, array $options = [] ): array {
        return AcfFieldFactory::user(
            self::args( $id, $label, $name, array_replace( [ 'role' => '', 'allow_null' => 1, 'multiple' => 1, 'return_format' => 'id' ], $options ) )
        );
    }

    public static function tab( string $id, string $label ): array {
        return AcfFieldFactory::tab(
            self::args( $id, $label, '', [ 'placement' => 'top', 'endpoint' => 0 ] )
        );
    }

    /** @return array<string,mixed> */
    private static function args( string $id, string $label, string $name, array $options ): array {
        return array_replace_recursive(
            [
                'key'          => 'field_smc_org_' . sanitize_key( $id ),
                'label'        => $label,
                'name'         => $name,
                'instructions' => '',
                'required'     => 0,
                'wrapper'      => [ 'width' => '', 'class' => '', 'id' => '' ],
            ],
            $options
        );
    }
}

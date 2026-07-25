<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Acf;

defined( 'ABSPATH' ) || exit;

final class FieldRegistry {
    /** @param array<string,mixed> $options */
    public static function text( string $id, string $label, string $name, array $options = [] ): array {
        return self::field( $id, $label, $name, 'text', $options );
    }

    /** @param array<string,mixed> $options */
    public static function textarea( string $id, string $label, string $name, array $options = [] ): array {
        return self::field( $id, $label, $name, 'textarea', array_replace( [ 'rows' => 4 ], $options ) );
    }

    /** @param array<string,mixed> $options */
    public static function wysiwyg( string $id, string $label, string $name, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'wysiwyg',
            array_replace( [ 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => 0, 'delay' => 1 ], $options )
        );
    }

    /** @param array<string,mixed> $options */
    public static function url( string $id, string $label, string $name, array $options = [] ): array {
        return self::field( $id, $label, $name, 'url', $options );
    }

    /** @param array<string,mixed> $options */
    public static function email( string $id, string $label, string $name, array $options = [] ): array {
        return self::field( $id, $label, $name, 'email', $options );
    }

    /** @param array<string,mixed> $options */
    public static function number( string $id, string $label, string $name, array $options = [] ): array {
        return self::field( $id, $label, $name, 'number', $options );
    }

    /** @param array<string,mixed> $options */
    public static function date( string $id, string $label, string $name, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'date_picker',
            array_replace( [ 'display_format' => 'F j, Y', 'return_format' => 'Y-m-d', 'first_day' => 1 ], $options )
        );
    }

    /** @param array<string,string> $choices @param array<string,mixed> $options */
    public static function select( string $id, string $label, string $name, array $choices, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'select',
            array_replace( [ 'choices' => $choices, 'allow_null' => 1, 'ui' => 1, 'return_format' => 'value' ], $options )
        );
    }

    /** @param array<string,mixed> $options */
    public static function toggle( string $id, string $label, string $name, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'true_false',
            array_replace( [ 'ui' => 1, 'default_value' => 1 ], $options )
        );
    }

    /** @param array<string,mixed> $options */
    public static function image( string $id, string $label, string $name, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'image',
            array_replace( [ 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all' ], $options )
        );
    }

    /** @param array<string,mixed> $options */
    public static function gallery( string $id, string $label, string $name, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'gallery',
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
        );
    }

    /** @param array<int,array<string,mixed>> $sub_fields @param array<string,mixed> $options */
    public static function group( string $id, string $label, string $name, array $sub_fields, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'group',
            array_replace( [ 'layout' => 'block', 'sub_fields' => $sub_fields ], $options )
        );
    }

    /** @param array<int,array<string,mixed>> $sub_fields @param array<string,mixed> $options */
    public static function repeater( string $id, string $label, string $name, array $sub_fields, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'repeater',
            array_replace(
                [
                    'layout'       => 'block',
                    'button_label' => 'Add Row',
                    'min'          => 0,
                    'sub_fields'   => $sub_fields,
                ],
                $options
            )
        );
    }

    /** @param list<string> $post_types @param array<string,mixed> $options */
    public static function relationship( string $id, string $label, string $name, array $post_types, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'relationship',
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
        );
    }

    /** @param array<string,mixed> $options */
    public static function user( string $id, string $label, string $name, array $options = [] ): array {
        return self::field(
            $id,
            $label,
            $name,
            'user',
            array_replace(
                [ 'role' => '', 'allow_null' => 1, 'multiple' => 1, 'return_format' => 'id' ],
                $options
            )
        );
    }

    public static function tab( string $id, string $label ): array {
        return [
            'key'       => self::key( $id ),
            'label'     => $label,
            'name'      => '',
            'type'      => 'tab',
            'placement' => 'top',
            'endpoint'  => 0,
        ];
    }

    /** @param array<string,mixed> $options */
    private static function field( string $id, string $label, string $name, string $type, array $options ): array {
        $field = [
            'key'           => self::key( $id ),
            'label'         => $label,
            'name'          => $name,
            'type'          => $type,
            'instructions'  => '',
            'required'      => 0,
            'wrapper'       => [ 'width' => '', 'class' => '', 'id' => '' ],
        ];

        return array_replace_recursive( $field, $options );
    }

    private static function key( string $id ): string {
        return 'field_smc_org_' . sanitize_key( $id );
    }
}

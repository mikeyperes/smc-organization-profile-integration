<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\ContentTypes;

use Hexa\PluginCore\ContentTypes\ContentTypeRegistry;
use Hexa\PluginCore\CoreContracts\PluginContextInterface;
use SMC\OrganizationProfile\Acf\OrganizationFields;

defined( 'ABSPATH' ) || exit;

final class OrganizationContentType {
    private static ?ContentTypeRegistry $registry = null;

    public static function registry( ?PluginContextInterface $context = null ): ContentTypeRegistry {
        if ( self::$registry instanceof ContentTypeRegistry ) {
            return self::$registry;
        }

        self::$registry = new ContentTypeRegistry(
            [
                'option_name'   => 'smc_content_type_settings',
                'capability'    => null !== $context ? (string) $context->get( 'capability', 'manage_options' ) : 'manage_options',
                'ajax_action'   => 'smc_save_content_type',
                'nonce_action'  => 'smc_content_types',
                'nonce_field'   => 'nonce',
                'hook_priority' => 5,
            ]
        );

        self::$registry->add(
            [
                'id'                => 'organization-profile-extension',
                'owner'             => 'HWS Base Tools (CPT) / SMC (fields and schema)',
                'description'       => 'Extends the HWS-owned Organization post type with SMC profile fields, normalized output, REST data, and Organization schema.',
                'registration_mode' => 'external',
                'enabled_default'   => true,
                'post_type'         => [
                    'key'          => 'organization',
                    'singular'     => 'Organization',
                    'plural'       => 'Organizations',
                    'rewrite_slug' => 'organization',
                    'args'         => [],
                ],
                'field_groups'      => [
                    [
                        'id'              => 'smc-organization-profile-fields',
                        'label'           => 'SMC Organization Profile Fields',
                        'description'     => 'Canonical Organization identity, footprint, people, commercial, reputation, media, and schema-control fields.',
                        'group_key'       => OrganizationFields::GROUP_KEY,
                        'enabled_default' => true,
                        'definition'      => [ OrganizationFields::class, 'definition' ],
                        'fields'          => OrganizationFields::field_labels(),
                        'dependencies'    => [ 'HWS Base Tools Organization CPT' ],
                    ],
                ],
            ]
        );

        return self::$registry;
    }
}

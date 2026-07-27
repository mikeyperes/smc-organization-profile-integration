<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Http;

use SMC\OrganizationProfile\Data\OrganizationRepository;
use SMC\OrganizationProfile\Schema\OrganizationGraph;
use SMC\OrganizationProfile\Schema\OrganizationSchema;
use SMC\OrganizationProfile\Support\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class RestController {
    public const NAMESPACE = 'smc/v1';

    public static function register(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        if ( ! Settings::enabled( Settings::REST_ENABLED ) ) {
            return;
        }

        register_rest_route(
            self::NAMESPACE,
            '/organizations',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ self::class, 'index' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/organizations/primary',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ self::class, 'primary' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/organizations/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ self::class, 'show' ],
                'permission_callback' => '__return_true',
                'args'                => [ 'id' => [ 'required' => true, 'sanitize_callback' => 'absint' ] ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/organizations/(?P<id>\d+)/schema',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ self::class, 'schema' ],
                'permission_callback' => '__return_true',
                'args'                => [ 'id' => [ 'required' => true, 'sanitize_callback' => 'absint' ] ],
            ]
        );

        register_rest_field(
            'organization',
            'smc_schema',
            [
                'get_callback' => static function ( array $object ): array {
                    $id = absint( $object['id'] ?? 0 );
                    return OrganizationSchema::build( $id, true );
                },
                'schema' => [
                    'description' => 'Live SMC Organization schema node.',
                    'type'        => 'object',
                    'readonly'    => true,
                ],
            ]
        );
    }

    public static function index(): WP_REST_Response {
        $items = [];
        foreach ( OrganizationRepository::published_ids() as $post_id ) {
            $data = OrganizationRepository::data( $post_id );
            if ( ! $data ) {
                continue;
            }

            $items[] = [
                'id'         => $post_id,
                'name'       => $data->name(),
                'url'        => $data->permalink(),
                'api_url'    => rest_url( self::NAMESPACE . '/organizations/' . $post_id ),
                'schema_url' => rest_url( self::NAMESPACE . '/organizations/' . $post_id . '/schema' ),
                'is_primary' => $post_id === OrganizationRepository::primary_id(),
            ];
        }

        return new WP_REST_Response( [ 'count' => count( $items ), 'organizations' => $items ], 200 );
    }

    public static function primary(): WP_REST_Response|WP_Error {
        $data = OrganizationRepository::data();
        if ( ! $data ) {
            return new WP_Error( 'smc_primary_not_found', 'No published primary organization is configured.', [ 'status' => 404 ] );
        }

        $id = $data->id();
        return new WP_REST_Response(
            [
                'id'          => $id,
                'source_kind' => $data->kind(),
                'fields'      => $data->export(),
                'schema'      => OrganizationSchema::build( $id, true ),
                'graph'       => OrganizationGraph::build( $id, get_queried_object_id() ?: $id ),
            ],
            200
        );
    }

    public static function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::organization_response( absint( $request['id'] ) );
    }

    public static function schema( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id = absint( $request['id'] );
        $error = self::validate_public_organization( $post_id );
        if ( $error instanceof WP_Error ) {
            return $error;
        }

        return new WP_REST_Response(
            [
                'id'     => $post_id,
                'schema' => OrganizationSchema::build( $post_id, true ),
                'graph'  => OrganizationGraph::build( $post_id, $post_id ),
            ],
            200
        );
    }

    private static function organization_response( int $post_id ): WP_REST_Response|WP_Error {
        $error = self::validate_public_organization( $post_id );
        if ( $error instanceof WP_Error ) {
            return $error;
        }

        $data = OrganizationRepository::data( $post_id );
        if ( ! $data ) {
            return new WP_Error( 'smc_organization_not_found', 'Organization not found.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response(
            [
                'id'         => $post_id,
                'post'       => [
                    'title'     => $data->name(),
                    'permalink' => $data->permalink(),
                    'status'    => get_post_status( $post_id ),
                    'modified'  => get_post_modified_time( DATE_ATOM, true, $post_id ),
                ],
                'fields'     => $data->export(),
                'schema'     => OrganizationSchema::build( $post_id, true ),
                'graph'      => OrganizationGraph::build( $post_id, $post_id ),
                'schema_url' => rest_url( self::NAMESPACE . '/organizations/' . $post_id . '/schema' ),
            ],
            200
        );
    }

    private static function validate_public_organization( int $post_id ): ?WP_Error {
        if ( ! OrganizationRepository::is_organization( $post_id ) ) {
            return new WP_Error( 'smc_organization_not_found', 'Organization not found.', [ 'status' => 404 ] );
        }

        if ( 'publish' !== get_post_status( $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'smc_organization_not_public', 'Organization is not public.', [ 'status' => 404 ] );
        }

        return null;
    }
}

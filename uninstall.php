<?php

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Organization content and profile fields are intentionally retained.
delete_transient( 'smc_organization_schema_cache' );

<?php
/**
 * Plugin Name: SMC Organization Profile Integration
 * Plugin URI: https://github.com/mikeyperes/smc-organization-profile-integration
 * Description: Organization fields, schema graphs, REST output, and presentation helpers for HWS-managed company websites.
 * Version: 3.1.0
 * Author: Michael Peres
 * Author URI: https://michaelperes.com
 * Text Domain: smc-organization-profile-integration
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/mikeyperes/smc-organization-profile-integration
 * GitHub Branch: main
 */

declare( strict_types=1 );

namespace SMC\OrganizationProfile;

defined( 'ABSPATH' ) || exit;

define( 'SMC_VERSION', '3.1.0' );
define( 'SMC_PLUGIN_FILE', __FILE__ );
define( 'SMC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SMC_PLUGIN_DIR . 'src/Autoloader.php';
Autoloader::register( SMC_PLUGIN_DIR . 'src' );

$core_root = SMC_PLUGIN_DIR . 'lib/hexa-wordpress-plugin-core';
if ( is_readable( $core_root . '/bootstrap.php' ) ) {
    require_once $core_root . '/bootstrap.php';
    \hexa_plugin_core_register_package( 'smc-organization-profile-integration', $core_root );
}

Plugin::register();

register_activation_hook( SMC_PLUGIN_FILE, [ Plugin::class, 'activate' ] );
register_deactivation_hook( SMC_PLUGIN_FILE, [ Plugin::class, 'deactivate' ] );

<?php
/**
 * Plugin Name:       WC Variation Studio
 * Plugin URI:        https://example.com/wc-variation-studio
 * Description:       Unified WooCommerce variation swatches, multi-image/video variation galleries, and dynamic stock-driven main image & gallery orchestration.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * WC requires at least: 9.0
 * WC tested up to:   9.8
 * Author:            Custom Engineering
 * License:           GPL-2.0-or-later
 * Text Domain:       wc-variation-studio
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCVS_VERSION', '1.0.0' );
define( 'WCVS_PLUGIN_FILE', __FILE__ );
define( 'WCVS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCVS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Declare WooCommerce High-Performance Order Storage (HPOS) compatibility.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// Simple PSR-4 autoloader for WcVariationStudio namespace.
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'WcVariationStudio\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = WCVS_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		\WcVariationStudio\Core\Plugin::instance()->init();
	},
	20
);

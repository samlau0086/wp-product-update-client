<?php
/**
 * Plugin Name:       WP Product Update Client
 * Description:       Integrates with the WP Product Update Server to deliver updates for purchased plugins after authentication.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WP Product Updates
 * Text Domain:       wp-product-update-client
 */

define( 'WP_PRODUCT_UPDATE_CLIENT_VERSION', '1.0.0' );
define( 'WP_PRODUCT_UPDATE_CLIENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_PRODUCT_UPDATE_CLIENT_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_PRODUCT_UPDATE_CLIENT_BASENAME', plugin_basename( __FILE__ ) );

require_once WP_PRODUCT_UPDATE_CLIENT_PATH . 'includes/class-autoloader.php';

WP_Product_Update_Client\Plugin::get_instance();

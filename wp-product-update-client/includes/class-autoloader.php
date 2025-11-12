<?php
/**
 * Autoloader for WP Product Update Client classes.
 */

namespace WP_Product_Update_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple PSR-4 like autoloader for the plugin namespace.
 */
class Autoloader {

    /**
     * Registers autoloader hooks.
     */
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Loads classes from the WP_Product_Update_Client namespace.
     *
     * @param string $class Class name.
     */
    public static function autoload( $class ) {
        if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
            return;
        }

        $relative_class = substr( $class, strlen( __NAMESPACE__ . '\\' ) );
        $relative_path  = strtolower( str_replace( '\\', '/', $relative_class ) );
        $path_parts     = explode( '/', $relative_path );
        $file_name      = array_pop( $path_parts );
        $directory      = implode( '/', $path_parts );
        $file           = WP_PRODUCT_UPDATE_CLIENT_PATH . 'includes/';

        if ( ! empty( $directory ) ) {
            $file .= trailingslashit( $directory );
        }

        $file .= 'class-' . $file_name . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}

Autoloader::register();

<?php
/**
 * Core plugin bootstrap.
 */

namespace WP_Product_Update_Client;

use WP_Product_Update_Client\Admin\Settings_Page;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin singleton.
 */
class Plugin {

    /**
     * Singleton instance.
     *
     * @var Plugin
     */
    private static $instance;

    /**
     * Authentication manager.
     *
     * @var Authentication_Manager
     */
    private $auth_manager;

    /**
     * Update manager.
     *
     * @var Update_Manager
     */
    private $update_manager;

    /**
     * API client.
     *
     * @var API_Client
     */
    private $api_client;

    /**
     * Settings page controller.
     *
     * @var Settings_Page
     */
    private $settings_page;

    /**
     * Retrieves the singleton instance.
     *
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }

        return self::$instance;
    }

    /**
     * Initializes plugin services.
     */
    private function init() {
        $this->api_client     = new API_Client();
        $this->auth_manager   = new Authentication_Manager( $this->api_client );
        $this->update_manager = new Update_Manager( $this->auth_manager, $this->api_client );
        $this->settings_page  = new Settings_Page( $this->auth_manager, $this->api_client );

        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'admin_init', array( $this, 'register_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( $this, 'maybe_show_login_notice' ) );
    }

    /**
     * Loads translation files.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'wp-product-update-client', false, dirname( WP_PRODUCT_UPDATE_CLIENT_BASENAME ) . '/languages' );
    }

    /**
     * Registers admin assets.
     */
    public function register_assets() {
        wp_register_style(
            'wp-product-update-client-admin',
            WP_PRODUCT_UPDATE_CLIENT_URL . 'assets/css/admin.css',
            array(),
            WP_PRODUCT_UPDATE_CLIENT_VERSION
        );
    }

    /**
     * Enqueues admin assets on plugin pages.
     *
     * @param string $hook Hook suffix.
     */
    public function enqueue_assets( $hook ) {
        if ( 'settings_page_wp-product-update-client' === $hook ) {
            wp_enqueue_style( 'wp-product-update-client-admin' );
        }
    }

    /**
     * Displays an admin notice prompting for login if required.
     */
    public function maybe_show_login_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( $this->auth_manager->is_authenticated() ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $allowed_screens = array(
            'plugins',
            'update-core',
            'settings_page_wp-product-update-client',
        );

        if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
            return;
        }

        $url = add_query_arg( 'page', 'wp-product-update-client', admin_url( 'options-general.php' ) );
        ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    '<strong>%1$s</strong> %2$s',
                    esc_html__( 'Product updates are locked.', 'wp-product-update-client' ),
                    wp_kses_post( sprintf( __( 'Please <a href="%s">log in to the update server</a> to enable manual and automatic updates.', 'wp-product-update-client' ), esc_url( $url ) ) )
                );
                ?>
            </p>
        </div>
        <?php
    }
}

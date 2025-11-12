<?php
/**
 * Admin settings page for configuring the update client.
 */

namespace WP_Product_Update_Client\Admin;

use WP_Product_Update_Client\API_Client;
use WP_Product_Update_Client\Authentication_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders settings and login controls.
 */
class Settings_Page {

    const MENU_SLUG = 'wp-product-update-client';

    /**
     * Authentication manager.
     *
     * @var Authentication_Manager
     */
    private $auth_manager;

    /**
     * API client instance.
     *
     * @var API_Client
     */
    private $api_client;

    /**
     * Constructor.
     *
     * @param Authentication_Manager $auth_manager Auth manager.
     * @param API_Client              $api_client   API client.
     */
    public function __construct( Authentication_Manager $auth_manager, API_Client $api_client ) {
        $this->auth_manager = $auth_manager;
        $this->api_client   = $api_client;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_wp_product_update_client_login', array( $this, 'handle_login' ) );
        add_action( 'admin_post_wp_product_update_client_logout', array( $this, 'handle_logout' ) );
    }

    /**
     * Registers the admin menu entry.
     */
    public function register_menu() {
        add_options_page(
            __( 'Product Updates', 'wp-product-update-client' ),
            __( 'Product Updates', 'wp-product-update-client' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * Registers plugin settings.
     */
    public function register_settings() {
        register_setting(
            'wp_product_update_client',
            API_Client::OPTION_SETTINGS,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => array(),
            )
        );

        add_settings_section(
            'wp_product_update_client_general',
            __( 'Update Server', 'wp-product-update-client' ),
            '__return_false',
            self::MENU_SLUG
        );

        add_settings_field(
            'api_base',
            __( 'Server URL', 'wp-product-update-client' ),
            array( $this, 'render_server_url_field' ),
            self::MENU_SLUG,
            'wp_product_update_client_general'
        );
    }

    /**
     * Renders the settings page content.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-product-update-client' ) );
        }

        $settings    = $this->api_client->get_settings();
        $token       = $this->auth_manager->get_token();
        $is_auth     = $this->auth_manager->is_authenticated();
        $message_raw = isset( $_GET['message'] ) ? wp_unslash( $_GET['message'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $class_raw   = isset( $_GET['class'] ) ? wp_unslash( $_GET['class'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $message     = $message_raw ? sanitize_text_field( rawurldecode( $message_raw ) ) : '';
        $class       = $class_raw ? sanitize_text_field( rawurldecode( $class_raw ) ) : 'notice notice-info';
        ?>
        <div class="wrap wp-product-update-client-settings">
            <h1><?php esc_html_e( 'Product Update Service', 'wp-product-update-client' ); ?></h1>
            <?php if ( ! empty( $message ) ) : ?>
                <div class="<?php echo esc_attr( $class ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_product_update_client' );
                do_settings_sections( self::MENU_SLUG );
                submit_button();
                ?>
            </form>
            <div class="card">
                <h2><?php esc_html_e( 'Account', 'wp-product-update-client' ); ?></h2>
                <?php if ( $is_auth ) : ?>
                    <p><?php echo esc_html( sprintf( __( 'Logged in as %s.', 'wp-product-update-client' ), isset( $token['user']['name'] ) ? $token['user']['name'] : __( 'unknown user', 'wp-product-update-client' ) ) ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'wp_product_update_client_logout', 'wp_product_update_client_nonce' ); ?>
                        <input type="hidden" name="action" value="wp_product_update_client_logout" />
                        <?php submit_button( __( 'Log out from update server', 'wp-product-update-client' ), 'secondary', 'submit', false ); ?>
                    </form>
                <?php else : ?>
                    <p><?php esc_html_e( 'Log in with the credentials associated with your purchased plugins to enable updates.', 'wp-product-update-client' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wp-product-update-client-credentials">
                        <?php wp_nonce_field( 'wp_product_update_client_login', 'wp_product_update_client_nonce' ); ?>
                        <input type="hidden" name="action" value="wp_product_update_client_login" />
                        <p>
                            <label for="wp-product-update-client-username" class="screen-reader-text"><?php esc_html_e( 'Username', 'wp-product-update-client' ); ?></label>
                            <input type="text" class="widefat" id="wp-product-update-client-username" name="username" required />
                        </p>
                        <p>
                            <label for="wp-product-update-client-password" class="screen-reader-text"><?php esc_html_e( 'Password', 'wp-product-update-client' ); ?></label>
                            <input type="password" class="widefat" id="wp-product-update-client-password" name="password" required />
                        </p>
                        <p>
                            <?php submit_button( __( 'Log in to update server', 'wp-product-update-client' ), 'primary', 'submit', false ); ?>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renders the server URL input.
     */
    public function render_server_url_field() {
        $settings = $this->api_client->get_settings();
        ?>
        <input type="url" class="regular-text" name="<?php echo esc_attr( API_Client::OPTION_SETTINGS ); ?>[api_base]" value="<?php echo isset( $settings['api_base'] ) ? esc_url( $settings['api_base'] ) : ''; ?>" placeholder="https://example.com" required />
        <p class="description"><?php esc_html_e( 'Enter the base URL of your WP Product Update Server installation.', 'wp-product-update-client' ); ?></p>
        <?php
    }

    /**
     * Handles login submissions.
     */
    public function handle_login() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this action.', 'wp-product-update-client' ) );
        }

        check_admin_referer( 'wp_product_update_client_login', 'wp_product_update_client_nonce' );

        $username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ( empty( $username ) || empty( $password ) ) {
            $this->redirect_with_message( 'notice notice-error', __( 'Please provide both username and password.', 'wp-product-update-client' ) );
        }

        $login = $this->auth_manager->login( $username, $password );

        if ( is_wp_error( $login ) ) {
            $this->redirect_with_message( 'notice notice-error', $login->get_error_message() );
        }

        $this->redirect_with_message( 'notice notice-success', __( 'Successfully logged in to the update server.', 'wp-product-update-client' ) );
    }

    /**
     * Handles logout requests.
     */
    public function handle_logout() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this action.', 'wp-product-update-client' ) );
        }

        check_admin_referer( 'wp_product_update_client_logout', 'wp_product_update_client_nonce' );

        $this->auth_manager->logout();

        $this->redirect_with_message( 'notice notice-success', __( 'You have been logged out from the update server.', 'wp-product-update-client' ) );
    }

    /**
     * Sanitizes settings submitted by the user.
     *
     * @param array $settings Raw settings.
     *
     * @return array
     */
    public function sanitize_settings( $settings ) {
        $sanitized = array();

        if ( isset( $settings['api_base'] ) ) {
            $sanitized['api_base'] = esc_url_raw( trim( $settings['api_base'] ) );
        }

        if ( isset( $settings['remember_token'] ) ) {
            $sanitized['remember_token'] = sanitize_text_field( $settings['remember_token'] );
        }

        if ( isset( $settings['token_expires'] ) ) {
            $sanitized['token_expires'] = (int) $settings['token_expires'];
        }

        return $sanitized;
    }

    /**
     * Redirects back to the settings page with an admin notice.
     *
     * @param string $class   Notice classes.
     * @param string $message Message text.
     */
    private function redirect_with_message( $class, $message ) {
        $args = array(
            'page'    => self::MENU_SLUG,
            'message' => rawurlencode( $message ),
            'class'   => rawurlencode( $class ),
        );

        wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
        exit;
    }
}

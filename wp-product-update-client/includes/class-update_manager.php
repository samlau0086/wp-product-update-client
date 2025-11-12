<?php
/**
 * Plugin update integration with WordPress.
 */

namespace WP_Product_Update_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hooks into WordPress update routines to fetch packages from the update server.
 */
class Update_Manager {

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
     * Currently downloading package URL.
     *
     * @var string|null
     */
    private $current_package_url;

    /**
     * Constructor.
     *
     * @param Authentication_Manager $auth_manager Auth manager.
     * @param API_Client              $api_client   API client.
     */
    public function __construct( Authentication_Manager $auth_manager, API_Client $api_client ) {
        $this->auth_manager = $auth_manager;
        $this->api_client   = $api_client;

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update_data' ) );
        add_filter( 'plugins_api', array( $this, 'provide_plugin_information' ), 10, 3 );
        add_filter( 'upgrader_pre_download', array( $this, 'guard_downloads' ), 10, 4 );
        add_filter( 'auto_update_plugin', array( $this, 'ensure_auto_updates_require_login' ), 10, 2 );
        add_filter( 'http_request_args', array( $this, 'authorize_download_request' ), 10, 2 );
    }

    /**
     * Requests update data from the server.
     *
     * @param object $transient Update transient.
     *
     * @return object
     */
    public function inject_update_data( $transient ) {
        if ( ! $this->auth_manager->is_authenticated() ) {
            return $transient;
        }

        if ( empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
            return $transient;
        }

        $payload = array();
        foreach ( $transient->checked as $plugin_file => $version ) {
            $payload[] = array(
                'plugin_file' => $plugin_file,
                'version'     => $version,
            );
        }

        $response = $this->api_client->post(
            'check-updates',
            array( 'plugins' => $payload ),
            $this->auth_manager->authorized_headers()
        );

        if ( is_wp_error( $response ) ) {
            return $transient;
        }

        if ( empty( $response['updates'] ) || ! is_array( $response['updates'] ) ) {
            return $transient;
        }

        foreach ( $response['updates'] as $update ) {
            if ( empty( $update['plugin_file'] ) || empty( $update['version'] ) ) {
                continue;
            }

            $plugin_file = $update['plugin_file'];
            $plugin_data = (object) array(
                'slug'        => isset( $update['slug'] ) ? $update['slug'] : dirname( $plugin_file ),
                'plugin'      => $plugin_file,
                'new_version' => $update['version'],
                'package'     => isset( $update['package'] ) ? $update['package'] : '',
                'requires'    => isset( $update['requires'] ) ? $update['requires'] : '',
                'tested'      => isset( $update['tested'] ) ? $update['tested'] : '',
                'sections'    => isset( $update['sections'] ) ? $update['sections'] : array(),
                'icons'       => isset( $update['icons'] ) ? $update['icons'] : array(),
                'banners'     => isset( $update['banners'] ) ? $update['banners'] : array(),
                'banners_rtl' => isset( $update['banners_rtl'] ) ? $update['banners_rtl'] : array(),
                'homepage'    => isset( $update['homepage'] ) ? $update['homepage'] : '',
            );

            $transient->response[ $plugin_file ] = $plugin_data;
        }

        return $transient;
    }

    /**
     * Supplies plugin information for the details modal.
     *
     * @param false|object|array $result Result.
     * @param string             $action Action.
     * @param object             $args   Arguments.
     *
     * @return false|object|array
     */
    public function provide_plugin_information( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! $this->auth_manager->is_authenticated() ) {
            return $result;
        }

        if ( empty( $args->slug ) ) {
            return $result;
        }

        $response = $this->api_client->post(
            'plugin-information',
            array( 'slug' => $args->slug ),
            $this->auth_manager->authorized_headers()
        );

        if ( is_wp_error( $response ) ) {
            return $result;
        }

        return (object) $response;
    }

    /**
     * Ensures download requests are blocked when the user is not logged in.
     *
     * @param bool        $reply      Response.
     * @param string      $package    Package URL.
     * @param \WP_Upgrader $upgrader  Upgrader instance.
     * @param array       $hook_extra Hook data.
     *
     * @return bool|\WP_Error
     */
    public function guard_downloads( $reply, $package, $upgrader, $hook_extra ) {
        $this->current_package_url = null;

        $base = $this->api_client->get_api_base();

        if ( empty( $base ) || 0 !== strpos( $package, $base ) ) {
            return $reply;
        }

        if ( ! $this->auth_manager->is_authenticated() ) {
            return new \WP_Error( 'wp_product_update_client_not_authenticated', __( 'Please log in to the update service before installing updates.', 'wp-product-update-client' ) );
        }

        $this->current_package_url = $package;

        return $reply;
    }

    /**
     * Prevents automatic updates when not authenticated.
     *
     * @param bool   $should_update Whether plugin should auto update.
     * @param object $item          Update data.
     *
     * @return bool
     */
    public function ensure_auto_updates_require_login( $should_update, $item ) {
        $base = $this->api_client->get_api_base();

        if ( empty( $base ) ) {
            return $should_update;
        }

        if ( ! isset( $item->package ) || ! is_string( $item->package ) || 0 !== strpos( $item->package, $base ) ) {
            return $should_update;
        }

        if ( ! $this->auth_manager->is_authenticated() ) {
            return false;
        }

        return $should_update;
    }

    /**
     * Adds authorization headers to download requests when necessary.
     *
     * @param array  $args HTTP request args.
     * @param string $url  Request URL.
     *
     * @return array
     */
    public function authorize_download_request( $args, $url ) {
        if ( empty( $this->current_package_url ) ) {
            return $args;
        }

        if ( $url !== $this->current_package_url ) {
            return $args;
        }

        $args['headers'] = $this->auth_manager->authorized_headers( isset( $args['headers'] ) ? $args['headers'] : array() );
        $this->current_package_url = null;

        return $args;
    }
}

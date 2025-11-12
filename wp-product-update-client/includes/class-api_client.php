<?php
/**
 * HTTP client for communicating with the update server.
 */

namespace WP_Product_Update_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles HTTP requests to the WP Product Update Server.
 */
class API_Client {

    const OPTION_SETTINGS = 'wp_product_update_client_settings';

    /**
     * Cached settings.
     *
     * @var array|null
     */
    private $settings;

    /**
     * Returns the base API URL configured by the site owner.
     *
     * @return string
     */
    public function get_api_base() {
        $settings = $this->get_settings();

        return isset( $settings['api_base'] ) ? untrailingslashit( $settings['api_base'] ) : '';
    }

    /**
     * Retrieves the stored settings array.
     *
     * @return array
     */
    public function get_settings() {
        if ( null === $this->settings ) {
            $defaults       = array(
                'api_base'       => '',
                'remember_token' => '',
                'token_expires'  => 0,
            );
            $this->settings = wp_parse_args( get_option( self::OPTION_SETTINGS, array() ), $defaults );
        }

        return $this->settings;
    }

    /**
     * Updates stored settings.
     *
     * @param array $settings Settings to persist.
     */
    public function update_settings( array $settings ) {
        $this->settings = $settings;
        update_option( self::OPTION_SETTINGS, $settings );
    }

    /**
     * Performs a POST request to the API.
     *
     * @param string $path    API path.
     * @param array  $body    Request body.
     * @param array  $headers Extra headers.
     *
     * @return array|\WP_Error
     */
    public function post( $path, array $body = array(), array $headers = array() ) {
        $url = $this->build_url( $path );
        if ( empty( $url ) ) {
            return new \WP_Error( 'missing_api_base', __( 'The update server URL has not been configured.', 'wp-product-update-client' ) );
        }

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 20,
                'headers' => wp_parse_args(
                    $headers,
                    array(
                        'Accept'       => 'application/json',
                        'Content-Type' => 'application/json',
                    )
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        return $this->handle_response( $response );
    }

    /**
     * Performs a GET request to the API.
     *
     * @param string $path    API path.
     * @param array  $headers Extra headers.
     *
     * @return array|\WP_Error
     */
    public function get( $path, array $headers = array() ) {
        $url = $this->build_url( $path );
        if ( empty( $url ) ) {
            return new \WP_Error( 'missing_api_base', __( 'The update server URL has not been configured.', 'wp-product-update-client' ) );
        }

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 20,
                'headers' => wp_parse_args(
                    $headers,
                    array( 'Accept' => 'application/json' )
                ),
            )
        );

        return $this->handle_response( $response );
    }

    /**
     * Adds the authentication token to request headers.
     *
     * @param array $headers Existing headers.
     *
     * @return array
     */
    public function with_token_header( array $headers = array() ) {
        $settings = $this->get_settings();

        if ( empty( $settings['remember_token'] ) ) {
            return $headers;
        }

        $headers['Authorization'] = 'Bearer ' . $settings['remember_token'];

        return $headers;
    }

    /**
     * Builds a URL from the base and supplied path.
     *
     * @param string $path Path or endpoint.
     *
     * @return string
     */
    private function build_url( $path ) {
        $path = ltrim( $path, '/' );
        $base = $this->get_api_base();

        if ( empty( $base ) ) {
            return '';
        }

        return $base . '/' . $path;
    }

    /**
     * Handles responses from wp_remote_*.
     *
     * @param array|\WP_Error $response Response data.
     *
     * @return array|\WP_Error
     */
    private function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( 200 > $status || 299 < $status ) {
            $message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : __( 'Unexpected response from the update server.', 'wp-product-update-client' );

            return new \WP_Error( 'api_error', $message, array( 'status' => $status ) );
        }

        if ( null === $data ) {
            return new \WP_Error( 'api_error', __( 'Unable to parse the response from the update server.', 'wp-product-update-client' ) );
        }

        return $data;
    }
}

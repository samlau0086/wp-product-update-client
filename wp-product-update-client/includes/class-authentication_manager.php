<?php
/**
 * Handles user authentication with the update server.
 */

namespace WP_Product_Update_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages authentication state for the update client.
 */
class Authentication_Manager {

    const OPTION_TOKEN = 'wp_product_update_client_token';

    /**
     * API client instance.
     *
     * @var API_Client
     */
    private $api_client;

    /**
     * Cached token data.
     *
     * @var array|null
     */
    private $token;

    /**
     * Constructor.
     *
     * @param API_Client $api_client API client instance.
     */
    public function __construct( API_Client $api_client ) {
        $this->api_client = $api_client;
    }

    /**
     * Determines if a user is authenticated with the update server.
     *
     * @return bool
     */
    public function is_authenticated() {
        $token = $this->get_token();

        if ( empty( $token['token'] ) ) {
            return false;
        }

        if ( ! empty( $token['expires'] ) && time() >= (int) $token['expires'] ) {
            return false;
        }

        return true;
    }

    /**
     * Performs login and stores the token information.
     *
     * @param string $username Username.
     * @param string $password Password.
     *
     * @return true|\WP_Error
     */
    public function login( $username, $password ) {
        $payload = array(
            'username' => $username,
            'password' => $password,
            'site'     => home_url(),
        );

        $response = $this->api_client->post( 'wp-json/wp-product-update-server/v1/login', $payload );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( empty( $response['token'] ) ) {
            return new \WP_Error( 'missing_token', __( 'The update server did not return a token.', 'wp-product-update-client' ) );
        }

        $token_value = sanitize_text_field( $response['token'] );
        $token_data = array(
            'token'   => $token_value,
            'expires' => isset( $response['expires'] ) ? (int) $response['expires'] : 0,
            'user'    => isset( $response['user'] ) ? $response['user'] : array(),
        );

        update_option( self::OPTION_TOKEN, $token_data );

        $settings                   = $this->api_client->get_settings();
        $settings['remember_token'] = $token_value;
        $settings['token_expires']  = $token_data['expires'];
        $this->api_client->update_settings( $settings );

        $this->token = $token_data;

        return true;
    }

    /**
     * Clears the stored token.
     */
    public function logout() {
        delete_option( self::OPTION_TOKEN );

        $settings                     = $this->api_client->get_settings();
        $settings['remember_token']   = '';
        $settings['token_expires']    = 0;
        $this->api_client->update_settings( $settings );

        $this->token = null;
    }

    /**
     * Returns the stored token data.
     *
     * @return array
     */
    public function get_token() {
        if ( null === $this->token ) {
            $this->token = get_option( self::OPTION_TOKEN, array() );
        }

        return $this->token;
    }

    /**
     * Adds authentication headers to requests.
     *
     * @param array $headers Existing headers.
     *
     * @return array
     */
    public function authorized_headers( array $headers = array() ) {
        $token = $this->get_token();

        if ( empty( $token['token'] ) ) {
            return $headers;
        }

        $headers['Authorization'] = 'Bearer ' . $token['token'];

        return $headers;
    }
}

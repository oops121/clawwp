<?php
/**
 * AgentWallet API client.
 *
 * Thin HTTP wrapper for the hosted AgentWallet REST API
 * at hifriendbot.com. Handles authentication, requests,
 * and error normalization.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_AgentWallet_Client {

    /** @var string */
    private $base_url;

    /** @var string */
    private $username;

    /** @var string */
    private $api_key;

    /**
     * @param string $base_url API base URL (with trailing slash).
     * @param string $username AgentWallet username.
     * @param string $api_key  AgentWallet API key (plain text).
     */
    public function __construct( $base_url, $username, $api_key ) {
        $this->base_url = trailingslashit( $base_url );
        $this->username = $username;
        $this->api_key  = $api_key;
    }

    /**
     * Validate the base URL to prevent SSRF.
     *
     * @return true|WP_Error
     */
    private function validate_url( $url ) {
        if ( false === wp_http_validate_url( $url ) ) {
            return new WP_Error( 'agentwallet_invalid_url', 'AgentWallet API URL is invalid or points to a private/reserved IP address.' );
        }
        return true;
    }

    /**
     * Build from saved ClawWP settings.
     *
     * @return self|WP_Error Client instance or error if not configured.
     */
    public static function from_settings() {
        $base_url = ClawWP::get_option( 'agentwallet_api_url', 'https://hifriendbot.com/wp-json/agentwallet/v1/' );
        $username = ClawWP::get_option( 'agentwallet_username' );
        $api_key  = ClawWP::decrypt( ClawWP::get_option( 'agentwallet_api_key' ) );

        if ( empty( $username ) || empty( $api_key ) ) {
            return new WP_Error( 'agentwallet_not_configured', 'AgentWallet credentials are not configured. Go to ClawWP > Settings to add your AgentWallet username and API key.' );
        }

        return new self( $base_url, $username, $api_key );
    }

    /**
     * Create a new wallet.
     *
     * @param string $label    Wallet label.
     * @param int    $chain_id Chain ID.
     * @return array|WP_Error
     */
    public function create_wallet( $label, $chain_id ) {
        return $this->post( 'wallets', array(
            'label'    => $label,
            'chain_id' => $chain_id,
        ) );
    }

    /**
     * List wallets.
     *
     * @param int $page     Page number.
     * @param int $per_page Results per page.
     * @return array|WP_Error
     */
    public function list_wallets( $page = 1, $per_page = 20 ) {
        return $this->get( 'wallets', array(
            'page'     => $page,
            'per_page' => $per_page,
        ) );
    }

    /**
     * Get a specific wallet.
     *
     * @param int $wallet_id Wallet ID.
     * @return array|WP_Error
     */
    public function get_wallet( $wallet_id ) {
        return $this->get( 'wallets/' . (int) $wallet_id );
    }

    /**
     * Get native coin balance.
     *
     * @param int $wallet_id Wallet ID.
     * @param int $chain_id  Chain ID.
     * @return array|WP_Error
     */
    public function get_balance( $wallet_id, $chain_id ) {
        return $this->get( 'wallets/' . (int) $wallet_id . '/balance', array(
            'chain_id' => $chain_id,
        ) );
    }

    /**
     * Get ERC-20 token balance.
     *
     * @param int    $wallet_id     Wallet ID.
     * @param int    $chain_id      Chain ID.
     * @param string $token_address Token contract address.
     * @return array|WP_Error
     */
    public function get_token_balance( $wallet_id, $chain_id, $token_address ) {
        return $this->get( 'wallets/' . (int) $wallet_id . '/token-balance', array(
            'chain_id' => $chain_id,
            'token'    => $token_address,
        ) );
    }

    /**
     * Send (sign + broadcast) a transaction.
     *
     * @param int   $wallet_id Wallet ID.
     * @param array $params    Transaction params (chain_id, to, value, data, gas_limit).
     * @return array|WP_Error
     */
    public function send_transaction( $wallet_id, $params ) {
        return $this->post( 'wallets/' . (int) $wallet_id . '/send', $params );
    }

    /**
     * List transactions.
     *
     * @param int $page     Page number.
     * @param int $per_page Results per page.
     * @return array|WP_Error
     */
    public function list_transactions( $page = 1, $per_page = 20 ) {
        return $this->get( 'transactions', array(
            'page'     => $page,
            'per_page' => $per_page,
        ) );
    }

    /**
     * List supported chains.
     *
     * @return array|WP_Error
     */
    public function list_chains() {
        return $this->get( 'chains' );
    }

    /**
     * Get usage / billing stats.
     *
     * @return array|WP_Error
     */
    public function get_usage() {
        return $this->get( 'usage' );
    }

    /**
     * Make a GET request.
     *
     * @param string $endpoint Relative endpoint path.
     * @param array  $query    Query parameters.
     * @return array|WP_Error
     */
    private function get( $endpoint, $query = array() ) {
        $url = $this->base_url . $endpoint;
        if ( ! empty( $query ) ) {
            $url = add_query_arg( $query, $url );
        }

        $valid = $this->validate_url( $url );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => $this->auth_headers(),
        ) );

        return $this->parse_response( $response );
    }

    /**
     * Make a POST request.
     *
     * @param string $endpoint Relative endpoint path.
     * @param array  $body     Request body.
     * @return array|WP_Error
     */
    private function post( $endpoint, $body = array() ) {
        $url = $this->base_url . $endpoint;

        $valid = $this->validate_url( $url );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        $response = wp_remote_post( $url, array(
            'timeout' => 15,
            'headers' => array_merge( $this->auth_headers(), array(
                'Content-Type' => 'application/json',
            ) ),
            'body'    => wp_json_encode( $body ),
        ) );

        return $this->parse_response( $response );
    }

    /**
     * Build HTTP Basic Auth headers.
     *
     * @return array
     */
    private function auth_headers() {
        return array(
            'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for HTTP Basic Auth.
        );
    }

    /**
     * Parse the API response.
     *
     * @param array|WP_Error $response wp_remote_* response.
     * @return array|WP_Error Parsed body or error.
     */
    private function parse_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'agentwallet_request_failed', 'AgentWallet request failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $message = isset( $body['error'] ) ? $body['error'] : 'AgentWallet API error (HTTP ' . $code . ').';
            return new WP_Error( 'agentwallet_api_error', $message );
        }

        if ( null === $body ) {
            return new WP_Error( 'agentwallet_invalid_response', 'Invalid response from AgentWallet API.' );
        }

        return $body;
    }
}

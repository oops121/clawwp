<?php
/**
 * Webhook handler — REST API route registration.
 *
 * Registers all ClawWP REST endpoints and routes
 * incoming requests to the appropriate handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Webhook_Handler {

    const NAMESPACE = 'clawwp/v1';

    /**
     * Register all REST API routes.
     */
    public function register_routes() {
        // Admin sidebar chat.
        register_rest_route( self::NAMESPACE, '/chat', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_chat' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'message'         => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                'conversation_id' => array( 'required' => false, 'type' => 'integer' ),
                'channel'         => array( 'required' => false, 'type' => 'string', 'default' => 'webchat' ),
            ),
        ) );

        // Telegram webhook.
        register_rest_route( self::NAMESPACE, '/telegram', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_telegram' ),
            'permission_callback' => '__return_true', // Telegram verifies via secret token.
        ) );

        // Slack webhook (Pro).
        register_rest_route( self::NAMESPACE, '/slack', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_slack' ),
            'permission_callback' => '__return_true',
        ) );

        // Discord interactions (Pro).
        register_rest_route( self::NAMESPACE, '/discord', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_discord' ),
            'permission_callback' => '__return_true',
        ) );

        // Pairing endpoint.
        register_rest_route( self::NAMESPACE, '/pair', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_pair' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'code' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        // Usage data.
        register_rest_route( self::NAMESPACE, '/usage', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_usage' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'period' => array( 'required' => false, 'type' => 'string', 'default' => 'month' ),
            ),
        ) );

        // Conversations list.
        register_rest_route( self::NAMESPACE, '/conversations', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_conversations' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
        ) );

        // License activation.
        register_rest_route( self::NAMESPACE, '/license/activate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_license_activate' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'license_key' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        // License deactivation.
        register_rest_route( self::NAMESPACE, '/license/deactivate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_license_deactivate' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
        ) );

        // AgentWallet connection test.
        register_rest_route( self::NAMESPACE, '/agentwallet-test', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_agentwallet_test' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
        ) );

        // MCP server management.
        register_rest_route( self::NAMESPACE, '/mcp-servers', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_mcp_add_server' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'name'        => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                'transport'   => array( 'required' => false, 'type' => 'string', 'default' => 'http' ),
                'endpoint'    => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'esc_url_raw' ),
                'auth_type'   => array( 'required' => false, 'type' => 'string', 'default' => 'none' ),
                'credentials' => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
                'command'     => array( 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/mcp-servers/(?P<id>[a-z0-9_-]+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'handle_mcp_remove_server' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
        ) );

        register_rest_route( self::NAMESPACE, '/mcp-servers/(?P<id>[a-z0-9_-]+)/discover', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_mcp_discover' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
        ) );

        register_rest_route( self::NAMESPACE, '/mcp-servers/(?P<id>[a-z0-9_-]+)/toggle', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_mcp_toggle' ),
            'permission_callback' => array( $this, 'check_admin_auth' ),
            'args'                => array(
                'enabled' => array( 'required' => true, 'type' => 'boolean' ),
            ),
        ) );

        // Health check.
        register_rest_route( self::NAMESPACE, '/health', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_health' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Handle admin sidebar chat messages.
     */
    public function handle_chat( WP_REST_Request $request ) {
        $message         = $request->get_param( 'message' );
        $conversation_id = $request->get_param( 'conversation_id' );
        $user_id         = get_current_user_id();

        try {
            $agent  = ClawWP::get_agent();
            $result = $agent->handle_message( $message, $user_id, 'webchat', 'admin-sidebar', $conversation_id );
            return new WP_REST_Response( $result, 200 );
        } catch ( Exception $e ) {
            // Log the full error for debugging, but return a safe message to the client.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[ClawWP] Chat error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            return new WP_REST_Response( array(
                'response' => __( 'Something went wrong processing your message. Please try again.', 'clawwp' ),
                'error'    => true,
            ), 500 );
        }
    }

    /**
     * Handle Telegram webhook.
     */
    public function handle_telegram( WP_REST_Request $request ) {
        // Verify the secret token — reject ALL requests if secret is not configured.
        $secret = ClawWP::get_option( 'telegram_webhook_secret' );
        $header = $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );

        if ( empty( $secret ) || empty( $header ) || ! hash_equals( $secret, $header ) ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        $body = $request->get_json_params();
        if ( empty( $body ) ) {
            return new WP_REST_Response( array( 'ok' => true ), 200 );
        }

        // Route to the Telegram channel handler.
        $telegram_token = ClawWP::get_option( 'telegram_bot_token' );
        if ( empty( $telegram_token ) ) {
            return new WP_REST_Response( array( 'error' => 'Telegram not configured' ), 500 );
        }

        $channel = new ClawWP_Channel_Telegram( ClawWP::decrypt( $telegram_token ) );
        $channel->handle_incoming( $request );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    /**
     * Handle Slack webhook.
     */
    public function handle_slack( WP_REST_Request $request ) {
        // Verify Slack request signature.
        $signing_secret = ClawWP::get_option( 'slack_signing_secret' );
        if ( empty( $signing_secret ) ) {
            return new WP_REST_Response( array( 'error' => 'Slack not configured' ), 500 );
        }

        $timestamp = $request->get_header( 'X-Slack-Request-Timestamp' );
        $signature = $request->get_header( 'X-Slack-Signature' );

        // Reject requests older than 5 minutes to prevent replay attacks.
        if ( empty( $timestamp ) || abs( time() - (int) $timestamp ) > 300 ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        $raw_body  = $request->get_body();
        $sig_base  = 'v0:' . $timestamp . ':' . $raw_body;
        $computed  = 'v0=' . hash_hmac( 'sha256', $sig_base, ClawWP::decrypt( $signing_secret ) );

        if ( empty( $signature ) || ! hash_equals( $computed, $signature ) ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        // URL verification challenge.
        $body = $request->get_json_params();
        if ( isset( $body['type'] ) && 'url_verification' === $body['type'] ) {
            return new WP_REST_Response( array( 'challenge' => sanitize_text_field( $body['challenge'] ?? '' ) ), 200 );
        }

        /**
         * Route to Slack channel handler (Pro).
         *
         * @see ClawWP_Channel_Slack
         */
        do_action( 'clawwp_slack_webhook', $request );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    /**
     * Handle Discord interactions.
     */
    public function handle_discord( WP_REST_Request $request ) {
        // Verify Discord Ed25519 signature.
        $public_key = ClawWP::get_option( 'discord_public_key' );
        if ( empty( $public_key ) ) {
            return new WP_REST_Response( array( 'error' => 'Discord not configured' ), 500 );
        }

        $signature = $request->get_header( 'X-Signature-Ed25519' );
        $timestamp = $request->get_header( 'X-Signature-Timestamp' );
        $raw_body  = $request->get_body();

        if ( empty( $signature ) || empty( $timestamp ) || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        try {
            $verified = sodium_crypto_sign_verify_detached(
                hex2bin( $signature ),
                $timestamp . $raw_body,
                hex2bin( $public_key )
            );
        } catch ( \Exception $e ) {
            $verified = false;
        }

        if ( ! $verified ) {
            return new WP_REST_Response( array( 'error' => 'Unauthorized' ), 401 );
        }

        $body = $request->get_json_params();

        // Discord ping verification.
        if ( isset( $body['type'] ) && 1 === (int) $body['type'] ) {
            return new WP_REST_Response( array( 'type' => 1 ), 200 );
        }

        /**
         * Route to Discord channel handler (Pro).
         *
         * @see ClawWP_Channel_Discord
         */
        do_action( 'clawwp_discord_webhook', $request );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    /**
     * Handle pairing code submission.
     */
    public function handle_pair( WP_REST_Request $request ) {
        $code    = $request->get_param( 'code' );
        $user_id = get_current_user_id();

        $permissions = new ClawWP_Permissions();
        $result      = $permissions->complete_pairing( $user_id, $code );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'channel' => $result['channel'],
            'message' => sprintf( __( 'Successfully paired with %s!', 'clawwp' ), ucfirst( $result['channel'] ) ),
        ), 200 );
    }

    /**
     * Handle usage data request.
     */
    public function handle_usage( WP_REST_Request $request ) {
        $period  = $request->get_param( 'period' );
        $user_id = get_current_user_id();
        $tracker = new ClawWP_Cost_Tracker();

        return new WP_REST_Response( array(
            'summary'   => $tracker->get_usage_summary( $user_id, $period ),
            'daily'     => $tracker->get_daily_breakdown( $user_id ),
            'by_model'  => $tracker->get_model_breakdown( $user_id, $period ),
        ), 200 );
    }

    /**
     * Handle conversations list request.
     */
    public function handle_conversations( WP_REST_Request $request ) {
        $user_id      = get_current_user_id();
        $conversation = new ClawWP_Conversation();
        $list         = $conversation->list_conversations( $user_id );

        return new WP_REST_Response( array( 'conversations' => $list ), 200 );
    }

    /**
     * Handle license activation.
     */
    public function handle_license_activate( WP_REST_Request $request ) {
        $license_key = $request->get_param( 'license_key' );
        $result      = ClawWP_License::activate( $license_key );

        $status_code = $result['success'] ? 200 : 400;
        return new WP_REST_Response( $result, $status_code );
    }

    /**
     * Handle license deactivation.
     */
    public function handle_license_deactivate( WP_REST_Request $request ) {
        $result = ClawWP_License::deactivate();
        return new WP_REST_Response( $result, 200 );
    }

    /**
     * Health check endpoint.
     */
    public function handle_health( WP_REST_Request $request ) {
        return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
    }

    /**
     * Handle AgentWallet connection test.
     */
    public function handle_agentwallet_test( WP_REST_Request $request ) {
        $client = ClawWP_AgentWallet_Client::from_settings();
        if ( is_wp_error( $client ) ) {
            return new WP_REST_Response( array( 'success' => false, 'error' => $client->get_error_message() ), 200 );
        }

        $result = $client->list_chains();
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( array( 'success' => false, 'error' => $result->get_error_message() ), 200 );
        }

        $chain_count = is_array( $result ) ? count( $result ) : 0;
        return new WP_REST_Response( array( 'success' => true, 'chains' => $chain_count ), 200 );
    }

    /**
     * Handle adding a new MCP server.
     */
    public function handle_mcp_add_server( WP_REST_Request $request ) {
        $name        = $request->get_param( 'name' );
        $transport   = $request->get_param( 'transport' );
        $endpoint    = $request->get_param( 'endpoint' );
        $auth_type   = $request->get_param( 'auth_type' );
        $credentials = $request->get_param( 'credentials' );
        $command     = $request->get_param( 'command' );

        // Validate transport-specific requirements.
        if ( 'stdio' === $transport ) {
            if ( empty( $command ) ) {
                return new WP_REST_Response( array( 'error' => 'Command is required for stdio transport.' ), 400 );
            }
        } else {
            if ( empty( $endpoint ) ) {
                return new WP_REST_Response( array( 'error' => 'Endpoint URL is required for HTTP transport.' ), 400 );
            }
        }

        // Generate a slug from the name.
        $id = sanitize_key( str_replace( ' ', '-', strtolower( $name ) ) );
        if ( empty( $id ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid server name.' ), 400 );
        }

        $registry = new ClawWP_MCP_Registry();

        // Check for duplicates.
        if ( $registry->get_server( $id ) ) {
            return new WP_REST_Response( array( 'error' => 'Server with this ID already exists.' ), 400 );
        }

        $server = $registry->add_server( $id, $name, $endpoint, $auth_type, $credentials, $transport, $command );

        // Auto-discover tools on add.
        $tools = $registry->discover_tools( $id );
        $tool_count = is_wp_error( $tools ) ? 0 : count( $tools );
        $discover_error = is_wp_error( $tools ) ? $tools->get_error_message() : null;

        // Refresh server data after discovery.
        $server = $registry->get_server( $id );

        return new WP_REST_Response( array(
            'success'        => true,
            'server'         => array(
                'id'       => $server['id'],
                'name'     => $server['name'],
                'endpoint' => $server['endpoint'],
                'tools'    => $tool_count,
            ),
            'discover_error' => $discover_error,
        ), 201 );
    }

    /**
     * Handle removing an MCP server.
     */
    public function handle_mcp_remove_server( WP_REST_Request $request ) {
        $id       = sanitize_key( $request->get_param( 'id' ) );
        $registry = new ClawWP_MCP_Registry();

        if ( $registry->is_builtin( $id ) ) {
            return new WP_REST_Response( array( 'error' => 'Built-in servers cannot be removed.' ), 400 );
        }

        if ( ! $registry->remove_server( $id ) ) {
            return new WP_REST_Response( array( 'error' => 'Server not found.' ), 404 );
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Handle toggling a built-in MCP server on or off.
     */
    public function handle_mcp_toggle( WP_REST_Request $request ) {
        $id      = sanitize_key( $request->get_param( 'id' ) );
        $enabled = (bool) $request->get_param( 'enabled' );

        $registry = new ClawWP_MCP_Registry();

        if ( ! $registry->is_builtin( $id ) ) {
            return new WP_REST_Response( array( 'error' => 'Only built-in servers can be toggled.' ), 400 );
        }

        $registry->toggle_builtin( $id, $enabled );

        // Auto-discover if enabling and no tools cached yet.
        if ( $enabled ) {
            $server = $registry->get_server( $id );
            if ( empty( $server['tools'] ) ) {
                $registry->discover_tools( $id );
            }
        }

        return new WP_REST_Response( array( 'success' => true, 'enabled' => $enabled ), 200 );
    }

    /**
     * Handle re-discovering tools from an MCP server.
     */
    public function handle_mcp_discover( WP_REST_Request $request ) {
        $id       = sanitize_key( $request->get_param( 'id' ) );
        $registry = new ClawWP_MCP_Registry();

        $tools = $registry->discover_tools( $id );
        if ( is_wp_error( $tools ) ) {
            return new WP_REST_Response( array( 'success' => false, 'error' => $tools->get_error_message() ), 200 );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'tools'   => count( $tools ),
            'names'   => array_map( function( $t ) { return $t['name'] ?? ''; }, $tools ),
        ), 200 );
    }

    /**
     * Permission callback: require authenticated admin user.
     */
    public function check_admin_auth( WP_REST_Request $request ) {
        return current_user_can( 'manage_options' );
    }
}

<?php
/**
 * Wallet tool (Pro) — manage crypto wallets and send blockchain transactions.
 *
 * Connects to the hosted AgentWallet service at hifriendbot.com
 * for wallet creation, balance checks, and on-chain transactions
 * across 9 EVM chains.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_Tool_Wallet extends ClawWP_Tool {

    public function get_name() {
        return 'wallet';
    }

    public function get_description() {
        return 'Manage crypto wallets and send blockchain transactions via AgentWallet. Create wallets, check balances, send ETH or tokens, and view transactions. Supports Ethereum, Base, Polygon, BSC, PulseChain, Arbitrum, Optimism, Avalanche, and Zora.';
    }

    public function get_parameters() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'action' => array(
                    'type'        => 'string',
                    'enum'        => array(
                        'create_wallet', 'list_wallets', 'get_balance',
                        'get_token_balance', 'send_transaction',
                        'list_transactions', 'list_chains', 'get_usage',
                    ),
                    'description' => 'The wallet operation to perform.',
                ),
                'wallet_id' => array(
                    'type'        => 'integer',
                    'description' => 'Wallet ID (required for get_balance, get_token_balance, send_transaction).',
                ),
                'chain_id' => array(
                    'type'        => 'integer',
                    'description' => 'Chain ID. Common values: 1 (Ethereum), 8453 (Base), 137 (Polygon), 56 (BSC), 369 (PulseChain), 42161 (Arbitrum), 10 (Optimism).',
                ),
                'label' => array(
                    'type'        => 'string',
                    'description' => 'Wallet label (for create_wallet).',
                ),
                'to' => array(
                    'type'        => 'string',
                    'description' => 'Destination address (for send_transaction). Must be a valid 0x-prefixed Ethereum address.',
                ),
                'value' => array(
                    'type'        => 'string',
                    'description' => 'Amount to send in wei (for send_transaction). Use "0" for token-only transfers.',
                ),
                'data' => array(
                    'type'        => 'string',
                    'description' => 'Contract call data as hex string (for send_transaction). Used for ERC-20 transfers and contract interactions.',
                ),
                'token' => array(
                    'type'        => 'string',
                    'description' => 'ERC-20 token contract address (for get_token_balance).',
                ),
                'gas_limit' => array(
                    'type'        => 'string',
                    'description' => 'Gas limit override (for send_transaction). Auto-estimated if omitted.',
                ),
            ),
            'required' => array( 'action' ),
        );
    }

    public function get_required_capability() {
        return 'manage_options';
    }

    public function is_pro() {
        return true;
    }

    public function execute( array $params ) {
        $client = ClawWP_AgentWallet_Client::from_settings();
        if ( is_wp_error( $client ) ) {
            return array( 'error' => $client->get_error_message() );
        }

        $action = $params['action'];

        switch ( $action ) {
            case 'create_wallet':
                return $this->create_wallet( $client, $params );
            case 'list_wallets':
                return $this->list_wallets( $client, $params );
            case 'get_balance':
                return $this->get_balance( $client, $params );
            case 'get_token_balance':
                return $this->get_token_balance( $client, $params );
            case 'send_transaction':
                return $this->send_transaction( $client, $params );
            case 'list_transactions':
                return $this->list_transactions( $client, $params );
            case 'list_chains':
                return $this->list_chains( $client );
            case 'get_usage':
                return $this->get_usage( $client );
            default:
                return array( 'error' => 'Unknown wallet action.' );
        }
    }

    private function create_wallet( $client, $params ) {
        if ( empty( $params['label'] ) ) {
            return array( 'error' => 'label is required to create a wallet.' );
        }

        $chain_id = (int) ( $params['chain_id'] ?? 1 );
        $result   = $client->create_wallet( $params['label'], $chain_id );

        if ( is_wp_error( $result ) ) {
            return array( 'error' => $result->get_error_message() );
        }

        return array(
            'success'   => true,
            'wallet_id' => $result['wallet_id'] ?? null,
            'address'   => $result['address'] ?? null,
            'label'     => $result['label'] ?? $params['label'],
            'chain_id'  => $result['chain_id'] ?? $chain_id,
        );
    }

    private function list_wallets( $client, $params ) {
        $result = $client->list_wallets();

        if ( is_wp_error( $result ) ) {
            return array( 'error' => $result->get_error_message() );
        }

        return $result;
    }

    private function get_balance( $client, $params ) {
        if ( empty( $params['wallet_id'] ) ) {
            return array( 'error' => 'wallet_id is required.' );
        }

        $chain_id = (int) ( $params['chain_id'] ?? 1 );
        $result   = $client->get_balance( $params['wallet_id'], $chain_id );

        if ( is_wp_error( $result ) ) {
            return array( 'error' => $result->get_error_message() );
        }

        // Add human-readable balance if raw wei is available.
        if ( isset( $result['balance_wei'] ) && ! isset( $result['balance_eth'] ) ) {
            $result['balance_eth'] = $this->wei_to_eth( $result['balance_wei'] );
        }

        return $result;
    }

    private function get_token_balance( $client, $params ) {
        if ( empty( $params['wallet_id'] ) ) {
            return array( 'error' => 'wallet_id is required.' );
        }
        if ( empty( $params['token'] ) ) {
            return array( 'error' => 'token contract address is required.' );
        }
        if ( ! preg_match( '/^0x[0-9a-fA-F]{40}$/', $params['token'] ) ) {
            return array( 'error' => 'Invalid token address format. Must be 0x-prefixed with 40 hex characters.' );
        }

        $chain_id = (int) ( $params['chain_id'] ?? 1 );
        $result   = $client->get_token_balance( $params['wallet_id'], $chain_id, $params['token'] );

        if ( is_wp_error( $result ) ) {
            return array( 'error' => $result->get_error_message() );
        }

        return $result;
    }

    private function send_transaction( $client, $params ) {
        if ( empty( $params['wallet_id'] ) ) {
            return array( 'error' => 'wallet_id is required.' );
        }
        if ( empty( $params['to'] ) ) {
            return array( 'error' => 'to address is required.' );
        }
        if ( empty( $params['chain_id'] ) ) {
            return array( 'error' => 'chain_id is required.' );
        }

        // Validate Ethereum address format (0x + 40 hex chars).
        if ( ! preg_match( '/^0x[0-9a-fA-F]{40}$/', $params['to'] ) ) {
            return array( 'error' => 'Invalid Ethereum address format. Must be 0x-prefixed with 40 hex characters.' );
        }

        // Validate value is a numeric string (wei amount).
        $value = $params['value'] ?? '0';
        if ( ! preg_match( '/^\d+$/', $value ) ) {
            return array( 'error' => 'Value must be a numeric string in wei (e.g. "0" or "1000000000000000000").' );
        }

        $tx_params = array(
            'chain_id' => (int) $params['chain_id'],
            'to'       => sanitize_text_field( $params['to'] ),
            'value'    => $value,
        );

        if ( ! empty( $params['data'] ) ) {
            // Validate hex-encoded contract call data.
            if ( ! preg_match( '/^0x[0-9a-fA-F]*$/', $params['data'] ) ) {
                return array( 'error' => 'Contract data must be a 0x-prefixed hex string.' );
            }
            $tx_params['data'] = sanitize_text_field( $params['data'] );
        }
        if ( ! empty( $params['gas_limit'] ) ) {
            $tx_params['gas_limit'] = sanitize_text_field( $params['gas_limit'] );
        }

        $result = $client->send_transaction( $params['wallet_id'], $tx_params );

        if ( is_wp_error( $result ) ) {
            return array( 'error' => $result->get_error_message() );
        }

        return array(
            'success' => true,
            'tx_hash' => $result['tx_hash'] ?? null,
        );
    }

    private function list_transactions( $client, $params ) {
        $result = $client->list_transactions();

        if ( is_wp_error( $result ) ) {
            return array( 'error' => $result->get_error_message() );
        }

        return $result;
    }

    private function list_chains( $client ) {
        $result = $client->list_chains();

        if ( is_wp_error( $result ) ) {
            return array( 'error' => $result->get_error_message() );
        }

        return $result;
    }

    private function get_usage( $client ) {
        $result = $client->get_usage();

        if ( is_wp_error( $result ) ) {
            return array( 'error' => $result->get_error_message() );
        }

        return $result;
    }

    /**
     * Convert wei string to ETH for display.
     *
     * @param string $wei Amount in wei.
     * @return string Amount in ETH.
     */
    private function wei_to_eth( $wei ) {
        if ( function_exists( 'bcdiv' ) ) {
            return bcdiv( $wei, '1000000000000000000', 18 );
        }
        return (string) ( (float) $wei / 1e18 );
    }
}

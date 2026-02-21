<?php
/**
 * HiFriendbot API Proxy provider.
 *
 * Routes AI requests through HiFriendbot.com instead of directly
 * to the AI provider. No API key needed — uses the Pro license key
 * for authentication. 500K tokens/month included.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_AI_Proxy extends ClawWP_AI_Provider {

    const PROXY_URL = 'https://hifriendbot.com/wp-json/hifriendbot/v1/clawwp/proxy';
    const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /**
     * Available models via proxy (subset — no Opus to control costs).
     */
    const MODELS = array(
        'claude-sonnet-4-6' => array( 'name' => 'Claude Sonnet 4.6', 'input' => 3.00, 'output' => 15.00 ),
        'claude-haiku-4-5-20251001'  => array( 'name' => 'Claude Haiku 4.5',  'input' => 0.80, 'output' => 4.00 ),
    );

    /** @var string License key used as bearer token. */
    private $license_key;

    /**
     * @param string $license_key Pro license key for auth.
     * @param string $model       Model to use (defaults to Sonnet).
     */
    public function __construct( $license_key, $model = '' ) {
        $this->license_key = $license_key;
        $this->model       = $model ?: self::DEFAULT_MODEL;
        // Parent expects api_key, but proxy doesn't use one directly.
        parent::__construct( 'proxy', $this->model );
    }

    /**
     * @inheritDoc
     */
    public function chat( array $messages, string $system = '', array $tools = array() ) {
        $body = array(
            'messages' => $this->format_messages( $messages ),
            'model'    => $this->model,
        );

        if ( ! empty( $system ) ) {
            $body['system'] = $system;
        }

        if ( ! empty( $tools ) ) {
            $body['tools'] = $this->format_tools( $tools );
        }

        $response = $this->api_request( self::PROXY_URL, $body, $this->get_headers() );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        return $this->parse_response( $response );
    }

    /**
     * @inheritDoc
     */
    public function stream( array $messages, string $system = '', array $tools = array(), callable $callback = null ) {
        // Proxy doesn't support streaming yet — fall back to non-streaming.
        return $this->chat( $messages, $system, $tools );
    }

    /**
     * @inheritDoc
     */
    public function get_model_id() {
        return $this->model;
    }

    /**
     * @inheritDoc
     */
    public function get_provider_name() {
        return 'hfb_proxy';
    }

    /**
     * Get the pricing for the current model.
     *
     * @return array{input: float, output: float} Price per million tokens.
     */
    public function get_pricing() {
        return self::MODELS[ $this->model ] ?? self::MODELS[ self::DEFAULT_MODEL ];
    }

    /**
     * Check if proxy is configured (has a license key).
     */
    public function is_configured() {
        return ! empty( $this->license_key );
    }

    /**
     * Get request headers with bearer auth.
     */
    private function get_headers() {
        return array(
            'Authorization' => 'Bearer ' . $this->license_key,
            'Content-Type'  => 'application/json',
        );
    }

    /**
     * Parse a Claude Messages API response into our standard format.
     *
     * The proxy returns the raw Claude API response, so parsing
     * is identical to the Claude provider.
     */
    private function parse_response( $data ) {
        $content    = '';
        $tool_calls = array();

        if ( ! empty( $data['content'] ) ) {
            foreach ( $data['content'] as $block ) {
                if ( 'text' === ( $block['type'] ?? '' ) ) {
                    $content .= $block['text'];
                } elseif ( 'tool_use' === ( $block['type'] ?? '' ) ) {
                    $tool_calls[] = array(
                        'id'    => $block['id'],
                        'name'  => $block['name'],
                        'input' => $block['input'] ?? array(),
                    );
                }
            }
        }

        return new ClawWP_AI_Response( array(
            'content'     => $content,
            'tool_calls'  => ! empty( $tool_calls ) ? $tool_calls : null,
            'stop_reason' => $data['stop_reason'] ?? 'end_turn',
            'tokens_in'   => $data['usage']['input_tokens'] ?? 0,
            'tokens_out'  => $data['usage']['output_tokens'] ?? 0,
            'model'       => $data['model'] ?? $this->model,
        ) );
    }

    /**
     * Format messages for the Anthropic Messages API.
     */
    private function format_messages( array $messages ) {
        $formatted = array();

        foreach ( $messages as $msg ) {
            $role = $msg['role'];

            if ( 'tool' === $role ) {
                $formatted[] = array(
                    'role'    => 'user',
                    'content' => array(
                        array(
                            'type'        => 'tool_result',
                            'tool_use_id' => $msg['tool_call_id'],
                            'content'     => is_string( $msg['content'] ) ? $msg['content'] : wp_json_encode( $msg['content'] ),
                        ),
                    ),
                );
                continue;
            }

            if ( 'assistant' === $role && ! empty( $msg['tool_calls'] ) ) {
                $content_blocks = array();
                if ( ! empty( $msg['content'] ) ) {
                    $content_blocks[] = array(
                        'type' => 'text',
                        'text' => $msg['content'],
                    );
                }
                foreach ( $msg['tool_calls'] as $tc ) {
                    $content_blocks[] = array(
                        'type'  => 'tool_use',
                        'id'    => $tc['id'],
                        'name'  => $tc['name'],
                        'input' => $tc['input'] ?? array(),
                    );
                }
                $formatted[] = array(
                    'role'    => 'assistant',
                    'content' => $content_blocks,
                );
                continue;
            }

            $formatted[] = array(
                'role'    => $role,
                'content' => $msg['content'] ?? '',
            );
        }

        return $formatted;
    }

    /**
     * Format tool definitions for the Anthropic Messages API.
     */
    private function format_tools( array $tools ) {
        $formatted = array();

        foreach ( $tools as $tool ) {
            $schema = $tool['parameters'] ?? array( 'type' => 'object', 'properties' => new \stdClass() );
            $schema = $this->fix_schema_properties( $schema );

            $formatted[] = array(
                'name'         => $tool['name'],
                'description'  => $tool['description'] ?? '',
                'input_schema' => $schema,
            );
        }

        return $formatted;
    }

    /**
     * Recursively ensure 'properties' fields are objects, not arrays.
     *
     * PHP decodes JSON {} as array() which re-encodes as [] instead of {}.
     * Anthropic requires properties to be a dictionary/object.
     *
     * @param mixed $schema Schema or sub-schema.
     * @return mixed Fixed schema.
     */
    private function fix_schema_properties( $schema ) {
        if ( ! is_array( $schema ) ) {
            return $schema;
        }

        if ( array_key_exists( 'properties', $schema ) ) {
            if ( is_array( $schema['properties'] ) && empty( $schema['properties'] ) ) {
                $schema['properties'] = new \stdClass();
            } elseif ( is_array( $schema['properties'] ) ) {
                foreach ( $schema['properties'] as $key => $prop ) {
                    $schema['properties'][ $key ] = $this->fix_schema_properties( $prop );
                }
            }
        }

        if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
            $schema['items'] = $this->fix_schema_properties( $schema['items'] );
        }

        return $schema;
    }
}

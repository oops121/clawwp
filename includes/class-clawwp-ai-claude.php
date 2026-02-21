<?php
/**
 * Anthropic Claude AI provider.
 *
 * Implements the ClawWP_AI_Provider interface for Anthropic's Messages API
 * with support for tool use (function calling).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_AI_Claude extends ClawWP_AI_Provider {

    const API_URL = 'https://api.anthropic.com/v1/messages';
    const API_VERSION = '2023-06-01';
    const DEFAULT_MODEL = 'claude-sonnet-4-5-20250929';
    const MAX_TOKENS = 4096;

    /**
     * Available Claude models with pricing (per million tokens).
     */
    const MODELS = array(
        'claude-opus-4-6'             => array( 'name' => 'Claude Opus 4.6',   'input' => 15.00, 'output' => 75.00 ),
        'claude-sonnet-4-5-20250929'  => array( 'name' => 'Claude Sonnet 4.5', 'input' => 3.00,  'output' => 15.00 ),
        'claude-haiku-4-5-20251001'   => array( 'name' => 'Claude Haiku 4.5',  'input' => 0.80,  'output' => 4.00 ),
    );

    public function __construct( $api_key, $model = '' ) {
        parent::__construct( $api_key, $model ?: self::DEFAULT_MODEL );
    }

    /**
     * @inheritDoc
     */
    public function chat( array $messages, string $system = '', array $tools = array() ) {
        $body = $this->build_request_body( $messages, $system, $tools );

        $response = $this->api_request( self::API_URL, $body, $this->get_headers() );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        return $this->parse_response( $response );
    }

    /**
     * @inheritDoc
     */
    public function stream( array $messages, string $system = '', array $tools = array(), callable $callback = null ) {
        $body           = $this->build_request_body( $messages, $system, $tools );
        $body['stream'] = true;

        $full_content = '';
        $tool_calls   = array();
        $tokens_in    = 0;
        $tokens_out   = 0;
        $current_tool = null;
        $current_json = '';

        $result = $this->api_stream_request(
            self::API_URL,
            $body,
            $this->get_headers(),
            function ( $event ) use ( &$full_content, &$tool_calls, &$tokens_in, &$tokens_out, &$current_tool, &$current_json, $callback ) {
                $type = $event['type'] ?? '';

                switch ( $type ) {
                    case 'message_start':
                        $tokens_in = $event['message']['usage']['input_tokens'] ?? 0;
                        break;

                    case 'content_block_start':
                        $block = $event['content_block'] ?? array();
                        if ( 'tool_use' === ( $block['type'] ?? '' ) ) {
                            $current_tool = array(
                                'id'    => $block['id'],
                                'name'  => $block['name'],
                                'input' => array(),
                            );
                            $current_json = '';
                        }
                        break;

                    case 'content_block_delta':
                        $delta = $event['delta'] ?? array();
                        if ( 'text_delta' === ( $delta['type'] ?? '' ) ) {
                            $text = $delta['text'] ?? '';
                            $full_content .= $text;
                            if ( $callback ) {
                                $callback( 'text', $text );
                            }
                        } elseif ( 'input_json_delta' === ( $delta['type'] ?? '' ) ) {
                            $current_json .= $delta['partial_json'] ?? '';
                        }
                        break;

                    case 'content_block_stop':
                        if ( $current_tool ) {
                            $current_tool['input'] = json_decode( $current_json, true ) ?: array();
                            $tool_calls[]          = $current_tool;
                            $current_tool          = null;
                            $current_json          = '';
                            if ( $callback ) {
                                $callback( 'tool_call', end( $tool_calls ) );
                            }
                        }
                        break;

                    case 'message_delta':
                        $tokens_out = $event['usage']['output_tokens'] ?? $tokens_out;
                        break;
                }
            }
        );

        if ( is_wp_error( $result ) ) {
            throw new Exception( $result->get_error_message() );
        }

        return new ClawWP_AI_Response( array(
            'content'     => $full_content,
            'tool_calls'  => ! empty( $tool_calls ) ? $tool_calls : null,
            'stop_reason' => ! empty( $tool_calls ) ? 'tool_use' : 'end_turn',
            'tokens_in'   => $tokens_in,
            'tokens_out'  => $tokens_out,
            'model'       => $this->model,
        ) );
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
        return 'anthropic';
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
     * Build the request body for the Messages API.
     */
    private function build_request_body( array $messages, string $system, array $tools ) {
        $body = array(
            'model'      => $this->model,
            'max_tokens' => self::MAX_TOKENS,
            'messages'   => $this->format_messages( $messages ),
        );

        if ( ! empty( $system ) ) {
            $body['system'] = $system;
        }

        if ( ! empty( $tools ) ) {
            $body['tools'] = $this->format_tools( $tools );
        }

        return $body;
    }

    /**
     * Format messages for the Anthropic Messages API.
     */
    private function format_messages( array $messages ) {
        $formatted = array();

        foreach ( $messages as $msg ) {
            $role = $msg['role'];

            // Anthropic uses 'user' and 'assistant' roles.
            // Tool results are sent as 'user' messages with tool_result content.
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

            // Assistant messages with tool calls.
            if ( 'assistant' === $role && ! empty( $msg['tool_calls'] ) ) {
                $content = array();
                if ( ! empty( $msg['content'] ) ) {
                    $content[] = array( 'type' => 'text', 'text' => $msg['content'] );
                }
                foreach ( $msg['tool_calls'] as $tc ) {
                    $content[] = array(
                        'type'  => 'tool_use',
                        'id'    => $tc['id'],
                        'name'  => $tc['name'],
                        'input' => $tc['input'],
                    );
                }
                $formatted[] = array( 'role' => 'assistant', 'content' => $content );
                continue;
            }

            // Standard text messages.
            if ( 'system' !== $role ) {
                $formatted[] = array(
                    'role'    => $role,
                    'content' => $msg['content'],
                );
            }
        }

        return $formatted;
    }

    /**
     * Format tools for the Anthropic tool use format.
     */
    private function format_tools( array $tools ) {
        $formatted = array();
        foreach ( $tools as $tool ) {
            $schema = $this->fix_schema_properties( $tool['parameters'] ?? array( 'type' => 'object', 'properties' => new \stdClass() ) );
            $formatted[] = array(
                'name'         => $tool['name'],
                'description'  => $tool['description'],
                'input_schema' => $schema,
            );
        }
        return $formatted;
    }

    /**
     * Recursively ensure 'properties' fields are objects, not arrays.
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

    /**
     * Parse the API response into a ClawWP_AI_Response.
     */
    private function parse_response( array $response ) {
        $content    = '';
        $tool_calls = array();

        foreach ( ( $response['content'] ?? array() ) as $block ) {
            if ( 'text' === $block['type'] ) {
                $content .= $block['text'];
            } elseif ( 'tool_use' === $block['type'] ) {
                $tool_calls[] = array(
                    'id'    => $block['id'],
                    'name'  => $block['name'],
                    'input' => $block['input'],
                );
            }
        }

        return new ClawWP_AI_Response( array(
            'content'     => $content,
            'tool_calls'  => ! empty( $tool_calls ) ? $tool_calls : null,
            'stop_reason' => $response['stop_reason'] ?? 'end_turn',
            'tokens_in'   => $response['usage']['input_tokens'] ?? 0,
            'tokens_out'  => $response['usage']['output_tokens'] ?? 0,
            'model'       => $response['model'] ?? $this->model,
        ) );
    }

    /**
     * Get request headers for the Anthropic API.
     */
    private function get_headers() {
        return array(
            'x-api-key'         => $this->api_key,
            'anthropic-version' => self::API_VERSION,
        );
    }
}

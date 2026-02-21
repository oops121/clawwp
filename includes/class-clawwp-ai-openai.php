<?php
/**
 * OpenAI AI provider (Pro).
 *
 * Implements the ClawWP_AI_Provider interface for OpenAI's Chat Completions API
 * with support for function calling (tools).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ClawWP_AI_OpenAI extends ClawWP_AI_Provider {

    const API_URL = 'https://api.openai.com/v1/chat/completions';
    const DEFAULT_MODEL = 'gpt-4o';
    const MAX_TOKENS = 4096;

    /**
     * Available OpenAI models with pricing (per million tokens).
     */
    const MODELS = array(
        'gpt-4o'         => array( 'name' => 'GPT-4o',      'input' => 2.50,  'output' => 10.00 ),
        'gpt-4o-mini'    => array( 'name' => 'GPT-4o Mini',  'input' => 0.15,  'output' => 0.60 ),
        'gpt-4-turbo'    => array( 'name' => 'GPT-4 Turbo',  'input' => 10.00, 'output' => 30.00 ),
        'o1'             => array( 'name' => 'o1',            'input' => 15.00, 'output' => 60.00 ),
        'o1-mini'        => array( 'name' => 'o1 Mini',       'input' => 3.00,  'output' => 12.00 ),
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
        $pending_tool = array(); // Index => partial tool call data.

        $result = $this->api_stream_request(
            self::API_URL,
            $body,
            $this->get_headers(),
            function ( $event ) use ( &$full_content, &$tool_calls, &$tokens_in, &$tokens_out, &$pending_tool, $callback ) {
                $choice = $event['choices'][0] ?? array();
                $delta  = $choice['delta'] ?? array();

                // Text content.
                if ( ! empty( $delta['content'] ) ) {
                    $full_content .= $delta['content'];
                    if ( $callback ) {
                        $callback( 'text', $delta['content'] );
                    }
                }

                // Tool calls (streamed incrementally).
                if ( ! empty( $delta['tool_calls'] ) ) {
                    foreach ( $delta['tool_calls'] as $tc_delta ) {
                        $idx = $tc_delta['index'];

                        if ( ! isset( $pending_tool[ $idx ] ) ) {
                            $pending_tool[ $idx ] = array(
                                'id'    => $tc_delta['id'] ?? '',
                                'name'  => $tc_delta['function']['name'] ?? '',
                                'args'  => '',
                            );
                        }

                        if ( ! empty( $tc_delta['id'] ) ) {
                            $pending_tool[ $idx ]['id'] = $tc_delta['id'];
                        }
                        if ( ! empty( $tc_delta['function']['name'] ) ) {
                            $pending_tool[ $idx ]['name'] = $tc_delta['function']['name'];
                        }
                        if ( isset( $tc_delta['function']['arguments'] ) ) {
                            $pending_tool[ $idx ]['args'] .= $tc_delta['function']['arguments'];
                        }
                    }
                }

                // Usage info (in final chunk).
                if ( ! empty( $event['usage'] ) ) {
                    $tokens_in  = $event['usage']['prompt_tokens'] ?? $tokens_in;
                    $tokens_out = $event['usage']['completion_tokens'] ?? $tokens_out;
                }

                // Finish reason.
                if ( ! empty( $choice['finish_reason'] ) ) {
                    // Finalize any pending tool calls.
                    foreach ( $pending_tool as $pt ) {
                        $parsed_args = json_decode( $pt['args'], true );
                        $tool_calls[] = array(
                            'id'    => $pt['id'],
                            'name'  => $pt['name'],
                            'input' => is_array( $parsed_args ) ? $parsed_args : array(),
                        );
                    }
                    if ( $callback && ! empty( $tool_calls ) ) {
                        foreach ( $tool_calls as $tc ) {
                            $callback( 'tool_call', $tc );
                        }
                    }
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
        return 'openai';
    }

    /**
     * Build the request body for the Chat Completions API.
     */
    private function build_request_body( array $messages, string $system, array $tools ) {
        $formatted = $this->format_messages( $messages, $system );

        $body = array(
            'model'      => $this->model,
            'max_tokens' => self::MAX_TOKENS,
            'messages'   => $formatted,
        );

        if ( ! empty( $tools ) ) {
            $body['tools'] = $this->format_tools( $tools );
        }

        return $body;
    }

    /**
     * Format messages for OpenAI Chat Completions API.
     */
    private function format_messages( array $messages, string $system ) {
        $formatted = array();

        // System message first.
        if ( ! empty( $system ) ) {
            $formatted[] = array(
                'role'    => 'system',
                'content' => $system,
            );
        }

        foreach ( $messages as $msg ) {
            $role = $msg['role'];

            // Tool results go as 'tool' role in OpenAI format.
            if ( 'tool' === $role ) {
                $formatted[] = array(
                    'role'         => 'tool',
                    'content'      => is_string( $msg['content'] ) ? $msg['content'] : wp_json_encode( $msg['content'] ),
                    'tool_call_id' => $msg['tool_call_id'] ?? '',
                );
                continue;
            }

            // Assistant messages with tool calls.
            if ( 'assistant' === $role && ! empty( $msg['tool_calls'] ) ) {
                $openai_tool_calls = array();
                foreach ( $msg['tool_calls'] as $tc ) {
                    $openai_tool_calls[] = array(
                        'id'       => $tc['id'],
                        'type'     => 'function',
                        'function' => array(
                            'name'      => $tc['name'],
                            'arguments' => wp_json_encode( $tc['input'] ),
                        ),
                    );
                }
                $formatted[] = array(
                    'role'       => 'assistant',
                    'content'    => $msg['content'] ?: null,
                    'tool_calls' => $openai_tool_calls,
                );
                continue;
            }

            // System messages already added above.
            if ( 'system' === $role ) {
                continue;
            }

            // Standard user/assistant messages.
            $formatted[] = array(
                'role'    => $role,
                'content' => $msg['content'],
            );
        }

        return $formatted;
    }

    /**
     * Format tools for OpenAI function calling format.
     */
    private function format_tools( array $tools ) {
        $formatted = array();
        foreach ( $tools as $tool ) {
            $formatted[] = array(
                'type'     => 'function',
                'function' => array(
                    'name'        => $tool['name'],
                    'description' => $tool['description'],
                    'parameters'  => $tool['parameters'],
                ),
            );
        }
        return $formatted;
    }

    /**
     * Parse the API response into a ClawWP_AI_Response.
     */
    private function parse_response( array $response ) {
        $choice     = $response['choices'][0] ?? array();
        $message    = $choice['message'] ?? array();
        $content    = $message['content'] ?? '';
        $tool_calls = array();

        if ( ! empty( $message['tool_calls'] ) ) {
            foreach ( $message['tool_calls'] as $tc ) {
                $tool_calls[] = array(
                    'id'    => $tc['id'],
                    'name'  => $tc['function']['name'],
                    'input' => json_decode( $tc['function']['arguments'], true ) ?: array(),
                );
            }
        }

        return new ClawWP_AI_Response( array(
            'content'     => $content,
            'tool_calls'  => ! empty( $tool_calls ) ? $tool_calls : null,
            'stop_reason' => $this->map_finish_reason( $choice['finish_reason'] ?? 'stop' ),
            'tokens_in'   => $response['usage']['prompt_tokens'] ?? 0,
            'tokens_out'  => $response['usage']['completion_tokens'] ?? 0,
            'model'       => $response['model'] ?? $this->model,
        ) );
    }

    /**
     * Map OpenAI finish_reason to ClawWP stop_reason.
     */
    private function map_finish_reason( $reason ) {
        $map = array(
            'stop'          => 'end_turn',
            'tool_calls'    => 'tool_use',
            'length'        => 'max_tokens',
            'content_filter' => 'end_turn',
        );
        return $map[ $reason ] ?? 'end_turn';
    }

    /**
     * Get request headers for the OpenAI API.
     */
    private function get_headers() {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key,
        );
    }
}

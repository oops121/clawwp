<?php
/**
 * Conversations log page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$conversation_mgr = new ClawWP_Conversation();
$user_id = get_current_user_id();
$conversations = $conversation_mgr->list_conversations( $user_id, '', 50 );
?>
<div class="clawwp-wrap">
    <div class="clawwp-page-header">
        <div class="clawwp-page-header-top">
            <img src="<?php echo esc_url( CLAWWP_PLUGIN_URL . 'assets/images/icon.svg' ); ?>" alt="ClawWP" class="clawwp-page-header-logo" />
            <span class="clawwp-page-header-brand">ClawWP</span>
        </div>
        <h1><?php esc_html_e( 'Conversations', 'clawwp' ); ?></h1>
        <p><?php esc_html_e( 'View your chat history across all channels', 'clawwp' ); ?></p>
    </div>

    <div class="clawwp-page-content">

        <?php if ( ! empty( $conversations ) ) : ?>
        <div class="clawwp-table-wrap">
            <div class="clawwp-table-scroll">
                <table class="clawwp-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;"><?php esc_html_e( 'ID', 'clawwp' ); ?></th>
                            <th><?php esc_html_e( 'Channel', 'clawwp' ); ?></th>
                            <th><?php esc_html_e( 'First Message', 'clawwp' ); ?></th>
                            <th style="width: 90px;"><?php esc_html_e( 'Messages', 'clawwp' ); ?></th>
                            <th><?php esc_html_e( 'Started', 'clawwp' ); ?></th>
                            <th><?php esc_html_e( 'Last Active', 'clawwp' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $conversations as $convo ) : ?>
                        <tr>
                            <td class="clawwp-text-muted">#<?php echo esc_html( $convo['id'] ); ?></td>
                            <td><span class="clawwp-badge clawwp-badge--channel"><?php echo esc_html( ucfirst( $convo['channel'] ) ); ?></span></td>
                            <td><?php echo esc_html( wp_trim_words( $convo['first_message'] ?? "\xe2\x80\x94", 15 ) ); ?></td>
                            <td><?php echo esc_html( $convo['message_count'] ); ?></td>
                            <td class="clawwp-text-muted"><?php echo esc_html( human_time_diff( strtotime( $convo['created_at'] ) ) ); ?> ago</td>
                            <td class="clawwp-text-muted"><?php echo esc_html( human_time_diff( strtotime( $convo['updated_at'] ) ) ); ?> ago</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else : ?>
        <div class="clawwp-empty">
            <div class="clawwp-empty-icon">&#128172;</div>
            <h3><?php esc_html_e( 'No conversations yet', 'clawwp' ); ?></h3>
            <p><?php esc_html_e( 'Start chatting with ClawWP to see your conversation history here.', 'clawwp' ); ?></p>
        </div>
        <?php endif; ?>

    </div>
</div>

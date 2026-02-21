<?php
/**
 * Dashboard page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="clawwp-wrap">
    <div class="clawwp-page-header">
        <div class="clawwp-page-header-top">
            <img src="<?php echo esc_url( CLAWWP_PLUGIN_URL . 'assets/images/icon.svg' ); ?>" alt="ClawWP" class="clawwp-page-header-logo" />
            <span class="clawwp-page-header-brand">ClawWP</span>
        </div>
        <h1><?php esc_html_e( 'Dashboard', 'clawwp' ); ?></h1>
        <p><?php esc_html_e( 'Your WordPress AI Agent by HiFriendbot', 'clawwp' ); ?></p>
    </div>

    <div class="clawwp-page-content">

        <!-- Status Cards -->
        <div class="clawwp-cards">
            <div class="clawwp-card">
                <div class="clawwp-card-label"><?php esc_html_e( 'AI Provider', 'clawwp' ); ?></div>
                <?php if ( $has_api_key ) : ?>
                    <div class="clawwp-card-value"><span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Connected', 'clawwp' ); ?></span></div>
                    <div class="clawwp-card-detail">
                        <?php
                        $current_model = ClawWP::get_option( 'claude_model', 'claude-sonnet-4-5-20250929' );
                        echo esc_html( ClawWP_AI_Claude::MODELS[ $current_model ]['name'] ?? 'Claude' );
                        ?>
                    </div>
                <?php else : ?>
                    <div class="clawwp-card-value"><span class="clawwp-status clawwp-status--error"><?php esc_html_e( 'Not configured', 'clawwp' ); ?></span></div>
                    <div class="clawwp-card-detail"><a href="<?php echo esc_url( admin_url( 'admin.php?page=clawwp-settings' ) ); ?>"><?php esc_html_e( 'Add API key', 'clawwp' ); ?></a></div>
                <?php endif; ?>
            </div>

            <div class="clawwp-card">
                <div class="clawwp-card-label"><?php esc_html_e( 'Telegram', 'clawwp' ); ?></div>
                <?php if ( $has_telegram ) : ?>
                    <div class="clawwp-card-value"><span class="clawwp-status clawwp-status--ok"><?php esc_html_e( 'Connected', 'clawwp' ); ?></span></div>
                <?php else : ?>
                    <div class="clawwp-card-value"><span class="clawwp-status clawwp-status--warn"><?php esc_html_e( 'Not connected', 'clawwp' ); ?></span></div>
                    <div class="clawwp-card-detail"><a href="<?php echo esc_url( admin_url( 'admin.php?page=clawwp-settings' ) ); ?>"><?php esc_html_e( 'Set up Telegram', 'clawwp' ); ?></a></div>
                <?php endif; ?>
            </div>

            <div class="clawwp-card">
                <div class="clawwp-card-label"><?php esc_html_e( 'Paired Channels', 'clawwp' ); ?></div>
                <div class="clawwp-card-value"><?php echo esc_html( count( $pairings ) ); ?></div>
            </div>

            <div class="clawwp-card">
                <div class="clawwp-card-label"><?php esc_html_e( 'This Month', 'clawwp' ); ?></div>
                <div class="clawwp-card-value clawwp-card-value--accent">$<?php echo esc_html( number_format( $usage['total_cost'], 2 ) ); ?></div>
                <div class="clawwp-card-detail">
                    <?php
                    // translators: %1$s: number of requests, %2$s: number of tokens.
                    printf(
                        esc_html__( '%1$s requests &middot; %2$s tokens', 'clawwp' ),
                        esc_html( number_format( $usage['request_count'] ) ),
                        esc_html( number_format( $usage['total_tokens_in'] + $usage['total_tokens_out'] ) )
                    ); ?>
                </div>
            </div>
        </div>

        <!-- Recent Conversations -->
        <div class="clawwp-section">
            <h2 class="clawwp-section-title"><?php esc_html_e( 'Recent Conversations', 'clawwp' ); ?></h2>

            <?php if ( ! empty( $recent ) ) : ?>
            <div class="clawwp-table-wrap">
                <div class="clawwp-table-scroll">
                    <table class="clawwp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Channel', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'First Message', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Messages', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Last Active', 'clawwp' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent as $convo ) : ?>
                            <tr>
                                <td><span class="clawwp-badge clawwp-badge--channel"><?php echo esc_html( ucfirst( $convo['channel'] ) ); ?></span></td>
                                <td><?php echo esc_html( wp_trim_words( $convo['first_message'] ?? "\xe2\x80\x94", 12 ) ); ?></td>
                                <td><?php echo esc_html( $convo['message_count'] ); ?></td>
                                <td class="clawwp-text-muted"><?php echo esc_html( human_time_diff( strtotime( $convo['updated_at'] ) ) ); ?> ago</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <p style="margin-top: 12px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=clawwp-conversations' ) ); ?>"><?php esc_html_e( 'View all conversations', 'clawwp' ); ?> &rarr;</a></p>
            <?php else : ?>
            <div class="clawwp-empty">
                <div class="clawwp-empty-icon">&#128172;</div>
                <h3><?php esc_html_e( 'No conversations yet', 'clawwp' ); ?></h3>
                <p><?php esc_html_e( 'Start chatting with the sidebar on the right to begin!', 'clawwp' ); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Start Guide -->
        <?php if ( ! $has_api_key ) : ?>
        <div class="clawwp-info-box">
            <h3><?php esc_html_e( 'Quick Start', 'clawwp' ); ?></h3>
            <ol>
                <?php // translators: %s: link to Anthropic Console. ?>
                <li><?php printf( esc_html__( 'Get an API key from %s', 'clawwp' ), '<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">Anthropic Console</a>' ); ?></li>
                <?php // translators: %s: link to Settings page. ?>
                <li><?php printf( esc_html__( 'Enter it in %s', 'clawwp' ), '<a href="' . esc_url( admin_url( 'admin.php?page=clawwp-settings' ) ) . '">' . esc_html__( 'Settings', 'clawwp' ) . '</a>' ); ?></li>
                <li><?php esc_html_e( 'Open the chat sidebar on the right and say hello!', 'clawwp' ); ?></li>
                <li><?php esc_html_e( '(Optional) Connect Telegram to manage your site on the go', 'clawwp' ); ?></li>
            </ol>
        </div>
        <?php endif; ?>

    </div>
</div>

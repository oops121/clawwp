<?php
/**
 * Sidebar chat widget template.
 *
 * Renders a collapsible chat panel docked to the right side
 * of every wp-admin page. Communicates with ClawWP's REST API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="clawwp-sidebar" class="clawwp-sidebar clawwp-sidebar--collapsed">
    <!-- Toggle Button -->
    <button id="clawwp-sidebar-toggle" class="clawwp-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle ClawWP Chat', 'clawwp' ); ?>">
        <span class="clawwp-sidebar-toggle-icon">&#128172;</span>
        <span class="clawwp-sidebar-toggle-label"><?php esc_html_e( 'ClawWP', 'clawwp' ); ?></span>
    </button>

    <!-- Chat Panel -->
    <div class="clawwp-sidebar-panel">
        <div class="clawwp-sidebar-header">
            <h3><?php esc_html_e( 'ClawWP', 'clawwp' ); ?></h3>
            <div class="clawwp-sidebar-actions">
                <button id="clawwp-new-chat" class="clawwp-btn-icon" title="<?php esc_attr_e( 'New conversation', 'clawwp' ); ?>">&#43;</button>
                <button id="clawwp-sidebar-close" class="clawwp-btn-icon" title="<?php esc_attr_e( 'Close', 'clawwp' ); ?>">&#10005;</button>
            </div>
        </div>

        <div id="clawwp-messages" class="clawwp-sidebar-messages">
            <div class="clawwp-message clawwp-message--assistant">
                <div class="clawwp-message-content">
                    <?php printf(
                        esc_html__( 'Hi! I\'m ClawWP, your AI agent for %s. How can I help?', 'clawwp' ),
                        '<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
                    ); ?>
                </div>
            </div>
        </div>

        <div id="clawwp-typing" class="clawwp-typing" style="display: none;">
            <span></span><span></span><span></span>
        </div>

        <form id="clawwp-chat-form" class="clawwp-sidebar-input">
            <textarea id="clawwp-input"
                      rows="1"
                      placeholder="<?php esc_attr_e( 'Ask ClawWP anything about your site...', 'clawwp' ); ?>"
                      autocomplete="off"></textarea>
            <button type="submit" id="clawwp-send" class="clawwp-btn-send" disabled>&#10148;</button>
        </form>

        <div class="clawwp-sidebar-footer">
            <span class="clawwp-token-count" id="clawwp-token-count"></span>
            <a href="https://hifriendbot.com/clawwp" target="_blank" rel="noopener" class="clawwp-branding">by HiFriendbot</a>
        </div>
    </div>
</div>

<?php
/**
 * Audit log page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$page_num = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$per_page = 50;
$offset   = ( $page_num - 1 ) * $per_page;

$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}clawwp_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$logs  = $wpdb->get_results( $wpdb->prepare(
    "SELECT a.*, u.display_name
     FROM {$wpdb->prefix}clawwp_audit_log a
     LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
     ORDER BY a.created_at DESC
     LIMIT %d OFFSET %d",
    $per_page,
    $offset
), ARRAY_A );

$total_pages = ceil( $total / $per_page );
?>
<div class="clawwp-wrap">
    <div class="clawwp-page-header">
        <div class="clawwp-page-header-top">
            <img src="<?php echo esc_url( CLAWWP_PLUGIN_URL . 'assets/images/icon.svg' ); ?>" alt="ClawWP" class="clawwp-page-header-logo" />
            <span class="clawwp-page-header-brand">ClawWP</span>
        </div>
        <h1><?php esc_html_e( 'Audit Log', 'clawwp' ); ?></h1>
        <p><?php esc_html_e( 'Every action taken by ClawWP is logged here for security and accountability', 'clawwp' ); ?></p>
    </div>

    <div class="clawwp-page-content">

        <?php if ( ! empty( $logs ) ) : ?>
        <div class="clawwp-table-wrap">
            <div class="clawwp-table-scroll">
                <table class="clawwp-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Time', 'clawwp' ); ?></th>
                            <th><?php esc_html_e( 'User', 'clawwp' ); ?></th>
                            <th><?php esc_html_e( 'Channel', 'clawwp' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'clawwp' ); ?></th>
                            <th><?php esc_html_e( 'Details', 'clawwp' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td class="clawwp-text-muted" title="<?php echo esc_attr( $log['created_at'] ); ?>" style="white-space: nowrap;">
                                <?php echo esc_html( human_time_diff( strtotime( $log['created_at'] ) ) ); ?> ago
                            </td>
                            <td><?php echo esc_html( $log['display_name'] ?? '#' . $log['user_id'] ); ?></td>
                            <td><span class="clawwp-badge clawwp-badge--channel"><?php echo esc_html( ucfirst( $log['channel'] ) ); ?></span></td>
                            <td><code><?php echo esc_html( $log['action'] ); ?></code></td>
                            <td>
                                <?php
                                $details = json_decode( $log['details'], true );
                                if ( $details ) {
                                    $summary = array();
                                    foreach ( $details as $k => $v ) {
                                        if ( is_array( $v ) ) {
                                            $summary[] = $k . ': [' . implode( ', ', $v ) . ']';
                                        } else {
                                            $summary[] = $k . ': ' . $v;
                                        }
                                    }
                                    echo '<span class="clawwp-text-muted" style="font-size: 12px;">' . esc_html( implode( ' | ', $summary ) ) . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ( $total_pages > 1 ) : ?>
        <div class="clawwp-pagination">
            <?php
            echo wp_kses_post( paginate_links( array(
                'base'      => admin_url( 'admin.php?page=clawwp-audit&paged=%#%' ),
                'format'    => '',
                'current'   => $page_num,
                'total'     => $total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ) ) );
            ?>
        </div>
        <?php endif; ?>

        <?php else : ?>
        <div class="clawwp-empty">
            <div class="clawwp-empty-icon">&#128274;</div>
            <h3><?php esc_html_e( 'No audit events yet', 'clawwp' ); ?></h3>
            <p><?php esc_html_e( 'Actions will be logged here as ClawWP processes requests.', 'clawwp' ); ?></p>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
/**
 * Usage & Costs page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tracker  = new ClawWP_Cost_Tracker();
$user_id  = get_current_user_id();
$period   = sanitize_text_field( wp_unslash( $_GET['period'] ?? 'month' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$period   = in_array( $period, array( 'today', 'week', 'month', 'all' ), true ) ? $period : 'month';

$summary  = $tracker->get_usage_summary( $user_id, $period );
$daily    = $tracker->get_daily_breakdown( $user_id );
$by_model = $tracker->get_model_breakdown( $user_id, $period );
$budget   = (float) ClawWP::get_option( 'monthly_budget', 0 );

$periods = array(
    'today' => __( 'Today', 'clawwp' ),
    'week'  => __( 'This Week', 'clawwp' ),
    'month' => __( 'This Month', 'clawwp' ),
    'all'   => __( 'All Time', 'clawwp' ),
);
?>
<div class="clawwp-wrap">
    <div class="clawwp-page-header">
        <div class="clawwp-page-header-top">
            <img src="<?php echo esc_url( CLAWWP_PLUGIN_URL . 'assets/images/icon.svg' ); ?>" alt="ClawWP" class="clawwp-page-header-logo" />
            <span class="clawwp-page-header-brand">ClawWP</span>
        </div>
        <h1><?php esc_html_e( 'Usage & Costs', 'clawwp' ); ?></h1>
        <p><?php esc_html_e( 'Track your AI token usage and estimated costs', 'clawwp' ); ?></p>
    </div>

    <div class="clawwp-page-content">

        <!-- Period Tabs -->
        <div class="clawwp-tabs">
            <?php foreach ( $periods as $key => $label ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=clawwp-costs&period=' . $key ) ); ?>"
                   class="clawwp-tab <?php echo esc_attr( $period === $key ? 'clawwp-tab--active' : '' ); ?>">
                    <?php echo esc_html( $label ); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Summary Cards -->
        <div class="clawwp-cards">
            <div class="clawwp-card">
                <div class="clawwp-card-label"><?php esc_html_e( 'Estimated Cost', 'clawwp' ); ?></div>
                <div class="clawwp-card-value clawwp-card-value--accent">$<?php echo esc_html( number_format( $summary['total_cost'], 2 ) ); ?></div>
                <?php if ( $budget > 0 ) : ?>
                    <?php
                    $pct = min( 100, ( $summary['total_cost'] / $budget ) * 100 );
                    $bar_class = $pct > 90 ? 'danger' : ( $pct > 70 ? 'warn' : 'ok' );
                    ?>
                    <div class="clawwp-card-detail">
                        <?php
                        // translators: %1$.0f: percentage used, %2$.2f: budget amount.
                        printf( esc_html__( '%1$.0f%% of $%2$.2f budget', 'clawwp' ), esc_html( $pct ), esc_html( $budget ) );
                        ?>
                    </div>
                    <div class="clawwp-progress">
                        <div class="clawwp-progress-bar clawwp-progress-bar--<?php echo esc_attr( $bar_class ); ?>" style="width: <?php echo esc_attr( $pct ); ?>%;"></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="clawwp-card">
                <div class="clawwp-card-label"><?php esc_html_e( 'Requests', 'clawwp' ); ?></div>
                <div class="clawwp-card-value"><?php echo esc_html( number_format( $summary['request_count'] ) ); ?></div>
            </div>

            <div class="clawwp-card">
                <div class="clawwp-card-label"><?php esc_html_e( 'Input Tokens', 'clawwp' ); ?></div>
                <div class="clawwp-card-value"><?php echo esc_html( number_format( $summary['total_tokens_in'] ) ); ?></div>
            </div>

            <div class="clawwp-card">
                <div class="clawwp-card-label"><?php esc_html_e( 'Output Tokens', 'clawwp' ); ?></div>
                <div class="clawwp-card-value"><?php echo esc_html( number_format( $summary['total_tokens_out'] ) ); ?></div>
            </div>
        </div>

        <!-- By Model Breakdown -->
        <?php if ( ! empty( $by_model ) ) : ?>
        <div class="clawwp-section">
            <h2 class="clawwp-section-title"><?php esc_html_e( 'Usage by Model', 'clawwp' ); ?></h2>
            <div class="clawwp-table-wrap">
                <div class="clawwp-table-scroll">
                    <table class="clawwp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Model', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Requests', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Input Tokens', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Output Tokens', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Estimated Cost', 'clawwp' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $by_model as $row ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $row['model'] ); ?></code></td>
                                <td><?php echo esc_html( number_format( $row['requests'] ) ); ?></td>
                                <td><?php echo esc_html( number_format( $row['tokens_in'] ) ); ?></td>
                                <td><?php echo esc_html( number_format( $row['tokens_out'] ) ); ?></td>
                                <td>$<?php echo esc_html( number_format( $row['cost'], 4 ) ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Daily Breakdown -->
        <?php if ( ! empty( $daily ) ) : ?>
        <div class="clawwp-section">
            <h2 class="clawwp-section-title"><?php esc_html_e( 'Daily Usage (Last 30 Days)', 'clawwp' ); ?></h2>
            <div class="clawwp-table-wrap">
                <div class="clawwp-table-scroll">
                    <table class="clawwp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Date', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Requests', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Tokens', 'clawwp' ); ?></th>
                                <th><?php esc_html_e( 'Cost', 'clawwp' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( array_reverse( $daily ) as $day ) : ?>
                            <tr>
                                <td><?php echo esc_html( $day['date'] ); ?></td>
                                <td><?php echo esc_html( number_format( $day['requests'] ) ); ?></td>
                                <td><?php echo esc_html( number_format( $day['tokens_in'] + $day['tokens_out'] ) ); ?></td>
                                <td>$<?php echo esc_html( number_format( $day['cost'], 4 ) ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( empty( $by_model ) && empty( $daily ) ) : ?>
        <div class="clawwp-empty">
            <div class="clawwp-empty-icon">&#128200;</div>
            <h3><?php esc_html_e( 'No usage data yet', 'clawwp' ); ?></h3>
            <p><?php esc_html_e( 'Start chatting with ClawWP to see your usage and costs here.', 'clawwp' ); ?></p>
        </div>
        <?php endif; ?>

    </div>
</div>

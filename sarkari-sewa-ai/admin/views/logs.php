<?php
/**
 * Admin Logs View.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$severity_filter = isset( $_GET['severity'] ) ? sanitize_key( $_GET['severity'] ) : '';
$logs = Sarkari_Sewa_AI_Logger::get_logs( 100, $severity_filter );
?>

<div class="wrap sarkari-ai-wrap">
	<div class="sarkari-ai-header">
		<div class="sarkari-ai-header-title">
			<span class="sarkari-ai-badge">LOGS</span>
			<h1>Activity & Error Logs</h1>
		</div>
		<p class="sarkari-ai-header-desc">
			Review queries, API provider responses, vector synchronization events, and rate-limit triggers.
		</p>
	</div>

	<div class="sarkari-ai-panel">
		<div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
			<div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-logs' ) ); ?>" class="button <?php echo empty( $severity_filter ) ? 'button-primary' : 'button-secondary'; ?>">All</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-logs&severity=error' ) ); ?>" class="button <?php echo $severity_filter === 'error' ? 'button-primary' : 'button-secondary'; ?>">Errors</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-logs&severity=warning' ) ); ?>" class="button <?php echo $severity_filter === 'warning' ? 'button-primary' : 'button-secondary'; ?>">Warnings</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sarkari-sewa-ai-logs&severity=info' ) ); ?>" class="button <?php echo $severity_filter === 'info' ? 'button-primary' : 'button-secondary'; ?>">Info</a>
			</div>
			<div>
				<button type="button" id="btn-clear-logs" class="button button-link-delete">Clear All Logs</button>
			</div>
		</div>
		<div class="panel-body">
			<?php if ( empty( $logs ) ) : ?>
				<p style="text-align:center;color:#64748b;padding:30px;">No logs recorded yet.</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:160px;">Timestamp</th>
							<th style="width:100px;">Severity</th>
							<th style="width:140px;">Event Type</th>
							<th>Message</th>
							<th style="width:120px;">Client IP</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) :
							$sev_class = 'log-sev-' . esc_attr( $log['severity'] );
						?>
							<tr>
								<td><?php echo esc_html( wp_date( 'd M Y, h:i:s A', strtotime( $log['created_at'] ) ) ); ?></td>
								<td><span class="log-badge <?php echo $sev_class; ?>"><?php echo esc_html( strtoupper( $log['severity'] ) ); ?></span></td>
								<td><code><?php echo esc_html( $log['event_type'] ); ?></code></td>
								<td><?php echo esc_html( $log['message'] ); ?></td>
								<td><code><?php echo esc_html( $log['ip_address'] ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>

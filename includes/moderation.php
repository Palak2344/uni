<?php
/**
 * Background moderation rules:
 *   - Posts expire after N days (daily WP-Cron job) so stale rooms and filled jobs disappear.
 *   - Approving a member's post marks them "trusted" so later posts skip the queue.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'unihive_daily', 'unihive_expire_posts' );

function unihive_expire_posts() {
	$expired = get_posts(
		array(
			'post_type'      => 'unihive_post',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 200,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- once a day, bounded.
				array(
					'key'     => '_unihive_expires',
					'value'   => time(),
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	foreach ( $expired as $post_id ) {
		update_post_meta( $post_id, '_unihive_expired', 1 );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
	}
	return count( $expired );
}

add_action(
	'transition_post_status',
	function ( $new_status, $old_status, $post ) {
		if ( 'unihive_post' !== $post->post_type || 'publish' !== $new_status ) {
			return;
		}
		if ( 'pending' === $old_status ) {
			update_user_meta( $post->post_author, 'unihive_trusted', 1 );
		}
		// Posts published from wp-admin get the default lifespan too.
		$expires = (int) get_post_meta( $post->ID, '_unihive_expires', true );
		if ( ! $expires || $expires < time() ) {
			update_post_meta( $post->ID, '_unihive_expires', time() + (int) unihive_setting( 'expiry_days' ) * DAY_IN_SECONDS );
			delete_post_meta( $post->ID, '_unihive_expired' );
		}
	},
	10,
	3
);

/**
 * Clears reports once a moderator has reviewed a post and decided it can stay.
 */
function unihive_clear_reports( $post_id ) {
	delete_post_meta( $post_id, '_unihive_reports' );
	delete_post_meta( $post_id, '_unihive_report_count' );
}

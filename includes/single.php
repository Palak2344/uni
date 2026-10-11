<?php
/**
 * Single UniHive post (/hive/<slug>/): works with any theme by appending details,
 * contact box and actions to the post content. Non-members never reach this
 * (access.php redirects them to the board).
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'the_content', 'unihive_single_content', 20 );

// Themes print the author's full name (and an author-archive link) on single posts;
// UniHive shows "Priya S." and links back to the board instead.
add_filter(
	'get_the_author_display_name',
	function ( $name, $user_id ) {
		return ( ! is_admin() && is_singular( 'unihive_post' ) ) ? unihive_author_label( $user_id ) : $name;
	},
	10,
	2
);
add_filter(
	'author_link',
	function ( $link ) {
		return ( ! is_admin() && is_singular( 'unihive_post' ) ) ? unihive_board_url() : $link;
	}
);

function unihive_single_content( $content ) {
	if ( ! is_singular( 'unihive_post' ) || get_the_ID() !== get_queried_object_id() || doing_filter( 'get_the_excerpt' ) ) {
		return $content;
	}
	if ( ! unihive_user_can_access() ) { // Belt and braces; template_redirect already handled this.
		return '';
	}

	$post                = get_post();
	list( $slug, $name ) = unihive_post_category( $post->ID );
	$location            = get_post_meta( $post->ID, '_unihive_location', true );
	$method              = get_post_meta( $post->ID, '_unihive_contact_method', true );
	$value               = get_post_meta( $post->ID, '_unihive_contact_value', true );
	$methods             = unihive_contact_methods();
	$is_owner            = (int) $post->post_author === get_current_user_id();
	$reported            = 'reported' === unihive_query_arg( 'hive_msg' );

	ob_start();
	?>
	<div class="unihive uh-single">
		<?php if ( $reported ) : ?>
			<p class="uh-notice uh-notice--info" role="status"><?php esc_html_e( 'Thanks for flagging this. A moderator will review it.', 'unihive' ); ?></p>
		<?php endif; ?>
		<div class="uh-card__meta">
			<?php if ( $name ) : ?>
				<a class="uh-chip uh-chip--<?php echo esc_attr( $slug ); ?>" href="<?php echo esc_url( unihive_board_url( array( 'hive_cat' => $slug ) ) ); ?>"><?php echo esc_html( $name ); ?></a>
			<?php endif; ?>
			<?php if ( $location ) : ?>
				<span class="uh-card__loc"><?php echo esc_html( $location ); ?></span>
			<?php endif; ?>
			<span>
				<?php
				/* translators: 1: member name, 2: time ago */
				printf( esc_html__( 'Posted by %1$s · %2$s ago', 'unihive' ), esc_html( unihive_author_label( $post->post_author ) ), esc_html( human_time_diff( get_post_time( 'U', true, $post ), time() ) ) );
				?>
			</span>
			<span><?php echo esc_html( unihive_expiry_label( $post->ID ) ); ?></span>
		</div>
	</div>
	<?php
	$header = ob_get_clean();

	ob_start();
	?>
	<div class="unihive uh-single">
		<?php if ( $method && $value && isset( $methods[ $method ] ) ) : ?>
			<aside class="uh-contact">
				<h3><?php esc_html_e( 'Contact', 'unihive' ); ?></h3>
				<p>
					<span class="uh-contact__method"><?php echo esc_html( strtok( $methods[ $method ], ' ' ) ); ?></span>
					<a href="<?php echo esc_url( unihive_contact_href( $method, $value ), array( 'mailto', 'tel', 'https', 'http' ) ); ?>" rel="nofollow noopener" target="_blank"><?php echo esc_html( $value ); ?></a>
				</p>
				<p class="uh-help"><?php esc_html_e( 'Stay safe: view a room (or a video walkthrough) before paying anything, never pay “visa” or “job” fees to strangers, and report anything that feels off. Unigator does not verify posts.', 'unihive' ); ?></p>
			</aside>
		<?php endif; ?>

		<div class="uh-single__actions">
			<a class="uh-btn" href="<?php echo esc_url( unihive_board_url( $slug ? array( 'hive_cat' => $slug ) : array() ) ); ?>">← <?php esc_html_e( 'Back to UniHive', 'unihive' ); ?></a>
			<?php if ( $is_owner ) : ?>
				<?php unihive_render_owner_button( $post->ID, 'delete', __( 'Delete my post', 'unihive' ) ); ?>
			<?php elseif ( ! $reported ) : ?>
				<details class="uh-report">
					<summary><?php esc_html_e( 'Report this post', 'unihive' ); ?></summary>
					<form method="post">
						<input type="hidden" name="unihive_action" value="report">
						<input type="hidden" name="post_id" value="<?php echo (int) $post->ID; ?>">
						<?php wp_nonce_field( 'unihive_report_' . $post->ID, '_unihive_nonce' ); ?>
						<?php foreach ( unihive_report_reasons() as $key => $label ) : ?>
							<label class="uh-radio uh-radio--block"><input type="radio" name="reason" value="<?php echo esc_attr( $key ); ?>" <?php checked( 'scam', $key ); ?>> <span><?php echo esc_html( $label ); ?></span></label>
						<?php endforeach; ?>
						<button type="submit" class="uh-btn uh-btn--danger"><?php esc_html_e( 'Send report', 'unihive' ); ?></button>
					</form>
				</details>
			<?php endif; ?>
		</div>
	</div>
	<?php
	$footer = ob_get_clean();

	return $header . '<div class="uh-body">' . $content . '</div>' . $footer;
}

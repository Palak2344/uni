<?php
/**
 * wp-admin moderation: list-table columns and filters, "Reported" view, one-click Approve,
 * pending-count bubble, a details meta box, and the settings page.
 */

defined( 'ABSPATH' ) || exit;

/* ---------- List table ---------- */

add_filter(
	'manage_unihive_post_posts_columns',
	function ( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['uh_status']  = __( 'State', 'unihive' );
				$out['uh_contact'] = __( 'Contact', 'unihive' );
				$out['uh_reports'] = __( 'Reports', 'unihive' );
				$out['uh_expires'] = __( 'Expires', 'unihive' );
			}
		}
		return $out;
	}
);

add_action(
	'manage_unihive_post_posts_custom_column',
	function ( $column, $post_id ) {
		switch ( $column ) {
			case 'uh_status':
				list( $state, $label ) = unihive_status_label( get_post( $post_id ) );
				printf( '<span class="uh-admin-status uh-admin-status--%s">%s</span>', esc_attr( $state ), esc_html( $label ) );
				break;
			case 'uh_contact':
				$methods = unihive_contact_methods();
				$method  = get_post_meta( $post_id, '_unihive_contact_method', true );
				if ( isset( $methods[ $method ] ) ) {
					printf( '%s<br><code>%s</code>', esc_html( strtok( $methods[ $method ], ' ' ) ), esc_html( get_post_meta( $post_id, '_unihive_contact_value', true ) ) );
				}
				break;
			case 'uh_reports':
				$count = (int) get_post_meta( $post_id, '_unihive_report_count', true );
				echo $count ? '<strong class="uh-admin-reports">' . (int) $count . '</strong>' : '—';
				break;
			case 'uh_expires':
				$expires = (int) get_post_meta( $post_id, '_unihive_expires', true );
				echo $expires ? esc_html( wp_date( get_option( 'date_format' ), $expires ) ) : '—';
				break;
		}
	},
	10,
	2
);

// Category filter dropdown above the list.
add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( 'unihive_post' !== $post_type ) {
			return;
		}
		wp_dropdown_categories(
			array(
				'taxonomy'        => 'unihive_category',
				'name'            => 'unihive_category',
				'value_field'     => 'slug',
				'show_option_all' => __( 'All categories', 'unihive' ),
				'selected'        => isset( $_GET['unihive_category'] ) ? sanitize_key( $_GET['unihive_category'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification
				'hide_empty'      => false,
				'orderby'         => 'term_id',
			)
		);
	}
);

// "Reported (n)" view next to All / Published / Pending.
add_filter(
	'views_edit-unihive_post',
	function ( $views ) {
		$reported = get_posts(
			array(
				'post_type'      => 'unihive_post',
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_unihive_report_count',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$current            = ! empty( $_GET['unihive_reported'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$views['uh_report'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( admin_url( 'edit.php?post_type=unihive_post&post_status=all&unihive_reported=1' ) ),
			$current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Reported', 'unihive' ),
			count( $reported )
		);
		return $views;
	}
);

add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'unihive_post' !== $query->get( 'post_type' ) || empty( $_GET['unihive_reported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$query->set(
			'meta_query',
			array(
				array(
					'key'     => '_unihive_report_count',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			)
		);
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'meta_key', '_unihive_report_count' );
		$query->set( 'order', 'DESC' );
	}
);

// One-click "Approve" for pending posts (also clears reports).
add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( 'unihive_post' === $post->post_type && 'pending' === $post->post_status && current_user_can( 'publish_unihive_posts' ) ) {
			$url     = wp_nonce_url( admin_url( 'admin-post.php?action=unihive_approve&post=' . $post->ID ), 'unihive_approve_' . $post->ID );
			$actions = array( 'uh_approve' => '<a href="' . esc_url( $url ) . '"><strong>' . esc_html__( 'Approve', 'unihive' ) . '</strong></a>' ) + $actions;
		}
		return $actions;
	},
	10,
	2
);

add_action(
	'admin_post_unihive_approve',
	function () {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'unihive_approve_' . $post_id );
		if ( ! current_user_can( 'publish_unihive_posts' ) || 'unihive_post' !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to approve UniHive posts.', 'unihive' ) );
		}
		unihive_clear_reports( $post_id );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);
		wp_safe_redirect( admin_url( 'edit.php?post_type=unihive_post&unihive_approved=1' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! empty( $_GET['unihive_approved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Post approved and published. The member is now trusted: their next posts go live without review.', 'unihive' ) . '</p></div>';
		}
		if ( ! empty( $_GET['unihive_demo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Demo content created.', 'unihive' ) . '</p></div>';
		}
	}
);

// Pending count bubble on the UniHive menu, like Comments.
add_action(
	'admin_menu',
	function () {
		global $menu;
		$pending = (int) wp_count_posts( 'unihive_post' )->pending;
		if ( ! $pending || ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $i => $item ) {
			if ( 'edit.php?post_type=unihive_post' === $item[2] ) {
				$menu[ $i ][0] .= ' <span class="awaiting-mod count-' . $pending . '"><span class="pending-count">' . $pending . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	},
	99
);

add_action(
	'admin_head',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'unihive_post' !== $screen->post_type ) {
			return;
		}
		echo '<style>
			.fixed .column-uh_status{width:9em}.fixed .column-uh_contact{width:13em}.fixed .column-uh_reports{width:4.5em}
			.fixed .column-uh_expires{width:7.5em}.fixed .column-taxonomy-unihive_category{width:9em}.fixed .column-date{width:10%}
			.column-uh_contact code{font-size:11px;word-break:break-all;background:none;padding:0}
			.uh-admin-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;background:#eef1f1}
			.uh-admin-status--live{background:#e2f3e8;color:#135e2e}.uh-admin-status--pending{background:#fff4d6;color:#7a5200}
			.uh-admin-status--hidden{background:#fde2e1;color:#8a1f17}.uh-admin-status--expired{background:#eceeef;color:#50575e}
			.uh-admin-reports{color:#b32d2e}
			#uh-details .uh-row{margin:0 0 12px}#uh-details label{display:block;font-weight:600;margin-bottom:4px}
			#uh-details input[type=text],#uh-details select{width:100%;max-width:420px}
			#uh-details .uh-reports li{margin:0 0 4px}
		</style>';
	}
);

/* ---------- Edit screen meta box ---------- */

add_action(
	'add_meta_boxes_unihive_post',
	function () {
		add_meta_box( 'uh-details', __( 'UniHive details', 'unihive' ), 'unihive_render_meta_box', 'unihive_post', 'normal', 'high' );
	}
);

function unihive_render_meta_box( $post ) {
	wp_nonce_field( 'unihive_meta_' . $post->ID, '_unihive_meta_nonce' );
	$method  = get_post_meta( $post->ID, '_unihive_contact_method', true );
	$expires = (int) get_post_meta( $post->ID, '_unihive_expires', true );
	$reports = get_post_meta( $post->ID, '_unihive_reports', true );
	$reasons = unihive_report_reasons();
	?>
	<p class="uh-row">
		<label for="uh-m-method"><?php esc_html_e( 'Contact method', 'unihive' ); ?></label>
		<select id="uh-m-method" name="uh_contact_method">
			<?php foreach ( unihive_contact_methods() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $method, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="uh-row">
		<label for="uh-m-value"><?php esc_html_e( 'Contact value', 'unihive' ); ?></label>
		<input id="uh-m-value" type="text" name="uh_contact_value" value="<?php echo esc_attr( get_post_meta( $post->ID, '_unihive_contact_value', true ) ); ?>">
	</p>
	<p class="uh-row">
		<label for="uh-m-location"><?php esc_html_e( 'City / country', 'unihive' ); ?></label>
		<input id="uh-m-location" type="text" name="uh_location" value="<?php echo esc_attr( get_post_meta( $post->ID, '_unihive_location', true ) ); ?>">
	</p>
	<p class="uh-row">
		<label for="uh-m-expires"><?php esc_html_e( 'Expires on', 'unihive' ); ?></label>
		<input id="uh-m-expires" type="date" name="uh_expires" value="<?php echo $expires ? esc_attr( wp_date( 'Y-m-d', $expires ) ) : ''; ?>">
	</p>
	<?php if ( is_array( $reports ) && $reports ) : ?>
		<div class="uh-row">
			<label><?php esc_html_e( 'Member reports', 'unihive' ); ?></label>
			<ul class="uh-reports">
				<?php foreach ( $reports as $user_id => $report ) : ?>
					<li>
						<?php
						printf(
							'%1$s — %2$s <span class="description">(%3$s)</span>',
							esc_html( unihive_author_label( $user_id ) ),
							esc_html( isset( $reasons[ $report['reason'] ] ) ? $reasons[ $report['reason'] ] : $report['reason'] ),
							esc_html( wp_date( get_option( 'date_format' ), $report['time'] ) )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
			<label><input type="checkbox" name="uh_clear_reports" value="1"> <?php esc_html_e( 'Clear reports (post reviewed and OK)', 'unihive' ); ?></label>
		</div>
	<?php endif; ?>
	<?php
}

add_action(
	'save_post_unihive_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['_unihive_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_unihive_meta_nonce'] ) ), 'unihive_meta_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$method = isset( $_POST['uh_contact_method'] ) ? sanitize_key( $_POST['uh_contact_method'] ) : '';
		if ( isset( unihive_contact_methods()[ $method ] ) ) {
			update_post_meta( $post_id, '_unihive_contact_method', $method );
		}
		$value = isset( $_POST['uh_contact_value'] ) ? sanitize_text_field( wp_unslash( $_POST['uh_contact_value'] ) ) : '';
		$clean = unihive_clean_contact( $method, $value );
		update_post_meta( $post_id, '_unihive_contact_value', is_wp_error( $clean ) ? $value : $clean );
		update_post_meta( $post_id, '_unihive_location', isset( $_POST['uh_location'] ) ? sanitize_text_field( wp_unslash( $_POST['uh_location'] ) ) : '' );

		$date = isset( $_POST['uh_expires'] ) ? sanitize_text_field( wp_unslash( $_POST['uh_expires'] ) ) : '';
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$ts = ( new DateTimeImmutable( $date . ' 23:59:59', wp_timezone() ) )->getTimestamp();
			update_post_meta( $post_id, '_unihive_expires', $ts );
			if ( $ts > time() ) {
				delete_post_meta( $post_id, '_unihive_expired' );
			}
		}
		if ( ! empty( $_POST['uh_clear_reports'] ) ) {
			unihive_clear_reports( $post_id );
		}
	}
);

/* ---------- Settings ---------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=unihive_post', __( 'UniHive settings', 'unihive' ), __( 'Settings', 'unihive' ), 'manage_options', 'unihive-settings', 'unihive_render_settings' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'unihive',
			'unihive_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'unihive_sanitize_settings',
			)
		);
	}
);

function unihive_sanitize_settings( $input ) {
	$defaults = unihive_default_settings();
	$input    = is_array( $input ) ? $input : array();
	return array(
		'board_page'       => absint( isset( $input['board_page'] ) ? $input['board_page'] : 0 ),
		'membership_url'   => esc_url_raw( isset( $input['membership_url'] ) ? $input['membership_url'] : '' ),
		'pmpro_levels'     => sanitize_text_field( isset( $input['pmpro_levels'] ) ? $input['pmpro_levels'] : $defaults['pmpro_levels'] ),
		'approval'         => in_array( isset( $input['approval'] ) ? $input['approval'] : '', array( 'all', 'first', 'none' ), true ) ? $input['approval'] : 'first',
		'expiry_days'      => max( 1, min( 365, absint( isset( $input['expiry_days'] ) ? $input['expiry_days'] : 30 ) ) ),
		'max_per_day'      => min( 50, absint( isset( $input['max_per_day'] ) ? $input['max_per_day'] : 3 ) ),
		'report_threshold' => max( 1, min( 20, absint( isset( $input['report_threshold'] ) ? $input['report_threshold'] : 3 ) ) ),
		'demo_switcher'    => empty( $input['demo_switcher'] ) ? 0 : 1,
	);
}

function unihive_render_settings() {
	$s = wp_parse_args( (array) get_option( 'unihive_settings', array() ), unihive_default_settings() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'UniHive settings', 'unihive' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'unihive' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="uh-board"><?php esc_html_e( 'Board page', 'unihive' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'unihive_settings[board_page]',
								'id'                => 'uh-board',
								'selected'          => (int) $s['board_page'],
								'show_option_none'  => __( '— Select —', 'unihive' ),
								'option_none_value' => 0,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'The page containing the [unihive] shortcode.', 'unihive' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="uh-join"><?php esc_html_e( 'Membership / upgrade URL', 'unihive' ); ?></label></th>
					<td><input id="uh-join" class="regular-text" type="url" name="unihive_settings[membership_url]" value="<?php echo esc_attr( $s['membership_url'] ); ?>" placeholder="<?php echo esc_attr( unihive_membership_url() ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="uh-levels"><?php esc_html_e( 'Paid Memberships Pro levels with access', 'unihive' ); ?></label></th>
					<td>
						<input id="uh-levels" class="regular-text" type="text" name="unihive_settings[pmpro_levels]" value="<?php echo esc_attr( $s['pmpro_levels'] ); ?>">
						<p class="description">
							<?php
							echo function_exists( 'pmpro_hasMembershipLevel' )
								? esc_html__( 'Paid Memberships Pro is active: members of these levels (names or IDs, comma-separated) can use UniHive.', 'unihive' )
								: esc_html__( 'Paid Memberships Pro is not active, so access uses roles: Unigator Pro, Unigator Premium, UniHive Moderator and Administrator.', 'unihive' );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Approval', 'unihive' ); ?></th>
					<td>
						<?php
						$options = array(
							'first' => __( 'Review a member’s first post, then trust them (recommended)', 'unihive' ),
							'all'   => __( 'Review every post before it goes live', 'unihive' ),
							'none'  => __( 'Publish immediately (rely on reports)', 'unihive' ),
						);
						foreach ( $options as $key => $label ) {
							printf( '<label style="display:block;margin-bottom:6px"><input type="radio" name="unihive_settings[approval]" value="%s" %s> %s</label>', esc_attr( $key ), checked( $s['approval'], $key, false ), esc_html( $label ) );
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="uh-exp"><?php esc_html_e( 'Posts expire after', 'unihive' ); ?></label></th>
					<td><input id="uh-exp" type="number" min="1" max="365" name="unihive_settings[expiry_days]" value="<?php echo (int) $s['expiry_days']; ?>" class="small-text"> <?php esc_html_e( 'days (members can renew)', 'unihive' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="uh-max"><?php esc_html_e( 'Posts per member per day', 'unihive' ); ?></label></th>
					<td><input id="uh-max" type="number" min="0" max="50" name="unihive_settings[max_per_day]" value="<?php echo (int) $s['max_per_day']; ?>" class="small-text"> <?php esc_html_e( '(0 = no limit)', 'unihive' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="uh-rep"><?php esc_html_e( 'Hide a post after', 'unihive' ); ?></label></th>
					<td><input id="uh-rep" type="number" min="1" max="20" name="unihive_settings[report_threshold]" value="<?php echo (int) $s['report_threshold']; ?>" class="small-text"> <?php esc_html_e( 'reports from different members', 'unihive' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Demo user switcher', 'unihive' ); ?></th>
					<td>
						<label><input type="checkbox" name="unihive_settings[demo_switcher]" value="1" <?php checked( $s['demo_switcher'] ); ?>> <?php esc_html_e( 'Show the “View as” bar for the demo accounts', 'unihive' ); ?></label>
						<p class="description"><?php esc_html_e( 'Local demos only: it logs you in as demo users without a password. It only works when WP_ENVIRONMENT_TYPE is local or development. Never enable on a live site.', 'unihive' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Demo content', 'unihive' ); ?></h2>
		<p><?php esc_html_e( 'Creates the UniHive and Membership pages, demo members (Free, Pro, Premium, Moderator) and sample posts in all four categories, including a pending and a reported post. Safe to run twice.', 'unihive' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="unihive_seed_demo">
			<?php wp_nonce_field( 'unihive_seed_demo' ); ?>
			<?php submit_button( __( 'Create demo content', 'unihive' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_unihive_seed_demo',
	function () {
		check_admin_referer( 'unihive_seed_demo' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'unihive' ) );
		}
		unihive_seed_demo();
		wp_safe_redirect( admin_url( 'edit.php?post_type=unihive_post&unihive_demo=1' ) );
		exit;
	}
);

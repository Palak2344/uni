<?php
/**
 * Demo content and the local-only "View as" bar used to walk through the prototype.
 *
 * Demo accounts (password in README.md, local demo only):
 *   sam_free (Free) · priya_pro, rahul_pro, quickvisa_help (Pro) · arjun_premium (Premium) · meera_mod (Moderator)
 */

defined( 'ABSPATH' ) || exit;

const UNIHIVE_DEMO_PASSWORD = 'unihive-demo';

function unihive_demo_users() {
	return array(
		'sam_free'       => array( 'Sam', 'Joseph', 'subscriber', 'Free member' ),
		'priya_pro'      => array( 'Priya', 'Sharma', 'unigator_pro', 'Pro member' ),
		'rahul_pro'      => array( 'Rahul', 'Verma', 'unigator_pro', 'Pro member (new, untrusted)' ),
		'arjun_premium'  => array( 'Arjun', 'Nair', 'unigator_premium', 'Premium member' ),
		'meera_mod'      => array( 'Meera', 'Iyer', 'unihive_moderator', 'Moderator' ),
		'quickvisa_help' => array( 'Quick', 'Visa', 'unigator_pro', 'Pro member (reported)' ),
	);
}

function unihive_seed_demo( $enable_switcher = false ) {
	unihive_install();

	// Pages.
	$board = get_page_by_path( 'unihive' );
	$board_id = $board ? $board->ID : wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'UniHive',
			'post_name'    => 'unihive',
			'post_content' => "<!-- wp:shortcode -->\n[unihive]\n<!-- /wp:shortcode -->",
		)
	);
	if ( ! get_page_by_path( 'membership' ) ) {
		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Membership',
				'post_name'    => 'membership',
				'post_content' => "<!-- wp:paragraph -->\n<p>Placeholder for the Paid Memberships Pro levels page (Free · Pro · Premium). On the live site this is <code>[pmpro_levels]</code> with checkout.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead><tr><th>Plan</th><th>UniHive</th></tr></thead><tbody><tr><td>Free</td><td>Teaser only: categories and live post counts</td></tr><tr><td>Pro</td><td>Read all posts, see contact details, create posts</td></tr><tr><td>Premium</td><td>Everything in Pro (+ optional extras, e.g. pinned posts)</td></tr></tbody></table></figure>\n<!-- /wp:table -->",
			)
		);
	}
	$settings = array( 'board_page' => $board_id );
	if ( $enable_switcher ) {
		$settings['demo_switcher'] = 1;
	}
	unihive_update_settings( $settings );

	// Users.
	$ids = array();
	foreach ( unihive_demo_users() as $login => $user ) {
		$existing = get_user_by( 'login', $login );
		$ids[ $login ] = $existing ? $existing->ID : wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => UNIHIVE_DEMO_PASSWORD,
				'user_email'   => $login . '@example.com',
				'first_name'   => $user[0],
				'last_name'    => $user[1],
				'display_name' => $user[0] . ' ' . $user[1],
				'role'         => $user[2],
			)
		);
	}
	foreach ( array( 'priya_pro', 'arjun_premium', 'meera_mod' ) as $login ) {
		update_user_meta( $ids[ $login ], 'unihive_trusted', 1 );
	}

	if ( get_option( 'unihive_demo_seeded' ) ) {
		return $board_id;
	}

	// Posts: [author, category, title, description, location, method, value, days ago, status].
	$posts = array(
		array( 'priya_pro', 'accommodation', 'Room in 3-bed flat near University of Manchester, January move-in', "£620/month including bills, 12 minutes' walk to Oxford Road. Two Indian postgrads already live here and the kitchen is vegetarian. Happy to do a video walkthrough before you decide, and no deposit until you have seen the room and the contract.", 'Manchester, UK', 'whatsapp', '+447700900123', 1, 'publish' ),
		array( 'arjun_premium', 'accommodation', 'Looking for a flatmate in Scarborough, Toronto from September', 'I start at UTSC in September and I am looking for one flatmate to share a 2-bed near Morningside. Budget CAD 900–1,000 each. Non-smoker, quiet, cooks a lot. Message me and we can do a video call first.', 'Toronto, Canada', 'email', 'arjun_premium@example.com', 2, 'publish' ),
		array( 'priya_pro', 'accommodation', 'How I checked my Melbourne rental was not a scam', "Last year a 'landlord' asked me for a bond before any inspection. What I do now: ask for a live video inspection, check the owner on the lease matches, only pay bond through the official state bond authority, never by bank transfer to a personal account. Add your tips below if you have more.", 'Melbourne, Australia', 'email', 'priya_pro@example.com', 6, 'publish' ),
		array( 'arjun_premium', 'work-visa', 'UK Graduate Route: my exact timeline and documents', "Applied the week my results were confirmed. Timeline: university reported completion in 6 days, application online in 40 minutes, identity check via the app, decision in 3 weeks. Documents: passport, BRP/eVisa, CAS number. Happy to answer questions about the health surcharge and switching to Skilled Worker later.", 'London, UK', 'link', 'https://www.linkedin.com/in/example-arjun', 3, 'publish' ),
		array( 'priya_pro', 'work-visa', 'Germany 18-month job-seeker residence permit: documents checklist that worked', 'Got my permit to look for work after graduation at the Ausländerbehörde in Berlin. Bring: degree certificate or confirmation letter, proof of health insurance, proof of funds, rental contract and Anmeldung. Book the appointment early, slots go fast.', 'Berlin, Germany', 'email', 'priya_pro@example.com', 4, 'publish' ),
		array( 'arjun_premium', 'work-visa', 'Australia 485 visa: biometrics delay, anyone else waiting?', 'Applied for the Temporary Graduate visa in August and biometrics were requested only last week. Would love to hear typical waiting times from anyone who applied from Sydney or Melbourne recently.', 'Sydney, Australia', 'email', 'arjun_premium@example.com', 8, 'publish' ),
		array( 'priya_pro', 'jobs', 'Junior Data Analyst role in Dublin, visa sponsorship available', 'My team is hiring a junior data analyst (SQL, Python, dashboards). Critical Skills permit sponsorship is possible for the right candidate. Applications close on 30 October. I can pass on CVs, but I cannot promise an interview.', 'Dublin, Ireland', 'email', 'priya_pro@example.com', 2, 'publish' ),
		array( 'arjun_premium', 'jobs', 'Referral: Graduate Software Engineer roles at a Manchester fintech', 'Happy to refer 2–3 Unigator members for graduate SWE roles starting next summer. Please send a one-page CV and the role link. I will reply to everyone within a week.', 'Manchester, UK', 'link', 'https://forms.example.com/referral', 5, 'publish' ),
		array( 'priya_pro', 'jobs', 'Mechanical graduates: interview questions from three Toronto firms', 'Sharing the technical and behavioural questions I got at three mid-size engineering firms in the GTA, plus what I would prepare differently. Reach out if you want the full notes.', 'Toronto, Canada', 'email', 'priya_pro@example.com', 9, 'publish' ),
		array( 'arjun_premium', 'part-time-jobs', 'Weekend barista shifts at campus café, 12–16 hours a week', 'Our campus café is hiring weekend baristas. Shifts fit within the 20-hours-a-week term-time limit. No experience needed, training provided. Ask for Lena at the counter or email me and I will introduce you.', 'Leeds, UK', 'email', 'arjun_premium@example.com', 1, 'publish' ),
		array( 'priya_pro', 'part-time-jobs', 'Maths and physics tutoring in Sydney, flexible hours', 'A tutoring centre near Central is looking for STEM students to tutor high-schoolers. Evenings and Saturdays, easy to keep within the student-visa limit of 48 hours per fortnight during term.', 'Sydney, Australia', 'phone', '+61491570006', 3, 'publish' ),
		array( 'arjun_premium', 'part-time-jobs', 'Warehouse night shifts in Brampton: honest review before you sign up', 'I worked nights for four months. Pay is decent, but check that the agency pays you directly (not cash), and that total hours respect your study-permit limit. Happy to share which agencies were fine and which to avoid.', 'Brampton, Canada', 'whatsapp', '+14165550142', 7, 'publish' ),
		// Needs moderator approval: new member, and off-topic (demonstrates the queue).
		array( 'rahul_pro', 'accommodation', 'Selling winter jacket and duvet before I move out', 'Barely used winter jacket (M) and a double duvet. £40 for both, collection from Headingley.', 'Leeds, UK', 'whatsapp', '+447700900456', 0, 'pending' ),
		// Reported by three members and auto-hidden (demonstrates reports).
		array( 'quickvisa_help', 'work-visa', 'Guaranteed Canada work permit in 30 days, message our agent', '100% guaranteed approval, no documents needed. Pay a small processing fee today and our agent will handle everything for you.', 'Online', 'whatsapp', '+14165550199', 1, 'pending' ),
	);
	// One expired post so "My posts" shows the Renew action.
	$posts[] = array( 'priya_pro', 'part-time-jobs', 'Christmas temp roles at a Manchester retail store', 'Seasonal temp roles over the winter break, when full-time hours are allowed. Applications are now closed.', 'Manchester, UK', 'email', 'priya_pro@example.com', 40, 'expired' );

	$expiry = (int) unihive_setting( 'expiry_days' ) * DAY_IN_SECONDS;
	foreach ( $posts as $p ) {
		list( $author, $cat, $title, $desc, $location, $method, $value, $days, $status ) = $p;
		$time    = time() - $days * DAY_IN_SECONDS - wp_rand( 0, 6 ) * HOUR_IN_SECONDS;
		$post_id = wp_insert_post(
			array(
				'post_type'     => 'unihive_post',
				'post_title'    => $title,
				'post_content'  => $desc,
				'post_status'   => 'expired' === $status ? 'draft' : $status,
				'post_author'   => $ids[ $author ],
				'post_date'     => wp_date( 'Y-m-d H:i:s', $time ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}
		wp_set_object_terms( $post_id, $cat, 'unihive_category' );
		update_post_meta( $post_id, '_unihive_contact_method', $method );
		update_post_meta( $post_id, '_unihive_contact_value', $value );
		update_post_meta( $post_id, '_unihive_location', $location );
		update_post_meta( $post_id, '_unihive_expires', $time + $expiry );
		if ( 'expired' === $status ) {
			update_post_meta( $post_id, '_unihive_expired', 1 );
		}
		if ( 'quickvisa_help' === $author ) {
			$log = array();
			foreach ( array( 'priya_pro', 'arjun_premium', 'rahul_pro' ) as $i => $reporter ) {
				$log[ $ids[ $reporter ] ] = array(
					'reason' => 'agent',
					'time'   => time() - ( 3 - $i ) * HOUR_IN_SECONDS,
				);
			}
			update_post_meta( $post_id, '_unihive_reports', $log );
			update_post_meta( $post_id, '_unihive_report_count', count( $log ) );
		}
	}

	update_option( 'unihive_demo_seeded', 1 );
	return $board_id;
}

/* ---------- "View as" bar (local demos only) ---------- */

function unihive_demo_switcher_enabled() {
	return unihive_setting( 'demo_switcher' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
}

function unihive_demo_personas() {
	return array(
		'visitor'       => __( 'Visitor', 'unihive' ),
		'sam_free'      => __( 'Free member', 'unihive' ),
		'priya_pro'     => __( 'Pro member', 'unihive' ),
		'rahul_pro'     => __( 'New Pro member', 'unihive' ),
		'arjun_premium' => __( 'Premium member', 'unihive' ),
		'meera_mod'     => __( 'Moderator', 'unihive' ),
		'admin'         => __( 'Admin', 'unihive' ),
	);
}

add_action(
	'init',
	function () {
		if ( empty( $_GET['uh_view_as'] ) || ! unihive_demo_switcher_enabled() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$persona = sanitize_user( wp_unslash( $_GET['uh_view_as'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( unihive_demo_personas()[ $persona ] ) || ! isset( $_GET['_uhnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_uhnonce'] ) ), 'unihive_view_as' ) ) {
			return;
		}
		wp_clear_auth_cookie();
		wp_set_current_user( 0 );
		if ( 'visitor' !== $persona ) {
			$user = get_user_by( 'login', $persona );
			if ( ! $user && 'admin' === $persona ) {
				$admins = get_users(
					array(
						'role'   => 'administrator',
						'number' => 1,
					)
				);
				$user   = $admins ? $admins[0] : null;
			}
			if ( $user ) {
				wp_set_current_user( $user->ID );
				wp_set_auth_cookie( $user->ID, true );
			}
		}
		$target = remove_query_arg( array( 'uh_view_as', '_uhnonce', 'hive_msg', 'hive_gate' ) );
		wp_safe_redirect( 'meera_mod' === $persona || 'admin' === $persona ? admin_url( 'edit.php?post_type=unihive_post' ) : $target );
		exit;
	},
	1
);

function unihive_demo_current_persona() {
	if ( ! is_user_logged_in() ) {
		return 'visitor';
	}
	$login = wp_get_current_user()->user_login;
	if ( isset( unihive_demo_personas()[ $login ] ) ) {
		return $login;
	}
	return current_user_can( 'manage_options' ) ? 'admin' : '';
}

function unihive_render_demo_bar() {
	if ( ! unihive_demo_switcher_enabled() ) {
		return;
	}
	$current = unihive_demo_current_persona();
	$nonce   = wp_create_nonce( 'unihive_view_as' );
	$base    = is_admin() ? unihive_board_url() : remove_query_arg( array( 'hive_msg', 'hive_gate' ) );
	echo '<div class="uh-demo-bar" role="navigation" aria-label="Demo view switcher"><strong>Demo · view as</strong>';
	foreach ( unihive_demo_personas() as $key => $label ) {
		printf(
			'<a href="%s"%s>%s</a>',
			esc_url(
				add_query_arg(
					array(
						'uh_view_as' => $key,
						'_uhnonce'   => $nonce,
					),
					$base
				)
			),
			$key === $current ? ' class="is-current" aria-current="true"' : '',
			esc_html( $label )
		);
	}
	echo '</div>';
}

add_action( 'wp_footer', 'unihive_render_demo_bar' );
add_action( 'admin_footer', 'unihive_render_demo_bar' );

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( unihive_demo_switcher_enabled() ) {
			wp_enqueue_style( 'unihive' );
		}
	},
	20
);

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( unihive_demo_switcher_enabled() ) {
			wp_enqueue_style( 'unihive', UNIHIVE_URL . 'assets/unihive.css', array(), UNIHIVE_VERSION );
		}
	}
);

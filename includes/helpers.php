<?php
/**
 * Settings, fixed vocabularies and small formatting helpers.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The four UniHive categories. Fixed on purpose: UniHive is for these topics only,
 * so members cannot create new ones and admins edit the list here, in code.
 */
function unihive_categories() {
	return array(
		'accommodation'  => array(
			'name' => __( 'Accommodation', 'unihive' ),
			'desc' => __( 'Rooms, flatshares, sublets and first-hand housing tips near campus.', 'unihive' ),
		),
		'work-visa'      => array(
			'name' => __( 'Work Visa', 'unihive' ),
			'desc' => __( 'Post-study work routes, sponsorship and real application timelines.', 'unihive' ),
		),
		'jobs'           => array(
			'name' => __( 'Jobs', 'unihive' ),
			'desc' => __( 'Graduate and full-time roles, referrals and interview experiences.', 'unihive' ),
		),
		'part-time-jobs' => array(
			'name' => __( 'Part-time Jobs', 'unihive' ),
			'desc' => __( 'Term-time and holiday work that fits your student-visa hour limits.', 'unihive' ),
		),
	);
}

function unihive_contact_methods() {
	return array(
		'email'    => __( 'Email', 'unihive' ),
		'whatsapp' => __( 'WhatsApp', 'unihive' ),
		'phone'    => __( 'Phone', 'unihive' ),
		'link'     => __( 'Link (LinkedIn, form or listing)', 'unihive' ),
	);
}

function unihive_report_reasons() {
	return array(
		'scam'    => __( 'Scam or suspicious (e.g. deposit before viewing)', 'unihive' ),
		'agent'   => __( 'Paid agent / visa or admission service', 'unihive' ),
		'spam'    => __( 'Spam, advertising or off-topic', 'unihive' ),
		'abuse'   => __( 'Offensive or discriminatory', 'unihive' ),
		'stale'   => __( 'No longer available', 'unihive' ),
		'other'   => __( 'Something else', 'unihive' ),
	);
}

function unihive_default_settings() {
	return array(
		'board_page'       => 0,
		'membership_url'   => '',
		'pmpro_levels'     => 'Pro,Premium',
		'approval'         => 'first', // all | first | none.
		'expiry_days'      => 30,
		'max_per_day'      => 3,
		'report_threshold' => 3,
		'demo_switcher'    => 0,
	);
}

function unihive_setting( $key ) {
	$settings = wp_parse_args( (array) get_option( 'unihive_settings', array() ), unihive_default_settings() );
	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

function unihive_update_settings( array $changes ) {
	$settings = wp_parse_args( (array) get_option( 'unihive_settings', array() ), unihive_default_settings() );
	update_option( 'unihive_settings', array_merge( $settings, $changes ) );
}

function unihive_board_url( $args = array() ) {
	$page_id = (int) unihive_setting( 'board_page' );
	$url     = $page_id ? get_permalink( $page_id ) : home_url( '/unihive/' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

function unihive_membership_url() {
	$url = unihive_setting( 'membership_url' );
	if ( ! $url && function_exists( 'pmpro_url' ) ) {
		$url = pmpro_url( 'levels' );
	}
	return $url ? $url : home_url( '/membership/' );
}

/**
 * Validates a contact value for the chosen method.
 *
 * @return string|WP_Error Clean value, or an error message for the form.
 */
function unihive_clean_contact( $method, $value ) {
	$value = trim( (string) $value );
	switch ( $method ) {
		case 'email':
			return is_email( $value ) ? sanitize_email( $value ) : new WP_Error( 'contact', __( 'Please enter a valid email address.', 'unihive' ) );
		case 'whatsapp':
		case 'phone':
			$digits = preg_replace( '/\D/', '', $value );
			if ( strlen( $digits ) < 8 || strlen( $digits ) > 15 ) {
				return new WP_Error( 'contact', __( 'Please enter the number with country code, e.g. +44 7700 900123.', 'unihive' ) );
			}
			return '+' . $digits;
		case 'link':
			$url = esc_url_raw( $value, array( 'https', 'http' ) );
			return $url && wp_http_validate_url( $url ) ? $url : new WP_Error( 'contact', __( 'Please enter a full link starting with https://', 'unihive' ) );
	}
	return new WP_Error( 'contact', __( 'Please choose how members should contact you.', 'unihive' ) );
}

function unihive_contact_href( $method, $value ) {
	switch ( $method ) {
		case 'email':
			return 'mailto:' . $value;
		case 'whatsapp':
			return 'https://wa.me/' . preg_replace( '/\D/', '', $value );
		case 'phone':
			return 'tel:' . preg_replace( '/[^\d+]/', '', $value );
		case 'link':
			return $value;
	}
	return '';
}

/**
 * Category term for a post, as [slug, name]; UniHive posts carry exactly one.
 */
function unihive_post_category( $post_id ) {
	$terms = get_the_terms( $post_id, 'unihive_category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array( '', '' );
	}
	return array( $terms[0]->slug, $terms[0]->name );
}

/**
 * "Priya S." rather than a full name: enough to recognise a member, less to scrape.
 */
function unihive_author_label( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return __( 'Former member', 'unihive' );
	}
	if ( $user->first_name ) {
		return trim( $user->first_name . ' ' . ( $user->last_name ? mb_substr( $user->last_name, 0, 1 ) . '.' : '' ) );
	}
	return $user->display_name;
}

function unihive_expiry_label( $post_id ) {
	$expires = (int) get_post_meta( $post_id, '_unihive_expires', true );
	if ( ! $expires ) {
		return '';
	}
	$days = (int) ceil( ( $expires - time() ) / DAY_IN_SECONDS );
	if ( $days <= 0 ) {
		return __( 'Expired', 'unihive' );
	}
	/* translators: %d: number of days */
	return sprintf( _n( 'Expires in %d day', 'Expires in %d days', $days, 'unihive' ), $days );
}

/**
 * Human-readable state of a member's own post for the "My posts" view.
 */
function unihive_status_label( $post ) {
	if ( 'publish' === $post->post_status ) {
		return array( 'live', __( 'Live', 'unihive' ) );
	}
	if ( 'draft' === $post->post_status && get_post_meta( $post->ID, '_unihive_expired', true ) ) {
		return array( 'expired', __( 'Expired', 'unihive' ) );
	}
	if ( 'pending' === $post->post_status && (int) get_post_meta( $post->ID, '_unihive_report_count', true ) >= (int) unihive_setting( 'report_threshold' ) ) {
		return array( 'hidden', __( 'Hidden – under review', 'unihive' ) );
	}
	if ( 'pending' === $post->post_status ) {
		return array( 'pending', __( 'Awaiting approval', 'unihive' ) );
	}
	return array( 'draft', ucfirst( $post->post_status ) );
}

/**
 * Email site admin + UniHive moderators. Filter `unihive_notify_moderators` to return false to mute.
 */
function unihive_notify_moderators( $post_id, $subject, $intro ) {
	if ( ! apply_filters( 'unihive_notify_moderators', true, $post_id, $subject ) ) {
		return;
	}
	$emails = array( get_option( 'admin_email' ) );
	foreach ( get_users( array( 'role' => 'unihive_moderator', 'fields' => array( 'user_email' ) ) ) as $mod ) {
		$emails[] = $mod->user_email;
	}
	$body = $intro . "\n\n" . get_the_title( $post_id ) . "\n" . admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ) . "\n\n"
		. admin_url( 'edit.php?post_type=unihive_post&post_status=pending' );
	wp_mail( array_unique( array_filter( $emails ) ), '[UniHive] ' . $subject, $body );
}

<?php
/**
 * Who can see and post in UniHive.
 *
 *   Visitors and Free members → teaser only (what UniHive is, live counts, upgrade link).
 *   Pro and Premium members   → read every post, see contact details, create posts.
 *   Moderators and admins     → everything, plus approve/edit/delete in wp-admin.
 */

defined( 'ABSPATH' ) || exit;

function unihive_user_can_moderate( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return $user_id && user_can( $user_id, 'unihive_moderate' );
}

function unihive_user_can_access( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	if ( unihive_user_can_moderate( $user_id ) ) {
		return true;
	}

	if ( function_exists( 'pmpro_hasMembershipLevel' ) ) {
		// Paid Memberships Pro is the source of truth for paid plans: an expired or
		// cancelled subscription loses access automatically. Levels are set in Settings.
		$levels  = array_filter( array_map( 'trim', explode( ',', (string) unihive_setting( 'pmpro_levels' ) ) ) );
		$allowed = $levels && pmpro_hasMembershipLevel( $levels, $user_id );
	} else {
		// Fallback for any membership plugin that assigns roles (Ultimate Member, Members, etc.).
		$allowed = user_can( $user_id, 'unihive_access' );
	}

	/**
	 * Lets another membership plugin decide, e.g. MemberPress or WooCommerce Memberships.
	 */
	return (bool) apply_filters( 'unihive_user_can_access', $allowed, $user_id );
}

/**
 * Plan name to show in the UI ("Pro", "Premium", "Free"...).
 */
function unihive_user_plan_label( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return '';
	}
	if ( function_exists( 'pmpro_getMembershipLevelForUser' ) ) {
		$level = pmpro_getMembershipLevelForUser( $user_id );
		return $level ? $level->name : __( 'Free', 'unihive' );
	}
	$user = get_userdata( $user_id );
	if ( in_array( 'unigator_premium', (array) $user->roles, true ) ) {
		return __( 'Premium', 'unihive' );
	}
	if ( in_array( 'unigator_pro', (array) $user->roles, true ) ) {
		return __( 'Pro', 'unihive' );
	}
	if ( unihive_user_can_moderate( $user_id ) ) {
		return __( 'Moderator', 'unihive' );
	}
	return __( 'Free', 'unihive' );
}

/**
 * Single post URLs (/hive/…) are members-only: everyone else is sent to the board page,
 * which explains UniHive and links to membership plans.
 */
add_action(
	'template_redirect',
	function () {
		if ( is_singular( 'unihive_post' ) && ! unihive_user_can_access() ) {
			wp_safe_redirect( unihive_board_url( array( 'hive_gate' => 1 ) ) );
			exit;
		}
	}
);

// Members-only pages should never be indexed, even if a URL leaks.
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_singular( 'unihive_post' ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);

// Page caches must not serve a member's view of UniHive to anyone else.
add_action(
	'template_redirect',
	function () {
		$post = get_post();
		if ( is_singular( 'unihive_post' ) || ( is_singular() && $post && has_shortcode( $post->post_content, 'unihive' ) ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true ); // Honoured by WP Super Cache, W3TC, LiteSpeed and others.
			}
			nocache_headers();
		}
	},
	1
);

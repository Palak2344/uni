<?php
/**
 * Plugin Name:       UniHive
 * Description:       Members-only community board for Unigator: Accommodation, Work Visa, Jobs and Part-time Jobs. Pro/Premium members can read and post; moderators approve, review reports and remove posts from wp-admin.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Unigator
 * License:           GPL-2.0-or-later
 * Text Domain:       unihive
 *
 * Architecture (see docs/UniHive-Proposal.pdf):
 *   - Custom post type `unihive_post` + taxonomy `unihive_category` (4 locked terms)
 *   - Post meta for contact method/value, location, expiry and reports
 *   - Access = Paid Memberships Pro level (if active) or the `unihive_access` capability
 *   - [unihive] shortcode renders the board: teaser for non-members, filters + form for members
 */

defined( 'ABSPATH' ) || exit;

define( 'UNIHIVE_VERSION', '0.1.0' );
define( 'UNIHIVE_FILE', __FILE__ );
define( 'UNIHIVE_DIR', plugin_dir_path( __FILE__ ) );
define( 'UNIHIVE_URL', plugin_dir_url( __FILE__ ) );

require_once UNIHIVE_DIR . 'includes/helpers.php';
require_once UNIHIVE_DIR . 'includes/post-type.php';
require_once UNIHIVE_DIR . 'includes/access.php';
require_once UNIHIVE_DIR . 'includes/submission.php';
require_once UNIHIVE_DIR . 'includes/board.php';
require_once UNIHIVE_DIR . 'includes/single.php';
require_once UNIHIVE_DIR . 'includes/moderation.php';
require_once UNIHIVE_DIR . 'includes/admin.php';
require_once UNIHIVE_DIR . 'includes/demo.php';

register_activation_hook( __FILE__, 'unihive_activate' );
register_deactivation_hook( __FILE__, 'unihive_deactivate' );

/**
 * One-time setup: roles/capabilities, the four category terms, the daily expiry job.
 */
function unihive_install() {
	unihive_register_post_type();
	unihive_register_taxonomy();
	unihive_install_roles();
	unihive_install_terms();
	if ( ! wp_next_scheduled( 'unihive_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'unihive_daily' );
	}
	update_option( 'unihive_version', UNIHIVE_VERSION );
}

function unihive_activate() {
	unihive_install();
	flush_rewrite_rules();
}

function unihive_deactivate() {
	wp_clear_scheduled_hook( 'unihive_daily' );
	flush_rewrite_rules();
}

// Covers installs where the plugin was mounted/activated without the activation hook running.
add_action(
	'init',
	function () {
		if ( get_option( 'unihive_version' ) !== UNIHIVE_VERSION ) {
			unihive_install();
			flush_rewrite_rules();
		}
	},
	20
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_style( 'unihive', UNIHIVE_URL . 'assets/unihive.css', array(), UNIHIVE_VERSION );
		$post = get_post();
		if ( is_singular( 'unihive_post' ) || ( is_singular() && $post && has_shortcode( $post->post_content, 'unihive' ) ) ) {
			wp_enqueue_style( 'unihive' );
		}
	}
);

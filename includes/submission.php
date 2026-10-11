<?php
/**
 * Front-end actions by members: create a post, report a post, delete or renew their own post.
 *
 * Every action checks: nonce → UniHive access → ownership where relevant.
 * Spam defences on create: honeypot field, per-member daily limit, link limit,
 * and first-post approval (settings → "Approval").
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', 'unihive_handle_actions', 5 );

function unihive_handle_actions() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || empty( $_POST['unihive_action'] ) ) {
		return;
	}
	switch ( sanitize_key( $_POST['unihive_action'] ) ) {
		case 'create':
			unihive_handle_create();
			break;
		case 'report':
			unihive_handle_report();
			break;
		case 'delete':
			unihive_handle_owner_action( 'delete' );
			break;
		case 'renew':
			unihive_handle_owner_action( 'renew' );
			break;
	}
}

/**
 * Form state shared with the renderer so a failed submission re-displays with its errors.
 */
function unihive_form_state( $set = null ) {
	static $state = array(
		'errors' => array(),
		'values' => array(),
	);
	if ( null !== $set ) {
		$state = $set;
	}
	return $state;
}

function unihive_post_field( $key ) {
	return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security -- sanitised by caller.
}

function unihive_handle_create() {
	$errors  = array();
	$user_id = get_current_user_id();

	if ( ! wp_verify_nonce( sanitize_text_field( unihive_post_field( '_unihive_nonce' ) ), 'unihive_create' ) ) {
		$errors[] = __( 'Your session expired. Please submit the form again.', 'unihive' );
	}
	if ( ! unihive_user_can_access() ) {
		$errors[] = __( 'Only Pro and Premium members can post in UniHive.', 'unihive' );
	}
	// Honeypot: humans never see this field. Bots that fill it get a normal-looking response.
	if ( '' !== unihive_post_field( 'unihive_website' ) ) {
		wp_safe_redirect( unihive_board_url( array( 'hive_msg' => 'pending' ) ) );
		exit;
	}

	$values = array(
		'title'          => sanitize_text_field( unihive_post_field( 'unihive_title' ) ),
		'category'       => sanitize_key( unihive_post_field( 'unihive_category' ) ),
		'description'    => sanitize_textarea_field( unihive_post_field( 'unihive_description' ) ),
		'location'       => sanitize_text_field( unihive_post_field( 'unihive_location' ) ),
		'contact_method' => sanitize_key( unihive_post_field( 'unihive_contact_method' ) ),
		'contact_value'  => sanitize_text_field( unihive_post_field( 'unihive_contact_value' ) ),
		'rules'          => '' !== unihive_post_field( 'unihive_rules' ),
	);

	if ( ! $errors ) {
		$title_len = mb_strlen( $values['title'] );
		if ( $title_len < 8 || $title_len > 120 ) {
			$errors['title'] = __( 'Title should be 8–120 characters.', 'unihive' );
		}
		if ( ! isset( unihive_categories()[ $values['category'] ] ) ) {
			$errors['category'] = __( 'Choose one of the four categories.', 'unihive' );
		}
		$desc_len = mb_strlen( $values['description'] );
		if ( $desc_len < 30 || $desc_len > 3000 ) {
			$errors['description'] = __( 'Description should be 30–3000 characters so members have enough detail.', 'unihive' );
		} elseif ( preg_match_all( '#https?://#i', $values['description'] ) > 2 ) {
			$errors['description'] = __( 'Please keep links in the description to two or fewer.', 'unihive' );
		}
		if ( mb_strlen( $values['location'] ) > 80 ) {
			$errors['location'] = __( 'Location should be under 80 characters.', 'unihive' );
		}
		$contact = unihive_clean_contact( $values['contact_method'], $values['contact_value'] );
		if ( is_wp_error( $contact ) ) {
			$errors['contact'] = $contact->get_error_message();
		} else {
			$values['contact_value'] = $contact;
		}
		if ( ! $values['rules'] ) {
			$errors['rules'] = __( 'Please confirm the UniHive rules.', 'unihive' );
		}
		$limit = (int) unihive_setting( 'max_per_day' );
		if ( $limit && ! unihive_user_can_moderate() && unihive_recent_post_count( $user_id ) >= $limit ) {
			/* translators: %d: posts per day */
			$errors[] = sprintf( __( 'You can create up to %d posts per day. Please try again tomorrow.', 'unihive' ), $limit );
		}
	}

	if ( $errors ) {
		unihive_form_state(
			array(
				'errors' => $errors,
				'values' => $values,
			)
		);
		return; // The [unihive] shortcode re-renders the form with these errors.
	}

	$status  = unihive_initial_status( $user_id );
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'unihive_post',
			'post_title'   => $values['title'],
			'post_content' => $values['description'],
			'post_status'  => $status,
			'post_author'  => $user_id,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		unihive_form_state(
			array(
				'errors' => array( $post_id->get_error_message() ),
				'values' => $values,
			)
		);
		return;
	}

	wp_set_object_terms( $post_id, $values['category'], 'unihive_category' );
	update_post_meta( $post_id, '_unihive_contact_method', $values['contact_method'] );
	update_post_meta( $post_id, '_unihive_contact_value', $values['contact_value'] );
	update_post_meta( $post_id, '_unihive_location', $values['location'] );
	update_post_meta( $post_id, '_unihive_expires', time() + (int) unihive_setting( 'expiry_days' ) * DAY_IN_SECONDS );

	if ( 'pending' === $status ) {
		unihive_notify_moderators( $post_id, __( 'New post awaiting approval', 'unihive' ), __( 'A member created a UniHive post that needs approval:', 'unihive' ) );
	}

	wp_safe_redirect( unihive_board_url( array( 'hive_msg' => 'publish' === $status ? 'published' : 'pending' ) ) );
	exit;
}

/**
 * Approval policy. "first" (default): a member's first post is reviewed; once a moderator
 * approves one, the member is trusted and later posts go live immediately.
 */
function unihive_initial_status( $user_id ) {
	if ( unihive_user_can_moderate( $user_id ) ) {
		return 'publish';
	}
	switch ( unihive_setting( 'approval' ) ) {
		case 'none':
			return 'publish';
		case 'all':
			return 'pending';
		default:
			return get_user_meta( $user_id, 'unihive_trusted', true ) ? 'publish' : 'pending';
	}
}

function unihive_recent_post_count( $user_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'unihive_post',
			'author'         => $user_id,
			'post_status'    => array( 'publish', 'pending', 'draft', 'trash' ),
			'date_query'     => array(
				array(
					// post_date (site time), not post_date_gmt: WordPress leaves the GMT date empty on pending posts.
					'column' => 'post_date',
					'after'  => wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
				),
			),
			'fields'         => 'ids',
			'posts_per_page' => 50,
			'no_found_rows'  => true,
		)
	);
	return count( $query->posts );
}

function unihive_handle_report() {
	$post_id = absint( unihive_post_field( 'post_id' ) );
	$user_id = get_current_user_id();
	$post    = get_post( $post_id );

	if ( ! $post || 'unihive_post' !== $post->post_type || 'publish' !== $post->post_status
		|| ! wp_verify_nonce( sanitize_text_field( unihive_post_field( '_unihive_nonce' ) ), 'unihive_report_' . $post_id )
		|| ! unihive_user_can_access() || (int) $post->post_author === $user_id ) {
		wp_safe_redirect( unihive_board_url() );
		exit;
	}

	$reason = sanitize_key( unihive_post_field( 'reason' ) );
	if ( ! isset( unihive_report_reasons()[ $reason ] ) ) {
		$reason = 'other';
	}

	$log = get_post_meta( $post_id, '_unihive_reports', true );
	$log = is_array( $log ) ? $log : array();
	if ( ! isset( $log[ $user_id ] ) ) { // One report per member per post.
		$log[ $user_id ] = array(
			'reason' => $reason,
			'time'   => time(),
		);
		update_post_meta( $post_id, '_unihive_reports', $log );
		update_post_meta( $post_id, '_unihive_report_count', count( $log ) );
	}

	// Enough independent reports → hide immediately and let a moderator decide.
	if ( count( $log ) >= (int) unihive_setting( 'report_threshold' ) ) {
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'pending',
			)
		);
		unihive_notify_moderators( $post_id, __( 'Post hidden after reports', 'unihive' ), __( 'This post reached the report threshold and was hidden until reviewed:', 'unihive' ) );
		wp_safe_redirect( unihive_board_url( array( 'hive_msg' => 'reported' ) ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'hive_msg', 'reported', get_permalink( $post_id ) ) );
	exit;
}

/**
 * Members can delete (to Trash, so moderators can still audit) or renew their own posts.
 */
function unihive_handle_owner_action( $action ) {
	$post_id = absint( unihive_post_field( 'post_id' ) );
	$post    = get_post( $post_id );
	$back    = unihive_board_url( array( 'hive_view' => 'mine' ) );

	if ( ! $post || 'unihive_post' !== $post->post_type
		|| ! wp_verify_nonce( sanitize_text_field( unihive_post_field( '_unihive_nonce' ) ), 'unihive_' . $action . '_' . $post_id )
		|| ( (int) $post->post_author !== get_current_user_id() && ! unihive_user_can_moderate() ) ) {
		wp_safe_redirect( $back );
		exit;
	}

	if ( 'delete' === $action ) {
		wp_trash_post( $post_id );
		wp_safe_redirect( add_query_arg( 'hive_msg', 'deleted', $back ) );
		exit;
	}

	// Renew: only expired posts, and only while the member still has access.
	if ( 'draft' === $post->post_status && get_post_meta( $post_id, '_unihive_expired', true ) && unihive_user_can_access() ) {
		delete_post_meta( $post_id, '_unihive_expired' );
		update_post_meta( $post_id, '_unihive_expires', time() + (int) unihive_setting( 'expiry_days' ) * DAY_IN_SECONDS );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);
		$back = add_query_arg( 'hive_msg', 'renewed', $back );
	}
	wp_safe_redirect( $back );
	exit;
}

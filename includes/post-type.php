<?php
/**
 * Data model: `unihive_post` custom post type, `unihive_category` taxonomy and post meta.
 *
 * Why a CPT and not a forum: a UniHive post is a structured listing (category, contact
 * method, location, lifespan), not an open-ended thread. A CPT gives us wp-admin list
 * tables, statuses (pending/publish/draft) and trash for free, with no extra tables.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'unihive_register_post_type' );
add_action( 'init', 'unihive_register_taxonomy' );
add_action( 'init', 'unihive_register_meta' );

function unihive_register_post_type() {
	register_post_type(
		'unihive_post',
		array(
			'labels'              => array(
				'name'               => __( 'UniHive', 'unihive' ),
				'singular_name'      => __( 'UniHive post', 'unihive' ),
				'menu_name'          => __( 'UniHive', 'unihive' ),
				'all_items'          => __( 'All posts', 'unihive' ),
				'add_new_item'       => __( 'Add UniHive post', 'unihive' ),
				'edit_item'          => __( 'Edit UniHive post', 'unihive' ),
				'view_item'          => __( 'View post', 'unihive' ),
				'search_items'       => __( 'Search UniHive', 'unihive' ),
				'not_found'          => __( 'No UniHive posts found.', 'unihive' ),
				'not_found_in_trash' => __( 'No UniHive posts in Trash.', 'unihive' ),
			),
			// Not "public": keeps posts out of search, sitemaps and nav menus. Still
			// queryable so members can open a single post (access is checked in access.php).
			'public'              => false,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false, // Keeps members-only content out of /wp-json/.
			'has_archive'         => false, // No public archive or feed.
			'menu_icon'           => 'dashicons-groups',
			'menu_position'       => 26,
			'rewrite'             => array(
				'slug'       => 'hive',
				'with_front' => false,
				'feeds'      => false,
			),
			'supports'            => array( 'title', 'editor', 'author' ),
			'capability_type'     => array( 'unihive_post', 'unihive_posts' ),
			'map_meta_cap'        => true,
		)
	);
}

function unihive_register_taxonomy() {
	register_taxonomy(
		'unihive_category',
		'unihive_post',
		array(
			'labels'            => array(
				'name'          => __( 'Categories', 'unihive' ),
				'singular_name' => __( 'Category', 'unihive' ),
				'all_items'     => __( 'All categories', 'unihive' ),
			),
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_menu'      => false, // The four terms are fixed; no "add category" screen.
			'show_in_rest'      => false,
			'rewrite'           => false,
			'query_var'         => 'unihive_category',
			'capabilities'      => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_unihive_posts',
			),
		)
	);
}

function unihive_register_meta() {
	$keys = array(
		'_unihive_contact_method' => 'string',
		'_unihive_contact_value'  => 'string',
		'_unihive_location'       => 'string',
		'_unihive_expires'        => 'integer', // Unix timestamp; the daily job unpublishes after it.
		'_unihive_report_count'   => 'integer',
	);
	foreach ( $keys as $key => $type ) {
		register_post_meta(
			'unihive_post',
			$key,
			array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => function () {
					return current_user_can( 'edit_unihive_posts' );
				},
			)
		);
	}
}

function unihive_install_terms() {
	foreach ( unihive_categories() as $slug => $category ) {
		if ( ! term_exists( $slug, 'unihive_category' ) ) {
			wp_insert_term( $category['name'], 'unihive_category', array( 'slug' => $slug, 'description' => $category['desc'] ) );
		}
	}
}

/**
 * Capabilities for managing UniHive posts in wp-admin (moderators and admins only).
 * Members never get these: they post through the front-end form, which checks access itself.
 */
function unihive_admin_caps() {
	$caps = array();
	foreach ( array( 'edit_%s', 'edit_others_%s', 'edit_published_%s', 'edit_private_%s', 'publish_%s', 'read_private_%s', 'delete_%s', 'delete_others_%s', 'delete_published_%s', 'delete_private_%s' ) as $pattern ) {
		$caps[ sprintf( $pattern, 'unihive_posts' ) ] = true;
	}
	return $caps;
}

/**
 * Roles:
 *   unigator_pro / unigator_premium – can read and post (capability `unihive_access`).
 *     With Paid Memberships Pro active, membership levels decide access instead (access.php).
 *   unihive_moderator – can approve, edit and delete any UniHive post, nothing else in wp-admin.
 */
function unihive_install_roles() {
	$member = array(
		'read'           => true,
		'unihive_access' => true,
	);
	add_role( 'unigator_pro', __( 'Unigator Pro', 'unihive' ), $member );
	add_role( 'unigator_premium', __( 'Unigator Premium', 'unihive' ), $member );

	$staff = array_merge(
		array(
			'unihive_access'   => true,
			'unihive_moderate' => true,
		),
		unihive_admin_caps()
	);
	add_role( 'unihive_moderator', __( 'UniHive Moderator', 'unihive' ), array_merge( array( 'read' => true ), $staff ) );

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( $staff as $cap => $grant ) {
			$admin->add_cap( $cap );
		}
	}
}

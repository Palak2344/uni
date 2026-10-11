<?php
/**
 * [unihive] shortcode: the UniHive page.
 *
 * Non-members see a teaser (what UniHive is, live post counts, upgrade/login links).
 * Members see: create-post form, category tabs, search, "My posts", paginated list.
 * Filters are plain GET links (?hive_cat=jobs), so they work without JavaScript,
 * can be bookmarked and map to a single indexed taxonomy query.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'unihive', 'unihive_board_shortcode' );

function unihive_board_shortcode() {
	wp_enqueue_style( 'unihive' );

	// Remember which page holds the board so gates and redirects can point to it.
	$page_id = get_the_ID();
	if ( $page_id && 'page' === get_post_type( $page_id ) && ! unihive_setting( 'board_page' ) ) {
		unihive_update_settings( array( 'board_page' => $page_id ) );
	}

	ob_start();
	echo '<div class="unihive">';
	unihive_render_notice();
	if ( unihive_user_can_access() ) {
		unihive_render_board();
	} else {
		unihive_render_teaser();
	}
	echo '</div>';
	return ob_get_clean();
}

function unihive_query_arg( $key ) {
	return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- read-only filters.
}

function unihive_render_notice() {
	$messages = array(
		'published' => array( 'success', __( 'Your post is live in UniHive.', 'unihive' ) ),
		'pending'   => array( 'info', __( 'Thanks! Your post is waiting for a quick moderator check. Once one post is approved, your future posts go live instantly.', 'unihive' ) ),
		'reported'  => array( 'info', __( 'Thanks for flagging this. A moderator will review it.', 'unihive' ) ),
		'deleted'   => array( 'success', __( 'Your post was deleted.', 'unihive' ) ),
		/* translators: %d: days */
		'renewed'   => array( 'success', sprintf( __( 'Your post is live again for %d days.', 'unihive' ), (int) unihive_setting( 'expiry_days' ) ) ),
	);
	$key = unihive_query_arg( 'hive_msg' );
	if ( isset( $messages[ $key ] ) ) {
		printf( '<p class="uh-notice uh-notice--%1$s" role="status">%2$s</p>', esc_attr( $messages[ $key ][0] ), esc_html( $messages[ $key ][1] ) );
	}
	if ( unihive_query_arg( 'hive_gate' ) && ! unihive_user_can_access() ) {
		printf( '<p class="uh-notice uh-notice--info" role="status">%s</p>', esc_html__( 'That post is only visible to Pro and Premium members.', 'unihive' ) );
	}
}

/**
 * Live post counts per category (term counts only include published posts).
 */
function unihive_category_counts() {
	$counts = array_fill_keys( array_keys( unihive_categories() ), 0 );
	$terms  = get_terms(
		array(
			'taxonomy'   => 'unihive_category',
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			if ( isset( $counts[ $term->slug ] ) ) {
				$counts[ $term->slug ] = (int) $term->count;
			}
		}
	}
	return $counts;
}

function unihive_render_teaser() {
	$counts   = unihive_category_counts();
	$logged   = is_user_logged_in();
	$total    = array_sum( $counts );
	$join_url = unihive_membership_url();
	?>
	<section class="uh-hero">
		<p class="uh-eyebrow"><?php esc_html_e( 'Members-only community', 'unihive' ); ?></p>
		<p class="uh-lead"><?php esc_html_e( 'Students helping students with the practical side of studying abroad: a place to live, the right work visa, and work that fits your course.', 'unihive' ); ?></p>
		<?php if ( $logged ) : ?>
			<p class="uh-plan">
				<?php
				/* translators: %s: plan name */
				printf( esc_html__( 'You are on the %s plan. UniHive is included with Pro and Premium.', 'unihive' ), '<strong>' . esc_html( unihive_user_plan_label() ) . '</strong>' );
				?>
			</p>
		<?php endif; ?>
		<div class="uh-actions">
			<a class="uh-btn uh-btn--primary" href="<?php echo esc_url( $join_url ); ?>"><?php $logged ? esc_html_e( 'Upgrade to Pro or Premium', 'unihive' ) : esc_html_e( 'See membership plans', 'unihive' ); ?></a>
			<?php if ( ! $logged ) : ?>
				<a class="uh-btn" href="<?php echo esc_url( wp_login_url( unihive_board_url() ) ); ?>"><?php esc_html_e( 'Log in', 'unihive' ); ?></a>
			<?php endif; ?>
		</div>
	</section>

	<h3 class="uh-section-title">
		<?php
		/* translators: %d: number of posts */
		printf( esc_html( _n( '%d live post inside, across four categories', '%d live posts inside, across four categories', $total, 'unihive' ) ), (int) $total );
		?>
	</h3>
	<div class="uh-grid">
		<?php foreach ( unihive_categories() as $slug => $category ) : ?>
			<div class="uh-cat uh-cat--<?php echo esc_attr( $slug ); ?>">
				<span class="uh-chip uh-chip--<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $category['name'] ); ?></span>
				<p><?php echo esc_html( $category['desc'] ); ?></p>
				<p class="uh-cat__count">
					<?php
					/* translators: %d: number of posts */
					printf( esc_html( _n( '%d post', '%d posts', $counts[ $slug ], 'unihive' ) ), (int) $counts[ $slug ] );
					?>
					<span class="uh-lock" aria-hidden="true">🔒</span>
				</p>
			</div>
		<?php endforeach; ?>
	</div>

	<ul class="uh-benefits">
		<li><?php esc_html_e( 'Read every post and see contact details', 'unihive' ); ?></li>
		<li><?php esc_html_e( 'Post your own housing, visa or job request in under a minute', 'unihive' ); ?></li>
		<li><?php esc_html_e( 'Moderated and paid-members-only, so fewer scams and no agents', 'unihive' ); ?></li>
	</ul>
	<?php
}

function unihive_render_board() {
	$categories = unihive_categories();
	$cat        = unihive_query_arg( 'hive_cat' );
	$cat        = isset( $categories[ $cat ] ) ? $cat : '';
	$view_mine  = 'mine' === unihive_query_arg( 'hive_view' );
	$search     = mb_substr( unihive_query_arg( 'hive_q' ), 0, 80 );
	$paged      = max( 1, absint( unihive_query_arg( 'hive_page' ) ) );
	$counts     = unihive_category_counts();
	$base       = unihive_board_url();

	$args = array(
		'post_type'      => 'unihive_post',
		'post_status'    => 'publish',
		'posts_per_page' => 10,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( $cat ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery -- indexed term relationship lookup.
			array(
				'taxonomy' => 'unihive_category',
				'field'    => 'slug',
				'terms'    => $cat,
			),
		);
	}
	if ( $search ) {
		$args['s'] = $search;
	}
	if ( $view_mine ) {
		$args['author']      = get_current_user_id();
		$args['post_status'] = array( 'publish', 'pending', 'draft' );
	}
	$query = new WP_Query( $args );
	?>
	<section class="uh-hero uh-hero--compact">
		<p class="uh-eyebrow">
			<?php
			/* translators: %s: plan name */
			printf( esc_html__( 'Signed in · %s member', 'unihive' ), esc_html( unihive_user_plan_label() ) );
			?>
		</p>
		<p class="uh-lead"><?php esc_html_e( 'Ask for or share help with accommodation, work visas, jobs and part-time jobs. Keep it specific: city, dates, and how to reach you.', 'unihive' ); ?></p>
	</section>

	<?php unihive_render_form(); ?>

	<nav class="uh-tabs" aria-label="<?php esc_attr_e( 'UniHive categories', 'unihive' ); ?>">
		<a class="uh-tab<?php echo ( ! $cat && ! $view_mine ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'All', 'unihive' ); ?> <span><?php echo (int) array_sum( $counts ); ?></span></a>
		<?php foreach ( $categories as $slug => $category ) : ?>
			<a class="uh-tab<?php echo ( $cat === $slug && ! $view_mine ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'hive_cat', $slug, $base ) ); ?>"><?php echo esc_html( $category['name'] ); ?> <span><?php echo (int) $counts[ $slug ]; ?></span></a>
		<?php endforeach; ?>
	</nav>

	<form class="uh-search" method="get" action="<?php echo esc_url( $base ); ?>" role="search">
		<?php if ( $cat ) : ?>
			<input type="hidden" name="hive_cat" value="<?php echo esc_attr( $cat ); ?>">
		<?php endif; ?>
		<?php if ( $view_mine ) : ?>
			<input type="hidden" name="hive_view" value="mine">
		<?php endif; ?>
		<label class="screen-reader-text" for="uh-q"><?php esc_html_e( 'Search UniHive', 'unihive' ); ?></label>
		<input id="uh-q" type="search" name="hive_q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by city, university or keyword…', 'unihive' ); ?>">
		<button type="submit" class="uh-btn"><?php esc_html_e( 'Search', 'unihive' ); ?></button>
		<a class="uh-tab uh-tab--mine<?php echo $view_mine ? ' is-active' : ''; ?>" href="<?php echo esc_url( $view_mine ? $base : add_query_arg( 'hive_view', 'mine', $base ) ); ?>"><?php esc_html_e( 'My posts', 'unihive' ); ?></a>
	</form>

	<div class="uh-list">
		<?php
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				unihive_render_card( get_post(), $view_mine );
			}
			wp_reset_postdata();
		} else {
			echo '<p class="uh-empty">';
			if ( $view_mine ) {
				esc_html_e( 'You have not posted yet. Use “Create a post” above.', 'unihive' );
			} elseif ( $search ) {
				esc_html_e( 'No posts match that search. Try a city or a broader word, or post your own request.', 'unihive' );
			} else {
				esc_html_e( 'No posts here yet. Be the first to post in this category.', 'unihive' );
			}
			echo '</p>';
		}
		?>
	</div>
	<?php
	if ( $query->max_num_pages > 1 ) {
		$links = paginate_links(
			array(
				'base'      => add_query_arg( 'hive_page', '%#%', remove_query_arg( 'hive_msg' ) ), // Escaped by paginate_links().
				'format'    => '',
				'current'   => $paged,
				'total'     => $query->max_num_pages,
				'prev_text' => __( '← Newer', 'unihive' ),
				'next_text' => __( 'Older →', 'unihive' ),
			)
		);
		echo '<nav class="uh-pagination" aria-label="' . esc_attr__( 'UniHive pages', 'unihive' ) . '">' . wp_kses_post( $links ) . '</nav>';
	}
}

function unihive_render_card( $post, $owner_view = false ) {
	list( $slug, $name ) = unihive_post_category( $post->ID );
	$location            = get_post_meta( $post->ID, '_unihive_location', true );
	$method              = get_post_meta( $post->ID, '_unihive_contact_method', true );
	$methods             = unihive_contact_methods();
	$is_live             = 'publish' === $post->post_status;
	?>
	<article class="uh-card<?php echo $is_live ? '' : ' is-muted'; ?>">
		<div class="uh-card__meta">
			<?php if ( $name ) : ?>
				<span class="uh-chip uh-chip--<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></span>
			<?php endif; ?>
			<?php if ( $location ) : ?>
				<span class="uh-card__loc"><?php echo esc_html( $location ); ?></span>
			<?php endif; ?>
			<span>
				<?php
				/* translators: %s: time ago */
				printf( esc_html__( '%s ago', 'unihive' ), esc_html( human_time_diff( get_post_time( 'U', true, $post ), time() ) ) );
				?>
			</span>
			<?php
			if ( $owner_view ) :
				list( $state, $label ) = unihive_status_label( $post );
				?>
				<span class="uh-status uh-status--<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
		</div>
		<h3 class="uh-card__title">
			<?php if ( $is_live ) : ?>
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
			<?php else : ?>
				<?php echo esc_html( get_the_title( $post ) ); ?>
			<?php endif; ?>
		</h3>
		<p class="uh-card__excerpt"><?php echo esc_html( wp_trim_words( $post->post_content, 32 ) ); ?></p>
		<div class="uh-card__foot">
			<span>
				<?php
				/* translators: %s: member name */
				printf( esc_html__( 'Posted by %s', 'unihive' ), esc_html( unihive_author_label( $post->post_author ) ) );
				?>
			</span>
			<?php if ( $method && isset( $methods[ $method ] ) ) : ?>
				<span>
					<?php
					/* translators: %s: contact method */
					printf( esc_html__( 'Contact via %s', 'unihive' ), esc_html( strtok( $methods[ $method ], ' ' ) ) );
					?>
				</span>
			<?php endif; ?>
			<?php if ( $is_live ) : ?>
				<span><?php echo esc_html( unihive_expiry_label( $post->ID ) ); ?></span>
			<?php endif; ?>
			<?php if ( $owner_view ) : ?>
				<span class="uh-card__actions">
					<?php
					list( $state ) = unihive_status_label( $post );
					if ( 'expired' === $state ) {
						unihive_render_owner_button( $post->ID, 'renew', __( 'Renew', 'unihive' ) );
					}
					unihive_render_owner_button( $post->ID, 'delete', __( 'Delete', 'unihive' ) );
					?>
				</span>
			<?php endif; ?>
		</div>
	</article>
	<?php
}

function unihive_render_owner_button( $post_id, $action, $label ) {
	?>
	<form method="post" class="uh-inline-form">
		<input type="hidden" name="unihive_action" value="<?php echo esc_attr( $action ); ?>">
		<input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>">
		<?php wp_nonce_field( 'unihive_' . $action . '_' . $post_id, '_unihive_nonce' ); ?>
		<button type="submit" class="uh-link-btn<?php echo 'delete' === $action ? ' uh-link-btn--danger' : ''; ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

/**
 * Create-post form. Collapsed by default; opens itself when re-shown with errors.
 * Fields match the brief: title, category, description, contact method (+ optional location).
 */
function unihive_render_form() {
	$state  = unihive_form_state();
	$errors = $state['errors'];
	$v      = wp_parse_args(
		$state['values'],
		array(
			'title'          => '',
			'category'       => unihive_query_arg( 'hive_cat' ),
			'description'    => '',
			'location'       => '',
			'contact_method' => 'email',
			'contact_value'  => '',
			'rules'          => false,
		)
	);
	$open  = $errors || unihive_query_arg( 'hive_new' );
	$field_error = function ( $key ) use ( $errors ) {
		if ( isset( $errors[ $key ] ) ) {
			printf( '<span class="uh-field-error">%s</span>', esc_html( $errors[ $key ] ) );
		}
	};
	?>
	<details class="uh-create" <?php echo $open ? 'open' : ''; ?>>
		<summary><span aria-hidden="true">+</span> <?php esc_html_e( 'Create a post', 'unihive' ); ?></summary>
		<form method="post" action="<?php echo esc_url( unihive_board_url() ); ?>" class="uh-form" novalidate>
			<?php
			$general = array_filter( $errors, 'is_int', ARRAY_FILTER_USE_KEY );
			if ( $general ) {
				echo '<div class="uh-notice uh-notice--error" role="alert">' . esc_html( implode( ' ', $general ) ) . '</div>';
			} elseif ( $errors ) {
				echo '<div class="uh-notice uh-notice--error" role="alert">' . esc_html__( 'Please fix the highlighted fields.', 'unihive' ) . '</div>';
			}
			?>
			<input type="hidden" name="unihive_action" value="create">
			<?php wp_nonce_field( 'unihive_create', '_unihive_nonce' ); ?>
			<div class="uh-hp" aria-hidden="true">
				<label for="uh-website">Website</label>
				<input id="uh-website" type="text" name="unihive_website" tabindex="-1" autocomplete="off">
			</div>

			<fieldset class="uh-field">
				<legend><?php esc_html_e( 'Category', 'unihive' ); ?></legend>
				<div class="uh-radio-row">
					<?php foreach ( unihive_categories() as $slug => $category ) : ?>
						<label class="uh-radio">
							<input type="radio" name="unihive_category" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $v['category'], $slug ); ?> required>
							<span><?php echo esc_html( $category['name'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<?php $field_error( 'category' ); ?>
			</fieldset>

			<p class="uh-field">
				<label for="uh-title"><?php esc_html_e( 'Title', 'unihive' ); ?></label>
				<input id="uh-title" type="text" name="unihive_title" maxlength="120" value="<?php echo esc_attr( $v['title'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Room near University of Leeds from January', 'unihive' ); ?>" required>
				<?php $field_error( 'title' ); ?>
			</p>

			<p class="uh-field">
				<label for="uh-description"><?php esc_html_e( 'Description', 'unihive' ); ?></label>
				<textarea id="uh-description" name="unihive_description" rows="6" maxlength="3000" required placeholder="<?php esc_attr_e( 'Dates, rent or pay, hours, requirements, what you need from others…', 'unihive' ); ?>"><?php echo esc_textarea( $v['description'] ); ?></textarea>
				<?php $field_error( 'description' ); ?>
			</p>

			<p class="uh-field">
				<label for="uh-location"><?php esc_html_e( 'City / country (optional)', 'unihive' ); ?></label>
				<input id="uh-location" type="text" name="unihive_location" maxlength="80" value="<?php echo esc_attr( $v['location'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Leeds, UK', 'unihive' ); ?>">
				<?php $field_error( 'location' ); ?>
			</p>

			<div class="uh-field uh-field--contact">
				<p>
					<label for="uh-contact-method"><?php esc_html_e( 'Contact method', 'unihive' ); ?></label>
					<select id="uh-contact-method" name="unihive_contact_method">
						<?php foreach ( unihive_contact_methods() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $v['contact_method'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="uh-contact-value"><?php esc_html_e( 'Email, number or link', 'unihive' ); ?></label>
					<input id="uh-contact-value" type="text" name="unihive_contact_value" value="<?php echo esc_attr( $v['contact_value'] ); ?>" required>
				</p>
				<?php $field_error( 'contact' ); ?>
				<p class="uh-help"><?php esc_html_e( 'Only visible to signed-in Pro and Premium members.', 'unihive' ); ?></p>
			</div>

			<p class="uh-field">
				<label class="uh-check">
					<input type="checkbox" name="unihive_rules" value="1" <?php checked( $v['rules'] ); ?>>
					<span><?php esc_html_e( 'I’m sharing as a student, not as an agent or paid service, and I won’t ask anyone for money or deposits through UniHive.', 'unihive' ); ?></span>
				</label>
				<?php $field_error( 'rules' ); ?>
			</p>

			<p><button type="submit" class="uh-btn uh-btn--primary"><?php esc_html_e( 'Publish post', 'unihive' ); ?></button></p>
		</form>
	</details>
	<?php
}

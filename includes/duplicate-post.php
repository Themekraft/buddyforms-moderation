<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Function creates post duplicate as a draft and redirects then to the edit post screen
 */
add_action( 'admin_action_buddyforms_moderation_duplicate_post', 'buddyforms_moderation_duplicate_post' );
function buddyforms_moderation_duplicate_post() {
	$is_ajax = wp_doing_ajax();

	if ( ! is_user_logged_in() ) {
		buddyforms_moderation_duplicate_post_error( __( 'You are not logged in.', 'buddyforms-review' ), 401, $is_ajax );
	}

	// phpcs:ignore WordPress.Security.NonceVerification -- The nonce is checked below, once the post ID is known.
	$post_id = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : 0;
	if ( empty( $post_id ) ) {
		buddyforms_moderation_duplicate_post_error( __( 'No post to duplicate has been supplied.', 'buddyforms-review' ), 400, $is_ajax );
	}

	$nonce = isset( $_REQUEST['duplicate_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['duplicate_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, buddyforms_moderation_duplicate_post_nonce_action( $post_id ) ) ) {
		buddyforms_moderation_duplicate_post_error( __( 'The link you followed has expired. Please try again.', 'buddyforms-review' ), 403, $is_ajax );
	}

	if ( ! buddyforms_moderation_user_can_duplicate_post( $post_id ) ) {
		buddyforms_moderation_duplicate_post_error( __( 'Sorry, you are not allowed to create an edit draft of this post.', 'buddyforms-review' ), 403, $is_ajax );
	}

	$new_post_id = buddyforms_moderation_duplicate_post_from_original( $post_id );
	if ( empty( $new_post_id ) || is_wp_error( $new_post_id ) ) {
		buddyforms_moderation_duplicate_post_error( __( 'The edit draft could not be created.', 'buddyforms-review' ), 500, $is_ajax );
	}

	$redirect = admin_url( 'post.php?action=edit&post=' . absint( $new_post_id ) );
	if ( $is_ajax ) {
		wp_send_json(
			array(
				'error'     => false,
				'error_msg' => '0',
				'redirect'  => $redirect,
			)
		);
	}

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Nonce action of the "Create new Edit Draft" links.
 *
 * @param int $post_id ID of the post to duplicate.
 *
 * @return string
 */
function buddyforms_moderation_duplicate_post_nonce_action( $post_id ) {
	return 'buddyforms_moderation_duplicate_post_' . absint( $post_id );
}

/**
 * URL of the "Create new Edit Draft" action for a post.
 *
 * @param int $post_id ID of the post to duplicate.
 *
 * @return string
 */
function buddyforms_moderation_duplicate_post_url( $post_id ) {
	return wp_nonce_url(
		admin_url( 'admin.php?action=buddyforms_moderation_duplicate_post&post_id=' . absint( $post_id ) ),
		buddyforms_moderation_duplicate_post_nonce_action( $post_id ),
		'duplicate_nonce'
	);
}

/**
 * Whether the current user may create an edit draft of a post.
 *
 * Only published or moderated posts can be duplicated. The user needs to be able to edit the
 * original post in WordPress, or to edit it in BuddyForms terms: the author of an entry of a
 * BuddyForms form with the form's edit permission (or its "all" permission).
 *
 * @param int $post_id ID of the post to duplicate.
 *
 * @return bool
 */
function buddyforms_moderation_user_can_duplicate_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	if ( ! in_array( $post->post_status, array( 'publish', 'approved', 'awaiting-review' ), true ) ) {
		return false;
	}

	if ( current_user_can( 'edit_post', $post->ID ) ) {
		return true;
	}

	if ( ! function_exists( 'buddyforms_get_form_slug_by_post_id' ) || ! function_exists( 'bf_user_can' ) ) {
		return false;
	}

	$form_slug = buddyforms_get_form_slug_by_post_id( $post->ID );
	if ( empty( $form_slug ) || 'none' === $form_slug ) {
		return false;
	}

	$user_id  = get_current_user_id();
	$can_edit = false;
	if ( bf_user_can( $user_id, 'buddyforms_' . $form_slug . '_all', array(), $form_slug ) ) {
		$can_edit = true;
	} elseif ( (int) $post->post_author === (int) $user_id ) {
		$can_edit = bf_user_can( $user_id, 'buddyforms_' . $form_slug . '_edit', array(), $form_slug );
	}

	return (bool) apply_filters( 'buddyforms_user_can_edit', $can_edit, $form_slug, $post->ID );
}

/**
 * Stop a duplicate request with an error: JSON for AJAX requests, wp_die() otherwise.
 *
 * @param string $message Error message.
 * @param int    $status  HTTP status code.
 * @param bool   $is_ajax Whether the request is an AJAX request.
 */
function buddyforms_moderation_duplicate_post_error( $message, $status, $is_ajax ) {
	if ( $is_ajax ) {
		wp_send_json(
			array(
				'error'     => true,
				'error_msg' => esc_html( $message ),
			),
			$status
		);
	}

	wp_die( esc_html( $message ), esc_html__( 'Create new Edit Draft', 'buddyforms-review' ), array( 'response' => absint( $status ), 'back_link' => true ) );
}

function buddyforms_moderation_duplicate_post_from_original( $post_id ) {
	/*
	 * and all the original post data then
	 */
	$post = get_post( $post_id );
	/*
	 * if you don't want current user to be the new post author,
	 * then change next couple of lines to this: $new_post_author = $post->post_author;
	 */
	$current_user    = wp_get_current_user();
	$new_post_author = $current_user->ID;

	/*
	 * if post data exists, create the post duplicate
	 */
	if ( isset( $post ) && $post != null ) {
		$post_title   = ! empty( $_POST['buddyforms_form_title'] ) ? sanitize_title( wp_unslash( $_POST['buddyforms_form_title'] ) ) : $post->post_title;
		$post_content = ! empty( $_POST['buddyforms_form_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['buddyforms_form_content'] ) ) : $post->post_content;
		$args         = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->ID,
			'post_password'  => $post->post_password,
			'post_status'    => 'edit-draft',
			'post_title'     => $post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order,
		);

		$new_post_id = wp_insert_post( $args );

		/*
		 * get all current post terms ad set them to the new post draft
		 */
		$taxonomies = get_object_taxonomies( $post->post_type ); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
			wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
		}

		$orig_author  = get_post_meta( $post_id, 'orig_author', true );
		$orig_post_id = get_post_meta( $post_id, 'orig_post_id', true );
		if ( $orig_author == '' ) {
			$orig_author  = $post->post_author;
			$orig_post_id = $post->ID;
		}

		$post_meta_infos                   = get_metadata( 'post', $post_id );
		$post_meta_infos['orig_author'][]  = $orig_author;
		$post_meta_infos['orig_post_id'][] = $orig_post_id;
		if ( ! empty( $post_meta_infos ) ) {
			foreach ( $post_meta_infos as $meta_info_key => $meta_info ) {
				if ( ! empty( $meta_info_key ) && ! empty( $meta_info ) && ! empty( $meta_info[0] ) ) {
					update_metadata( 'post', $new_post_id, $meta_info_key, addslashes( $meta_info[0] ) );
				}
			}
		}

		return $new_post_id;
	} else {
		return false;
	}
}

/**
 * Add an action to the post list to create a new draft from a published post
 *
 * @param array   $actions
 * @param WP_Post $post
 *
 * @return array
 * @since 1.4.0 Added the action to the post list and validate to show the action only to the published posts
 */
function buddyforms_moderation_duplicate_post_link( $actions, $post ) {
	if ( current_user_can( 'edit_pages' ) && $post->post_status === 'publish' ) {
		$actions['duplicate'] = '<a data-post_id="' . absint( $post->ID ) . '" href="' . esc_url( buddyforms_moderation_duplicate_post_url( $post->ID ) ) . '" title="' . __( 'Create new Edit Draft', 'buddyforms-review' ) . '" rel="permalink">' . __( 'Create new Edit Draft', 'buddyforms-review' ) . '</a>';
	}

	return $actions;
}

add_filter( 'post_row_actions', 'buddyforms_moderation_duplicate_post_link', 10, 2 );


/**
 * Add the duplicate link to admin bar only when the post is published
 *
 * @param WP_Admin_Bar $wp_admin_bar
 *
 * @since 1.4.0 Added the condition to check if the user have the capability to edit pages
 */
function buddyforms_moderation_admin_bar_mod_button( $wp_admin_bar ) {
	global $post;

	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	if ( ! $post || isset( $post ) && $post->post_status != 'publish' ) {
		return;
	}

	$args = array(
		'id'    => 'buddyforms-admin-moderation',
		'title' => __( 'Create new Edit Draft', 'buddyforms-review' ),
		'href'  => buddyforms_moderation_duplicate_post_url( $post->ID ),
		'meta'  => array(
			'data-post_id' => $post->ID,
			'class'        => 'buddyforms-admin-bar-moderation',
		),
	);
	$wp_admin_bar->add_node( $args );
}

add_action( 'admin_bar_menu', 'buddyforms_moderation_admin_bar_mod_button', 50 );

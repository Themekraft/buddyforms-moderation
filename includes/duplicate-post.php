<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Function creates post duplicate as a draft and redirects then to the edit post screen
 */
add_action( 'admin_action_buddyforms_moderation_duplicate_post', 'buddyforms_moderation_duplicate_post' );
function buddyforms_moderation_duplicate_post() {
	if ( is_user_logged_in() ) {
		global $wpdb;
		if ( ! ( isset( $_GET['post_id'] ) || isset( $_POST['post_id'] ) || ( isset( $_REQUEST['action'] ) && 'buddyforms_moderation_duplicate_post' == $_REQUEST['action'] ) ) ) {
			wp_die( 'No post to duplicate has been supplied!' );
		}

		/*
		 * get the original post id
		 */
		$post_id = ( isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : absint( $_POST['post_id'] ) );

		if ( ! empty( $post_id ) ) {
			$new_post_id = buddyforms_moderation_duplicate_post_from_original( $post_id );
			if ( $new_post_id !== false && is_numeric( $new_post_id ) ) {
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					$results = array(
						'error'     => false,
						'error_msg' => '0',
						'redirect'  => admin_url( 'post.php?action=edit&post=' . $new_post_id ),
					);
					echo json_encode( $results );
					die;
				} else {
					wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
				}
			} else {
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					$results = array(
						'error'     => true,
						'error_msg' => 'Post creation failed, could not find original post: ' . $post_id,
						'redirect'  => get_permalink(),
					);
					echo json_encode( $results );
					die;
				} else {
					wp_redirect( get_permalink() );
				}
			}
		}
	} else {
		$results = array(
			'error'     => true,
			'error_msg' => 'You are not logged in.',
			'redirect'  => wp_login_url( get_permalink() ),
		);
		echo json_encode( $results );
		die;
	}
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
		$actions['duplicate'] = '<a data-post_id="' . $post->ID . '" href="' . wp_nonce_url( 'admin.php?action=buddyforms_moderation_duplicate_post&post_id=' . $post->ID, basename( __FILE__ ), 'duplicate_nonce' ) . '" title="' . __( 'Create new Edit Draft', 'buddyforms-review' ) . '" rel="permalink">' . __( 'Create new Edit Draft', 'buddyforms-review' ) . '</a>';
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
		'href'  => get_admin_url() . wp_nonce_url( 'admin.php?action=buddyforms_moderation_duplicate_post&post_id=' . $post->ID, basename( __FILE__ ), 'duplicate_nonce' ),
		'meta'  => array(
			'data-post_id' => $post->ID,
			'class'        => 'buddyforms-admin-bar-moderation',
		),
	);
	$wp_admin_bar->add_node( $args );
}

add_action( 'admin_bar_menu', 'buddyforms_moderation_admin_bar_mod_button', 50 );

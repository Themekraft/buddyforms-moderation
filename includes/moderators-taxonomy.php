<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function buddyforms_moderators_register_posts_taxonomy() {
	global $buddyforms;

	/**
	 * Taxonomy: Moderators Posts.
	 */

	$labels = array(
		'name'          => __( 'Moderators Posts', 'buddyforms-review' ),
		'singular_name' => __( 'Moderator Post', 'buddyforms-review' ),
	);

	$args = array(
		'label'                 => __( 'Moderators Posts', 'buddyforms-review' ),
		'labels'                => $labels,
		'public'                => false,
		'publicly_queryable'    => false,
		'hierarchical'          => false,
		'show_ui'               => false,
		'show_in_menu'          => false,
		'show_in_nav_menus'     => false,
		'query_var'             => false,
		'rewrite'               => false,
		'show_admin_column'     => false,
		'show_in_rest'          => false,
		'rest_base'             => 'moderators_posts',
		'rest_controller_class' => 'WP_REST_Terms_Controller',
		'show_in_quick_edit'    => false,
	);

	$cforms = buddyforms_moderators_get_forms();

	$post_types = array();
	foreach ( $cforms as $slug => $form_name ) {
		$post_types[] = $buddyforms[ $slug ]['post_type'];
	}

	register_taxonomy( 'buddyforms_moderators', $post_types, $args );

	register_taxonomy( 'buddyforms_moderators_posts', 'user', $args );
}

add_action( 'init', 'buddyforms_moderators_register_posts_taxonomy', 9999 );

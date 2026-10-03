<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Create a shortcode to display the logged in collaborative user posts
 *
 * @param $args
 *
 * @return string
 */
function buddyforms_moderators_list_posts_to_moderate( $args ) {
	global $the_lp_query;
	$output = '';

	if ( ! is_user_logged_in() ) {
		return '';
	}

	$bfmod_fs = buddyforms_moderation_freemius();
	if ( ! empty( $bfmod_fs ) && $bfmod_fs->is_paying_or_trial__premium_only() ) {
		ob_start();
		$forced_posts_ids = array();

		$user_is_moderator_in_forms = array();
		$user                       = wp_get_current_user();
		$current_user_roles         = (array) $user->roles;
		$forced_moderators_roles    = buddyforms_moderation_all_form_forcing_moderators_by_role();
		if ( ! empty( $forced_moderators_roles ) ) {
			foreach ( $forced_moderators_roles as $form_slug => $forced_moderator_role ) {
				if ( in_array( $forced_moderator_role, $current_user_roles ) ) {
					$user_is_moderator_in_forms[] = $form_slug;
				}
			}
		}

		$post_type_to_mderate = isset( $args['post_type'] ) && post_type_exists( $args['post_type'] ) ? $args['post_type'] : 'any';

		$post_type_to_mderate_plural_label = 'posts';
		if ( $post_type_to_mderate !== 'any' ) {
			/**
			 * @var WP_Post_Type $post_type
			 */
			$post_type = get_post_type_object( $post_type_to_mderate );
			if ( isset( $post_type->labels->name ) && ! empty( $post_type->labels->name ) ) {
				$post_type_to_mderate_plural_label = esc_html( $post_type->labels->name );
			}
		}

		$errormessage = sprintf(
			/* translators: %s: plural name of the post type to moderate. */
			__( 'No %s to moderate at the moment.', 'buddyforms-review' ),
			$post_type_to_mderate_plural_label
		);

		$forced_moderation_args = array(
			'fields'      => 'ids',
			'post_type'   => $post_type_to_mderate,
			'post_status' => 'awaiting-review',
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'     => '_bf_form_slug',
					'value'   => $user_is_moderator_in_forms,
					'compare' => 'IN',
				),
			),
		);

		$forced_moderation_args = apply_filters( 'buddyforms_moderation_list_posts_to_moderate_query', $forced_moderation_args );

		$query_forced_moderation = new WP_Query( $forced_moderation_args );

		if ( $query_forced_moderation->have_posts() ) {
			$forced_posts_ids = $query_forced_moderation->posts;
		}

		$user_posts = wp_get_object_terms( get_current_user_id(), 'buddyforms_moderators_posts', array( 'fields' => 'slugs' ) );

		if ( ! empty( $user_posts ) ) {
			$query_user_posts = new WP_Query(
				array(
					'fields'      => 'ids',
					'post__in'    => $user_posts,
					'post_status' => 'awaiting-review',
					'post_type'   => 'any',
				)
			);

			if ( $query_user_posts->have_posts() ) {
				if ( empty( $forced_posts_ids ) ) {
					$forced_posts_ids = $query_user_posts->posts;
				} else {
					$forced_posts_ids = array_merge( $forced_posts_ids, $query_user_posts->posts );
				}
			}
		}

		if ( ! empty( $forced_posts_ids ) ) {

			$the_lp_query = new WP_Query(
				array(
					'post__in'    => $forced_posts_ids,
					'post_status' => 'awaiting-review',
					'post_type'   => 'any',
				)
			);

			if ( $the_lp_query->have_posts() ) {

				// Set flag to let further hook it's
				$_GET['buddyforms_list_posts_to_moderate'] = 1;

				buddyforms_locate_template( 'the-loop', $form_slug );
			} else {
				echo '<p>' . esc_html( $errormessage ) . '</p>';
			}
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html( $errormessage ) . '</p>';
		}

		BuddyFormsAssets::front_js_css();

		$output = ob_get_clean();
	}

	return $output;
}

add_shortcode( 'buddyforms_list_posts_to_moderate', 'buddyforms_moderators_list_posts_to_moderate' );

function buddyforms_moderators_actions_shortcode() {
	$output   = '';
	$bfmod_fs = buddyforms_moderation_freemius();
	if ( ! empty( $bfmod_fs ) && $bfmod_fs->is_paying_or_trial__premium_only() ) {
		global $post;

		if ( empty( $post ) ) {
			return $output;
		}

		$form_slug = buddyforms_get_form_slug_by_post_id( $post->ID );
		if ( empty( $form_slug ) ) {
			return $output;
		}

		$form = buddyforms_get_form_by_slug( $form_slug );
		if ( empty( $form ) ) {
			return $output;
		}

		$user                    = wp_get_current_user();
		$current_user_roles      = (array) $user->roles;
		$forced_moderators_roles = buddyforms_moderation_all_form_forcing_moderators_by_role();
		$is_moderation_by_role   = ( isset( $forced_moderators_roles[ $form_slug ] ) && in_array( $forced_moderators_roles[ $form_slug ], $current_user_roles ) );

		$user_posts                 = wp_get_object_terms( get_current_user_id(), 'buddyforms_moderators_posts', array( 'fields' => 'slugs' ) );
		$is_moderation_by_selection = in_array( $post->ID, $user_posts );

		if ( $is_moderation_by_role || $is_moderation_by_selection ) {
			ob_start();
			echo '<div class="buddyforms_moderators_action_container buddyforms-list">';
			buddyforms_moderators_actions_html( $form_slug, $post->ID );
			echo '</div>';
			BuddyFormsAssets::front_js_css( '', $form_slug );
			$output = ob_get_clean();
		}
	}

	return $output;
}

// add_shortcode( 'buddyforms_moderator_action', 'buddyforms_moderators_actions_shortcode' );

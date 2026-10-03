<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create the new Builder Form Elements
 *
 * @param $form_fields
 * @param $form_slug
 * @param $field_type
 * @param $field_id
 *
 * @return mixed
 */
function buddyforms_moderators_form_builder_form_elements( $form_fields, $form_slug, $field_type, $field_id ) {
	global $buddyforms;

	switch ( $field_type ) {
		case 'moderators':
			$roles = get_editable_roles();

			$roles_array = array( 'all' => __( 'All Roles', 'buddyforms-review' ) );
			foreach ( $roles as $role_kay => $role ) {
				$roles_array[ $role_kay ] = $role['name'];
			}

			$moderators = 'false';
			if ( isset( $buddyforms[ $form_slug ]['form_fields'][ $field_id ]['moderators'] ) ) {
				$moderators = $buddyforms[ $form_slug ]['form_fields'][ $field_id ]['moderators'];
			}
			$form_fields['general']['moderators'] = new Element_Select(
				'<b>' . __( 'Moderators', 'buddyforms-review' ) . '</b>',
				'buddyforms_options[form_fields][' . $field_id . '][moderators]',
				$roles_array,
				array(
					'value'         => $moderators,
					'data-field_id' => $field_id,
					'shortDesc'     => __( 'Let you users select the moderator(s) form the selected role or from all users.', 'buddyforms-review' ),
				)
			);

			$hide_for_moderators                           = isset( $buddyforms[ $form_slug ]['form_fields'][ $field_id ]['hide_for_moderators'] ) ? $buddyforms[ $form_slug ]['form_fields'][ $field_id ]['hide_for_moderators'] : 'false';
			$form_fields['general']['hide_for_moderators'] = new Element_Checkbox(
				'<b>' . __( 'Hide for Moderator', 'buddyforms-review' ) . '</b>',
				'buddyforms_options[form_fields][' . $field_id . '][hide_for_moderators]',
				array(
					'hide_for_moderators' => '<b>' . __( 'Hide', 'buddyforms-review' ) . '</b>',
				),
				array(
					'value'     => $hide_for_moderators,
					'shortDesc' => __( 'Hide this field for the moderators users.', 'buddyforms-review' ),
				)
			);

			$placeholder                                = isset( $buddyforms[ $form_slug ]['form_fields'][ $field_id ]['data-placeholder'] ) ? stripcslashes( $buddyforms[ $form_slug ]['form_fields'][ $field_id ]['data-placeholder'] ) : __( 'Select a Moderators', 'buddyforms-review' );
			$form_fields['general']['data-placeholder'] = new Element_Textbox(
				'<b>' . __( 'Placeholder', 'buddyforms-review' ) . '</b>',
				'buddyforms_options[form_fields][' . $field_id . '][data-placeholder]',
				array(
					'data'      => $field_id,
					'value'     => $placeholder,
					'shortDesc' => __( 'This string will be show inside the field.', 'buddyforms-review' ),
				)
			);

			break;
	}

	return $form_fields;
}

add_filter( 'buddyforms_form_element_add_field', 'buddyforms_moderators_form_builder_form_elements', 1, 5 );

/**
 * Display the new Moderator Fields in the frontend form
 *
 * @param Form  $form
 * @param array $form_args
 *
 * @return mixed
 */
function buddyforms_moderators_frontend_form_elements( $form, $form_args ) {
	global $buddyforms, $nonce;

	extract( $form_args );

	$post_type = $buddyforms[ $form_slug ]['post_type'];

	if ( ! $post_type ) {
		return $form;
	}

	if ( ! isset( $customfield['type'] ) ) {
		return $form;
	}

	if ( empty( $form_slug ) ) {
		return $form;
	}

	switch ( $customfield['type'] ) {
		case 'moderators':
			// Check if moderation is not forced from the form settings
			$moderation_options  = $buddyforms[ $form_slug ]['moderation'];
			$is_moderation_force = ( ! empty( $moderation_options ) && ! empty( $moderation_options['frontend-moderators'] ) && ! in_array( $moderation_options['frontend-moderators'], array( 'all', 'false' ) ) );

			if ( $is_moderation_force ) {
				return $form;
			}

			$hide_for_moderators = false;
			if ( ! empty( $customfield['hide_for_moderators'] ) ) {
				$hide_for_moderators = true;
			}

			if ( $customfield['moderators'] == 'all' ) {
				$blog_users = get_users();
			} else {
				$blog_users = get_users(
					array(
						'role' => $customfield['moderators'],
					)
				);
			}

			$hide_the_field  = false;
			$current_user_id = get_current_user_id();
			$options         = array();
			foreach ( $blog_users as $user ) {
				if ( $hide_for_moderators && $customfield['moderators'] !== 'all' && $user->ID == $current_user_id ) {
					$hide_the_field = true;
					break;
				}
				if ( $current_user_id !== $user->ID ) {
					$options[ $user->ID ] = $user->user_nicename;
				}
			}

			if ( $hide_the_field ) {
				return $form;
			}
			BuddyFormsAssets::load_select2_assets();

			$element_attr['class'] = $element_attr['class'] . ' bf-select2';
			$element_attr['value'] = get_post_meta( $post_id, 'buddyforms_moderators', true );

			$element_attr['id'] = 'buddyforms_moderators';
			if ( ! empty( $customfield['required'] ) ) {
				$element_attr['data-rule-has-moderation'] = true;
			}

			$element_attr['data-placeholder'] = $customfield['data-placeholder'];

			$labels_layout = isset( $buddyforms[ $form_slug ]['layout']['labels_layout'] ) ? $buddyforms[ $form_slug ]['layout']['labels_layout'] : 'inline';
			if ( $labels_layout == 'inline' ) {
				if ( ! empty( $customfield['required'] ) ) {
					$element_attr['data-placeholder'] .= $form->getRequiredPlainSignal();
				}
			}

			$element = new Element_Select( $customfield['name'], 'buddyforms_moderators', $options, $element_attr, $customfield );
			$element->setAttribute( 'multiple', 'multiple' );
			$element->unsetAttribute( 'data-tags' );
			$form->addElement( $element );

			break;

	}

	return $form;
}

add_filter( 'buddyforms_create_edit_form_display_element', 'buddyforms_moderators_frontend_form_elements', 1, 2 );

/**
 * Save Fields
 *
 * @param $customfield
 * @param $post_id
 */
function buddyforms_moderators_update_post_meta( $customfield, $post_id ) {

	$global_error = ErrorHandler::get_instance();

	if ( $customfield['type'] == 'moderators' ) {

		$form_slug = get_post_meta( $post_id, '_bf_form_slug', true );

		$global_error->add_error( new BuddyForms_Error( 'buddyforms_form_' . $form_slug, 'Just a test', '', $form_slug ) );

		// Create a editors array to store all editors.
		$moderators     = array();
		$old_moderators = get_post_meta( $post_id, 'buddyforms_moderators', true );

		// Update the editors post meta
		if ( ! empty( $_POST['buddyforms_moderators'] ) ) {
			update_post_meta( $post_id, 'buddyforms_moderators', buddyforms_sanitize_mixed( wp_unslash( $_POST['buddyforms_moderators'] ) ) );

			// Update the editors array
			foreach ( buddyforms_sanitize_mixed( wp_unslash( $_POST['buddyforms_moderators'] ) ) as $key => $moderator ) {
				$moderators[ $moderator ] = $moderator;
			}
		}

		if ( ! empty( $old_moderators ) && is_array( $old_moderators ) ) {
			// Loop through the old moderators and remove them from the buddyforms_moderators_posts taxonomy
			foreach ( $old_moderators as $post_moderator ) {
				if ( ! array_key_exists( $post_moderator, $moderators ) ) {
					wp_remove_object_terms( $post_moderator, strval( $post_id ), 'buddyforms_moderators_posts' );
				}
			}
		}

		if ( ! empty( $moderators ) ) {
			// Loop thru all moderators and add the post to the buddyforms_moderators_posts taxonomy
			foreach ( $moderators as $moderators_id ) {
				$moderator_posts = wp_set_object_terms( $moderators_id, strval( $post_id ), 'buddyforms_moderators_posts', true );
			}
		}
	}
}

add_action( 'buddyforms_update_post_meta', 'buddyforms_moderators_update_post_meta', 10, 2 );


add_filter( 'buddyforms_form_custom_validation', 'buddyforms_moderators_server_validation', 2, 2 );

function buddyforms_moderators_server_validation( $valid, $form_slug ) {
	$moderation_field = buddyforms_get_form_field_by( $form_slug, 'moderators', 'type' );
	if ( ! empty( $moderation_field ) && ! empty( $moderation_field['required'] ) && empty( $moderation_field['hide_for_moderators'] ) ) {
		$global_error = ErrorHandler::get_instance();
		if ( isset( $_POST['status'] ) && $_POST['status'] == 'awaiting-review' ) {
			$form                = buddyforms_get_form_by_slug( $form_slug );
			$moderation_options  = $form['moderation'];
			$is_moderation_force = ( ! empty( $moderation_options ) && ! empty( $moderation_options['frontend-moderators'] ) && ! in_array( $moderation_options['frontend-moderators'], array( 'all', 'false' ) ) );

			if ( $is_moderation_force ) {
				return $valid;
			}

			if ( ! isset( $_POST['buddyforms_moderators'] ) ) {
				$valid                    = false;
				$validation_error_message = __( 'Please select a Moderator!', 'buddyforms-review' );
				$global_error->add_error( new BuddyForms_Error( 'buddyforms_form_' . $form_slug, $validation_error_message, $moderation_field['slug'], $form_slug ) );
			}
		}
	}

	return $valid;
}


function buddyforms_moderators_the_loop_actions( $post_id ) {
	$post_status = get_post_status( $post_id );
	if ( $post_status !== 'awaiting-review' ) {
		return;
	}
	$moderation_posts   = array();
	$user               = wp_get_current_user();
	$current_user_roles = (array) $user->roles;
	$form_slug          = buddyforms_get_form_slug_by_post_id( $post_id );
	$forced_role        = buddyforms_moderation_get_forced_moderator_role_by_form_slug( $form_slug );
	if ( $forced_role !== 'all' ) {
		$current_user_belong_to_moderation_role = in_array( $forced_role, $current_user_roles );
	} else {
		$current_user_belong_to_moderation_role = true;
	}

	$user_posts = wp_get_object_terms( get_current_user_id(), 'buddyforms_moderators_posts', array( 'fields' => 'slugs' ) );
	if ( ! empty( $user_posts ) ) {
		if ( empty( $moderation_posts ) ) {
			$moderation_posts = $user_posts;
		} else {
			$moderation_posts = array_merge( $moderation_posts, $user_posts );
		}
	}
	$user_is_moderator = in_array( $post_id, $moderation_posts );
	if ( $user_is_moderator || $current_user_belong_to_moderation_role ) {
		buddyforms_moderators_actions_html( $form_slug, $post_id );
	}
}

add_action( 'buddyforms_the_loop_after_actions', 'buddyforms_moderators_the_loop_actions' );


add_action( 'wp_ajax_buddyforms_moderators_ajax_approve_post', 'buddyforms_moderators_ajax_approve_post' );
function buddyforms_moderators_ajax_approve_post() {
	try {
		if ( ! ( is_array( $_POST ) && defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			die();
		}

		if ( ! isset( $_POST['action'] ) || ! isset( $_POST['nonce'] ) ) {
			die();
		}
		if ( ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), __DIR__ . 'buddyforms_moderation' ) ) {
			die();
		}

		if ( ! isset( $_POST['post_id'] ) ) {
			echo esc_html__( 'There has been an error sending the message!', 'buddyforms-review' );
			die();
		}

		global $current_user, $buddyforms;
		$current_user = wp_get_current_user();

		$post_id = intval( $_POST['post_id'] );

		$form_slug = get_post_meta( $post_id, '_bf_form_slug', true );
		if ( ! $form_slug ) {
			esc_html_e( 'You are not allowed to access here! What are you doing here?', 'buddyforms-review' );
			die();
		}

		$approve = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'approved',
			)
		);

		if ( ! is_wp_error( $approve ) ) {
			$post      = get_post( $post_id );
			$user_info = get_userdata( $post->post_author );

			$mail_to        = $user_info->user_email;
			$moderator_user = get_userdata( get_current_user_id() );
			$from_email     = $moderator_user->user_email;
			$from_name      = $moderator_user->user_firstname;
			$from_last      = $moderator_user->user_lastname;

			if ( ! empty( $from_name ) ) {
				if ( ! empty( $from_last ) ) {
					$from_name .= ' ' . $from_last;
				}
			} else {
				$from_name = $from_email;
			}
			$moderation_options = buddyforms_get_form_option( $form_slug, 'moderation' );
			$subject            = ! empty( $moderation_options['approve_subject'] ) ? $moderation_options['approve_subject'] : __( 'Your submission got Approve', 'buddyforms-review' );
			$subject            = buddyforms_moderation_process_shortcode( $subject, $post_id, $form_slug );
			$email_body         = ! empty( $moderation_options['approve_message'] ) ? $moderation_options['approve_message'] : __( 'Hi [user_login], your submitted post [published_post_title] has ben approve.', 'buddyforms-review' );
			$email_body         = buddyforms_moderation_process_shortcode( $email_body, $post_id, $form_slug );
			$email_body         = nl2br( $email_body );
			$result             = buddyforms_email( $mail_to, $subject, $from_name, $from_email, $email_body, array(), array(), $form_slug, $post_id );
			if ( ! $result ) {
				buddyforms_moderation_error_log( 'Error sending the approve email for the post ' . $post_id );
			}
		}

		do_action( 'buddyforms_moderators_after_approving_post', $post_id );

		// Remove the post from the user posts taxonomy
		wp_remove_object_terms( get_current_user_id(), strval( $post_id ), 'buddyforms_moderators_posts' );

		// Remove the user from the post editors
		wp_remove_object_terms( $post_id, strval( get_current_user_id() ), 'buddyforms_moderators' );

	} catch ( Exception $ex ) {
		buddyforms_moderation_error_log( $ex->getMessage() );
	}
	die();
}

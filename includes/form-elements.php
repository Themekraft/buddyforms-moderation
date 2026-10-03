<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function buddyforms_moderation_admin_settings_sidebar_metabox() {
	add_meta_box( 'buddyforms_moderation', __( 'Moderation', 'buddyforms-review' ), 'buddyforms_moderation_admin_settings_sidebar_metabox_html', 'buddyforms', 'normal', 'low' );
	add_filter( 'postbox_classes_buddyforms_buddyforms_moderation', 'buddyforms_metabox_class' );
	add_filter( 'postbox_classes_buddyforms_buddyforms_moderation', 'buddyforms_metabox_show_if_form_type_post' );
	add_filter( 'postbox_classes_buddyforms_buddyforms_moderation', 'buddyforms_metabox_show_if_post_type_none' );
}

function buddyforms_moderation_admin_settings_sidebar_metabox_html() {
	global $post, $buddyforms;

	if ( $post->post_type != 'buddyforms' ) {
		return;
	}

	$buddyform = get_post_meta( get_the_ID(), '_buddyforms_options', true );

	$form_setup = array();

	$moderation_logic = isset( $buddyform['moderation_logic'] ) ? $buddyform['moderation_logic'] : 'default';

	$form_setup[] = new Element_Radio(
		'<b>' . __( 'Moderation Logic', 'buddyforms-review' ) . '</b>',
		'buddyforms_options[moderation_logic]',
		array(
			'default'       => 'Moderation is disabled<br>',
			'one_draft'     => 'Users can create, save and edit a draft until it is submitted for moderation. Once submitted, changes cannot be made until the post is approved. However, the user can delete the submitted post before it is approved.<br>',
			'hidden_draft'  => 'Users can only submit a post for moderation. Drafts cannot be saved.<br>',
			'many_drafts'   => 'Users can create as many drafts as they like and submit them for moderation. When a post is approved, all related older posts that are awaiting review get deleted. This could result in the post appearing multiple times in Edit Draft or Awaiting Moderation.<br>',
			'publish_draft' => 'Users can edit and publish posts that have got approved and published before without the need for a new moderation.',
		),
		array(
			'value' => $moderation_logic,
			// 'shortDesc'  => 'If a post is created or edited and the moderation logic is enabled the post is saved with post status edit-draft.
			// If a post is submit for moderation the post status is set to awaiting-approval'
		)
	);

	$label_submit = isset( $buddyform['moderation']['label_submit'] ) ? $buddyform['moderation']['label_submit'] : __( 'Submit', 'buddyforms-review' );
	$form_setup[] = new Element_Textbox( '<b>' . __( 'Label for Submit Button', 'buddyforms-review' ) . '</b>', 'buddyforms_options[moderation][label_submit]', array( 'value' => $label_submit ) );

	$label_save   = isset( $buddyform['moderation']['label_save'] ) ? $buddyform['moderation']['label_save'] : __( 'Save', 'buddyforms-review' );
	$form_setup[] = new Element_Textbox( '<b>' . __( 'Label for Save Button', 'buddyforms-review' ) . '</b>', 'buddyforms_options[moderation][label_save]', array( 'value' => $label_save ) );

	$label_review = isset( $buddyform['moderation']['label_review'] ) ? $buddyform['moderation']['label_review'] : __( 'Submit for moderation', 'buddyforms-review' );
	$form_setup[] = new Element_Textbox( '<b>' . __( 'Label for Submit for moderation Button', 'buddyforms-review' ) . '</b>', 'buddyforms_options[moderation][label_review]', array( 'value' => $label_review ) );

	$label_new_draft = isset( $buddyform['moderation']['label_new_draft'] ) ? $buddyform['moderation']['label_new_draft'] : __( 'Create new Draft', 'buddyforms-review' );
	$form_setup[]    = new Element_Textbox( '<b>' . __( 'Label for Create new Draft Button', 'buddyforms-review' ) . '</b>', 'buddyforms_options[moderation][label_new_draft]', array( 'value' => $label_new_draft ) );

	$label_no_edit = isset( $buddyform['moderation']['label_no_edit'] ) ? $buddyform['moderation']['label_no_edit'] : __( 'This Post is waiting for approval and can not be changed until it gets approved', 'buddyforms-review' );
	$form_setup[]  = new Element_Textarea( '<b>' . __( 'If the form is displayed but editing is disabled', 'buddyforms-review' ) . '</b>', 'buddyforms_options[moderation][label_no_edit]', array( 'value' => $label_no_edit ) );

	$label_publish = isset( $buddyform['moderation']['label_publish'] ) ? $buddyform['moderation']['label_publish'] : __( 'Publish', 'buddyforms-review' );
	$form_setup[]  = new Element_Textbox( '<b>' . __( 'Label for Publish Button', 'buddyforms-review' ) . '</b>', 'buddyforms_options[moderation][label_publish]', array( 'value' => $label_publish ) );

	$roles = get_editable_roles();

	$roles_array = array( 'all' => __( 'All Roles', 'buddyforms-review' ) );
	foreach ( $roles as $role_kay => $role ) {
		$roles_array[ $role_kay ] = $role['name'];
	}

	$frontend_moderators = 'false';
	if ( isset( $buddyform['moderation']['frontend-moderators'] ) ) {
		$frontend_moderators = $buddyform['moderation']['frontend-moderators'];
	}
	$element = new Element_Select(
		'<b>' . __( 'Frontend Moderators Role', 'buddyforms-review' ) . '</b>',
		'buddyforms_options[moderation][frontend-moderators]',
		$roles_array,
		array(
			'value'     => $frontend_moderators,
			'shortDesc' => __( 'Select which role the users will need to moderate the content from the front. This option takes precedence over the moderation field so it would not be shown to the user.', 'buddyforms-review' ),
		)
	);

	if ( buddyforms_moderation_freemius()->is_not_paying() ) {
		$element->setAttribute( 'disabled', 'disabled' );
	}

	$form_setup[] = $element;

	$element_name    = 'buddyforms_options[moderation][reject_subject]';
	$shortcodes_html = buddyforms_moderation_element_shortcodes_helper( $buddyform, $element_name );

	$reject_subject = ! empty( $buddyform['moderation']['reject_subject'] ) ? $buddyform['moderation']['reject_subject'] : __( 'Your submission got Rejected', 'buddyforms-review' );
	$form_setup[]   = new Element_Textbox(
		'<b>' . __( 'Reject Subject', 'buddyforms-review' ) . '</b>',
		$element_name,
		array(
			'value'     => $reject_subject,
			'shortDesc' => '<strong>' . __( 'You may use the shortcodes below to dynamically populate the Subject', 'buddyforms-review' ) . '</strong><br/>' . $shortcodes_html,
		)
	);

	$element_name    = 'buddyforms_options[moderation][reject_message]';
	$shortcodes_html = buddyforms_moderation_element_shortcodes_helper( $buddyform, $element_name );

	$reject_message = ! empty( $buddyform['moderation']['reject_message'] ) ? $buddyform['moderation']['reject_message'] : __( 'Hi [user_login], your submitted post [published_post_title] has ben rejected.', 'buddyforms-review' );
	$form_setup[]   = new Element_Textarea(
		'<b>' . __( 'Reject Message', 'buddyforms-review' ) . '</b>',
		$element_name,
		array(
			'value'     => $reject_message,
			'shortDesc' => '<strong>' . __( 'You may use the shortcodes below to dynamically populate the Message', 'buddyforms-review' ) . '</strong><br/>' . $shortcodes_html,
		)
	);

	$element_name    = 'buddyforms_options[moderation][approve_subject]';
	$shortcodes_html = buddyforms_moderation_element_shortcodes_helper( $buddyform, $element_name );

	$approve_subject = ! empty( $buddyform['moderation']['approve_subject'] ) ? $buddyform['moderation']['approve_subject'] : __( 'Your submission got Approve', 'buddyforms-review' );
	$form_setup[]    = new Element_Textbox(
		'<b>' . __( 'Approve Subject', 'buddyforms-review' ) . '</b>',
		$element_name,
		array(
			'value'     => $approve_subject,
			'shortDesc' => '<strong>' . __( 'You may use the shortcodes below to dynamically populate the Subject', 'buddyforms-review' ) . '</strong><br/>' . $shortcodes_html,
		)
	);

	$element_name    = 'buddyforms_options[moderation][approve_message]';
	$shortcodes_html = buddyforms_moderation_element_shortcodes_helper( $buddyform, $element_name );

	$approve_message = ! empty( $buddyform['moderation']['approve_message'] ) ? $buddyform['moderation']['approve_message'] : __( 'Hi [user_login], your submitted post [published_post_title] has ben approve.', 'buddyforms-review' );
	$form_setup[]    = new Element_Textarea(
		'<b>' . __( 'Approve Message', 'buddyforms-review' ) . '</b>',
		$element_name,
		array(
			'value'     => $approve_message,
			'shortDesc' => '<strong>' . __( 'You may use the shortcodes below to dynamically populate the Message', 'buddyforms-review' ) . '</strong><br/>' . $shortcodes_html,
		)
	);

	$draft_message = ! empty( $buddyform['moderation']['draft_message'] ) ? $buddyform['moderation']['draft_message'] : __( 'Form Saved Successfully', 'buddyforms-review' );
	$form_setup[]  = new Element_Textarea(
		'<b>' . __( 'Draft Form Message', 'buddyforms-review' ) . '</b>',
		'buddyforms_options[moderation][draft_message]',
		array(
			'value' => $draft_message,
		)
	);

	$awaiting_review_message = ! empty( $buddyform['moderation']['awaiting_review_message'] ) ? $buddyform['moderation']['awaiting_review_message'] : __( 'Form Submit to Review Successfully', 'buddyforms-review' );
	$form_setup[]            = new Element_Textarea(
		'<b>' . __( 'Awaiting Review Form Message', 'buddyforms-review' ) . '</b>',
		'buddyforms_options[moderation][awaiting_review_message]',
		array(
			'value' => $awaiting_review_message,
		)
	);

	$review_confirmation_message = isset( $buddyform['moderation']['review_confirmation_message'] ) ? $buddyform['moderation']['review_confirmation_message'] : __( 'Are you sure you want send it to moderation?', 'buddyforms-review' );
	$form_setup[]                = new Element_Textbox(
		'<b>' . __( 'Submit for Moderation Confirmation', 'buddyforms-review' ) . '</b>',
		'buddyforms_options[moderation][review_confirmation_message]',
		array(
			'value'     => $review_confirmation_message,
			'shortDesc' => __( 'Message shown on the confirmation window. Keep this option empty if you want to skip the confirmation before the submission.', 'buddyforms-review' ),
		)
	);

	buddyforms_display_field_group_table( $form_setup );
}

add_filter( 'add_meta_boxes', 'buddyforms_moderation_admin_settings_sidebar_metabox' );

/**
 * Display correct form action buttons
 *
 * @param Form   $form
 * @param string $form_slug
 * @param int    $post_id
 *
 * @return mixed
 */
function buddyforms_moderation_form_action_elements( $form, $form_slug, $post_id ) {
	global $buddyforms;

	$is_moderation_enabled = buddyforms_moderation_is_enabled( $form_slug );

	if ( empty( $is_moderation_enabled ) ) {
		return $form;
	}

	$moderation_logic = buddyforms_get_form_option( $form_slug, 'moderation_logic' );

	$moderation = buddyforms_get_form_option( $form_slug, 'moderation' );

	$submit_moderation_button = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_review'] ), 'awaiting-review' );
	$submit_button            = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_submit'] ), 'edit-draft' );
	$submit_save_button       = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_save'] ), 'edit-draft' );
	$submit_new_draft_button  = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_new_draft'] ), 'new-draft' );
	$submit_publish_button    = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_publish'] ), 'publish-draft' );

	$label_no_edit = new Element_HTML( '<div style="text-align: center; padding: 1rem;"><p>' . wp_kses_post( $moderation['label_no_edit'] ) . '</p></div>' );

	if (
		isset( $buddyforms[ $form_slug ]['moderation']['review_confirmation_message'] ) &&
		! empty( $buddyforms[ $form_slug ]['moderation']['review_confirmation_message'] )
	) {
		$submit_moderation_button->setAttribute( 'data-confirmation', $buddyforms[ $form_slug ]['moderation']['review_confirmation_message'] );
	}

	if ( is_user_logged_in() ) {
		// If post_id is 0 we have a new posts
		$post_status = get_post_status( $post_id ); // Get the Posts status
		if ( 'auto-draft' === $post_status ) {// New post
			if ( 'hidden_draft' == $moderation_logic ) {
				$form->addElement( $submit_moderation_button );
			} elseif ( 'publish_draft' == $moderation_logic ) {
				$form->addElement( $submit_moderation_button );
			} else {
				$form->addElement( $submit_save_button );
				$form->addElement( $submit_moderation_button );
			}
		} else { // Existing posts
			if ( 'one_draft' === $moderation_logic ) {
				if ( 'awaiting-review' == $post_status ) {
					$form->addElement( $label_no_edit );
				} else {
					$form->addElement( $submit_save_button );
					$form->addElement( $submit_moderation_button );
				}
			} elseif ( 'hidden_draft' === $moderation_logic ) {
				if ( 'awaiting-review' == $post_status ) {
					$form->addElement( $label_no_edit );
				} else {
					$form->addElement( $submit_moderation_button );
				}
			} elseif ( 'many_drafts' === $moderation_logic ) {
				if ( 'awaiting-review' === $post_status || 'publish' === $post_status ) {
					$parent_id = wp_get_post_parent_id( $post_id );
					if ( empty( $parent_id ) ) {
						$form->addElement( $submit_new_draft_button );
					} else {
						$form->addElement( $label_no_edit );
					}
				} else {
					$form->addElement( $submit_save_button );
					$form->addElement( $submit_moderation_button );
				}
			} elseif ( 'publish_draft' == $moderation_logic ) {
					$form->addElement( $submit_publish_button );
			}
		}
	} else {
		$form->addElement( $submit_button );
	}

	return $form;
}


/**
 * Display the new Form Element in the Frontend Form
 *
 * @param Form      $form
 * @param $form_slug
 * @param $post_id
 *
 * @return mixed
 */
function bf_moderation_create_frontend_form_element( $form, $form_slug, $post_id ) {
	$form = buddyforms_moderation_form_action_elements( $form, $form_slug, $post_id );

	return $form;
}

add_filter( 'buddyforms_create_edit_form_button', 'bf_moderation_create_frontend_form_element', 9999, 3 );

/**
 * @param $include
 * @param $form_slug
 * @param $form
 * @param $post_id
 *
 * @return mixed
 * @since 1.4.0 Only remove the button when the moderation is enabled
 */
function bf_moderation_include_form_action_button( $include, $form_slug, $form, $post_id ) {
	global $buddyforms;

	if ( ! isset( $buddyforms[ $form_slug ]['moderation_logic'] ) || $buddyforms[ $form_slug ]['moderation_logic'] == 'default' ) {
		return $include;
	}

	return false;
}

add_filter( 'buddyforms_include_form_draft_button', 'bf_moderation_include_form_action_button', 10, 4 );
add_filter( 'buddyforms_include_form_submit_button', 'bf_moderation_include_form_action_button', 10, 4 );


function buddyforms_moderation_ajax_process_edit_post_json_response( $json_args ) {
	global $buddyforms;

	if ( isset( $json_args ) ) {
		extract( $json_args );
	}

	if ( isset( $_POST['post_id'] ) ) {
		$post_id = absint( $_POST['post_id'] );
	}

	if ( empty( $post_id ) ) {
		return $json_args;
	}

	if ( ! isset( $_POST['form_slug'] ) ) {
		return $json_args;
	}

	$form_slug = buddyforms_sanitize_slug( wp_unslash( $_POST['form_slug'] ) );

	$is_moderation_enabled = buddyforms_moderation_is_enabled( $form_slug );

	if ( empty( $is_moderation_enabled ) ) {
		return $json_args;
	}

	$moderation_logic = buddyforms_get_form_option( $form_slug, 'moderation_logic' );

	if ( empty( $moderation_logic ) ) {
		return $json_args;
	}

	$moderation = buddyforms_get_form_option( $form_slug, 'moderation' );

	$label_moderation      = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_review'] ), 'awaiting-review' );
	$label_submit          = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_submit'] ), 'edit-draft' );
	$label_save            = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_save'] ), 'edit-draft' );
	$label_new_draft       = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_new_draft'] ), 'new-draft' );
	$submit_publish_button = buddyforms_moderation_submit_button( $form_slug, esc_attr( $moderation['label_publish'] ), 'publish-post' );

	$label_no_edit = new Element_HTML( '<div style="text-align: center; padding: 1rem;"><p>' . wp_kses_post( $moderation['label_no_edit'] ) . '</p></div>' );

	$post_status = get_post_status( $post_id ); // Get the Posts Status

	if ( is_user_logged_in() ) {
		// If post_id is 0 we have a new posts
		if ( 'auto-draft' === $post_status ) {// New post
			if ( 'hidden_draft' == $moderation_logic ) {
				$form_elements[] = $label_moderation;
			} else {
				$form_elements[] = $label_save;
				$form_elements[] = $label_moderation;
			}
		} else { // Existing posts
			if ( 'one_draft' === $moderation_logic ) {
				if ( 'awaiting-review' == $post_status ) {
					$form_elements[] = $label_no_edit;
				} else {
					$form_elements[] = $label_save;
					$form_elements[] = $label_moderation;
				}
			} elseif ( 'hidden_draft' === $moderation_logic ) {
				if ( 'awaiting-review' == $post_status ) {
					$form_elements[] = $label_no_edit;
				} else {
					$form_elements[] = $label_moderation;
				}
			} elseif ( 'many_drafts' === $moderation_logic ) {
				if ( 'awaiting-review' === $post_status || 'publish' === $post_status ) {
					$parent_id = wp_get_post_parent_id( $post_id );
					if ( empty( $parent_id ) ) {
						$form_elements[] = $label_new_draft;
					} else {
						$form_elements[] = $label_no_edit;
					}
				} else {
					$form_elements[] = $label_save;
					$form_elements[] = $label_moderation;
				}
			} elseif ( 'publish_draft' === $moderation_logic ) {
				$form_elements[] = $submit_publish_button;
			}
		}
	} else {
		$form_elements[] = $label_submit;
	}

	ob_start();
	foreach ( $form_elements as $key => $form_element ) {
		$form_element->render();
	}
	$field_html = ob_get_contents();
	ob_end_clean();

	$json_args['form_actions'] = $field_html;

	return $json_args;

}

function buddyforms_remove_private_prefix( $title ) {
	global $post;

	if( ! is_object( $post ) ) {
		return $title;
	}
	
	$post_id = $post->ID;
	$form_slug = buddyforms_get_form_slug_by_post_id( $post_id );
	if ( ! empty( $form_slug ) ) {
		$title = str_replace( 'Private: ', '', $title );
	}

	return $title;
}

add_filter( 'the_title', 'buddyforms_remove_private_prefix', 10, 2 );

add_filter( 'buddyforms_ajax_process_edit_post_json_response', 'buddyforms_moderation_ajax_process_edit_post_json_response', 10, 1 );

function bf_moderation_post_control_args( $args ) {
	if ( ! isset( $_POST['status'] ) ) {
		return $args;
	}

	$post_status = sanitize_text_field( wp_unslash( $_POST['status'] ) );

	if ( $post_status == 'new-draft' ) {
		$args['action'] = 'new-post';
		if ( $args['post_id'] != 0 ) {
			$args['post_parent'] = $args['post_id'];
		}
		$args['post_status'] = 'edit-draft';
		$old_status          = get_post_status( $args['post_id'] );
		if ( $old_status === 'awaiting-review' || $old_status === 'publish' || $old_status === 'approved' ) {
			$args['post_id'] = buddyforms_moderation_duplicate_post_from_original( $args['post_id'] );
		}
	}

	if ( $post_status == 'awaiting-review' ) {
		$args['post_status'] = 'awaiting-review';
	}

	if ( $post_status == 'publish-draft' ) {
		$args['post_status'] = 'publish';
	}

	return $args;
}

add_filter( 'buddyforms_update_post_args', 'bf_moderation_post_control_args', 10, 1 );

function bf_moderation_create_edit_form_post_id( $post_id ) {
	global $buddyforms;

	$form_slug = buddyforms_get_form_slug_by_post_id( $post_id );

	if ( ! $form_slug ) {
		return $post_id;
	}

	if ( ! isset( $buddyforms[ $form_slug ]['moderation_logic'] ) || $buddyforms[ $form_slug ]['moderation_logic'] == 'default' ) {
		return $post_id;
	}

	$args = array(
		'post_parent'    => $post_id,
		'posts_per_page' => 1,
		'post_status'    => 'edit-draft',
		'orderby '       => 'date',
		'order '         => 'DESC',
	);

	$children = new WP_Query( $args );

	if ( $children->have_posts() ) {
		$post_id = $children->posts[0]->ID;
	}

	return $post_id;

}

// add_filter( 'buddyforms_create_edit_form_post_id', 'bf_moderation_create_edit_form_post_id', 10, 1 );

function bf_moderation_post_status_css( $post_status_css, $form_slug ) {
	global $buddyforms;

	if ( ! isset( $buddyforms[ $form_slug ]['moderation_logic'] ) || $buddyforms[ $form_slug ]['moderation_logic'] == 'default' ) {
		return $post_status_css;
	}

	if ( $post_status_css == 'awaiting-review' ) {
		$post_status_css = 'bf-pending';
	}

	if ( $post_status_css == 'edit-draft' ) {
		$post_status_css = 'draft';
	}

	return $post_status_css;
}

add_filter( 'buddyforms_post_status_css', 'bf_moderation_post_status_css', 10, 2 );

/**
 * New post status
 *
 * @param $post_status
 * @param $form_slug
 *
 * @return mixed|string
 */
function buddyforms_moderation_create_edit_form_post_status( $post_status, $form_slug ) {
	global $buddyforms;

	if ( empty( $buddyforms[ $form_slug ]['moderation_logic'] ) || ( ! empty( $buddyforms[ $form_slug ]['moderation_logic'] ) && 'default' == $buddyforms[ $form_slug ]['moderation_logic'] ) ) {
		return $post_status;
	}

	if ( isset( $_POST['status'] ) ) {
		if ( $_POST['status'] == 'submitted' || $_POST['status'] == 'publish' ) {
			return 'edit-draft';
		}

		// What if someone enter a not existing post status?
		$post_status = sanitize_text_field( wp_unslash( $_POST['status'] ) );
	}

	return $post_status;
}

add_filter( 'buddyforms_create_edit_form_post_status', 'buddyforms_moderation_create_edit_form_post_status', 2, 101 );


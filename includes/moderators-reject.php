<?php

function buddyforms_moderators_reject_post( $post_id, $form_slug ) {
	buddyforms_add_bf_thickbox();
	ob_start();
	?>
	<script>
		jQuery(document).ready(function () {
			console.log('Aqui')
			jQuery(document).on("click", '#buddyforms_reject_post_as_moderator_<?php echo esc_js( $post_id ); ?>', function (evt) {
				const $this = jQuery(this);
				const post_reject_email_subject = jQuery('#post_reject_email_subject_<?php echo esc_js( $post_id ); ?>').val();
				const post_reject_email_message = jQuery('#post_reject_email_message_<?php echo esc_js( $post_id ); ?>').val();

				if (post_reject_email_subject == '') {
					alert('<?php esc_html_e( 'Mail Subject is a required field', 'buddyforms-review' ); ?>');
					return false;
				}
				if (post_reject_email_message == '') {
					alert('<?php esc_html_e( 'Message is a required field', 'buddyforms-review' ); ?>');
					return false;
				}

				const post_id = jQuery(this).attr("data-post_id");
				const form_slug = jQuery(this).attr("data-form_slug");

				jQuery.ajax({
					type: 'POST',
					dataType: "json",
					url: ajaxurl,
					data: {
						"action": "buddyforms_reject_post_as_moderator",
						"post_id": post_id,
						"nonce": '<?php echo wp_create_nonce( __DIR__ . 'buddyforms_moderation' ); ?>',
						"form_slug": form_slug,
						"post_reject_email_subject": post_reject_email_subject,
						"post_reject_email_message": post_reject_email_message
					},
					beforeSend: function() {
						$this.closest('#buddyforms_reject_wrap').LoadingOverlay("show", { zIndex: 100051 });
					 },
					success: function (data) {
						alert(data.result);
						window.location.reload();
					},
					error: function (request, status, error) {
						alert(request.responseText);
					},
					complete: function() {
						$this.closest('#buddyforms_reject_wrap').LoadingOverlay("hide");
					}
				});
			});
		});
	</script>
	<style>
		#buddyforms_reject_wrap input[type="text"] {
			width: 100%;
		}
	</style>

	<?php echo '<a id="buddyforms_reject" href="#TB_inline?width=800&height=600&inlineId=buddyforms_reject_modal_' . esc_attr( $post_id ) . '" title="' . esc_html__( 'Reject Post', 'buddyforms-review' ) . '" class="bf-thickbox buddyforms_moderators_reject buddyforms_moderators_action">' . esc_html__( 'Reject', 'buddyforms-review' ) . '</a>'; ?>

	<div id="buddyforms_reject_modal_<?php echo esc_attr( $post_id ); ?>" style="display:none;">
		<div id="buddyforms_reject_wrap">
			<br><br>
			<?php

			// Create the form object
			$form_id = 'buddyforms_reject_post_' . $post_id;

			$reject_form = new Form( $form_id );

			// Set the form attribute
			$reject_form->configure(
				array(
					'prevent' => array( 'bootstrap', 'jQuery', 'focus' ),
					'method'  => 'post',
				)
			);

			$moderation_options     = buddyforms_get_form_option( $form_slug, 'moderation' );
			$reject_request_subject = ! empty( $moderation_options['reject_subject'] ) ? $moderation_options['reject_subject'] : __( 'Your submission got Rejected', 'buddyforms-review' );
			$reject_request_subject = buddyforms_moderation_process_shortcode( $reject_request_subject, $post_id, $form_slug );
			$reject_form->addElement( new Element_Textbox( __( 'Subject', 'buddyforms-review' ), 'post_reject_email_subject_' . $post_id, array( 'value' => wp_kses_post( $reject_request_subject ) ) ) );

			$reject_request_message = ! empty( $moderation_options['reject_message'] ) ? $moderation_options['reject_message'] : __( 'Hi [user_login], your submitted post [published_post_title] has ben rejected.', 'buddyforms-review' );
			$reject_request_message = buddyforms_moderation_process_shortcode( $reject_request_message, $post_id, $form_slug );
			$reject_form->addElement(
				new Element_Textarea(
					'Add a Message',
					'post_reject_email_message_' . $post_id,
					array(
						'value' => wp_kses_post( $reject_request_message ),
						'class' => 'collaborative-publishing-message',
					)
				)
			);

			$reject_form->render();
			?>

			<br>
			<a id="buddyforms_reject_post_as_moderator_<?php echo esc_attr( $post_id ); ?>" data-post_id="<?php echo esc_attr( $post_id ); ?>" data-form_slug="<?php echo esc_attr( $form_slug ); ?>" href="#" class="button btn-primary btn"><?php esc_html_e( 'Reject Submission and send Message', 'buddyforms-review' ); ?></a>
		</div>
	</div>
	<?php
	$content = ob_get_clean();
	echo $content;
}

add_action( 'wp_ajax_buddyforms_reject_post_as_moderator', 'buddyforms_reject_post_as_moderator' );
function buddyforms_reject_post_as_moderator() {
	try {
		if ( ! ( is_array( $_POST ) && defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			die();
		}

		if ( ! isset( $_POST['action'] ) || ! isset( $_POST['nonce'] ) || empty( $_POST['form_slug'] ) ) {
			die();
		}
		if ( ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), __DIR__ . 'buddyforms_moderation' ) ) {
			die();
		}

		if ( ! isset( $_POST['post_id'] ) ) {
			echo esc_html__( 'There has been an error sending the message!', 'buddyforms-review' );
			die();
		}

		$post_id = intval( $_POST['post_id'] );

		if ( ! isset( $_POST['post_reject_email_subject'] ) ) {
			echo esc_html__( 'Please enter a valid Subject', 'buddyforms-review' );
			die();
		}

		if ( ! isset( $_POST['post_reject_email_message'] ) ) {
			echo esc_html__( 'Please enter a valid Message', 'buddyforms-review' );
			die();
		}

		$form_slug = 'buddyforms_contact_author_post_' . $post_id;
		if ( Form::isValid( $form_slug ) ) {

		} else {
			echo esc_html__( 'Please check the form.', 'buddyforms-review' );
			die();
		}

		$email_body = ! empty( $_POST['post_reject_email_message'] ) ? buddyforms_sanitize_mixed( wp_unslash( $_POST['post_reject_email_message'] ) ) : '';

		$email_body = wp_kses_post( $email_body );

		if ( empty( $email_body ) ) {
			echo esc_html__( 'Please enter a valid Message', 'buddyforms-review' );
			die();
		}

		$form_slug_parent = get_post_meta( $post_id, '_bf_form_slug', true );

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

		$subject    = sanitize_text_field( wp_unslash( $_POST['post_reject_email_subject'] ) );
		$email_body = buddyforms_moderation_process_shortcode( $email_body, $post_id, $form_slug_parent );

		$email_body = apply_filters( 'the_content', $email_body );
		$email_body = str_replace( ']]>', ']]&gt;', $email_body );

		$subject = buddyforms_moderation_process_shortcode( $subject, $post_id, $form_slug_parent );

		$email_body = nl2br( $email_body );

		$result = buddyforms_email( $mail_to, $subject, $from_name, $from_email, $email_body, array(), array(), $form_slug_parent, $post_id );

		$result_update = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'edit-draft',
			)
		);

		// Remove the post from the user posts taxonomy
		wp_remove_object_terms( get_current_user_id(), strval( $post_id ), 'buddyforms_moderators_posts' );

		// Remove the user from the post editors
		wp_remove_object_terms( $post_id, strval( get_current_user_id() ), 'buddyforms_moderators' );

		$json = array( 'result' => '' );
		if ( ! $result ) {
			$json['result'] = __( 'There has been an error sending the message!', 'buddyforms-review' );
		}

		if ( is_wp_error( $result_update ) ) {
			$json['result'] = __( 'There has been an error changing the post status!', 'buddyforms-review' );
		}

		$bf_moderation_message_history = get_post_meta( $post_id, '_bf_moderation_message_history', true );
		$history_entry                 = the_date( 'l, F j, Y' ) . '-' . $email_body;
		if ( ! empty( $bf_moderation_message_history ) && is_array( $bf_moderation_message_history ) ) {
			$bf_moderation_message_history[] = $history_entry;
		} else {
			$bf_moderation_message_history = array( $history_entry );
		}

		update_post_meta( $post_id, '_bf_moderation_message_history', $bf_moderation_message_history );

		$json['result'] = __( 'Post Rejected and your message has been sent.', 'buddyforms-review' );

		wp_send_json( $json );

	} catch ( Exception $ex ) {
		buddyforms_moderation_error_log( $ex->getMessage() );
	}
	die();
}

<?php
/**
 * Run on a disposable site with BuddyForms and BuddyForms Moderation active:
 * wp eval-file tests/security/moderators-authorization.php
 * Calls the real Approve, Reject and admin Reject handlers and the Moderators field save, each case in
 * its own process as a given user, then checks post status, mail recipients and saved moderators.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

if ( ! empty( $args ) ) {
	$case = json_decode( base64_decode( $args[0] ), true );
	wp_set_current_user( $case['user'] );
	add_filter(
		'pre_wp_mail',
		function ( $return, $atts ) {
			echo 'MAIL:' . wp_json_encode( (array) $atts['to'] ) . "\n";
			return true;
		},
		10,
		2
	);
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['REQUEST_URI']    = '/wp-admin/admin-ajax.php';
	$_POST                     = wp_slash( $case['post'] );
	if ( 'moderation' === $case['nonce'] ) {
		// The front-end handlers use __DIR__ . 'buddyforms_moderation' as nonce action.
		$dir            = dirname( ( new ReflectionFunction( 'buddyforms_moderators_ajax_approve_post' ) )->getFileName() );
		$_POST['nonce'] = wp_create_nonce( $dir . 'buddyforms_moderation' );
	} elseif ( ! empty( $case['nonce'] ) ) {
		$_POST['nonce'] = wp_create_nonce( $case['nonce'] );
	}
	$_REQUEST = $_POST;
	if ( 'save_moderators' === $case['run'] ) {
		buddyforms_moderators_update_post_meta( $case['field'], $case['post_id'] );
		return;
	}
	if ( ! defined( 'DOING_AJAX' ) ) {
		define( 'DOING_AJAX', true );
	}
	add_filter(
		'wp_die_ajax_handler',
		function () {
			return function () {
				exit( 0 );
			};
		}
	);
	call_user_func( $case['run'] );
	return;
}

foreach ( array( 'buddyforms_moderators_ajax_approve_post', 'buddyforms_reject_post_as_moderator', 'buddyforms_reject_now', 'buddyforms_moderators_update_post_meta' ) as $function ) {
	if ( ! function_exists( $function ) ) {
		WP_CLI::error( 'BuddyForms and BuddyForms Moderation (with the moderators feature) must be active: ' . $function . ' is missing.' );
	}
}

$marker = 'bfmod' . wp_rand( 100000, 999999 );
$users  = array();
$posts  = array();
$slugs  = array();
$passed = $failed = 0;

try {
	foreach ( array( 'author' => 'subscriber', 'picked' => 'subscriber', 'outsider' => 'subscriber', 'editor' => 'editor' ) as $who => $role ) {
		$id = wp_insert_user( array( 'user_login' => $marker . '-' . $who, 'user_email' => $who . '-' . $marker . '@example.com', 'user_pass' => wp_generate_password(), 'role' => $role ) );
		if ( is_wp_error( $id ) ) {
			throw new Exception( 'Unable to create user fixture' );
		}
		$users[ $who ] = $id;
	}

	// "forced": editors moderate every submission of the form. "picked": the author picks the moderators.
	$moderators_field = array( 'type' => 'moderators', 'slug' => 'moderators', 'name' => 'Moderators', 'moderators' => 'subscriber' );
	foreach ( array( 'forced' => 'editor', 'picked' => 'false' ) as $name => $role ) {
		$id = wp_insert_post( array( 'post_type' => 'buddyforms', 'post_status' => 'publish', 'post_title' => $marker . ' ' . $name, 'post_author' => 1 ), true );
		if ( is_wp_error( $id ) ) {
			throw new Exception( 'Unable to create form fixture' );
		}
		$posts[ 'form_' . $name ] = $id;
		$slugs[ $name ]           = get_post_field( 'post_name', $id );
		update_post_meta( $id, '_buddyforms_options', array( 'name' => $name, 'slug' => $slugs[ $name ], 'singular_name' => $name, 'post_type' => 'post', 'status' => 'publish', 'comment_status' => 'closed', 'form_fields' => array( 'f1' => $moderators_field ), 'moderation' => array( 'frontend-moderators' => $role ) ) );
	}
	buddyforms_regenerate_global_options();

	$entry = function ( $key, $form, $status ) use ( &$posts, $slugs, $users, $marker ) {
		$posts[ $key ] = wp_insert_post( array( 'post_type' => 'post', 'post_status' => $status, 'post_title' => $marker . ' ' . $key, 'post_author' => $users['author'] ) );
		update_post_meta( $posts[ $key ], '_bf_form_slug', $slugs[ $form ] );
		return $posts[ $key ];
	};
	$entry( 'forced', 'forced', 'awaiting-review' );
	$entry( 'picked', 'picked', 'awaiting-review' );
	$entry( 'published', 'picked', 'publish' );
	$entry( 'admin_reject', 'picked', 'publish' );
	$entry( 'field', 'picked', 'awaiting-review' );
	wp_set_object_terms( $users['picked'], strval( $posts['picked'] ), 'buddyforms_moderators_posts', true );

	$status = function ( $key ) use ( &$posts ) {
		clean_post_cache( $posts[ $key ] );
		return get_post_status( $posts[ $key ] );
	};
	$approve = function ( $who, $key, $nonce = 'moderation' ) use ( $users, &$posts ) {
		return array( 'run' => 'buddyforms_moderators_ajax_approve_post', 'user' => $users[ $who ], 'nonce' => $nonce, 'post' => array( 'action' => 'buddyforms_moderators_ajax_approve_post', 'post_id' => (string) $posts[ $key ] ) );
	};
	$reject = function ( $who, $key ) use ( $users, &$posts, $slugs ) {
		return array( 'run' => 'buddyforms_reject_post_as_moderator', 'user' => $users[ $who ], 'nonce' => 'moderation', 'post' => array( 'action' => 'buddyforms_reject_post_as_moderator', 'post_id' => (string) $posts[ $key ], 'form_slug' => $slugs['picked'], 'post_reject_email_subject' => 'Rejected', 'post_reject_email_message' => 'Rejected by ' . $who ) );
	};
	$admin_reject = function ( $who, $nonce ) use ( $users, &$posts ) {
		return array( 'run' => 'buddyforms_reject_now', 'user' => $users[ $who ], 'nonce' => $nonce, 'post' => array( 'action' => 'buddyforms_reject_now', 'post_id' => (string) $posts['admin_reject'], 'user_email' => 'attacker@example.com', 'bf_reject_mail_from' => 'noreply@example.com', 'bf_reject_mail_subject' => 'Rejected', 'bf_reject_mail_message' => 'Rejected' ) );
	};
	$author_mail = get_userdata( $users['author'] )->user_email;
	$admin_nonce = 'buddyforms_reject_now_' . $posts['admin_reject'];

	// Refusals run first: each allowed case changes the post it acts on.
	$cases = array(
		'outsider cannot approve a submission they do not moderate' => array( $approve( 'outsider', 'forced' ), function () use ( $status ) {
			return 'awaiting-review' === $status( 'forced' );
		} ),
		'author cannot approve their own submission'               => array( $approve( 'author', 'picked' ), function () use ( $status ) {
			return 'awaiting-review' === $status( 'picked' );
		} ),
		'picked moderator cannot approve a published post'         => array( $approve( 'picked', 'published' ), function () use ( $status ) {
			return 'publish' === $status( 'published' );
		} ),
		'editor cannot approve without the nonce'                  => array( $approve( 'editor', 'forced', '' ), function () use ( $status ) {
			return 'awaiting-review' === $status( 'forced' );
		} ),
		'outsider cannot reject a submission they do not moderate' => array( $reject( 'outsider', 'picked' ), function ( $out ) use ( $status ) {
			return 'awaiting-review' === $status( 'picked' ) && false === strpos( $out, 'MAIL:' );
		} ),
		'outsider cannot reject a published post'                  => array( $reject( 'outsider', 'published' ), function ( $out ) use ( $status ) {
			return 'publish' === $status( 'published' ) && false === strpos( $out, 'MAIL:' );
		} ),
		'subscriber cannot use the admin Reject with a valid nonce' => array( $admin_reject( 'outsider', $admin_nonce ), function ( $out ) use ( $status ) {
			return 'publish' === $status( 'admin_reject' ) && false === strpos( $out, 'MAIL:' );
		} ),
		'editor cannot use the admin Reject without the nonce'     => array( $admin_reject( 'editor', '' ), function ( $out ) use ( $status ) {
			return 'publish' === $status( 'admin_reject' ) && false === strpos( $out, 'MAIL:' );
		} ),
		'editor approves a submission of a form editors moderate'  => array( $approve( 'editor', 'forced' ), function () use ( $status ) {
			return ! in_array( $status( 'forced' ), array( 'awaiting-review', false ), true );
		} ),
		'picked moderator rejects the submission and mails the author' => array( $reject( 'picked', 'picked' ), function ( $out ) use ( $status, $author_mail ) {
			return 'edit-draft' === $status( 'picked' ) && false !== strpos( $out, 'MAIL:' . wp_json_encode( array( $author_mail ) ) );
		} ),
		'editor admin Reject mails the author, not the address in the request' => array( $admin_reject( 'editor', $admin_nonce ), function ( $out ) use ( $status, $author_mail ) {
			return 'edit-draft' === $status( 'admin_reject' ) && false !== strpos( $out, 'MAIL:' . wp_json_encode( array( $author_mail ) ) ) && false === strpos( $out, 'attacker@example.com' );
		} ),
		'Moderators field keeps only allowed users, never the author' => array(
			array( 'run' => 'save_moderators', 'user' => $users['author'], 'nonce' => '', 'post_id' => $posts['field'], 'field' => $moderators_field, 'post' => array( 'buddyforms_moderators' => array( (string) $users['author'], (string) $users['picked'], (string) $users['editor'], '999999999' ) ) ),
			function () use ( &$posts, $users ) {
				$saved = array_map( 'intval', (array) get_post_meta( $posts['field'], 'buddyforms_moderators', true ) );
				$terms = wp_get_object_terms( $users['author'], 'buddyforms_moderators_posts', array( 'fields' => 'slugs' ) );
				return array( $users['picked'] ) === $saved && empty( $terms );
			},
		),
	);

	foreach ( $cases as $name => $test ) {
		list( $case, $check ) = $test;
		$result = WP_CLI::launch_self( 'eval-file', array( __FILE__, base64_encode( wp_json_encode( $case ) ) ), array(), false, true );
		wp_cache_flush();
		$ok = 0 === $result->return_code && $check( $result->stdout );
		$ok ? ++$passed : ++$failed;
		WP_CLI::line( ( $ok ? 'PASS ' : 'FAIL ' ) . $name );
		if ( ! $ok ) {
			WP_CLI::line( '     ' . trim( $result->stdout . ' ' . $result->stderr ) );
		}
	}
} finally {
	foreach ( $posts as $id ) {
		wp_delete_post( $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
	buddyforms_regenerate_global_options();
}

WP_CLI::line( sprintf( 'Results: %d passed, %d failed', $passed, $failed ) );
if ( $failed ) {
	throw new Exception( 'Moderators authorization regression failed' );
}

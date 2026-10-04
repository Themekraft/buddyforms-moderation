<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/*
 * Plugin Name: BuddyForms Moderation ( Former: Review Logic )
 * Plugin URI: https://themekraft.com/products/review/
 * Description: Create new drafts or pending moderations from new or published posts without changing the live version.
 * Version: 1.5.2
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Requires Plugins: buddyforms
 * Author: ThemeKraft
 * Author URI: https://themekraft.com/buddyforms/
 * License: GPLv2 or later
 * Text Domain: buddyforms-review
 * Domain Path: /languages
 * Svn: buddyforms-review
 *
 * @fs_premium_only /includes/moderators.php, /includes/moderators-taxonomy.php, /includes/moderators-reject.php
 *
 *****************************************************************************
 *
 * This script is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 ****************************************************************************
 */

if ( ! function_exists( 'buddyforms_addon_plugin_dependencies_slug' ) ) {
	/**
	 * Let the running BuddyForms copy (free or premium) satisfy "Requires Plugins: buddyforms".
	 *
	 * @param string $slug Dependency slug.
	 *
	 * @return string
	 */
	function buddyforms_addon_plugin_dependencies_slug( $slug ) {
		if ( 'buddyforms' === $slug && defined( 'BUDDYFORMS_INSTALL_PATH' ) ) {
			return basename( BUDDYFORMS_INSTALL_PATH );
		}

		return $slug;
	}

	add_filter( 'wp_plugin_dependencies_slug', 'buddyforms_addon_plugin_dependencies_slug' );
}

add_action( 'init', 'bf_moderation_includes', 10 );
function bf_moderation_includes() {
	global $buddyforms_new;
	if ( ! empty( $buddyforms_new ) ) {
		include_once dirname( __FILE__ ) . '/includes/buddyforms-moderation.php';
		include_once dirname( __FILE__ ) . '/includes/form-elements.php';
		include_once dirname( __FILE__ ) . '/includes/duplicate-post.php';
		include_once dirname( __FILE__ ) . '/includes/functions.php';
		if ( buddyforms_moderation_freemius()->is_paying_or_trial__premium_only() ) {
			include_once dirname( __FILE__ ) . '/includes/moderators-taxonomy.php';
			include_once dirname( __FILE__ ) . '/includes/moderators-form-element.php';
			include_once dirname( __FILE__ ) . '/includes/moderators-reject.php';
		}
		include_once dirname( __FILE__ ) . '/includes/shortcodes.php';
		define( 'BUDDYFORMS_MODERATION_ASSETS', plugins_url( 'assets/', __FILE__ ) );
		define( 'BUDDYFORMS_MODERATION_VERSION', '1.5.2' );
	}
}

add_action( 'init', 'buddyforms_moderation_load_plugin_textdomain', 5 );

/**
 * Load the textdomain for the plugin
 */
function buddyforms_moderation_load_plugin_textdomain() {
	load_plugin_textdomain( 'buddyforms-review', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

function buddyforms_moderation_error_log( $message ) {
	if ( ! empty( $message ) ) {
		error_log( 'BF Moderation -- ' . $message );
	}
}

function buddyforms_moderation_need_buddyforms() {
	?>
	<style>.buddyforms-notice label.buddyforms-title {
			background: rgba(0, 0, 0, 0.3);
			color: #fff;
			padding: 2px 10px;
			position: absolute;
			top: 100%;
			bottom: auto;
			right: auto;
			-moz-border-radius: 0 0 3px 3px;
			-webkit-border-radius: 0 0 3px 3px;
			border-radius: 0 0 3px 3px;
			left: 10px;
			font-size: 12px;
			font-weight: bold;
			cursor: auto;
		}

		.buddyforms-notice .buddyforms-notice-body {
			margin: .5em 0;
			padding: 2px;
		}

		.buddyforms-notice.buddyforms-title {
			margin-bottom: 30px !important;
		}

		.buddyforms-notice {
			position: relative;
		}</style>
	<div class="error buddyforms-notice buddyforms-title"><label class="buddyforms-title">BuddyForms Moderation</label>
		<div class="buddyforms-notice-body"><b>Oops...</b> BuddyForms Moderation cannot run without <a target="_blank" href="https://themekraft.com/buddyforms/">BuddyForms</a>.</div>
	</div>
	<?php
}

/**
 * Clean permalink
 *
 * @since 1.4.5
 */
function buddyform_moderation_activate() {
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'buddyform_moderation_activate' );

if ( ! function_exists( 'bfmod_fs' ) ) {
	// Create a helper function for easy SDK access.
	function buddyforms_moderation_freemius() {
		global $bfmod_fs;

		try {
			if ( ! isset( $bfmod_fs ) ) {
				// Include Freemius SDK.
				if ( file_exists( dirname( dirname( __FILE__ ) ) . '/buddyforms/includes/resources/freemius/start.php' ) ) {
					// Try to load SDK from parent plugin folder.
					require_once dirname( dirname( __FILE__ ) ) . '/buddyforms/includes/resources/freemius/start.php';
				} elseif ( file_exists( dirname( dirname( __FILE__ ) ) . '/buddyforms-premium/includes/resources/freemius/start.php' ) ) {
					// Try to load SDK from premium parent plugin folder.
					require_once dirname( dirname( __FILE__ ) ) . '/buddyforms-premium/includes/resources/freemius/start.php';
				}

				$bfmod_fs = fs_dynamic_init(
					array(
						'id'                  => '409',
						'slug'                => 'buddyforms-review',
						'type'                => 'plugin',
						'public_key'          => 'pk_b92e3b1876e342874bdc7f6e80d05',
						'is_premium'          => true,
						'premium_suffix'      => 'Professional',
						// If your addon is a serviceware, set this option to false.
						'has_premium_version' => true,
						'has_paid_plans'      => true,
						'trial'               => array(
							'days'               => 7,
							'is_require_payment' => true,
						),
						'parent'              => array(
							'id'         => '391',
							'slug'       => 'buddyforms',
							'public_key' => 'pk_dea3d8c1c831caf06cfea10c7114c',
							'name'       => 'BuddyForms',
						),
						'menu'                => array(
							'slug'    => 'buddyforms',
							'support' => false,
						),
						'bundle_license_auto_activation' => true,
					)
				);

			}
		} catch ( Freemius_Exception $e ) {
			buddyforms_moderation_error_log( $e->getMessage() );
		}

		return $bfmod_fs;
	}
}

function bfmod_fs_is_parent_active_and_loaded() {
	// Check if the parent's init SDK method exists.
	return function_exists( 'buddyforms_core_fs' );
}

function bfmod_fs_is_parent_active() {
	$active_plugins = get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		$network_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
		$active_plugins         = array_merge( $active_plugins, array_keys( $network_active_plugins ) );
	}

	foreach ( $active_plugins as $basename ) {
		if ( 0 === strpos( strtolower( $basename ), 'buddyforms/' ) || 0 === strpos( strtolower( $basename ), 'buddyforms-premium/' ) ) {
			return true;
		}
	}

	return false;
}

function bfmod_fs_init() {
	if ( bfmod_fs_is_parent_active_and_loaded() ) {
		// Init Freemius.
		buddyforms_moderation_freemius();
		// Signal that the add-on's SDK was initiated.
		do_action( 'bfmod_fs_loaded' );
	} else {
		add_action( 'admin_notices', 'buddyforms_moderation_need_buddyforms' );
	}
}

if ( bfmod_fs_is_parent_active_and_loaded() ) {
	// If parent already included, init add-on.
	bfmod_fs_init();
} elseif ( bfmod_fs_is_parent_active() ) {
	// Init add-on only after the parent is loaded.
	add_action( 'buddyforms_core_fs_loaded', 'bfmod_fs_init' );
} else {
	// Even though the parent is not activated, execute add-on for activation / uninstall hooks.
	bfmod_fs_init();
}

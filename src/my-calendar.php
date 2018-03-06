<?php
/*
Plugin Name: My Calendar
Plugin URI: http://www.joedolson.com/my-calendar/
Description: Accessible WordPress event calendar plugin. Show events from multiple calendars on pages, in posts, or in widgets.
Author: Joseph C Dolson
Author URI: http://www.joedolson.com
Text Domain: my-calendar
Domain Path: lang
Version: 2.6.0-beta1
*/
/*  Copyright 2009-2018  Joe Dolson (email : joe@joedolson.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

global $mc_version, $wpdb;
$mc_version = '2.6.0-beta1';

define( 'MC_DEBUG', false );

register_activation_hook( __FILE__, 'mc_plugin_activated' );
register_deactivation_hook( __FILE__, 'mc_plugin_deactivated' );
function mc_plugin_activated() {
	flush_rewrite_rules();
	if ( my_calendar_exists() ) {
		mc_upgrade_db();
	}
	my_calendar_check();
}

register_activation_hook(__FILE__, 'mc_activation_hook');
function mc_activation_hook() {
    $required_php_version = '5.3.0';

    if ( version_compare( PHP_VERSION, $required_php_version, '<' ) ) { 
		$plugin_data = get_plugin_data(__FILE__, false);
		$message = sprintf( __( '%s requires PHP version %s or higher. Your current PHP version is %s', 'my-calendar' ), $plugin_data['Name'], $required_php_version, phpversion() );
		echo "<div class='error'><p>$message</p></div>";
		exit;
    } 
}

function mc_plugin_deactivated() {
	flush_rewrite_rules();
}

include( dirname( __FILE__ ) . '/includes/date-utilities.php' );
include( dirname( __FILE__ ) . '/includes/general-utilities.php' );
include( dirname( __FILE__ ) . '/includes/kses.php' );
include( dirname( __FILE__ ) . '/includes/screen-options.php' );
include( dirname( __FILE__ ) . '/includes/db.php' );
include( dirname( __FILE__ ) . '/includes/deprecated.php' );
include( dirname( __FILE__ ) . '/my-calendar-core.php' );
include( dirname( __FILE__ ) . '/my-calendar-install.php' );
include( dirname( __FILE__ ) . '/my-calendar-settings.php' );
include( dirname( __FILE__ ) . '/my-calendar-categories.php' );
include( dirname( __FILE__ ) . '/my-calendar-locations.php' );
include( dirname( __FILE__ ) . '/my-calendar-location-manager.php' );
include( dirname( __FILE__ ) . '/my-calendar-help.php' );
include( dirname( __FILE__ ) . '/my-calendar-event-manager.php' );
include( dirname( __FILE__ ) . '/my-calendar-styles.php' );
include( dirname( __FILE__ ) . '/my-calendar-behaviors.php' );
include( dirname( __FILE__ ) . '/my-calendar-events.php' );
include( dirname( __FILE__ ) . '/my-calendar-widgets.php' );
include( dirname( __FILE__ ) . '/my-calendar-upgrade-db.php' );
include( dirname( __FILE__ ) . '/my-calendar-output.php' );
include( dirname( __FILE__ ) . '/my-calendar-print.php' );
include( dirname( __FILE__ ) . '/my-calendar-templates.php' );
include( dirname( __FILE__ ) . '/my-calendar-limits.php' );
include( dirname( __FILE__ ) . '/my-calendar-shortcodes.php' );
include( dirname( __FILE__ ) . '/my-calendar-templating.php' );
include( dirname( __FILE__ ) . '/my-calendar-group-manager.php' );
include( dirname( __FILE__ ) . '/my-calendar-api.php' );
include( dirname( __FILE__ ) . '/my-calendar-generator.php' );

// Enable internationalisation
add_action( 'plugins_loaded', 'mc_load_textdomain' );
function mc_load_textdomain() {
	// don't change this; just gradually remove shipped translations if .org trans become complete(r).
	load_plugin_textdomain( 'my-calendar', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
}

// Add actions
add_action( 'admin_menu',          'my_calendar_menu' );
add_action( 'wp_head',             'my_calendar_head' );
add_action( 'delete_user',         'mc_deal_with_deleted_user' );
add_action( 'widgets_init',        'mc_register_widgets' );
add_action( 'init',                'my_calendar_add_feed' );
add_action( 'wp_footer',           'mc_footer_js' );
add_action( 'init',                'mc_export_vcal', 200 );
// Add filters 
add_filter( 'widget_text',         'do_shortcode', 9 );
add_filter( 'plugin_action_links', 'mc_plugin_action', 10, 2 );
add_filter( 'wp_title',            'mc_event_filter', 10, 3 );

/**
 * Register all My Calendar widgets
 */
function mc_register_widgets() {
	register_widget( 'my_calendar_today_widget' );
	register_widget( 'my_calendar_upcoming_widget' );
	register_widget( 'my_calendar_mini_widget' );
	register_widget( 'my_calendar_simple_search' );
	register_widget( 'my_calendar_filters' );
}


/** 
 * Customize canonical URL for My Calendar custom links
 */ 
add_action( 'init', 'mc_custom_canonical' );
function mc_custom_canonical() {
	add_action( 'wp_head', 'mc_canonical' );
	remove_action( 'wp_head', 'rel_canonical' );
}

function mc_canonical() {
	// original code
	if ( !is_singular() ) {
		return;
	}
	
	global $wp_the_query;
	if ( !$id = $wp_the_query->get_queried_object_id() ) {
		return;
	}

	// original code
	$link = get_permalink( $id );
	if ( $page = get_query_var('cpage') ) {
		$link = get_comments_pagenum_link( $page );
	}
	if ( isset( $_GET['mc_id'] ) ) { 
		$mc_id = ( absint( $_GET['mc_id'] ) ) ? $_GET['mc_id'] : false;
		$link = add_query_arg( 'mc_id', $mc_id, $link );
	}
	
	echo "<link rel='canonical' href='$link' />\n";	
}

/**
 * Produce My Calendar admin sidebar
 * 
 * @param string $show deprecated
 * @param mixed $add boolean or array
 * @param boolean $remove Hide commercial blocks
 *
 * @return string HTML output
 */
function mc_show_sidebar( $show = '', $add = false, $remove = false ) {
	$add = apply_filters( 'mc_custom_sidebar_panels', $add );

	if ( current_user_can( 'mc_view_help' ) ) {
		?>
		<div class="postbox-container jcd-narrow">
		<div class="metabox-holder">
		<?php if ( is_array( $add ) ) {
			foreach ( $add as $key => $value ) {
				?>
				<div class="ui-sortable meta-box-sortables">
					<div class="postbox">
						<h2 class='hndle'><?php echo $key; ?></h2>

						<div class='<?php echo sanitize_title( $key ); ?> inside'>
							<?php echo $value; ?>
						</div>
					</div>
				</div>
			<?php
			}
		} 
		if ( ! $remove ) { 
			if ( ! function_exists( 'mcs_submit_exists' ) ) { ?>
				<div class="ui-sortable meta-box-sortables">
					<div class="postbox sell support">
						<h2 class='sales hndle'><strong><?php _e( 'My Calendar Pro', 'my-calendar' ); ?></strong></h2>

						<div class="inside resources">
							<p class="mcbuy"><?php printf( __( "Buy <a href='%s' rel='external'>My Calendar Pro</a> &mdash; a more powerful calendar for your site.", 'my-calendar' ), 'https://www.joedolson.com/my-calendar/pro/' ); ?></p>
						</div>
					</div>
				</div>
			<?php 
			}
			if ( ! function_exists( 'mt_update_check' ) ) { 
			?>
				<div class="ui-sortable meta-box-sortables">
					<div class="postbox sell my-tickets">
						<h2 class='sales hndle'><strong><?php _e( 'My Tickets', 'my-calendar' ); ?></strong></h2>

						<div class="inside resources">
							<p class="mcbuy"><?php printf( __( 'Do you sell tickets to your events? <a href="%s" class="thickbox open-plugin-details-modal" rel="external">Use My Tickets</a> and sell directly from My Calendar.', 'my-calendar' ), admin_url( 'plugin-install.php?tab=plugin-information&plugin=my-tickets&TB_iframe=true&width=600&height=550' ) ); ?></p>
						
						</div>
					</div>
				</div>
			<?php 
			}
			if ( ! function_exists( 'mcs_submit_exists' ) ) { 
			?>
			<div class="ui-sortable meta-box-sortables">
				<div class="postbox support">
					<h2 class='hndle'><strong><?php _e( 'Support This Plug-in', 'my-calendar' ); ?></strong></h2>

					<div class="inside resources">
						<p class="follow-me">
							<a href="https://twitter.com/intent/follow?screen_name=joedolson"
							   class="twitter-follow-button" data-size="small" data-related="joedolson">Follow
								@joedolson</a>
							<script>!function (d, s, id) {
									var js, fjs = d.getElementsByTagName(s)[0];
									if (!d.getElementById(id)) {
										js = d.createElement(s);
										js.id = id;
										js.src = "https://platform.twitter.com/widgets.js";
										fjs.parentNode.insertBefore(js, fjs);
									}
								}(document, "script", "twitter-wjs");</script>
						</p>

						<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
							<p class="mcd">
								<input type="hidden" name="cmd" value="_s-xclick" />
								<input type="hidden" name="hosted_button_id" value="UZBQUG2LKKMRW" />
								<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" name="submit" alt="<?php _e( 'Make a Donation', 'my-calendar' ); ?>" />
							</p>
						</form>
					</div>
				</div>
			</div>
			<?php } ?>
		<?php } ?>
		<div class="ui-sortable meta-box-sortables">
			<div class="postbox">
				<h2 class='hndle'><?php _e( 'Get Help', 'my-calendar' ); ?></h2>

				<div class="inside">
					<ul>
						<li>
							<strong><a href="https://docs.joedolson.com/my-calendar/quick-start/"><?php _e( "Documentation", 'my-calendar' ); ?></a></strong>
						</li>
						<li>
							<strong><a href="<?php echo admin_url( "admin.php?page=my-calendar-help" ); ?>#mc-generator"><?php _e( "Shortcode Generator", 'my-calendar' ); ?></a></strong>
						</li>
						<li>
							<a href="<?php echo admin_url( "admin.php?page=my-calendar-help" ); ?>#get-support"><?php _e( "Get Support", 'my-calendar' ); ?></a>
						</li>
						<li>
							<div class="dashicons dashicons-editor-help" aria-hidden='true'></div>
							<a href="<?php echo admin_url( "admin.php?page=my-calendar-help" ); ?>"><?php _e( "My Calendar Help", 'my-calendar' ); ?></a>
						</li>
						<li>
							<div class="dashicons dashicons-yes" aria-hidden='true'></div>
							<a href="http://profiles.wordpress.org/users/joedolson/"><?php _e( 'Check out my other plug-ins', 'my-calendar' ); ?></a>
						</li>
						<li>
							<div class="dashicons dashicons-star-filled" aria-hidden='true'></div>
							<a href="http://wordpress.org/support/plugin/my-calendar/reviews/?filter=5"><?php _e( 'Rate this plug-in 5 stars!', 'my-calendar' ); ?></a>
						</li>
						<li>
							<div class="dashicons dashicons-translation" aria-hidden='true'></div>
							<a href="http://translate.joedolson.com/projects/my-calendar"><?php _e( 'Help translate this plug-in!', 'my-calendar' ); ?></a>
						</li>
					</ul>					
				</div>
			</div>
		</div>
		</div>
		</div>
	<?php
	}
}

/**
 * Add My Calendar menu items to main admin menu
 *
 */
function my_calendar_menu() {
	$icon_path = plugins_url( '/my-calendar/images' );

	if ( function_exists( 'add_menu_page' ) ) {
		if ( get_option( 'mc_remote' ) != 'true' ) {
			add_menu_page( __( 'My Calendar', 'my-calendar' ), __( 'My Calendar', 'my-calendar' ), 'mc_add_events', apply_filters( 'mc_modify_default', 'my-calendar' ), apply_filters( 'mc_modify_default_cb', 'my_calendar_edit' ), $icon_path . '/icon.png' );
		} else {
			add_menu_page( __( 'My Calendar', 'my-calendar' ), __( 'My Calendar', 'my-calendar' ), 'mc_edit_settings', 'my-calendar', 'my_calendar_settings', $icon_path . '/icon.png' );
		}
	}
	if ( function_exists( 'add_submenu_page' ) ) {
		add_action( "admin_head", 'my_calendar_write_js' );
		add_action( "admin_enqueue_scripts", 'my_calendar_add_styles' );
		if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
			// if we're accessing a remote site, remove these pages.
		} else {
			if ( isset( $_GET['event_id'] ) ) {
				$event_id   = absint( $_GET['event_id'] );
				$page_title = sprintf( __( 'Editing Event: %s', 'my-calendar' ), mc_get_data( 'event_title', $event_id ) );
			} else {
				$page_title = __( 'Add New Event', 'my-calendar' );
			}
			$edit = add_submenu_page( apply_filters( 'mc_locate_events_page', 'my-calendar' ), $page_title, __( 'Add New Event', 'my-calendar' ), 'mc_add_events', 'my-calendar', 'my_calendar_edit' );
			add_action( "load-$edit", 'mc_event_editing' );
			$manage = add_submenu_page( 'my-calendar', __( 'Manage Events', 'my-calendar' ), __( '&rarr; Manage Events', 'my-calendar' ), 'mc_add_events', 'my-calendar-manage', 'my_calendar_manage' );
			add_action( "load-$manage", 'mc_add_screen_option' );
			$groups = add_submenu_page( 'my-calendar', __( 'Event Groups', 'my-calendar' ), __( '&rarr; Event Groups', 'my-calendar' ), 'mc_manage_events', 'my-calendar-groups', 'my_calendar_group_edit' );
			add_action( "load-$groups", 'mc_add_screen_option' );			
			add_submenu_page( 'my-calendar', __( 'Add Event Locations', 'my-calendar' ), __( 'Add New Location', 'my-calendar' ), 'mc_edit_locations', 'my-calendar-locations', 'my_calendar_add_locations' );
			add_submenu_page( 'my-calendar', __( 'Manage Event Locations', 'my-calendar' ), __( '&rarr; Manage Locations', 'my-calendar' ), 'mc_edit_locations', 'my-calendar-location-manager', 'my_calendar_manage_locations' );
			add_submenu_page( 'my-calendar', __( 'Event Categories', 'my-calendar' ), __( 'Manage Categories', 'my-calendar' ), 'mc_edit_cats', 'my-calendar-categories', 'my_calendar_manage_categories' );
		}
		add_submenu_page( 'my-calendar', __( 'Style Editor', 'my-calendar' ), __( 'Style Editor', 'my-calendar' ), 'mc_edit_styles', 'my-calendar-styles', 'my_calendar_style_edit' );
		add_submenu_page( 'my-calendar', __( 'Script Manager', 'my-calendar' ), __( 'Script Manager', 'my-calendar' ), 'mc_edit_behaviors', 'my-calendar-behaviors', 'my_calendar_behaviors_edit' );
		add_submenu_page( 'my-calendar', __( 'Template Editor', 'my-calendar' ), __( 'Template Editor', 'my-calendar' ), 'mc_edit_templates', 'my-calendar-templates', 'mc_templates_edit' );
		add_submenu_page( 'my-calendar', __( 'Settings', 'my-calendar' ), __( 'Settings', 'my-calendar' ), 'mc_edit_settings', 'my-calendar-config', 'my_calendar_settings' );
		add_submenu_page( 'my-calendar', __( 'My Calendar Help', 'my-calendar' ), __( 'Help', 'my-calendar' ), 'mc_view_help', 'my-calendar-help', 'my_calendar_help' );
	}
	if ( function_exists( 'mcs_submissions' ) ) {
		$permission = apply_filters( 'mcs_submission_permissions', 'manage_options' );
		add_action( "admin_head", 'my_calendar_sub_js' );
		add_action( "admin_head", 'my_calendar_sub_styles' );
		add_submenu_page( 'my-calendar', __( 'My Calendar Pro Settings', 'my-calendar' ), __( 'My Calendar Pro', 'my-calendar' ), $permission, 'my-calendar-submissions', 'mcs_settings' );
		// only show payments screen if enabled
		if ( get_option( 'mcs_payments' ) == 'true' ) {
			add_submenu_page( 'my-calendar', __( 'Payments Received', 'my-calendar' ), __( 'Payments', 'my-calendar' ), $permission, 'my-calendar-payments', 'mcs_sales_page' );
		}
	}
}

// add shortcode interpreters
add_shortcode( 'my_calendar',                'my_calendar_insert' );
add_shortcode( 'my_calendar_upcoming',       'my_calendar_insert_upcoming' );
add_shortcode( 'my_calendar_today',          'my_calendar_insert_today' );
add_shortcode( 'my_calendar_locations',      'my_calendar_locations' );
add_shortcode( 'my_calendar_categories',     'my_calendar_categories' );
add_shortcode( 'my_calendar_access',         'my_calendar_access' );
add_shortcode( 'mc_filters',                 'my_calendar_filters' );
add_shortcode( 'my_calendar_show_locations', 'my_calendar_show_locations_list' );
add_shortcode( 'my_calendar_event',          'my_calendar_show_event' );
add_shortcode( 'my_calendar_search',         'my_calendar_search' );
add_shortcode( 'my_calendar_now',            'my_calendar_now' );
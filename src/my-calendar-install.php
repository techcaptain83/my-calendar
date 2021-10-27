<?php
/**
 * Installation process. Create tables, default options, etc.
 *
 * @category Core
 * @package  My Calendar
 * @author   Joe Dolson
 * @license  GPLv2 or later
 * @link     https://www.joedolson.com/my-calendar/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default settings for widgets.
 *
 * @return array
 */
function mc_widget_defaults() {
	$default_template = '<strong>{timerange after=", "}{daterange}</strong> &#8211; {linking_title}';

	$defaults = array(
		'upcoming' => array(
			'type'     => 'event',
			'before'   => 0,
			'after'    => 5,
			'template' => $default_template,
			'category' => '',
			'text'     => '',
			'title'    => 'Upcoming Events',
		),
		'today'    => array(
			'template' => $default_template,
			'category' => '',
			'title'    => 'Today\'s Events',
			'text'     => '',
		),
	);

	return apply_filters( 'mc_widget_defaults', $defaults );
}

/**
 * Define variables to be saved in settings. (Formerly globals.)
 *
 * @return array
 */
function mc_globals() {
	global $wpdb;

	$grid_template = '
<span class="event-time value-title">{time}{endtime before="<span class=\'time-separator\'> - </span><span class=\'end-time\'>" after="</span>"}</span>
{image before="<div class=\'mc-event-image\'>" after="</div>"}
<div class="sub-details">
	{hcard before="<div class=\'mc-location\'>" after="</div>"}
	{excerpt before="<div class=\'mc-excerpt\'>" after="</div>"}
</div>';

	$single_template = '
<span class="event-time value-title" title="{dtstart}">{time}<span class="time-separator"> - </span><span class="end-time value-title" title="{dtend}">{endtime}</span></span>
{image before="<div class=\'mc-event-image\'>" after="</div>"}
<div class="event-data">
	{runtime before="<p class=\'mc-runtime\'>" after="</p>"}
	{categories before="<p class=\'mc-categories\'>" after="</p>"}
</div>
<div class="sub-details">
	{hcard before="<div class=\'mc-location\'>" after="</div>"}
	{description before="<div class=\'mc-description\'>" after="</div>"}
	{map before="<div class=\'mc-map\'>" after="</div>"}
</div>';

	$charset_collate  = $wpdb->get_charset_collate();
	$event_fifth_week = ( mc_no_fifth_week() ) ? 1 : 0;

	$initial_db = 'CREATE TABLE ' . my_calendar_table() . " (
 event_id INT(11) NOT NULL AUTO_INCREMENT,
 event_begin DATE NOT NULL,
 event_end DATE NOT NULL,
 event_title VARCHAR(255) NOT NULL,
 event_desc TEXT NOT NULL,
 event_short TEXT NOT NULL,
 event_registration TEXT NOT NULL,
 event_tickets VARCHAR(255) NOT NULL,
 event_time TIME,
 event_endtime TIME,
 event_recur CHAR(3),
 event_repeats TEXT,
 event_status INT(1) NOT NULL DEFAULT '1',
 event_author BIGINT(20) UNSIGNED,
 event_host BIGINT(20) UNSIGNED,
 event_category BIGINT(20) UNSIGNED NOT NULL DEFAULT '1',
 event_link TEXT,
 event_post BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
 event_link_expires TINYINT(1) NOT NULL,
 event_location BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
 event_label VARCHAR(255) NOT NULL,
 event_street VARCHAR(255) NOT NULL,
 event_street2 VARCHAR(255) NOT NULL,
 event_city VARCHAR(255) NOT NULL,
 event_state VARCHAR(255) NOT NULL,
 event_postcode VARCHAR(10) NOT NULL,
 event_region VARCHAR(255) NOT NULL,
 event_country VARCHAR(255) NOT NULL,
 event_url TEXT,
 event_longitude FLOAT(10,6) NOT NULL DEFAULT '0',
 event_latitude FLOAT(10,6) NOT NULL DEFAULT '0',
 event_zoom INT(2) NOT NULL DEFAULT '14',
 event_phone VARCHAR(32) NOT NULL,
 event_phone2 VARCHAR(32) NOT NULL,
 event_access TEXT,
 event_group_id INT(11) NOT NULL DEFAULT '0',
 event_span INT(1) NOT NULL DEFAULT '0',
 event_approved INT(1) NOT NULL DEFAULT '1',
 event_flagged INT(1) NOT NULL DEFAULT '0',
 event_hide_end INT(1) NOT NULL DEFAULT '0',
 event_holiday INT(1) NOT NULL DEFAULT '0',
 event_fifth_week INT(1) NOT NULL DEFAULT '$event_fifth_week',
 event_image TEXT,
 event_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY  (event_id),
 KEY event_category (event_category)
 ) $charset_collate;";

	$initial_occur_db = 'CREATE TABLE ' . my_calendar_event_table() . " (
 occur_id INT(11) NOT NULL AUTO_INCREMENT,
 occur_event_id INT(11) NOT NULL,
 occur_begin DATETIME NOT NULL,
 occur_end DATETIME NOT NULL,
 occur_group_id INT(11) NOT NULL DEFAULT '0',
 PRIMARY KEY  (occur_id),
 KEY occur_event_id (occur_event_id)
 ) $charset_collate;";

	$initial_cat_db = 'CREATE TABLE ' . my_calendar_categories_table() . " (
 category_id INT(11) NOT NULL AUTO_INCREMENT,
 category_name VARCHAR(255) NOT NULL,
 category_color VARCHAR(7) NOT NULL,
 category_icon VARCHAR(128) NOT NULL,
 category_private INT(1) NOT NULL DEFAULT '0',
 category_term INT(11) NOT NULL DEFAULT '0',
 PRIMARY KEY  (category_id)
 ) $charset_collate;";

	$initial_loc_rel_db = 'CREATE TABLE ' . my_calendar_location_relationships_table() . " (
 relationship_id INT(11) NOT NULL AUTO_INCREMENT,
 location_id INT(11) NOT NULL,
 post_id INT(11) NOT NULL DEFAULT '1',
 PRIMARY KEY  (relationship_id),
 KEY location_id (location_id)
 ) $charset_collate;";

	$initial_rel_db = 'CREATE TABLE ' . my_calendar_category_relationships_table() . " (
 relationship_id INT(11) NOT NULL AUTO_INCREMENT,
 event_id INT(11) NOT NULL,
 category_id INT(11) NOT NULL DEFAULT '1',
 PRIMARY KEY  (relationship_id),
 KEY event_id (event_id)
 ) $charset_collate;";

	$initial_loc_db = 'CREATE TABLE ' . my_calendar_locations_table() . " (
 location_id INT(11) NOT NULL AUTO_INCREMENT,
 location_label VARCHAR(255) NOT NULL,
 location_street VARCHAR(255) NOT NULL,
 location_street2 VARCHAR(255) NOT NULL,
 location_city VARCHAR(255) NOT NULL,
 location_state VARCHAR(255) NOT NULL,
 location_postcode VARCHAR(10) NOT NULL,
 location_region VARCHAR(255) NOT NULL,
 location_url TEXT,
 location_country VARCHAR(255) NOT NULL,
 location_longitude FLOAT(10,6) NOT NULL DEFAULT '0',
 location_latitude FLOAT(10,6) NOT NULL DEFAULT '0',
 location_zoom INT(2) NOT NULL DEFAULT '14',
 location_phone VARCHAR(32) NOT NULL,
 location_phone2 VARCHAR(32) NOT NULL,
 location_access TEXT,
 PRIMARY KEY  (location_id)
 ) $charset_collate;";

	$globals = array(
		'initial_db'         => $initial_db,
		'initial_occur_db'   => $initial_occur_db,
		'initial_rel_db'     => $initial_rel_db,
		'initial_loc_db'     => $initial_loc_db,
		'initial_loc_rel_db' => $initial_loc_rel_db,
		'initial_cat_db'     => $initial_cat_db,
		'grid_template'      => addslashes( $grid_template ),
		'list_template'      => addslashes( $grid_template ),
		'mini_template'      => addslashes( $grid_template ),
		'single_template'    => addslashes( $single_template ),
	);

	return $globals;
}

/**
 * Create demo content for new installs.
 */
function mc_create_demo_content() {
	global $wpdb;
	// If site has no categories, this is a new install.
	$categories = $wpdb->get_results( 'SELECT category_id FROM ' . my_calendar_categories_table() );  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( empty( $categories ) ) {
		// Insert a category.
		mc_create_category(
			array(
				'category_name'  => 'General',
				'category_color' => '#ffffcc',
				'category_icon'  => 'event.svg',
			)
		);
		// Insert a location.
		$access  = array( 1, 2, 3, 4, 6, 8, 9 );
		$add     = array(
			'location_label'     => 'Minnesota Orchestra',
			'location_street'    => '1111 Nicollet Mall',
			'location_street2'   => '',
			'location_city'      => 'Minneapolis',
			'location_state'     => 'MN',
			'location_postcode'  => '55403',
			'location_region'    => '',
			'location_country'   => 'United States',
			'location_url'       => 'https://www.minnesotaorchestra.org',
			'location_latitude'  => '44.9722',
			'location_longitude' => '-93.2749',
			'location_zoom'      => 16,
			'location_phone'     => '612-371-5600',
			'location_phone2'    => '',
			'location_access'    => serialize( $access ),
		);
		$results = mc_insert_location( $add );
		do_action( 'mc_save_location', $results, $add, $add );
		// Insert an event.
		$submit = array(
			// Begin strings.
			'event_begin'        => date( 'Y-m-d', strtotime( '+1 day' ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			'event_end'          => date( 'Y-m-d', strtotime( '+1 day' ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			'event_title'        => 'Florence Price: Symphony No. 3 in c minor',
			'event_desc'         => "<p>Florence Price's <a href='https://en.wikipedia.org/wiki/Symphony_No._3_(Price)'>Symphony No. 3</a> was commissioned by the Works Progress Administration's <a href='https://en.wikipedia.org/wiki/Federal_Music_Project'>Federal Music Project</a> during the height of the Great Depression. It was first performed at the Detroit Institute of Arts on November 6, 1940, by the Detroit Civic Orchestra under the conductor Valter Poole.</p><p>The composition is Price's third symphony, following her Symphony in E minor—the first symphony by a black woman to be performed by a major American orchestra—and her lost Symphony No. 2.</p>",
			'event_short'        => "Florence Price's Symphony No.3 was first performed on November 6th, 1940. It was Ms. Price's third symphony, following her lost Symphony No. 2",
			'event_time'         => '19:30:00',
			'event_endtime'      => '21:00:00',
			'event_link'         => 'https://www.youtube.com/watch?v=1jgJ1OkjnaI&list=OLAK5uy_lKldgbFTYBDa7WN6jf2ubB595wncDU7yc&index=2',
			'event_label'        => '',
			'event_street'       => '',
			'event_street2'      => '',
			'event_city'         => '',
			'event_state'        => '',
			'event_postcode'     => '',
			'event_region'       => '',
			'event_country'      => '',
			'event_url'          => '',
			'event_recur'        => 'S1',
			'event_image'        => plugins_url( '/images/demo/event.jpg', __FILE__ ),
			'event_phone'        => '',
			'event_phone2'       => '',
			'event_access'       => '',
			'event_tickets'      => '',
			'event_registration' => '',
			'event_repeats'      => '',
			// Begin integers.
			'event_author'       => wp_get_current_user()->ID,
			'event_category'     => 1,
			'event_link_expires' => 0,
			'event_zoom'         => 16,
			'event_approved'     => 1,
			'event_host'         => wp_get_current_user()->ID,
			'event_flagged'      => 0,
			'event_fifth_week'   => 0,
			'event_holiday'      => 0,
			'event_group_id'     => 1,
			'event_span'         => 0,
			'event_hide_end'     => 0,
			// Begin floats.
			'event_longitude'    => '',
			'event_latitude'     => '',
			// Array: removed before DB insertion.
			'event_categories'   => array( 1 ),
		);

		$event    = array( true, false, $submit, false );
		$response = my_calendar_save( 'add', $event );
		$event_id = $response['event_id'];
		mc_update_event( 'event_location', (int) $results, $event_id );

		$e       = mc_get_first_event( $event_id );
		$post_id = $e->event_post;
		$image   = media_sideload_image( plugins_url( '/images/demo/event.jpg', __FILE__ ), $post_id, null, 'id' );

		set_post_thumbnail( $post_id, $image );
	}
}

/**
 * Save default settings.
 */
function mc_default_settings() {
	$globals = mc_globals();
	foreach ( $globals as $key => $global ) {
		${$key} = $global;
	}
	add_option( 'mc_display_single', array( 'author', 'host', 'ical', 'address', 'gcal', 'description', 'image', 'tickets', 'access', 'link', 'gmap_link' ) );
	add_option( 'mc_display_main', array( 'address', 'excerpt', 'image', 'tickets', 'access', 'gmap_link', 'more' ) );
	add_option( 'mc_display_mini', array( 'excerpt', 'image', 'more' ) );
	add_option( 'mc_use_permalinks', 'true' );
	add_option( 'mc_use_styles', 'false' );
	add_option( 'mc_show_months', 1 );
	add_option( 'mc_calendar_javascript', 0 );
	add_option( 'mc_list_javascript', 0 );
	add_option( 'mc_mini_javascript', 0 );
	add_option( 'mc_ajax_javascript', 0 );
	add_option( 'mc_notime_text', 'All day' );
	add_option( 'mc_hide_icons', 'true' );
	add_option( 'mc_event_link_expires', 'false' );
	add_option( 'mc_apply_color', 'background' );
	add_option( 'mc_input_options', mc_input_defaults() );
	add_option( 'mc_input_options_administrators', 'false' );
	add_site_option( 'mc_multisite', '0' );
	add_option( 'mc_event_mail', 'false' );
	add_option( 'mc_week_format', "M j, 'y" );
	add_option( 'mc_date_format', get_option( 'date_format' ) );
	// This option *must* be complete, if it's partial we get errors. So use update instead of add.
	update_option(
		'mc_templates',
		array(
			'title'      => '{time}: {title}',
			'title_list' => '{title}',
			'title_solo' => '{title}',
			'link'       => __( 'More information', 'my-calendar' ),
			'grid'       => $grid_template,
			'list'       => $list_template,
			'mini'       => $mini_template,
			'details'    => $single_template,
			'label'      => __( 'Read more', 'my-calendar' ),
		)
	);
	add_option( 'mc_css_file', 'twentytwenty.css' );
	add_option(
		'mc_style_vars',
		array(
			'--primary-dark'    => '#313233',
			'--primary-light'   => '#fff',
			'--secondary-light' => '#fff',
			'--secondary-dark'  => '#000',
			'--highlight-dark'  => '#666',
			'--highlight-light' => '#efefef',
		)
	);
	add_option( 'mc_time_format', get_option( 'time_format' ) );
	add_option( 'mc_show_weekends', 'true' );
	add_option( 'mc_convert', 'true' );
	add_option( 'mc_show_event_vcal', 'false' );
	add_option( 'mc_multisite_show', 0 );
	add_option( 'mc_topnav', 'toggle,timeframe,jump,nav' );
	add_option( 'mc_bottomnav', 'key,print' );
	add_option( 'mc_default_direction', 'DESC' );
	update_option( 'mc_update_notice', 1 );
	mc_add_roles();
	$has_uri = mc_guess_calendar();
	if ( false === $has_uri['response'] ) {
		// if mc_guess_calendar returns a string, no valid URI was found.
		$slug = sanitize_title( __( 'My Calendar', 'my-calendar' ) );
		mc_generate_calendar_page( $slug );
	}
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $initial_db );
	dbDelta( $initial_occur_db );
	dbDelta( $initial_cat_db );
	dbDelta( $initial_rel_db );
	dbDelta( $initial_loc_db );
	dbDelta( $initial_loc_rel_db );
}

/**
 * Create new calendar page
 *
 * @param string $slug Intended page name.
 *
 * @return int $post_ID for new page or previously existing page with same name.
 */
function mc_generate_calendar_page( $slug ) {
	global $current_user;
	$current_user = wp_get_current_user();
	if ( ! get_page_by_path( $slug ) ) {
		$page      = array(
			'post_title'   => __( 'My Calendar', 'my-calendar' ),
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => $current_user->ID,
			'ping_status'  => 'closed',
			'post_content' => '[my_calendar id="my-calendar"]',
		);
		$post_ID   = wp_insert_post( $page );
		$post_slug = wp_unique_post_slug( $slug, $post_ID, 'publish', 'page', 0 );
		wp_update_post(
			array(
				'ID'        => $post_ID,
				'post_name' => $post_slug,
			)
		);
	} else {
		$post    = get_page_by_path( $slug );
		$post_ID = $post->ID;
	}
	update_option( 'mc_uri', get_permalink( $post_ID ) );
	update_option( 'mc_uri_id', $post_ID );

	return $post_ID;
}

/**
 * See whether there are importable calendars present.
 */
function mc_check_imports() {
	$output = '';
	if ( 'true' !== get_option( 'ko_calendar_imported' ) ) {
		if ( function_exists( 'check_calendar' ) ) {
			$output .= "
			<div id='message' class='updated'>
				<p>" . __( 'My Calendar has identified that you have the Calendar plugin by Kieran O\'Shea installed. You can import those events and categories into the My Calendar database. Would you like to import these events?', 'my-calendar' ) . '</p>
				<form method="post" action="' . admin_url( 'admin.php?page=my-calendar-config' ) . '">
					<div>
						<input type="hidden" name="_wpnonce" value="' . wp_create_nonce( 'my-calendar-nonce' ) . '" />
					</div>
					<div>
						<input type="hidden" name="import" value="true"/>
						<input type="submit" value="' . __( 'Import from Calendar', 'my-calendar' ) . '" name="import-calendar" class="button-primary"/>
					</div>
				</form>
				<p>' . __( 'Although it is possible that this import could fail to import your events correctly, it should not have any impact on your existing Calendar database.', 'my-calendar' ) . '</p>
			</div>';
		}
	}

	echo $output;
}

/**
 * Transition event categories to category relationships
 *
 * @since 3.0.0
 */
function mc_transition_categories() {
	global $wpdb;
	$results = $wpdb->get_results( 'SELECT event_id, event_category FROM ' . my_calendar_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	foreach ( $results as $result ) {
		$event_id = $result->event_id;
		$category = $result->event_category;

		$insert = $wpdb->insert(
			my_calendar_category_relationships_table(),
			array(
				'event_id'    => $event_id,
				'category_id' => $category,
			),
			array( '%d', '%d' )
		);
	}
}

/**
 * Transition location relationships into own table.
 *
 * @param int $location_id Location ID from location table.
 * @param int $location_post Post ID from posts table.
 *
 * @since 3.3.0
 */
function mc_transition_location( $location_id, $location_post ) {
	global $wpdb;

	if ( $location_post ) {
		$insert = $wpdb->insert(
			my_calendar_location_relationships_table(),
			array(
				'location_id' => $location_id,
				'post_id'     => $location_post,
			),
			array( '%d', '%d' )
		);
	}
}

/**
 * Make a copy of modified CSS files and restore.
 *
 * @param string $source Source file.
 * @param string $dest Destination file.
 *
 * @return boolean.
 */
function my_calendar_copyr( $source, $dest ) {
	if ( ! WP_Filesystem() ) {
		exit;
	}
	global $wp_filesystem;
	if ( ! file_exists( $source ) ) {
		return false;
	}

	if ( ! is_dir( $dest ) ) {
		$wp_filesystem->mkdir( $dest );
	}
	// Copy directory into backup location.
	copy_dir( $source, $dest );

	return true;
}

/**
 * Remove copied files after copy process.
 *
 * @param string $dirname File directory.
 *
 * @return result.
 */
function my_calendar_rmdirr( $dirname ) {
	// Sanity check.
	if ( empty( $dirname ) ) {
		return false;
	}
	// Another sanity check.
	if ( ! file_exists( $dirname ) ) {
		return false;
	}
	// Simple delete for a file.
	if ( is_file( $dirname ) ) {
		return unlink( $dirname );
	}
	// List files for deletion.
	$files = list_files( $dirname, 2 );
	// Make sure we wait to remove directories until after everything is removed.
	foreach ( $files as $file ) {
		if ( is_dir( $file ) ) {
			my_calendar_rmdirr( $file );
		} elseif ( is_file( $file ) ) {
			unlink( $file );
		}
	}

	return @rmdir( $dirname );
}

/**
 * Backup styles and icons.
 *
 * @param string $process current process.
 * @param array  $plugin Current plugin.
 */
function my_calendar_backup( $process, $plugin ) {
	if ( isset( $plugin['plugin'] ) && 'my-calendar/my-calendar.php' === $plugin['plugin'] ) {
		$to   = dirname( __FILE__ ) . '/../styles_backup/';
		$from = dirname( __FILE__ ) . '/styles/';
		my_calendar_copyr( $from, $to );
	}
}

/**
 * Restore styles and icons.
 *
 * @param string $process current process.
 * @param array  $plugin Current plugin.
 */
function my_calendar_recover( $process, $plugin ) {
	if ( isset( $plugin['plugin'] ) && 'my-calendar/my-calendar.php' === $plugin['plugin'] ) {
		$from = dirname( __FILE__ ) . '/../styles_backup/';
		$to   = dirname( __FILE__ ) . '/styles/';
		my_calendar_copyr( $from, $to );
		if ( is_dir( $from ) ) {
			my_calendar_rmdirr( $from );
		}
	}
}

add_filter( 'upgrader_pre_install', 'my_calendar_backup', 10, 2 );
add_filter( 'upgrader_post_install', 'my_calendar_recover', 10, 2 );

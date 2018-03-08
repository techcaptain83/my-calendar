<?php
/**
 * Get event data. Queries to fetch events and create or modify objects.
 *
 * @category Events
 * @package  My Calendar
 * @author   Joe Dolson
 * @license  GPLv2 or later
 * @link     https://www.joedolson.com/my-calendar/
 *
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes objects to add needed properties.
 * 
 * @param object $object Event object
 *
 * @return object $object Modifed event.
 */
function mc_event_object( $object ) {
	if ( is_object( $object ) ) {
		if ( ! property_exists( $object, 'categories' ) ) {
			$object->categories = mc_get_categories( $object, false );
		}
		if ( ! property_exists( $object, 'location' ) && is_numeric( $object->event_location ) && $object->event_location != 0 ) {
			$object->location = mc_get_location( $object->event_location );
		}
	}

	return $object;
}

/**
 * Grab all events for the requested dates from calendar
 *
 * This function needs to be able to react to URL parameters for most factors, with the arguments being the default shown.
 *
 * @param array $args parameters to use for selecting events
 *
 * @return array qualified events
 */
function my_calendar_get_events( $args ) {

	$from     = isset( $args['from'] ) ? $args['from'] : '';
	$to       = isset( $args['to'] ) ? $args['to'] : '';
	$category = isset( $args['category'] ) ? $args['category'] : 'all';
	$ltype    = isset( $args['category'] ) ? $args['ltype'] : 'all';
	$lvalue   = isset( $args['lvalue'] ) ? $args['lvalue'] : 'all';
	$source   = isset( $args['source'] ) ? $args['source'] : 'calendar';
	$author   = isset( $args['author'] ) ? $args['author'] : 'all';
	$host     = isset( $args['host'] ) ? $args['host'] : 'all';
	$search   = isset( $args['search'] ) ? $args['search'] : '';
	$holidays = isset( $args['holidays'] ) ? $args['holidays'] : null;
	$site     = isset( $args['site'] ) ? $args['site'] : false;
	
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	
	if ( $holidays == 'holidays' && $category == '' ) {
		return array();
	}
	
	if ( $holidays === null ) {
		$ccategory = ( isset( $_GET['mcat'] ) && trim( $_GET['mcat'] ) != '' ) ? $_GET['mcat'] : $category;
	} else {
		$ccategory = $category;
	}
	$cltype  = ( isset( $_GET['ltype'] ) ) ? $_GET['ltype'] : $ltype;
	$clvalue = ( isset( $_GET['loc'] ) ) ? $_GET['loc'] : $lvalue;
	$clauth  = ( isset( $_GET['mc_auth'] ) ) ? $_GET['mc_auth'] : $author;
	$clhost  = ( isset( $_GET['mc_host'] ) ) ? $_GET['mc_host'] : $host;
	
	// if location value is not set, then location type shouldn't be set
	if ( $clvalue == 'all' ) {
		$cltype = 'all';
	}

	if ( ! mc_checkdate( $from ) || ! mc_checkdate( $to ) ) {
		return array();
	} // not valid dates
	
	$cat_limit          = ( $ccategory != 'all' ) ? mc_select_category( $ccategory ) : array();
	$join               = ( isset( $cat_limit[0] ) ) ? $cat_limit[0] : '';
	$select_category    = ( isset( $cat_limit[1] ) ) ? $cat_limit[1] : '';
	$select_author      = ( $clauth != 'all' ) ? mc_select_author( $clauth ) : '';
	$select_host        = ( $clhost != 'all' ) ? mc_select_host( $clhost ) : '';
	$select_location    = mc_select_location( $cltype, $clvalue );
	$select_access      = ( isset( $_GET['access'] ) ) ? mc_access_limit( $_GET['access'] ) : '';
	$select_published   = mc_select_published();
	$search             = mc_prepare_search_query( $search );
	
	$exclude_categories = mc_private_categories();
	$arr_events         = array();

	$event_query = "
SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end
FROM " . my_calendar_event_table( $site ) . " 
JOIN " . my_calendar_table( $site ) . " AS e
ON (event_id=occur_event_id) 					
JOIN " . my_calendar_categories_table( $site ) . " 
ON (event_category=category_id)  
$join
WHERE $select_published $select_category $select_location $select_author $select_host $select_access $search 
AND ( DATE(occur_begin) BETWEEN '$from 00:00:00' AND '$to 23:59:59' 
	OR DATE(occur_end) BETWEEN '$from 00:00:00' AND '$to 23:59:59' 
	OR ( DATE('$from') BETWEEN DATE(occur_begin) AND DATE(occur_end) ) 
	OR ( DATE('$to') BETWEEN DATE(occur_begin) AND DATE(occur_end) ) ) 
$exclude_categories
ORDER BY " . apply_filters( 'mc_primary_sort', 'occur_begin' ) . ", " . apply_filters( 'mc_secondary_sort', 'event_title ASC' );

	$events      = $mcdb->get_results( $event_query );
			
	if ( ! empty( $events ) ) {
		$cats = array();
		$locs = array();
		foreach ( array_keys( $events ) as $key ) {
			$event             =& $events[ $key ];
			$event->site_id    = $site;
			$object_id         = $event->event_id;
			$location_id       = $event->event_location;
			if ( !isset( $cats[$object_id] ) ) {
				$categories          = mc_get_categories( $event, false );
				$event->categories   = $categories;
				$cats[$object_id] = $categories;
			} else {
				$event->categories = $cats[$object_id];
			}
			if ( $location_id != 0 ) {
				if ( !isset( $locs[$object_id] ) ) {
					$location         = mc_get_location( $location_id );
					$event->location  = $location;
					$locs[$object_id] = $location;
				} else {
					$event->location = $locs[$object_id];
				}
			}
			$arr_events[] = mc_event_object( $event );
		}
	}
		
	return $arr_events;
}

/**
 * Fetch events for upcoming events list. Not date based; fetches the nearest events regardless of date.
 *
 * @param $args array of event limit parameters
 * 
 * @return array Set of matched events.
 */
function mc_get_all_events( $args ) {
	$category = isset( $args['category'] ) ? $args['category'] : 'default';
	$before   = isset( $args['before'] ) ? $args['before'] : 0;
	$after    = isset( $args['after'] ) ? $args['after'] : 6;
	$today    = isset( $args['today'] ) ? $args['today'] : 'no';
	$author   = isset( $args['author'] ) ? $args['author'] : 'default';
	$host     = isset( $args['host'] ) ? $args['host'] : 'default';
	$ltype    = isset( $args['ltype'] ) ? $args['ltype'] : '';
	$lvalue   = isset(  $args['lvalue'] ) ? $args['lvalue'] : '';
	$site     = isset( $args['site'] ) ? $args['site'] : false;
	$search   = isset( $args['search'] ) ? $args['search'] : '';
	
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	
	$exclude_categories = mc_private_categories();
	$cat_limit          = ( $category != 'default' ) ? mc_select_category( $category ) : array();
	$join               = ( isset( $cat_limit[0] ) ) ? $cat_limit[0] : '';
	$select_category    = ( isset( $cat_limit[1] ) ) ? $cat_limit[1] : '';		
	$select_location    = mc_select_location( $ltype, $lvalue );
	$select_access      = ( isset( $_GET['access'] ) ) ? mc_access_limit( $_GET['access'] ) : '';
	$select_published   = mc_select_published();
	$select_author      = ( $author != 'default' ) ? mc_select_author( $author ) : '';
	$select_host        = ( $host != 'default' ) ? mc_select_host( $host ) : '';
	$date               = date( 'Y-m-d', current_time( 'timestamp' ) );
	
	$limit   = "$select_published $select_category $select_author $select_host $select_location $select_access $search";
	$events1 = $events2 = $events3 = array();
	
	// Events before today
	if ( $before > 0 ) {
		$before  = $before + 15;
		$events1 = $mcdb->get_results( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end
		FROM " . my_calendar_event_table( $site ) . " 
		JOIN " . my_calendar_table( $site ) . " AS e
		ON (event_id=occur_event_id) 
		$join
		JOIN " . my_calendar_categories_table( $site ) . " as c 
		ON (e.event_category=c.category_id) 		
		WHERE $limit
		AND DATE(occur_begin) < '$date' 
		$exclude_categories 
		ORDER BY occur_begin DESC LIMIT 0,$before" );
	}
	// Events happening today
	if ( $today == 'yes' ) {
		$events3 = $mcdb->get_results( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end
		FROM " . my_calendar_event_table( $site ) . "
		JOIN " . my_calendar_table( $site ) . " AS e
		ON (event_id=occur_event_id) 
		$join
		JOIN " . my_calendar_categories_table( $site ) . " as c
		ON (e.event_category=c.category_id) 	
		WHERE $limit
		$exclude_categories 
		AND ( ( DATE(occur_begin) < '$date' AND DATE(occur_end) > '$date' ) OR DATE(occur_begin) = '$date' )" );
	}
	// Upcoming Events
	if ( $after > 0 ) {
		$after   = $after + 15;
		$events2 = $mcdb->get_results( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end
		FROM " . my_calendar_event_table( $site ) . " 
		JOIN " . my_calendar_table( $site ) . " AS e
		ON (event_id=occur_event_id) 
		$join
		JOIN " . my_calendar_categories_table( $site ) . " as c
		ON (e.event_category=c.category_id) 		
		WHERE $limit
		$exclude_categories 		
		AND DATE(occur_begin) > '$date' ORDER BY occur_begin ASC LIMIT 0,$after" );
	}
	
	$arr_events = array();
	if ( ! empty( $events1 ) || ! empty( $events2 ) || ! empty( $events3 ) ) {
		$arr_events = array_merge( $events1, $events3, $events2 );
	}
	
	$cats = array();
	foreach ( array_keys( $arr_events ) as $key ) {
		$event             =& $arr_events[ $key ];
		$event->site_id    = $site;
		$object_id = $event->event_id;
		if ( !isset( $fetched[$object_id] ) ) {
			$cats                = mc_get_categories( $event, false );
			$event->categories   = $cats;
			$fetched[$object_id] = $cats;
		} else {
			$event->categories = $fetched[$object_id];
		}
		$arr_events[] = mc_event_object( $event );
	}

	return $arr_events;
}	

/**
 * Fetch only the defined holiday category
 *
 * @param int $before Number of events before
 * @param int $after Number of events after
 * @param boolean $today Whether to include today's events
 * 
 * @return array events
 */
function mc_get_all_holidays( $before, $after, $today ) {
	if ( ! get_option( 'mc_skip_holidays_category' ) ) {
		return array();
	} else {
		$category = absint( get_option( 'mc_skip_holidays_category' ) );
		$args = array(
			'category' => $category,
			'before'   => $before,
			'after'    => $after,
			'today'    => $today
		);
		
		return mc_get_all_events( $args );
	}
}

/**
 * Get events for use in RSS feeds. Fetches most recently added events.
 *
 * @param integer $cat_id Category ID
 *
 * @return array of event objects
 */
function mc_get_rss_events( $cat_id = false ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	if ( $cat_id ) {
		$cat = "WHERE event_category = $cat_id AND event_approved = 1 AND event_flagged <> 1";
	} else {
		$cat = 'WHERE event_approved = 1 AND event_flagged <> 1';
	}
	$exclude_categories = mc_private_categories();
	$limit = apply_filters( 'mc_rss_feed_size', 30 );
	
	$events = $mcdb->get_results( 
		"SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end 
		FROM " . my_calendar_event_table() . " 
		JOIN " . my_calendar_table() . " ON (event_id=occur_event_id) 
		JOIN " . my_calendar_categories_table() . " ON (event_category=category_id) $cat 
		$exclude_categories
		ORDER BY event_added DESC LIMIT 0,$limit" );
	$groups = $output = array();
	foreach ( array_keys( $events ) as $key ) {
		$event =& $events[ $key ];
		if ( ! in_array( $event->occur_group_id, $groups ) ) {
			$output[ $event->event_begin ][] = $event;
		}
		if ( $event->event_span == 1 ) {
			$groups[] = $event->occur_group_id;
		}
	}
	
	return $output;
}

/**
 * Fetch results of an event search.
 *
 * @param mixed array/string $search mixed array (PRO) or string (Simple)
 *
 * @return array of event objects
 */
function mc_get_search_results( $search ) {
	global $wpdb;
	$mcdb = $wpdb;
	$event_array = array();
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	$before = apply_filters( 'mc_past_search_results', 0 );
	$after  = apply_filters( 'mc_future_search_results', 15 ); // return only future events, nearest 10	
	if ( is_array( $search ) ) {
		// if from & to are set, we need to use a date-based event query
		$from     = $search['from'];
		$to       = $search['to'];
		$category = ( isset( $search['category'] ) ) ? $search['category'] : null;
		$ltype    = ( isset( $search['ltype'] ) ) ? $search['ltype'] : null;
		$lvalue   = ( isset( $search['lvalue'] ) ) ? $search['lvalue'] : null;
		$author   = ( isset( $search['author'] ) ) ? $search['author'] : null;
		$host     = ( isset( $search['host'] ) ) ? $search['host'] : null;
		$search   = ( isset( $search['search'] ) ) ? $search['search'] : '';

		$args     = array(
			'from'     => $from,
			'to'       => $to,
			'category' => $category,
			'ltype'    => $ltype,
			'lvalue'   => $lvalue,
			'author'   => $author,
			'host'     => $host,
			'search'   => $search,
			'source'   => 'search'
		);
		$args        = apply_filters( 'mc_search_attributes', $args, $search );	
		$event_array = my_calendar_events( $args );				
	} else {
		// if not, we use relational event queries
		$args = array(
			'before' => $before,
			'after'  => $after,
			'search' => $search
		);
		
		$arr_events = mc_get_all_events( $args );
	
		$holidays      = mc_get_all_holidays( $before, $after, 'yes' );
		$holiday_array = mc_set_date_array( $holidays );
		
		if ( is_array( $arr_events ) && ! empty( $arr_events ) ) {
			$event_array = mc_set_date_array( $arr_events );
			if ( is_array( $holidays ) && count( $holidays ) > 0 ) {
				$event_array = mc_holiday_limit( $event_array, $holiday_array ); // if there are holidays, rejigger.
			}
		}
	}
	
	return $event_array;
}

/**
 * get event basic info
 * 
 * @param integer $id Event ID in my_calendar db
 * @param boolean $rebuild Get core data only if doing an event rebuild.
 *
 * @return Event object
 */
function mc_get_event_core( $id, $rebuild = false ) {
	if ( !is_numeric( $id ) ) {		
		return;
	}
	
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	
	if ( $rebuild ) {
		// get event data
		$event = $mcdb->get_row( "SELECT * FROM " . my_calendar_table() . " JOIN " . my_calendar_categories_table() . " ON (event_category=category_id) WHERE event_id=$id" );

	} else {
		$event = $mcdb->get_row( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end FROM " . my_calendar_event_table() . " JOIN " . my_calendar_table() . " ON (event_id=occur_event_id) JOIN " . my_calendar_categories_table() . " ON (event_category=category_id) WHERE event_id = $id ORDER BY occur_id ASC LIMIT 1" );
		
		$event = mc_event_object( $event );
	}
		
	return $event;
}

/**
 * Fetches the first fully-realized event object with all parameters even if the specific instance ID isn't available.
 * 
 * @param int $id Event core ID
 *
 * @return object Event
 */
function mc_get_first_event( $id ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	$event = $mcdb->get_row( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end FROM " . my_calendar_event_table() . " JOIN " . my_calendar_table() . " ON (event_id=occur_event_id) JOIN " . my_calendar_categories_table() . " ON (event_category=category_id) WHERE occur_event_id=$id" );
	
	$event = mc_event_object( $event );

	return $event;
}

/**
 * Fetch the instance of an event closest to today.
 *
 * @param int $id Event core ID
 * 
 * @return object Event
 */
function mc_get_nearest_event( $id ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	$event = $mcdb->get_row( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end FROM " . my_calendar_event_table() . " JOIN " . my_calendar_table() . " ON (event_id=occur_event_id) JOIN " . my_calendar_categories_table() . " ON (event_category=category_id) WHERE occur_event_id=$id ORDER BY ABS( DATEDIFF( occur_begin, NOW() ) )" );

	$event = mc_event_object( $event );

	return $event;
}

/**
 * Returns the event object for a specific instance of an event.
 * 
 * @param int $id  Event instance ID
 * @param string $type  'object' or 'html'
 *
 * @return mixed object/string
 */
function mc_get_event( $id, $type = 'object' ) {
	if ( !is_numeric( $id ) ) {
		return false;
	}
	
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	$event = $mcdb->get_row( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end FROM " . my_calendar_event_table() . " JOIN " . my_calendar_table() . " ON (event_id=occur_event_id) JOIN " . my_calendar_categories_table() . " ON (event_category=category_id) WHERE occur_id=$id" );
	if ( $type == 'object' ) {
		$event = mc_event_object( $event );
		return $event;
	} else {
		$date  = date( 'Y-m-d', strtotime( $event->occur_begin ) );
		$time  = date( 'H:i:s', strtotime( $event->occur_begin ) );
		$value = "<div id='mc_event'>" . my_calendar_draw_event( $event, 'single', $date, $time, 'single' ) . "</div>\n";

		return $value;
	}
	
	return false;
}

/**
 * Get a single data field from an event.
 *
 * @param string $field database column.
 * @param int $id Event core ID
 * 
 * @return mixed string/integer value
 */
function mc_get_data( $field, $id ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	
	$sql    = $mcdb->prepare( "SELECT $field FROM " . my_calendar_table() . " WHERE event_id = %d", $id );
	$result = $mcdb->get_var( $sql );

	return $result;
}


/** 
 * Main My Calendar event fetch
 * @since 2.3.0
 * 
 * Fetch all events according to date parameters and supported limits.
 *
 * @param array $args array including some of:
 *	 @param string $from Date formatted string 2014-2-10 
 *	 @param string $to Date formatted string 2014-2-17
 *   @param string/int $category Category ID or category name.
 * 	 @param string/int $ltype Location filter type. 
 *	 @param string $lvalue Location data to filter to.
 *	 @param string $source Source of data request.
 *	 @param int $author Author ID to filter to.
 *
 * @return array Array of event objects with dates as keys.
 */
function my_calendar_events( $args ) {
	$args        = apply_filters( 'my_calendar_events_args', $args );
	$events      = my_calendar_get_events( $args );
	$event_array = array();
	
	// get holidays to filter out
	$args['category'] = get_option( 'mc_skip_holidays_category' );
	$args['holidays'] = 'holidays';
	$holidays         = my_calendar_get_events( $args );
	$holiday_array    = mc_set_date_array( $holidays );
	// get events into an easily parseable set, keyed by date.
	if ( is_array( $events ) && ! empty( $events ) ) {
		$event_array = mc_set_date_array( $events );
		if ( is_array( $holidays ) && count( $holidays ) > 0 ) {
			$event_array = mc_holiday_limit( $event_array, $holiday_array ); // if there are holidays, rejigger.
		}
	}
		
	return $event_array;
}

/**
 * Get one event currently happening.
 *
 * @param mixed $category string/integer category ID or 'default'
 * @param string $template display Template
 * @param integer $site Site ID if fetching events from a different multisite instance
 *
 * @return string output HTML
 */
function my_calendar_events_now( $category = 'default', $template = '<strong>{link_title}</strong> {timerange}', $site = false ) {
	
	if ( $site ) {
		$site = ( $site == 'global' ) ? BLOG_ID_CURRENT_SITE : $site;
		switch_to_blog( $site );
	}	
		
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	
	$arr_events         = array();
	$select_published   = mc_select_published();	
	$cat_limit          = ( $category != 'default' ) ? mc_select_category( $category ) : array();
	$join               = ( isset( $cat_limit[0] ) ) ? $cat_limit[0] : '';
	$select_category    = ( isset( $cat_limit[1] ) ) ? $cat_limit[1] : '';
	$exclude_categories = mc_private_categories();
	
	// may add support for location/author/host later.
	$select_location = $select_author = $select_host = '';
	$now             = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) );
	$event_query     = "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end
					FROM " . my_calendar_event_table( $site ) . " AS o 
					JOIN " . my_calendar_table( $site ) . " AS e 
					ON (event_id=occur_event_id) 
					$join
					JOIN " . my_calendar_categories_table( $site ) . " AS c 
					ON (event_category=category_id) 
					WHERE $select_published $select_category $select_location $select_author $select_host 
					$exclude_categories
					AND ( CAST('$now' AS DATETIME) BETWEEN occur_begin AND occur_end ) 
						ORDER BY " . apply_filters( 'mc_primary_sort', 'occur_begin' ) . ", " . apply_filters( 'mc_secondary_sort', 'event_title ASC' );
	$events           = $mcdb->get_results( $event_query );
	if ( ! empty( $events ) ) {
		foreach ( array_keys( $events ) as $key ) {
			$event        =& $events[ $key ];
			$arr_events[] = $event;
		}
	}
	if ( !empty( $arr_events ) ) {
		$event = mc_create_tags( $arr_events[0] );

		if ( mc_key_exists( $template ) ) {
			$template = mc_get_custom_template( $template );
		}
		
		$output = mc_draw_template( $event, apply_filters( 'mc_happening_now_template', $template, $event ) );
		$return = ( get_option( 'mc_process_shortcodes' ) == 'true' ) ? do_shortcode( $output ) : $output;
	} else {
		$return = '';
	}
		
	if ( $site ) {
		restore_current_blog();
	}	
	
	return $return;
}

/**
 * Functions that return only partial event data or parse existing data sets
 */
 
// get all occurrences associated with an event.
function mc_get_occurrences( $id ) {
	global $wpdb;
	$id = absint( $id );
	if ( $id === 0 ) {
		return array();
	}

	$sql     = "SELECT occur_id, occur_event_id FROM " . my_calendar_event_table() . " WHERE occur_event_id=$id";
	$results = $wpdb->get_results( $sql );

	return $results;
}

/**
 * get all events related to an event ID (group IDs)
 */ 
/**
 * Get all events with a grouped relationship with the current event.
 *
 * @param int $it Event ID
 * 
 * @return array Array event IDs of related events
 */
function mc_get_related( $id ) {
	global $wpdb;
	$id = (int) $id;
	if ( $id === 0 ) {
		return '';
	}
		
	$sql     = "SELECT event_id FROM " . my_calendar_table() . " WHERE event_group_id=$id";
	$results = $wpdb->get_results( $sql );

	return $results;
}

/**
 * Check whether this is a valid preview scenario.
 *
 * @return boolean
 */
function mc_is_preview() {
	if ( isset( $_GET['preview'] ) && $_GET['preview'] == true && current_user_can( 'mc_manage_events' ) ) {
		return true;
	}
	
	return false;
}

/**
 * Remove non-holiday events from data if a holiday is present.
 *
 * @param array $events Array of event objects
 * @param array $holidays Array of event objects
 *
 * @return array Array of event objects with conflicts removed.
 */
function mc_holiday_limit( $events, $holidays ) {
	foreach ( array_keys( $events ) as $key ) {
		if ( ! empty( $holidays[ $key ] ) ) {
			foreach ( $events[ $key ] as $k => $event ) {
				if ( $event->event_category != get_option( 'mc_skip_holidays_category' ) && $event->event_holiday == 1 ) {
					unset( $events[ $key ][ $k ] );
				}
			}
		}
	}

	return $events;
}

/**
 * For date-based views, manipulate array to be organized by dates
 *
 * @param array $events Array of event objects returned by query
 * 
 * @return array $events indexed by date
 */ 
function mc_set_date_array( $events ) {
	$event_array = array();
	if ( is_array( $events ) && !empty( $events ) ) {
		foreach ( $events as $event ) {
			$date = date( 'Y-m-d', strtotime( $event->occur_begin ) );
			$end  = date( 'Y-m-d', strtotime( $event->occur_end ) );
			if ( $date != $end ) {
				$start = strtotime( $date );
				$end   = strtotime( $end );
				do {
					$date                   = date( 'Y-m-d', $start );
					$event_array[ $date ][] = $event;
					$start                  = strtotime( "+1 day", $start );
				} while ( $start <= $end );
			} else {
				$event_array[ $date ][] = $event;
			}
		}
	}

	return $event_array;
}

/**
 * Get post associated with a given My Calendar event
 *
 * @param int $event_id
 *
 * @return mixed int/boolean post ID if found; else false
 */
function mc_get_event_post( $event_id ) {
	$event = mc_get_first_event( $event_id );
	if ( is_object( $event ) ) {
		if ( property_exists( $event, 'event_post' ) && get_post_status( $event->event_post ) ) {
			return $event->event_post;
		}
	}
	
	return false;
}


/**
 * Check the type of database, so handling of search queries is correct.
 *
 * @return string type of database engine in use;
 */
function mc_get_db_type() {
	// this is unlikely to change, but it's not impossible.
	$db_type = get_transient( 'mc_db_type' );
	if ( ! $db_type ) {
		global $wpdb;
		$mcdb = $wpdb;
		$db_type = 'MyISAM';
		if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
			$mcdb = mc_remote_db();
		}	
		$my_calendar = my_calendar_table();
		$dbs = $mcdb->get_results( "SHOW TABLE STATUS WHERE name='$my_calendar'" );
		foreach ( $dbs as $db ) {
			if ( $db->Name == my_calendar_table() ) {
				$db_type = $db->Engine;
			}
		}
		set_transient( 'mc_db_type', $db_type, MONTH_IN_SECONDS );
	}

	return $db_type;
}
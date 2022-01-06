<?php
/**
 * General utilities, not directly related to events display, management, or organization.
 *
 * @category Utilities
 * @package  My Calendar
 * @author   Joe Dolson
 * @license  GPLv2 or later
 * @link     https://www.joedolson.com/my-calendar/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Switch sites in multisite environment.
 *
 * @return boolean
 */
function mc_switch_sites() {
	if ( function_exists( 'is_multisite' ) && is_multisite() ) {
		if ( get_site_option( 'mc_multisite' ) === '2' && my_calendar_table() !== my_calendar_table( 'global' ) ) {
			if ( get_option( 'mc_current_table' ) === '1' ) {
				// can post to either, but is currently set to post to central table.
				return true;
			}
		} elseif ( get_site_option( 'mc_multisite' ) === '1' && my_calendar_table() !== my_calendar_table( 'global' ) ) {
			// can only post to central table.
			return true;
		}
	}

	return false;
}

/**
 * Send a Tweet on approval of event
 *
 * @param string $prev Previous status.
 * @param string $new New status.
 */
function mc_tweet_approval( $prev, $new ) {
	if ( function_exists( 'wpt_post_to_twitter' ) && isset( $_POST['mc_twitter'] ) && trim( $_POST['mc_twitter'] ) !== '' ) {
		if ( ( 0 === (int) $prev || 2 === (int) $prev ) && 1 === (int) $new ) {
			wpt_post_to_twitter( stripslashes( $_POST['mc_twitter'] ) );
		}
	}
}

/**
 * Flatten event array; need an array that isn't multi dimensional
 * Once used in upcoming events?
 *
 * @param array $events Array of events.
 *
 * @return new array
 */
function mc_flatten_array( $events ) {
	$new_array = array();
	if ( is_array( $events ) ) {
		foreach ( $events as $event ) {
			foreach ( $event as $e ) {
				$new_array[] = $e;
			}
		}
	}

	return $new_array;
}

add_action( 'admin_menu', 'mc_add_outer_box' );
/**
 * Add meta boxes
 */
function mc_add_outer_box() {
	add_meta_box( 'mcs_add_event', __( 'My Calendar Event', 'my-calendar' ), 'mc_add_inner_box', 'mc-events', 'side', 'high' );
}

/**
 * Add inner metabox
 */
function mc_add_inner_box() {
	global $post;
	$event_id = get_post_meta( $post->ID, '_mc_event_id', true );
	if ( $event_id ) {
		$url     = admin_url( 'admin.php?page=my-calendar&mode=edit&event_id=' . $event_id );
		$event   = mc_get_first_event( $event_id );
		$content = '<p><strong>' . strip_tags( $event->event_title, mc_strip_tags() ) . '</strong><br />' . $event->event_begin . ' @ ' . $event->event_time . '</p>';
		if ( ! mc_is_recurring( $event ) ) {
			$recur    = mc_event_recur_string( $event, $event->event_begin );
			$content .= wpautop( $recur );
		}
		if ( '' !== $event->event_label ) {
			// Translators: Name of event location.
			$content .= '<p>' . sprintf( __( '<strong>Location:</strong> %s', 'my-calendar' ), strip_tags( $event->event_label, mc_strip_tags() ) ) . '</p>';
		}
		// Translators: Event URL.
		$content .= '<p>' . sprintf( __( '<a href="%s">Edit event</a>.', 'my-calendar' ), $url ) . '</p>';

		echo $content;
	}
}

/**
 * Pass group of allowed tags to strip_tags
 *
 * @return string of allowed tags parseable by strip_tags.
 */
function mc_strip_tags() {

	return apply_filters( 'mc_strip_tags', '<strong><em><i><b><span><br><a>' );
}

/**
 * Pass group of allowed tags to strip_tags
 *
 * @return string of allowed tags parseable by strip_tags.
 */
function mc_admin_strip_tags() {

	return '<strong><em><i><b><span><a><code><pre><br>';
}

/**
 * Old function for checking value of an option field
 *
 * @param string                   $field Name of the field.
 * @param mixed string/int/boolean $value Current value.
 * @param string                   $array if this setting is an array, the array key.
 * @param boolean                  $return whether to return or echo.
 *
 * @return checked=checked
 */
function mc_is_checked( $field, $value, $array = '', $return = false ) {
	if ( ! is_array( get_option( $field ) ) ) {
		if ( get_option( $field ) === (string) $value ) {
			if ( $return ) {
				return 'checked="checked"';
			} else {
				echo 'checked="checked"';
			}
		}
	} else {
		$setting = get_option( $field );
		if ( ! empty( $setting[ $array ]['enabled'] ) && (string) $setting[ $array ]['enabled'] === (string) $value ) {
			if ( $return ) {
				return 'checked="checked"';
			} else {
				echo 'checked="checked"';
			}
		}
	}
}

/**
 * Old function for checking value of an option field in a select
 *
 * @param string                   $field Name of the field.
 * @param mixed string/int/boolean $value Current value.
 * @param string                   $array if this setting is an array, the array key.
 *
 * @return string selected=selected
 */
function mc_is_selected( $field, $value, $array = '' ) {
	if ( ! is_array( get_option( $field ) ) ) {
		if ( get_option( $field ) === (string) $value ) {
			return 'selected="selected"';
		}
	} else {
		$setting = get_option( $field );
		if ( (string) $setting[ $array ]['enabled'] === (string) $value ) {
			return 'selected="selected"';
		}
	}

	return '';
}

/**
 * Old function for checking value of an option field.
 *
 * @deprecated 3.3.0
 *
 * @param string                   $field Name of the field.
 * @param mixed string/int/boolean $value Current value.
 * @param string                   $type checkbox, radio, option.
 *
 * @return string
 */
function mc_option_selected( $field, $value, $type = 'checkbox' ) {
	switch ( $type ) {
		case 'radio':
		case 'checkbox':
			$result = ' checked="checked"';
			break;
		case 'option':
			$result = ' selected="selected"';
			break;
		default:
			$result = '';
			break;
	}
	if ( $field === $value ) {
		$output = $result;
	} else {
		$output = '';
	}

	return $output;
}

/**
 * Check selection
 *
 * @param string                   $field Name of field.
 * @param mixed string/int/boolean $value Type of value.
 * @param string                   $type Type of input.
 *
 * @see mc_option_selected()
 */
function jd_option_selected( $field, $value, $type = 'checkbox' ) {

	return mc_option_selected( $field, $value, $type );
}

if ( ! function_exists( 'exif_imagetype' ) ) {
	/**
	 * This is a hack for people who don't have PHP installed with exif_imagetype
	 *
	 * @param string $filename Name of file.
	 *
	 * @return string type of file.
	 */
	function exif_imagetype( $filename ) {
		if ( ! is_dir( $filename ) && ( list( $width, $height, $type, $attr ) = getimagesize( $filename ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.NonVariableAssignmentFound
			return $type;
		}

		return false;
	}
}

/**
 * Return default state of link expiration checkbox. Replaces option.
 *
 * @return bool Default false.
 */
function mc_event_link_expires() {
	$return  = false;
	$default = get_option( 'mc_event_link_expires' );
	if ( 'true' === $default ) {
		$return = true;
	}

	return apply_filters( 'mc_event_link_expires', $return );
}

/**
 * Return default state of fifth week checkbox. Replaces option.
 *
 * @return bool Default true.
 */
function mc_no_fifth_week() {
	$return  = true;
	$default = get_option( 'mc_no_fifth_week' );
	if ( 'false' === $default ) {
		$return = false;
	}

	return apply_filters( 'mc_no_fifth_week', $return );
}

/**
 * Return default state of skip holidays checkbox. Replaces option.
 *
 * @return bool Default false.
 */
function mc_skip_holidays() {
	$return  = false;
	$default = get_option( 'mc_skip_holidays' );
	if ( 'true' === $default ) {
		$return = true;
	}

	return apply_filters( 'mc_skip_holidays', $return );
}

/**
 * Checks the contrast ratio of color & returns the optimal color to use with it.
 *
 * @param string $color hex value.
 *
 * @return string white or black hex value
 */
function mc_inverse_color( $color ) {
	$color = str_replace( '#', '', $color );
	if ( strlen( $color ) !== 6 ) {
		return '#000000';
	}
	$rgb       = '';
	$total     = 0;
	$red       = 0.299 * ( 255 - hexdec( substr( $color, 0, 2 ) ) );
	$green     = 0.587 * ( 255 - hexdec( substr( $color, 2, 2 ) ) );
	$blue      = 0.114 * ( 255 - hexdec( substr( $color, 4, 2 ) ) );
	$luminance = 1 - ( ( $red + $green + $blue ) / 255 );
	if ( $luminance < 0.5 ) {
		return '#ffffff';
	} else {
		return '#000000';
	}
}

/**
 * Shift color to an acceptable alternate color. Shifts dark colors darker and light colors lighter.
 *
 * @param string $color Color hex.
 *
 * @return string New color hex
 */
function mc_shift_color( $color ) {
	$color   = str_replace( '#', '', $color );
	$rgb     = '';
	$percent = ( mc_inverse_color( $color ) === '#ffffff' ) ? - 20 : 20;
	$per     = $percent / 100 * 255;
	// Percentage to work with. Change middle figure to control color temperature.
	if ( $per < 0 ) {
		// DARKER.
		$per = abs( $per ); // Turns Neg Number to Pos Number.
		for ( $x = 0; $x < 3; $x ++ ) {
			$c    = hexdec( substr( $color, ( 2 * $x ), 2 ) ) - $per;
			$c    = ( $c < 0 ) ? 0 : dechex( $c );
			$rgb .= ( strlen( $c ) < 2 ) ? '0' . $c : $c;
		}
	} else {
		// LIGHTER.
		for ( $x = 0; $x < 3; $x ++ ) {
			$c    = hexdec( substr( $color, ( 2 * $x ), 2 ) ) + $per;
			$c    = ( $c > 255 ) ? 'ff' : dechex( $c );
			$rgb .= ( strlen( $c ) < 2 ) ? '0' . $c : $c;
		}
	}

	return '#' . $rgb;
}

/**
 * Convert a CSV string into an array
 *
 * @param string $csv Data.
 * @param string $delimiter to use.
 * @param string $enclosure to wrap strings.
 * @param string $escape character.
 * @param string $terminator end of line character.
 *
 * @return array
 */
function mc_csv_to_array( $csv, $delimiter = ',', $enclosure = '"', $escape = '\\', $terminator = "\n" ) {
	$r    = array();
	$rows = explode( $terminator, trim( $csv ) );
	foreach ( $rows as $row ) {
		if ( trim( $row ) ) {
			$values          = explode( $delimiter, $row );
			$r[ $values[0] ] = ( isset( $values[1] ) ) ? str_replace( array( $enclosure, $escape ), '', $values[1] ) : $values[0];
		}
	}

	return $r;
}

/**
 * Return string for HTML email types
 */
function mc_html_type() {

	return 'text/html';
}

/**
 * Duplicate of mc_is_url, which really should have been in this file. Bugger.
 *
 * @param string $url URL.
 *
 * @return URL, if valid.
 */
function _mc_is_url( $url ) {

	return preg_match( '|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url );
}

/**
 * Check whether a link is external
 *
 * @param string $link URL.
 *
 * @return boolean true if not on current host
 */
function mc_external_link( $link ) {
	if ( ! _mc_is_url( $link ) ) {
		return 'class="error-link"';
	}

	$url   = parse_url( $link );
	$host  = $url['host'];
	$site  = parse_url( get_option( 'siteurl' ) );
	$known = $site['host'];

	if ( false === strpos( $host, $known ) ) {
		return true;
	}

	return false;
}

/**
 * Replace newline characters in a string
 *
 * @param string $string Any string.
 *
 * @return string string without newline chars
 */
function mc_newline_replace( $string ) {

	return (string) str_replace( array( "\r", "\r\n", "\n" ), '', $string );
}

/**
 * Reverse the order of an array
 *
 * @param array   $array Any array.
 * @param boolean $boolean true or false arguments for array_reverse.
 * @param string  $order sort order to use.
 *
 * @return array
 */
function reverse_array( $array, $boolean, $order ) {
	if ( 'desc' === $order ) {

		return array_reverse( $array, $boolean );
	} else {

		return $array;
	}
}

/**
 * Debugging handler shortcut
 *
 * @param string $subject Text for email subject.
 * @param string $body Text for email body.
 * @param string $email target email (if sending via email).
 */
function mc_debug( $subject, $body, $email = false ) {
	if ( defined( 'MC_DEBUG' ) && true === MC_DEBUG ) {
		if ( ! $email ) {
			$email = get_option( 'admin_email' );
		}
		if ( defined( 'MC_DEBUG_METHOD' ) && 'email' === MC_DEBUG_METHOD ) {
			wp_mail( get_option( 'admin_email' ), $subject, print_r( $body ) );
		} else {
			do_action( 'mc_debug', $subject, $body );
		}
	}
}

/**
 * Get users as options in a select
 *
 * @param string $selected Group of selected users. Comma-separated IDs.
 * @param string $group Type of roles to fetch.
 *
 * @return string select options.
 */
function mc_selected_users( $selected = '', $group = 'authors' ) {
	$options = apply_filters( 'mc_custom_user_select', '', $selected, $group );
	if ( '' !== $options ) {
		return $options;
	}
	$selected = explode( ',', $selected );
	$users    = mc_get_users( $group );
	foreach ( $users as $u ) {
		if ( in_array( $u->ID, $selected, true ) ) {
			$checked = ' selected="selected"';
		} else {
			$checked = '';
		}
		$display_name = ( '' === $u->display_name ) ? $u->user_nicename : $u->display_name;
		$options     .= '<option value="' . $u->ID . '"' . $checked . ">$display_name</option>\n";
	}

	return $options;
}

/**
 * Get users.
 *
 * @param string $group Not used except in filters.
 *
 * @return array of users
 */
function mc_get_users( $group = 'authors' ) {
	global $blog_id;
	$users = apply_filters( 'mc_get_users', false, $group, $blog_id );
	if ( $users ) {
		return $users;
	}
	$count = count_users( 'time' );
	$args  = array(
		'blog_id' => $blog_id,
		'orderby' => 'display_name',
		'fields'  => array( 'ID', 'user_nicename', 'display_name' ),
	);
	$args  = apply_filters( 'mc_filter_user_arguments', $args, $count, $group );
	$users = new WP_User_Query( $args );

	return $users->get_results();
}

/**
 * Display an update message.
 *
 * @param string         $message Update message.
 * @param boolean        $echo Echo or return. Default true (echo).
 * @param boolean|string $code Message code.
 *
 * @return string
 */
function mc_show_notice( $message, $echo = true, $code = false ) {
	if ( trim( $message ) === '' ) {
		return '';
	}
	$message = strip_tags( apply_filters( 'mc_filter_notice', $message, $code ), mc_admin_strip_tags() );
	$message = "<div class='updated'><p>$message</p></div>";
	if ( $echo ) {
		echo wp_kses_post( $message );
	} else {
		return $message;
	}
}

/**
 * Display an error message.
 *
 * @param string  $message Error message.
 * @param boolean $echo Echo or return. Default true (echo).
 *
 * @return string
 */
function mc_show_error( $message, $echo = true ) {
	if ( trim( $message ) === '' ) {
		return '';
	}
	$message = strip_tags( $message, mc_admin_strip_tags() );
	$message = "<div class='error'><p>$message</p></div>";
	if ( $echo ) {
		echo $message;
	} else {
		return $message;
	}
}

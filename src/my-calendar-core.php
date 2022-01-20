<?php
/**
 * Core functions of My Calendar infrastructure - installation, upgrading, action links, etc.
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
 * Add feeds to WordPress feed handler.
 */
function mc_add_feed() {
	add_feed( 'my-calendar-ics', 'my_calendar_ical' );
	add_feed( 'my-calendar-google', 'mc_ics_subscribe_google' );
	add_feed( 'my-calendar-outlook', 'mc_ics_subscribe_outlook' );
}

/**
 * If user is logged in, do not cache feeds.
 *
 * @param object $feed Feed object.
 */
function mc_cache_feeds( &$feed ) {
	if ( is_user_logged_in() ) {
		$feed->enable_cache( false );
	}
}
add_action( 'wp_feed_options', 'mc_cache_feeds' );

/**
 * Add plug-in info page links to Plugins page
 *
 * @param array  $links default set of plug-in links.
 * @param string $file Current file (not used by custom function.).
 *
 * @return array updated set of links
 */
function mc_plugin_action( $links, $file ) {
	if ( plugin_basename( dirname( __FILE__ ) . '/my-calendar.php' ) === $file ) {
		$links[] = '<a href="admin.php?page=my-calendar-config">' . __( 'Settings', 'my-calendar' ) . '</a>';
		$links[] = '<a href="admin.php?page=my-calendar-help">' . __( 'Help', 'my-calendar' ) . '</a>';
		if ( ! function_exists( 'mcs_submissions' ) ) {
			$links[] = '<a href="https://www.joedolson.com/my-calendar-pro/">' . __( 'Go Pro', 'my-calendar' ) . '</a>';
		}
	}

	return $links;
}

/**
 * Get custom styles dir locations, with trailing slash,
 * or get custom styles url locations, with trailing slash.
 *
 * @param string $type path or url, default = path.
 *
 * @return array with locations or empty.
 */
function mc_custom_dirs( $type = 'path' ) {
	$dirs = array();

	$dirs[] = ( 'path' === $type ) ? plugin_dir_path( __DIR__ ) . 'my-calendar-custom/styles/' : plugin_dir_url( __DIR__ ) . 'my-calendar-custom/styles/';
	$dirs[] = ( 'path' === $type ) ? plugin_dir_path( __DIR__ ) . 'my-calendar-custom/' : plugin_dir_url( __DIR__ ) . 'my-calendar-custom/';
	$dirs[] = ( 'path' === $type ) ? get_stylesheet_directory() . '/css/' : get_stylesheet_directory_uri() . '/css/';
	$dirs[] = ( 'path' === $type ) ? get_stylesheet_directory() . '/' : get_stylesheet_directory_uri() . '/';

	$directories = apply_filters( 'mc_custom_dirs', $dirs, $type );

	return ( is_array( $directories ) && ! empty( $directories ) ) ? $directories : $dirs;
}

/**
 * Check whether requested file exists in calendar custom directory.
 *
 * @param string $file file name.
 *
 * @return boolean
 */
function mc_file_exists( $file ) {
	$file   = sanitize_file_name( $file );
	$return = apply_filters( 'mc_file_exists', false, $file );
	if ( $return ) {
		return true;
	}
	foreach ( mc_custom_dirs() as $dir ) {
		if ( file_exists( $dir . $file ) ) {
			return true;
			break;
		}
	}

	return false;
}

/**
 * Fetch a file by path or URL. Checks multiple directories to see which to get.
 *
 * @param string $file name of file to get.
 * @param string $type either path or url.
 *
 * @return string full path or url.
 */
function mc_get_file( $file, $type = 'path' ) {
	$file = sanitize_file_name( $file ); // This will remove slashes as well.
	$dir  = plugin_dir_path( __FILE__ );
	$url  = plugin_dir_url( __FILE__ );
	$path = ( 'path' === $type ) ? $dir . $file : $url . $file;

	foreach ( mc_custom_dirs() as $key => $dir ) {
		if ( file_exists( $dir . $file ) ) {
			if ( 'path' === $type ) {
				$path = $dir . $file;
			} else {
				$urls = mc_custom_dirs( $type );
				$path = $urls[ $key ] . $file;
			}
			break;
		}
	}
	$path = apply_filters( 'mc_get_file', $path, $file );

	return $path;
}

add_filter( 'mc_registered_stylesheet', 'mc_preview_stylesheet', 10, 1 );
/**
 * Allow users with 'mc_edit_styles' permissions to preview stylesheets.
 *
 * @param string $file CSS filename.
 *
 * @return string
 */
function mc_preview_stylesheet( $file ) {
	if ( isset( $_GET['mcpreview'] ) && current_user_can( 'mc_edit_styles' ) ) {
		$file = mc_get_style_path( sanitize_text_field( $_GET['mcpreview'] ), 'url' );
		if ( $file ) {
			return $file;
		}
	}

	return $file;
}

add_action( 'wp_enqueue_scripts', 'mc_register_styles' );
/**
 * Publically enqueued styles & scripts
 */
function mc_register_styles() {
	global $wp_query;
	$version    = get_option( 'mc_version' );
	$this_post  = $wp_query->get_queried_object();
	$stylesheet = apply_filters( 'mc_registered_stylesheet', mc_get_style_path( get_option( 'mc_css_file' ), 'url' ) );
	wp_register_style( 'my-calendar-reset', plugins_url( 'css/reset.css', __FILE__ ), array( 'dashicons' ), $version );
	wp_register_style( 'my-calendar-style', $stylesheet, array( 'my-calendar-reset' ), $version );
	wp_register_style( 'my-calendar-locations', plugins_url( 'css/locations.css', __FILE__ ), array( 'dashicons' ), $version );

	if ( is_singular( 'mc-locations' ) ) {
		wp_enqueue_style( 'my-calendar-locations' );
	}
	$admin_stylesheet = plugins_url( 'css/mc-admin.css', __FILE__ );
	wp_register_style( 'my-calendar-admin-style', $admin_stylesheet, array(), $version );

	if ( current_user_can( 'mc_manage_events' ) ) {
		wp_enqueue_style( 'my-calendar-admin-style' );
	}

	$default     = apply_filters( 'mc_display_css_on_archives', true, $wp_query );
	$id          = ( is_object( $this_post ) && isset( $this_post->ID ) ) ? $this_post->ID : false;
	$js_array    = ( '' !== trim( get_option( 'mc_show_js', '' ) ) ) ? explode( ',', get_option( 'mc_show_js' ) ) : array();
	$css_array   = ( '' !== trim( get_option( 'mc_show_css', '' ) ) ) ? explode( ',', get_option( 'mc_show_css' ) ) : array();
	$use_default = ( $default && ! $id ) ? true : false;
	$js_usage    = ( ( empty( $js_array ) ) || ( $id && in_array( (string) $id, $js_array, true ) ) ) ? true : false;
	$css_usage   = ( ( empty( $css_array ) ) || ( $id && in_array( (string) $id, $css_array, true ) ) ) ? true : false;

	// check whether any scripts are actually enabled.
	if ( get_option( 'mc_calendar_javascript' ) !== '1' || get_option( 'mc_list_javascript' ) !== '1' || get_option( 'mc_mini_javascript' ) !== '1' || get_option( 'mc_ajax_javascript' ) !== '1' ) {
		if ( $use_default || $js_usage || is_singular( 'mc-events' ) || is_singular( 'mc-locations' ) ) {
			wp_enqueue_script( 'jquery' );
			if ( 'true' === get_option( 'mc_gmap' ) || mc_output_is_visible( 'gmap', 'single' ) || is_singular( 'mc-locations' ) ) {
				$api_key = get_option( 'mc_gmap_api_key' );
				if ( $api_key ) {
					wp_enqueue_script( 'gmaps', "https://maps.googleapis.com/maps/api/js?v=3&key=$api_key", array() );
					wp_enqueue_script( 'mc-maps', plugins_url( 'js/gmaps.js', __FILE__ ), array( 'gmaps' ), $version, true );
					wp_localize_script(
						'mc-maps',
						'gmaps',
						array(
							'toggle' => '<span class="dashicons dashicons-arrow-right" aria-hidden="true"></span><span class="screen-reader-text">' . __( 'Location Details', 'my-calendar' ) . '</span>',
						)
					);
				}
			}
		}
	}
	// True means styles are disabled.
	if ( 'true' !== get_option( 'mc_use_styles' ) ) {
		if ( $use_default || $css_usage ) {
			wp_enqueue_style( 'my-calendar-style' );
		}
	}

	if ( mc_is_tablet() && mc_file_exists( 'mc-tablet.css' ) ) {
		$tablet = mc_get_file( 'mc-tablet.css' );
		wp_register_style( 'my-calendar-tablet-style', $tablet );
		wp_enqueue_style( 'my-calendar-tablet-style' );
	}

	if ( mc_is_mobile() && mc_file_exists( 'mc-mobile.css' ) ) {
		$mobile = mc_get_file( 'mc-mobile.css' );
		wp_register_style( 'my-calendar-mobile-style', $mobile );
		wp_enqueue_style( 'my-calendar-mobile-style' );
	}
}

/**
 * Publically written head styles & scripts
 */
function mc_head() {
	global $wp_query;
	$array = array();

	if ( get_option( 'mc_use_styles' ) !== 'true' ) {
		$this_post     = $wp_query->get_queried_object();
		$id            = (string) ( is_object( $this_post ) && isset( $this_post->ID ) ) ? $this_post->ID : false;
		$array         = ( '' !== get_option( 'mc_show_css', '' ) ) ? explode( ',', get_option( 'mc_show_css' ) ) : $array;
		$category_vars = '';
		if ( ( is_array( $array ) && ! empty( $array ) ) || in_array( $id, $array, true ) || get_option( 'mc_show_css', '' ) === '' ) {
			// generate category colors.
			$category_css    = mc_generate_category_styles();
			$category_styles = $category_css['styles'];
			$category_vars   = $category_css['vars'];

			$styles     = (array) get_option( 'mc_style_vars' );
			$style_vars = '';
			foreach ( $styles as $key => $var ) {
				if ( $var ) {
					$style_vars .= sanitize_key( $key ) . ': ' . $var . '; ';
				}
			}
			if ( '' !== $style_vars ) {
				$style_vars = '.mc-main {' . $style_vars . $category_vars . '}';
			}

			$all_styles = "
<style type=\"text/css\">
<!--
/* Styles by My Calendar - Joseph C Dolson https://www.joedolson.com/ */
$category_styles
$style_vars
-->
</style>";
			echo $all_styles;
		}
	}
	if ( mc_is_single_event() ) {
		$mc_id = ( isset( $_GET['mc_id'] ) ) ? absint( $_GET['mc_id'] ) : false;
		if ( $mc_id ) {
			$event  = mc_get_event( $mc_id );
			$schema = mc_event_schema( $event );

			echo PHP_EOL . '<script type="application/ld+json">' . PHP_EOL . '[' . json_encode( map_deep( $schema, 'esc_html' ), JSON_UNESCAPED_SLASHES ) . ']' . PHP_EOL . '</script>' . PHP_EOL;
		}
	}

	if ( is_singular( 'mc-locations' ) ) {
		$loc_id   = mc_get_location_id( get_the_ID() );
		$location = mc_get_location( $loc_id );
		$schema   = mc_location_schema( $location );

		echo PHP_EOL . '<script type="application/ld+json">' . PHP_EOL . '[' . json_encode( map_deep( $schema, 'esc_html' ), JSON_UNESCAPED_SLASHES ) . ']' . PHP_EOL . '</script>' . PHP_EOL;
	}
}

/**
 * Generate category styles for use by My Calendar core.
 *
 * @return array Variable styles & category styles.
 */
function mc_generate_category_styles() {
	global $wpdb;
	$mcdb = $wpdb;
	if ( 'true' === get_option( 'mc_remote' ) && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	$category_styles = '';
	$category_vars   = '';
	$inv             = '';
	$type            = '';
	$alt             = '';
	$categories      = $mcdb->get_results( 'SELECT * FROM ' . my_calendar_categories_table( get_current_blog_id() ) . ' ORDER BY category_id ASC' );
	foreach ( $categories as $category ) {
		$class = mc_category_class( $category, 'mc_' );
		$hex   = ( strpos( $category->category_color, '#' ) !== 0 ) ? '#' : '';
		$color = $hex . $category->category_color;
		if ( '#' !== $color ) {
			$hcolor = mc_shift_color( $category->category_color );
			if ( 'font' === get_option( 'mc_apply_color' ) ) {
				$type = 'color';
				$alt  = 'background';
			} elseif ( 'background' === get_option( 'mc_apply_color' ) ) {
				$type = 'background';
				$alt  = 'color';
			}
			$inverse = mc_inverse_color( $color );
			$inv     = "$alt: $inverse !important;";
			if ( 'font' === get_option( 'mc_apply_color' ) || 'background' === get_option( 'mc_apply_color' ) ) {
				// always an anchor as of 1.11.0, apply also to title.
				$category_styles .= "\n.mc-main .$class .event-title, .mc-main .$class .event-title a { $type: $color !important; $inv }";
				$category_styles .= "\n.mc-main .$class .event-title a:hover, .mc-main .$class .event-title a:focus { $type: $hcolor !important;}";
			}
			// Variables aren't dependent on options.
			$category_vars .= '--category-' . $class . ': ' . $color . '; ';
		}
	}

	return array(
		'styles' => $category_styles,
		'vars'   => $category_vars,
	);
}

/**
 * Deal with events posted by a user when that user is deleted
 *
 * @param int $id user ID of deleted user.
 */
function mc_deal_with_deleted_user( $id ) {
	global $wpdb;
	$new        = $wpdb->get_var( 'SELECT MIN(ID) FROM ' . $wpdb->users, 0, 0 );
	$new_author = apply_filters( 'mc_deleted_author', $new );
	// This may not work quite right in multi-site. Need to explore further when I have time.
	$wpdb->get_results( $wpdb->prepare( 'UPDATE ' . my_calendar_table() . ' SET event_author=%d WHERE event_author=%d', $new_author, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$new_host = apply_filters( 'mc_deleted_host', $new );
	$wpdb->get_results( $wpdb->prepare( 'UPDATE ' . my_calendar_table() . ' SET event_host=%d WHERE event_host=%d', $new_host, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Move sidebars into the footer.
 *
 * @param string $classes Existing admin body classes.
 *
 * @return string New admin body classes
 */
function mc_admin_body_class( $classes ) {
	if ( 'true' === get_option( 'mc_sidebar_footer' ) ) {
		$classes .= ' mc-sidebar-footer';
	}

	return $classes;
}

/**
 * Write custom JS in admin head.
 */
function mc_write_js() {
	if ( isset( $_GET['page'] ) && ( 'my-calendar' === $_GET['page'] || 'my-calendar-config' === $_GET['page'] ) ) {
		?>
		<script>
			//<![CDATA[
			jQuery(document).ready(function ($) {
				$( '#mc-accordion' ).accordion( { collapsible: true, active: false, heightStyle: 'content' } );
				<?php
				if ( function_exists( 'wpt_post_to_twitter' ) && isset( $_GET['page'] ) && 'my-calendar' === $_GET['page'] ) {
					?>
				var mc_allowed = $( '#mc_twitter' ).attr( 'data-allowed' );
				$('#mc_twitter').charCount({
					allowed: mc_allowed,
					counterText: '<?php esc_html_e( 'Characters left: ', 'my-calendar' ); ?>'
				});
					<?php
				}
				?>
			});
			//]]>
		</script>
		<?php
	}
}

add_action( 'in_plugin_update_message-my-calendar/my-calendar.php', 'mc_plugin_update_message' );
/**
 * Display notices from  WordPress.org about updated versions.
 */
function mc_plugin_update_message() {
	global $mc_version;
	define( 'MC_PLUGIN_README_URL', 'http://svn.wp-plugins.org/my-calendar/trunk/readme.txt' );
	$response = wp_remote_get(
		MC_PLUGIN_README_URL,
		array(
			'user-agent' => 'WordPress/My Calendar' . $mc_version . '; ' . get_bloginfo( 'url' ),
		)
	);
	if ( ! is_wp_error( $response ) || is_array( $response ) ) {
		$data = $response['body'];
		$bits = explode( '== Upgrade Notice ==', $data );
		echo '</div><div id="mc-upgrade" class="notice inline notice-warning"><ul><li><strong style="color:#c22;">Upgrade Notes:</strong> ' . str_replace( '* ', '', nl2br( trim( $bits[1] ) ) ) . '</li></ul>';
	}
}

/**
 * Scripts for My Calendar footer; ideally only on pages where My Calendar exists
 */
function mc_footer_js() {
	global $wp_query;
	$version = get_option( 'mc_version' );
	$mcjs    = "<script>(function ($) { 'use strict'; $(function () { $( '.mc-main' ).removeClass( 'mcjs' ); });}(jQuery));</script>";

	if ( mc_is_mobile() && apply_filters( 'mc_disable_mobile_js', false ) ) {

		return;
	} else {
		$pages   = array();
		$show_js = get_option( 'mc_show_js', '' );
		if ( '' !== $show_js ) {
			$pages = explode( ',', $show_js );
		}
		if ( is_object( $wp_query ) && isset( $wp_query->post ) ) {
			$id = (string) $wp_query->post->ID;
		} else {
			$id = false;
		}
		if ( '1' === get_option( 'mc_use_custom_js' ) ) {
			$top     = '';
			$bottom  = '';
			$inner   = '';
			$list_js = stripcslashes( get_option( 'mc_listjs' ) );
			$cal_js  = stripcslashes( get_option( 'mc_caljs' ) );
			if ( 'true' === get_option( 'mc_open_uri' ) ) {
				// remove sections of javascript if necessary.
				$replacements = array(
					'$(this).parent().children().not(".event-title").toggle();',
					'e.preventDefault();',
				);
				$cal_js       = str_replace( $replacements, '', $cal_js );
			}
			$mini_js  = stripcslashes( get_option( 'mc_minijs' ) );
			$open_day = get_option( 'mc_open_day_uri' );
			if ( 'true' === $open_day || 'listanchor' === $open_day || 'calendaranchor' === $open_day ) {
				$mini_js = str_replace( 'e.preventDefault();', '', $mini_js );
			}
			$ajax_js = stripcslashes( get_option( 'mc_ajaxjs' ) );
			$inner   = '';

			if ( ! $id || ( ( is_array( $pages ) && in_array( $id, $pages, true ) ) ) || '' === $show_js ) {
				if ( get_option( 'mc_calendar_javascript' ) !== '1' ) {
					$inner .= "\n" . $cal_js;
				}
				if ( get_option( 'mc_list_javascript' ) !== '1' ) {
					$inner .= "\n" . $list_js;
				}
				if ( get_option( 'mc_mini_javascript' ) !== '1' ) {
					$inner .= "\n" . $mini_js;
				}
				if ( get_option( 'mc_ajax_javascript' ) !== '1' ) {
					$inner .= "\n" . $ajax_js;
				}
				$script = '
<script type="text/javascript">
(function( $ ) { \'use strict\';' . $inner . '}(jQuery));
</script>';
			}
			$inner = apply_filters( 'mc_filter_javascript_footer', $inner );
			echo ( '' !== $inner ) ? $script . $mcjs : '';
		} else {
			$enqueue_mcjs = false;
			if ( ! $id || ( is_array( $pages ) && in_array( $id, $pages, true ) ) || '' === $show_js ) {
				if ( '1' !== get_option( 'mc_calendar_javascript' ) && 'true' !== get_option( 'mc_open_uri' ) ) {
					$url          = apply_filters( 'mc_grid_js', plugins_url( 'js/mc-grid.js', __FILE__ ) );
					$enqueue_mcjs = true;
					wp_enqueue_script( 'mc.grid', $url, array( 'jquery' ), $version );
					wp_localize_script(
						'mc.grid',
						'mcgrid',
						array(
							'grid' => 'true',
						)
					);
				}
				if ( '1' !== get_option( 'mc_list_javascript' ) ) {
					$url          = apply_filters( 'mc_list_js', plugins_url( 'js/mc-list.js', __FILE__ ) );
					$enqueue_mcjs = true;
					wp_enqueue_script( 'mc.list', $url, array( 'jquery' ), $version );
					wp_localize_script(
						'mc.list',
						'mclist',
						array(
							'list' => 'true',
						)
					);
				}
				if ( '1' !== get_option( 'mc_mini_javascript' ) && 'true' !== get_option( 'mc_open_day_uri' ) ) {
					$url          = apply_filters( 'mc_mini_js', plugins_url( 'js/mc-mini.js', __FILE__ ) );
					$enqueue_mcjs = true;
					wp_enqueue_script( 'mc.mini', $url, array( 'jquery' ), $version );
					wp_localize_script(
						'mc.mini',
						'mcmini',
						array(
							'mini' => 'true',
						)
					);
				}
				if ( '1' !== get_option( 'mc_ajax_javascript' ) ) {
					$url          = apply_filters( 'mc_ajax_js', plugins_url( 'js/mc-ajax.js', __FILE__ ) );
					$enqueue_mcjs = true;
					wp_enqueue_script( 'mc.ajax', $url, array( 'jquery' ), $version );
					wp_localize_script(
						'mc.ajax',
						'mcAjax',
						array(
							'ajax' => 'true',
						)
					);
				}
				if ( $enqueue_mcjs ) {
					wp_enqueue_script( 'mc.mcjs', plugins_url( 'js/mcjs.js', __FILE__ ), array( 'jquery' ), $version );
					wp_localize_script(
						'mc.mcjs',
						'my_calendar',
						array(
							'newWindow' => __( 'Opens in new tab', 'my-calendar' ),
						)
					);
				}
			}
		}
	}
}

/**
 * Add stylesheets to My Calendar admin screens
 */
function mc_admin_styles() {
	global $current_screen;
	$version    = get_option( 'mc_version' );
	$id         = $current_screen->id;
	$is_mc_page = isset( $_GET['post'] ) && get_option( 'mc_uri_id' ) === $_GET['post'];

	if ( false !== strpos( $id, 'my-calendar' ) || $is_mc_page ) {
		// Toggle CSS & Scripts based on current mode.
		$mode = get_option( 'mc_default_admin_view' );
		if ( isset( $_GET['view'] ) && 'grid' === $_GET['view'] && 'grid' !== $mode ) {
			update_option( 'mc_default_admin_view', 'grid' );
			$mode = 'grid';
		}
		if ( isset( $_GET['view'] ) && 'list' === $_GET['view'] && 'list' !== $mode ) {
			update_option( 'mc_default_admin_view', 'list' );
			$mode = 'list';
		}
		$grid = ( 'grid' === $mode );
		if ( $grid ) {
			wp_register_style( 'my-calendar-reset', plugins_url( 'css/reset.css', __FILE__ ), array( 'dashicons' ), $version );
			wp_register_style( 'my-calendar-admin-style', plugins_url( 'css/admin.css', __FILE__ ), array( 'my-calendar-reset' ), $version );
			wp_enqueue_style( 'my-calendar-admin-style' );
			if ( '1' !== get_option( 'mc_calendar_javascript' ) && 'true' !== get_option( 'mc_open_uri' ) ) {
				$url          = apply_filters( 'mc_grid_js', plugins_url( 'js/mc-grid.js', __FILE__ ) );
				$enqueue_mcjs = true;
				wp_enqueue_script( 'mc.grid', $url, array( 'jquery' ), $version );
				wp_localize_script(
					'mc.grid',
					'mcgrid',
					array(
						'grid' => 'true',
					)
				);
			}
			if ( '1' !== get_option( 'mc_list_javascript' ) ) {
				$url          = apply_filters( 'mc_list_js', plugins_url( 'js/mc-list.js', __FILE__ ) );
				$enqueue_mcjs = true;
				wp_enqueue_script( 'mc.list', $url, array( 'jquery' ), $version );
				wp_localize_script(
					'mc.list',
					'mclist',
					array(
						'list' => 'true',
					)
				);
			}
			if ( '1' !== get_option( 'mc_mini_javascript' ) && 'true' !== get_option( 'mc_open_day_uri' ) ) {
				$url          = apply_filters( 'mc_mini_js', plugins_url( 'js/mc-mini.js', __FILE__ ) );
				$enqueue_mcjs = true;
				wp_enqueue_script( 'mc.mini', $url, array( 'jquery' ), $version );
				wp_localize_script(
					'mc.mini',
					'mcmini',
					array(
						'mini' => 'true',
					)
				);
			}
			if ( '1' !== get_option( 'mc_ajax_javascript' ) ) {
				$url          = apply_filters( 'mc_ajax_js', plugins_url( 'js/mc-ajax.js', __FILE__ ) );
				$enqueue_mcjs = true;
				wp_enqueue_script( 'mc.ajax', $url, array( 'jquery' ), $version );
				wp_localize_script(
					'mc.ajax',
					'mcAjax',
					array(
						'ajax' => 'true',
					)
				);
			}
			if ( $enqueue_mcjs ) {
				wp_enqueue_script( 'mc.mcjs', plugins_url( 'js/mcjs.js', __FILE__ ), array( 'jquery' ), $version );
				wp_localize_script(
					'mc.mcjs',
					'my_calendar',
					array(
						'newWindow' => __( 'Opens in new tab', 'my-calendar' ),
					)
				);
			}
		}
		wp_enqueue_style( 'mc-styles', plugins_url( 'css/mc-styles.css', __FILE__ ), $version );
	}
}

/**
 * Toggle admin URL values based on default admin view setting.
 *
 * @param string $url Admin URL location.
 *
 * @return string
 */
function mc_admin_url( $url ) {
	$mode = get_option( 'mc_default_admin_view' );
	if ( 'grid' === $mode ) {
		$url = add_query_arg( 'view', 'grid', $url );
	} else {
		$url = add_query_arg( 'view', 'list', $url );
	}

	return admin_url( $url );
}

/**
 * Add custom CSS variables in admin head.
 */
function mc_admin_head() {
	// generate category colors.
	$category_css    = mc_generate_category_styles();
	$category_styles = $category_css['styles'];
	$category_vars   = $category_css['vars'];

	$styles     = (array) get_option( 'mc_style_vars' );
	$style_vars = '';
	foreach ( $styles as $key => $var ) {
		if ( $var ) {
			$style_vars .= sanitize_key( $key ) . ': ' . $var . '; ';
		}
	}
	if ( '' !== $style_vars ) {
		$style_vars = '.mc-main {' . $style_vars . $category_vars . '}';
	}

	$all_styles = "
<style type=\"text/css\">
<!--
/* Styles by My Calendar - Joseph C Dolson https://www.joedolson.com/ */
$category_styles
$style_vars
-->
</style>";
	echo $all_styles;
}
add_action( 'admin_head', 'mc_admin_head' );

/**
 * Get current admin URL.
 *
 * @return string
 */
function mc_get_current_admin_url() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );

	if ( ! $uri ) {
		return '';
	}

	return remove_query_arg( array( '_wpnonce' ), admin_url( $uri ) );
}

/**
 * Attempts to correctly identify the current URL.
 */
function mc_get_current_url() {
	if ( is_admin() ) {
		return mc_get_current_admin_url();
	}
	global $wp, $wp_rewrite;
	$args = array();
	if ( isset( $_GET['page_id'] ) ) {
		$args = array( 'page_id' => absint( $_GET['page_id'] ) );
	}
	$current_url = home_url( add_query_arg( $args, $wp->request ) );

	if ( $wp_rewrite->using_index_permalinks() && false === strpos( $current_url, 'index.php' ) ) {
		$current_url = str_replace( home_url(), home_url( '/' ) . 'index.php', $current_url );
	}

	if ( $wp_rewrite->using_permalinks() ) {
		$current_url = trailingslashit( $current_url );
	}
	$current_url = apply_filters( 'mc_get_current_url', $current_url );

	return esc_url( $current_url );
}

/**
 * Check whether the current user should have permissions and doesn't
 */
function mc_if_needs_permissions() {
	$role = get_role( 'administrator' );
	if ( is_object( $role ) ) {
		$caps = $role->capabilities;
		if ( isset( $caps['mc_add_events'] ) ) {

			return;
		} else {
			$role->add_cap( 'mc_add_events' );
			$role->add_cap( 'mc_approve_events' );
			$role->add_cap( 'mc_manage_events' );
			$role->add_cap( 'mc_edit_cats' );
			$role->add_cap( 'mc_edit_styles' );
			$role->add_cap( 'mc_edit_behaviors' );
			$role->add_cap( 'mc_edit_templates' );
			$role->add_cap( 'mc_edit_settings' );
			$role->add_cap( 'mc_edit_locations' );
			$role->add_cap( 'mc_view_help' );
		}
	} else {

		return;
	}
}

/**
 * Grant capabilities to standard site roles
 *
 * @param mixed string/boolean $add Add capabilities to this role.
 * @param mixed string/boolean $manage Manage capabilities to this role.
 * @param mixed string/boolean $approve Approve capabilities to this role.
 */
function mc_add_roles( $add = false, $manage = false, $approve = false ) {
	$role = get_role( 'administrator' );
	$role->add_cap( 'mc_add_events' );
	$role->add_cap( 'mc_approve_events' );
	$role->add_cap( 'mc_manage_events' );
	$role->add_cap( 'mc_edit_cats' );
	$role->add_cap( 'mc_edit_styles' );
	$role->add_cap( 'mc_edit_behaviors' );
	$role->add_cap( 'mc_edit_templates' );
	$role->add_cap( 'mc_edit_settings' );
	$role->add_cap( 'mc_edit_locations' );
	$role->add_cap( 'mc_view_help' );

	if ( $add && $manage && $approve ) {
		// this is an upgrade.
		$subscriber  = get_role( 'subscriber' );
		$contributor = get_role( 'contributor' );
		$author      = get_role( 'author' );
		$editor      = get_role( 'editor' );
		$subscriber->add_cap( 'mc_view_help' );
		$contributor->add_cap( 'mc_view_help' );
		$author->add_cap( 'mc_view_help' );
		$editor->add_cap( 'mc_view_help' );
		switch ( $add ) {
			case 'read':
				$subscriber->add_cap( 'mc_add_events' );
				$contributor->add_cap( 'mc_add_events' );
				$author->add_cap( 'mc_add_events' );
				$editor->add_cap( 'mc_add_events' );
				break;
			case 'edit_posts':
				$contributor->add_cap( 'mc_add_events' );
				$author->add_cap( 'mc_add_events' );
				$editor->add_cap( 'mc_add_events' );
				break;
			case 'publish_posts':
				$author->add_cap( 'mc_add_events' );
				$editor->add_cap( 'mc_add_events' );
				break;
			case 'moderate_comments':
				$editor->add_cap( 'mc_add_events' );
				break;
		}
		switch ( $approve ) {
			case 'read':
				$subscriber->add_cap( 'mc_approve_events' );
				$contributor->add_cap( 'mc_approve_events' );
				$author->add_cap( 'mc_approve_events' );
				$editor->add_cap( 'mc_approve_events' );
				break;
			case 'edit_posts':
				$contributor->add_cap( 'mc_approve_events' );
				$author->add_cap( 'mc_approve_events' );
				$editor->add_cap( 'mc_approve_events' );
				break;
			case 'publish_posts':
				$author->add_cap( 'mc_approve_events' );
				$editor->add_cap( 'mc_approve_events' );
				break;
			case 'moderate_comments':
				$editor->add_cap( 'mc_approve_events' );
				break;
		}
		switch ( $manage ) {
			case 'read':
				$subscriber->add_cap( 'mc_manage_events' );
				$contributor->add_cap( 'mc_manage_events' );
				$author->add_cap( 'mc_manage_events' );
				$editor->add_cap( 'mc_manage_events' );
				break;
			case 'edit_posts':
				$contributor->add_cap( 'mc_manage_events' );
				$author->add_cap( 'mc_manage_events' );
				$editor->add_cap( 'mc_manage_events' );
				break;
			case 'publish_posts':
				$author->add_cap( 'mc_manage_events' );
				$editor->add_cap( 'mc_manage_events' );
				break;
			case 'moderate_comments':
				$editor->add_cap( 'mc_manage_events' );
				break;
		}
	}
}

/**
 * Verify that My Calendar tables exist
 */
function my_calendar_exists() {
	global $wpdb;
	$tables = $wpdb->get_results( 'show tables;' );
	foreach ( $tables as $table ) {
		foreach ( $table as $value ) {
			if ( my_calendar_table() === $value ) {
				// if the table exists, then My Calendar was already installed.
				return true;
			}
		}
	}

	return false;
}

/**
 * Check what version of My Calendar is installed; install or upgrade if needed
 */
function my_calendar_check() {
	// only execute this function for administrators.
	if ( current_user_can( 'manage_options' ) ) {
		global $wpdb, $mc_version;
		mc_if_needs_permissions();
		$current_version = ( '' === get_option( 'mc_version', '' ) ) ? get_option( 'my_calendar_version' ) : get_option( 'mc_version' );

		// If current version matches, don't bother running this.
		if ( $current_version === $mc_version ) {

			return true;
		}
		// Assume this is not a new install until we prove otherwise.
		$new_install        = false;
		$upgrade_path       = array();
		$my_calendar_exists = my_calendar_exists();

		if ( $my_calendar_exists && '' === $current_version ) {
			// If the table exists, but I don't know what version it is, I have to run the full cycle of upgrades.
			$current_version = '2.2.9';
		}

		if ( ! $my_calendar_exists ) {
			$new_install = true;
		} else {
			// For each release requiring an upgrade path, add a version compare.
			// Loop will run every relevant upgrade cycle.
			$valid_upgrades = array( '2.4.4', '3.0.0', '3.1.13', '3.3.0' );
			foreach ( $valid_upgrades as $upgrade ) {
				if ( version_compare( $current_version, $upgrade, '<' ) ) {
					$upgrade_path[] = $upgrade;
				}
			}
		}
		// Having determined upgrade path, assign new version number.
		update_option( 'mc_version', $mc_version );
		// Now we've determined what the current install is.
		if ( true === $new_install ) {
			// Add default settings.
			mc_default_settings();
		}

		mc_do_upgrades( $upgrade_path );

		/*
		 * If the user has fully uninstalled the plugin but kept the database of events, this will restore default
		 * settings and upgrade db if needed.
		*/
		if ( 'true' === get_option( 'mc_uninstalled' ) ) {
			mc_default_settings();
			update_option( 'mc_db_version', $mc_version );
			delete_option( 'mc_uninstalled' );
		}
	}
}

/**
 * Given a valid upgrade path, execute it.
 *
 * @param string $upgrade_path Specific path to execute.
 *
 * @return bool
 */
function mc_do_upgrades( $upgrade_path ) {
	global $mc_version;
	if ( empty( $upgrade_path ) ) {
		return false;
	}
	foreach ( $upgrade_path as $upgrade ) {
		switch ( $upgrade ) {
			case '3.3.0':
				// Event repeats is now a string, and prefers a date-like value.
				mc_upgrade_db();
				// Count cache no longer counts 'archived' events as published.
				mc_update_count_cache();
				// Shortcodes now executed by default.
				delete_option( 'mc_process_shortcodes' );
				// Remap display settings.
				$settings = array();
				$single   = get_option( 'mc_display_single', array() );
				$main     = get_option( 'mc_display_main', array() );
				$mini     = get_option( 'mc_display_mini', array() );
				if ( empty( $single ) || empty( $main ) || empty( $mini ) ) {
					$settings[] = ( 'true' === get_option( 'mc_display_author' ) ) ? 'author' : '';
					$settings[] = ( 'true' === get_option( 'mc_display_host' ) ) ? 'host' : '';
					$settings[] = ( 'true' === get_option( 'mc_show_event_vcal' ) ) ? 'ical' : '';
					$settings[] = ( 'true' === get_option( 'mc_show_gcal' ) ) ? 'gcal' : '';
					$settings[] = ( 'true' === get_option( 'mc_show_map' ) ) ? 'gmap_link' : '';
					$settings[] = ( 'true' === get_option( 'mc_gmap' ) ) ? 'gmap' : '';
					$settings[] = ( 'true' === get_option( 'mc_show_address' ) ) ? 'address' : '';
					$settings[] = ( 'true' === get_option( 'mc_short' ) ) ? 'excerpt' : '';
					$settings[] = ( 'true' === get_option( 'mc_desc' ) ) ? 'description' : '';
					$settings[] = ( 'true' === get_option( 'mc_image' ) ) ? 'image' : '';
					$settings[] = ( 'true' === get_option( 'mc_event_registration' ) ) ? 'tickets' : '';
					$settings[] = ( 'true' === get_option( 'mc_event_link' ) ) ? 'link' : '';
					$settings[] = ( 'true' === get_option( 'mc_display_more' ) ) ? 'more' : '';
					foreach ( $settings as $key => $value ) {
						if ( '' === $value ) {
							unset( $settings[ $key ] );
						}
					}
					if ( empty( $single ) ) {
						add_option( 'mc_display_single', $settings );
					}
					if ( empty( $main ) ) {
						add_option( 'mc_display_main', $settings );
					}
					if ( empty( $mini ) ) {
						add_option( 'mc_display_mini', $settings );
					}
				}
				add_option( 'mc_drop_settings', 'true' );
				delete_option( 'mc_display_author' );
				delete_option( 'mc_display_host' );
				delete_option( 'mc_show_event_vcal' );
				delete_option( 'mc_show_gcal' );
				delete_option( 'mc_show_map' );
				delete_option( 'mc_gmap' );
				delete_option( 'mc_show_address' );
				delete_option( 'mc_short' );
				delete_option( 'mc_desc' );
				delete_option( 'mc_image' );
				delete_option( 'mc_short' );
				delete_option( 'mc_event_registration' );
				delete_option( 'mc_event_link' );
				delete_option( 'mc_display_more' );
				delete_option( 'mc_title' );
				break;
			case '3.1.13':
				delete_option( 'mc_inverse_color' );
				mc_upgrade_db();
				break;
			case '3.0.0':
				delete_option( 'mc_event_open' );
				delete_option( 'mc_widget_defaults' );
				delete_option( 'mc_event_closed' );
				delete_option( 'mc_event_approve' );
				delete_option( 'mc_ical_utc' );
				delete_option( 'mc_user_settings_enabled' );
				delete_option( 'mc_user_location_type' );
				delete_option( 'mc_event_approve_perms' );
				delete_option( 'mc_location_type' );
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
				mc_transition_categories();
				break;
			case '2.4.4': // 8-11-2015 (2.4.0).
				$input_options               = get_option( 'mc_input_options' );
				$input_options['event_host'] = 'on';
				update_option( 'mc_input_options', $input_options );
				add_option( 'mc_default_direction', 'DESC' );
				break;
			default:
				break;
		}
	}

	return true;
}

add_action( 'admin_bar_menu', 'mc_admin_bar', 200 );
/**
 * Set up adminbar links
 */
function mc_admin_bar() {
	global $wp_admin_bar;
	$mc_id = get_option( 'mc_uri_id' );
	if ( mc_get_uri( 'boolean' ) ) {
		if ( is_page( $mc_id ) && current_user_can( 'mc_add_events' ) ) {
			$url  = apply_filters( 'mc_add_events_url', admin_url( 'admin.php?page=my-calendar' ) );
			$args = array(
				'id'    => 'mc-my-calendar',
				'title' => __( 'Add Event', 'my-calendar' ),
				'href'  => $url,
			);
		} else {
			$url  = esc_url( apply_filters( 'mc_adminbar_uri', mc_get_uri() ) );
			$args = array(
				'id'    => 'mc-my-calendar',
				'title' => __( 'My Calendar', 'my-calendar' ),
				'href'  => $url,
			);
		}
		$wp_admin_bar->add_node( $args );
	} else {
		$url  = admin_url( 'admin.php?page=my-calendar-config#my-calendar-manage' );
		$args = array(
			'id'    => 'mc-my-calendar',
			'title' => __( 'Set Calendar URL', 'my-calendar' ),
			'href'  => $url,
		);
		$wp_admin_bar->add_node( $args );
	}
	if ( current_user_can( 'mc_add_events' ) && 'true' !== get_option( 'mc_remote' ) ) {
		if ( ! is_page( $mc_id ) ) {
			$url  = apply_filters( 'mc_add_events_url', admin_url( 'admin.php?page=my-calendar' ) );
			$args = array(
				'id'     => 'mc-add-event',
				'title'  => __( 'Add Event', 'my-calendar' ),
				'href'   => $url,
				'parent' => 'mc-my-calendar',
			);
			$wp_admin_bar->add_node( $args );
		}
	}
	if ( isset( $_GET['mc_id'] ) && mc_can_edit_event( $_GET['mc_id'] ) ) {
		$event_id = mc_valid_id( $_GET['mc_id'] );
		$query    = array(
			'event_id' => $event_id,
			'ref'      => urlencode( mc_get_current_url() ),
		);
		$url      = add_query_arg( $query, admin_url( 'admin.php?page=my-calendar&mode=edit' ) );
		$args     = array(
			'id'     => 'mc-edit-event',
			'title'  => __( 'Edit Event', 'my-calendar' ),
			'href'   => $url,
			'parent' => 'mc-my-calendar',
		);
		$wp_admin_bar->add_node( $args );
	}
	if ( current_user_can( 'mc_manage_events' ) && current_user_can( 'mc_add_events' ) ) {
		$url  = admin_url( 'admin.php?page=my-calendar-manage' );
		$args = array(
			'id'     => 'mc-manage-events',
			'title'  => __( 'Events', 'my-calendar' ),
			'href'   => $url,
			'parent' => 'mc-my-calendar',
		);
		$wp_admin_bar->add_node( $args );
	}
	if ( current_user_can( 'mc_edit_cats' ) && current_user_can( 'mc_add_events' ) ) {
		$url  = admin_url( 'admin.php?page=my-calendar-categories' );
		$args = array(
			'id'     => 'mc-manage-categories',
			'title'  => __( 'Categories', 'my-calendar' ),
			'href'   => $url,
			'parent' => 'mc-my-calendar',
		);
		$wp_admin_bar->add_node( $args );
	}
	if ( current_user_can( 'mc_edit_locations' ) && current_user_can( 'mc_add_events' ) ) {
		$url  = admin_url( 'admin.php?page=my-calendar-location-manager' );
		$args = array(
			'id'     => 'mc-manage-locations',
			'title'  => __( 'Locations', 'my-calendar' ),
			'href'   => $url,
			'parent' => 'mc-my-calendar',
		);
		$wp_admin_bar->add_node( $args );
	}
	if ( function_exists( 'mcs_submissions' ) && is_numeric( get_option( 'mcs_submit_id' ) ) ) {
		$url  = get_permalink( get_option( 'mcs_submit_id' ) );
		$args = array(
			'id'     => 'mc-submit-events',
			'title'  => __( 'Public Submissions', 'my-calendar' ),
			'href'   => $url,
			'parent' => 'mc-my-calendar',
		);
		$wp_admin_bar->add_node( $args );
	}
}

/**
 * Label My Calendar pages in the admin.
 *
 * @param array  $states States for post.
 * @param object $post The post object.
 *
 * @return array
 */
function mc_admin_state( $states, $post ) {
	if ( is_admin() ) {
		if ( absint( get_option( 'mc_uri_id' ) ) === $post->ID ) {
			$states[] = __( 'My Calendar Page', 'my-calendar' );
		}
	}

	return $states;
}
add_filter( 'display_post_states', 'mc_admin_state', 10, 2 );

/**
 * Send email notification about an event.
 *
 * @param object $event Event object.
 */
function my_calendar_send_email( $event ) {
	$details = mc_create_tags( $event );
	$headers = array();
	// shift to boolean.
	$send_email_option = ( 'true' === get_option( 'mc_event_mail' ) ) ? true : false;
	$send_email        = apply_filters( 'mc_send_notification', $send_email_option, $details );
	if ( true === $send_email ) {
		add_filter( 'wp_mail_content_type', 'mc_html_type' );
	}
	if ( 'true' === get_option( 'mc_event_mail' ) ) {
		$to        = apply_filters( 'mc_event_mail_to', get_option( 'mc_event_mail_to' ), $details );
		$from      = ( '' === get_option( 'mc_event_mail_from', '' ) ) ? get_bloginfo( 'admin_email' ) : get_option( 'mc_event_mail_from' );
		$from      = apply_filters( 'mc_event_mail_from', $from, $details );
		$headers[] = 'From: ' . __( 'Event Notifications', 'my-calendar' ) . " <$from>";
		$bcc       = apply_filters( 'mc_event_mail_bcc', get_option( 'mc_event_mail_bcc' ), $details );
		if ( $bcc ) {
			$bcc = explode( PHP_EOL, $bcc );
			foreach ( $bcc as $b ) {
				$b = trim( $b );
				if ( is_email( $b ) ) {
					$headers[] = "Bcc: $b";
				}
			}
		}
		$headers = apply_filters( 'mc_customize_email_headers', $headers, $event );
		$subject = apply_filters( 'mc_event_mail_subject', get_option( 'mc_event_mail_subject' ), $details );
		$body    = apply_filters( 'mc_event_mail_body', get_option( 'mc_event_mail_message' ), $details );
		$subject = mc_draw_template( $details, $subject );
		$message = mc_draw_template( $details, $body );
		wp_mail( $to, $subject, $message, $headers );
	}
	if ( 'true' === get_option( 'mc_html_email' ) ) {
		remove_filter( 'wp_mail_content_type', 'mc_html_type' );
	}
}

/**
 * Checks submitted events against akismet, if available
 *
 * @param string $event_url Provided URL.
 * @param string $description Event description.
 * @param array  $post Posted details.
 *
 * @return boolean true if spam
 */
function mc_spam( $event_url = '', $description = '', $post = array() ) {
	global $akismet_api_host, $akismet_api_port;
	if ( current_user_can( 'mc_add_events' ) || apply_filters( 'mc_disable_spam_checking', false, $post ) ) { // is a privileged user.
		return apply_filters( 'mc_custom_spam_status', 0, $post );
	}
	$akismet = false;
	$c       = array();
	// check for Akismet.
	if ( ( function_exists( 'akismet_http_post' ) || method_exists( 'Akismet', 'http_post' ) ) && ( akismet_get_key() ) ) {
		$akismet = true;
	}
	if ( $akismet ) {
		$c['blog']                 = home_url();
		$c['user_ip']              = preg_replace( '/[^0-9., ]/', '', $_SERVER['REMOTE_ADDR'] );
		$c['user_agent']           = $_SERVER['HTTP_USER_AGENT'];
		$c['referrer']             = $_SERVER['HTTP_REFERER'];
		$c['comment_type']         = 'calendar-event';
		$c['blog_lang']            = get_bloginfo( 'language' );
		$c['blog_charset']         = get_bloginfo( 'charset' );
		$c['comment_author_url']   = $event_url;
		$c['comment_content']      = $description;
		$c['comment_author']       = $post['mcs_name'];
		$c['comment_author_email'] = $post['mcs_email'];

		$ignore = array( 'HTTP_COOKIE' );

		foreach ( $_SERVER as $key => $value ) {
			if ( ! in_array( $key, (array) $ignore, true ) ) {
				$c[ "$key" ] = $value;
			}
		}
		$query_string = '';
		foreach ( $c as $key => $data ) {
			$query_string .= $key . '=' . urlencode( stripslashes( (string) $data ) ) . '&';
		}
		if ( method_exists( 'Akismet', 'http_post' ) ) {
			$response = Akismet::http_post( $query_string, 'comment-check' );
		} else {
			$response = akismet_http_post( $query_string, $akismet_api_host, '/1.1/comment-check', $akismet_api_port );
		}
		if ( 'true' === $response[1] ) {
			return 1;
		} else {
			return 0;
		}
	}

	return 0;
}

/**
 * Cache total number of events for admin.
 */
function mc_update_count_cache() {
	global $wpdb;
	$published = $wpdb->get_var( 'SELECT count( event_id ) FROM ' . my_calendar_table() . ' WHERE event_approved = 1 AND event_status = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$draft     = $wpdb->get_var( 'SELECT count( event_id ) FROM ' . my_calendar_table() . ' WHERE event_approved = 0' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$trash     = $wpdb->get_var( 'SELECT count( event_id ) FROM ' . my_calendar_table() . ' WHERE event_approved = 2' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$archive   = $wpdb->get_var( 'SELECT count( event_id ) FROM ' . my_calendar_table() . ' WHERE event_status = 0' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$spam      = $wpdb->get_var( 'SELECT count( event_id ) FROM ' . my_calendar_table() . ' WHERE event_flagged = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$counts    = array(
		'published' => $published,
		'draft'     => $draft,
		'trash'     => $trash,
		'archive'   => $archive,
		'spam'      => $spam,
	);
	update_option( 'mc_count_cache', $counts );

	return $counts;
}

add_action( 'admin_enqueue_scripts', 'mc_datepicker' );
/**
 * Enqueue datepickers.
 */
function mc_datepicker() {
	global $current_screen;
	$id = $current_screen->id;

	if ( 'toplevel_page_my-calendar' === $id ) {
		mc_enqueue_duet();
	}
}

/**
 * Produce placeholders in a meaningful format.
 *
 * @return string
 */
function mc_parse_date_format() {
	$format = get_option( 'mcs_date_format' );
	switch ( $format ) {
		case 'Y-m-d':
			$parsed = 'YYYY-MM-DD';
			break;
		case 'm/d/Y':
			$parsed = 'MM/DD/YYYY';
			break;
		case 'd-m-Y':
			$parsed = 'DD-MM-YYYY';
			break;
		case 'j F Y':
			$parsed = 'DD MMMM YYYY';
			break;
		case 'M j, Y':
			$parsed = 'MMM DD, YYYY';
			break;
		default:
			$parsed = 'YYYY-MM-DD';
	}

	return $parsed;
}

/**
 * Enqueue Duet Date Picker.
 */
function mc_enqueue_duet() {
	global $mc_version;
	wp_enqueue_script( 'duet.js', plugins_url( 'js/duet/duet.js', __FILE__ ), array(), $mc_version );
	wp_enqueue_style( 'duet.css', plugins_url( 'js/duet/themes/default.css', __FILE__ ), array(), $mc_version );
	// Enqueue datepicker options.
	wp_enqueue_script( 'mc.duet', plugins_url( 'js/mc-datepicker.js', __FILE__ ), array( 'duet.js' ), $mc_version, true );
	wp_localize_script(
		'mc.duet',
		'duetFormats',
		array(
			'date' => ( get_option( 'mcs_date_format', '' ) ) ? get_option( 'mcs_date_format' ) : 'Y-m-d',
		)
	);
	wp_localize_script(
		'mc.duet',
		'duetLocalization',
		array(
			'buttonLabel'         => __( 'Choose date', 'my-calendar' ),
			'placeholder'         => mc_parse_date_format(),
			'selectedDateMessage' => __( 'Selected date is', 'my-calendar' ),
			'prevMonthLabel'      => __( 'Previous month', 'my-calendar' ),
			'nextMonthLabel'      => __( 'Next month', 'my-calendar' ),
			'monthSelectLabel'    => __( 'Month', 'my-calendar' ),
			'yearSelectLabel'     => __( 'Year', 'my-calendar' ),
			'closeLabel'          => __( 'Close window', 'my-calendar' ),
			'keyboardInstruction' => __( 'You can use arrow keys to navigate dates', 'my-calendar' ),
			'calendarHeading'     => __( 'Choose a date', 'my-calendar' ),
			'dayNames'            => array(
				date_i18n( 'D', strtotime( 'Sunday' ) ),
				date_i18n( 'D', strtotime( 'Monday' ) ),
				date_i18n( 'D', strtotime( 'Tuesday' ) ),
				date_i18n( 'D', strtotime( 'Wednesday' ) ),
				date_i18n( 'D', strtotime( 'Thursday' ) ),
				date_i18n( 'D', strtotime( 'Friday' ) ),
				date_i18n( 'D', strtotime( 'Saturday' ) ),
			),
			'monthNames'          => array(
				date_i18n( 'F', strtotime( 'January 1' ) ),
				date_i18n( 'F', strtotime( 'February 1' ) ),
				date_i18n( 'F', strtotime( 'March 1' ) ),
				date_i18n( 'F', strtotime( 'April 1' ) ),
				date_i18n( 'F', strtotime( 'May 1' ) ),
				date_i18n( 'F', strtotime( 'June 1' ) ),
				date_i18n( 'F', strtotime( 'July 1' ) ),
				date_i18n( 'F', strtotime( 'August 1' ) ),
				date_i18n( 'F', strtotime( 'September 1' ) ),
				date_i18n( 'F', strtotime( 'October 1' ) ),
				date_i18n( 'F', strtotime( 'November 1' ) ),
				date_i18n( 'F', strtotime( 'December 1' ) ),
			),
			'monthNamesShort'     => array(
				date_i18n( 'M', strtotime( 'January 1' ) ),
				date_i18n( 'M', strtotime( 'February 1' ) ),
				date_i18n( 'M', strtotime( 'March 1' ) ),
				date_i18n( 'M', strtotime( 'April 1' ) ),
				date_i18n( 'M', strtotime( 'May 1' ) ),
				date_i18n( 'M', strtotime( 'June 1' ) ),
				date_i18n( 'M', strtotime( 'July 1' ) ),
				date_i18n( 'M', strtotime( 'August 1' ) ),
				date_i18n( 'M', strtotime( 'September 1' ) ),
				date_i18n( 'M', strtotime( 'October 1' ) ),
				date_i18n( 'M', strtotime( 'November 1' ) ),
				date_i18n( 'M', strtotime( 'December 1' ) ),
			),
			'locale'              => str_replace( '_', '-', get_locale() ),
		)
	);
}

add_action( 'admin_enqueue_scripts', 'mc_scripts' );
/**
 * Enqueue My Calendar admin scripts
 */
function mc_scripts() {
	global $current_screen;
	$version = get_option( 'mc_version' );
	$id      = $current_screen->id;
	$slug    = sanitize_title( __( 'My Calendar', 'my-calendar' ) );

	if ( false !== strpos( $id, 'my-calendar' ) ) {
		$mcs_version = ( get_option( 'mcs_version', '' ) ) ? get_option( 'mcs_version' ) : 1.0;
		wp_enqueue_script( 'mc.admin', plugins_url( 'js/jquery.admin.js', __FILE__ ), array( 'jquery', 'jquery-ui-sortable', 'wp-a11y' ), $version );
		wp_localize_script(
			'mc.admin',
			'mcAdmin',
			array(
				'thumbHeight'   => get_option( 'thumbnail_size_h' ),
				'deleteButton'  => __( 'Cancel', 'my-calendar' ),
				'restoreButton' => __( 'Restore', 'my-calendar' ),
				'imageRemoved'  => __( 'Featured image removed', 'my-calendar' ),
				'modalTitle'    => __( 'Choose an Image', 'my-calendar' ),
				'buttonName'    => __( 'Select', 'my-calendar' ),
				'mcs'           => $mcs_version,
			)
		);
		if ( version_compare( $mcs_version, '2.1', '<' ) ) {
			wp_enqueue_style( 'mcs-back-compat', plugins_url( 'css/backcompat.css', __FILE__ ), array(), $version );
		}
	}

	wp_enqueue_style( 'wp-color-picker' );
	// Switch to wp_add_inline_script when no longer supporting WP 4.4.x.
	wp_enqueue_script( 'mc-color-picker', plugins_url( 'js/color-picker.js', __FILE__ ), array( 'wp-color-picker' ), $version, true );

	if ( 'toplevel_page_my-calendar' === $id || $slug . '_page_my-calendar-config' === $id ) {
		wp_enqueue_script( 'jquery-ui-accordion' );
	}

	if ( 'toplevel_page_my-calendar' === $id ) {
		wp_enqueue_script( 'jquery-ui-autocomplete' ); // required for character counting.
		if ( function_exists( 'wp_enqueue_media' ) && ! did_action( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
	}
	if ( $slug . '_page_my-calendar-locations' === $id || 'toplevel_page_my-calendar' === $id ) {
		$api_key = get_option( 'mc_gmap_api_key' );
		if ( $api_key ) {
			wp_enqueue_script( 'gmaps', "https://maps.googleapis.com/maps/api/js?v=3&key=$api_key", array() );
			wp_enqueue_script( 'mc-maps', plugins_url( 'js/gmaps.js', __FILE__ ), array( 'gmaps' ), $version, true );
			wp_localize_script(
				'mc-maps',
				'gmaps',
				array(
					'toggle' => '<span class="dashicons dashicons-arrow-right" aria-hidden="true"></span><span class="screen-reader-text">' . __( 'Toggle Address', 'my-calendar' ) . '</span>',
				)
			);
		}
	}

	if ( 'toplevel_page_my-calendar' === $id && function_exists( 'wpt_post_to_twitter' ) ) {
		wp_enqueue_script( 'charCount', plugins_url( 'wp-to-twitter/js/jquery.charcount.js' ), array( 'jquery' ), $version );
	}
	if ( 'toplevel_page_my-calendar' === $id || $slug . '_page_my-calendar-manage' === $id ) {
		if ( current_user_can( 'mc_manage_events' ) ) {
			wp_enqueue_script( 'mc.ajax', plugins_url( 'js/ajax.js', __FILE__ ), array( 'jquery' ), $version );
			$event_id = ( isset( $_GET['event_id'] ) ) ? (int) $_GET['event_id'] : '';
			wp_localize_script(
				'mc.ajax',
				'mc_data',
				array(
					'action'   => 'delete_occurrence',
					'recur'    => 'add_date',
					'security' => wp_create_nonce( 'mc-delete-nonce' ),
					'url'      => esc_url( add_query_arg( 'event_id', $event_id, admin_url( 'admin.php?page=my-calendar&mode=edit' ) ) ),
				)
			);
			wp_localize_script(
				'mc.ajax',
				'mc_recur',
				array(
					'action'   => 'display_recurrence',
					'security' => wp_create_nonce( 'mc-recurrence-nonce' ),
				)
			);
			wp_enqueue_script( 'mc.ajaxcats', plugins_url( 'js/ajax-cats.js', __FILE__ ), array( 'jquery' ), $version );
			wp_localize_script(
				'mc.ajaxcats',
				'mc_cats',
				array(
					'action'   => 'add_category',
					'security' => wp_create_nonce( 'mc-add-category-nonce' ),
				)
			);
		}
		if ( mc_count_locations() > apply_filters( 'mc_convert_locations_select_to_autocomplete', 90 ) ) {
			wp_enqueue_script( 'accessible-autocomplete', plugins_url( '/js/accessible-autocomplete.min.js', __FILE__ ), array(), $version );
			wp_enqueue_script( 'mc-autocomplete', plugins_url( '/js/autocomplete.js', __FILE__ ), array( 'jquery', 'accessible-autocomplete' ), $version, true );
			wp_localize_script(
				'mc-autocomplete',
				'mclocations',
				array(
					'ajaxurl'  => admin_url( 'admin-ajax.php' ),
					'security' => wp_create_nonce( 'mc-search-locations' ),
					'action'   => 'mc_core_autocomplete_search_locations',
				)
			);
		}
	}

	if ( $slug . '_page_my-calendar-config' === $id ) {
		wp_enqueue_script( 'accessible-autocomplete', plugins_url( '/js/accessible-autocomplete.min.js', __FILE__ ), array(), $version );
		wp_enqueue_script( 'mc-autocomplete', plugins_url( '/js/autocomplete.js', __FILE__ ), array( 'jquery', 'accessible-autocomplete' ), $version, true );
		wp_localize_script(
			'mc-autocomplete',
			'mcpages',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'security' => wp_create_nonce( 'mc-search-pages' ),
				'action'   => 'mc_core_autocomplete_search_pages',
			)
		);
	}

	if ( $slug . '_page_my-calendar-categories' === $id ) {
		wp_enqueue_script( 'accessible-autocomplete', plugins_url( '/js/accessible-autocomplete.min.js', __FILE__ ), array(), $version );
		wp_enqueue_script( 'mc-autocomplete', plugins_url( '/js/autocomplete.js', __FILE__ ), array( 'jquery', 'accessible-autocomplete' ), $version, true );
		wp_localize_script(
			'mc-autocomplete',
			'mcicons',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'security' => wp_create_nonce( 'mc-search-icons' ),
				'action'   => 'mc_core_autocomplete_search_icons',
			)
		);
	}
}


add_filter( 'mc_time_format', 'mc_time_format', 10, 1 );
/**
 * Default time format is 'h:i A' (standard US time format).
 * Pass a string using pickadate.time rules: http://amsul.ca/pickadate.js/time/#formatting-rules.
 *
 * @param string $format Default time format string.
 *
 * @return string new format.
 */
function mc_time_format( $format ) {
	if ( 'G:i' === get_option( 'mc_time_format' ) || 'H:i' === get_option( 'mc_time_format' ) || 'G:i' === get_option( 'time_format' ) || 'H:i' === get_option( 'time_format' ) ) {
		return 'H:i'; // European 24-hour format.
	}

	return $format;
}

/**
 * Return a table header with sortability.
 *
 * @param string      $label Column label.
 * @param bool|string $sort ascending or descending.
 * @param string      $sortby Column currently sorted.
 * @param string      $sorted This sort column.
 * @param bool|string $url URL to sort column.
 *
 * @return string
 */
function mc_table_header( $label, $sort, $sortby, $sorted, $url = false ) {
	$id    = sanitize_title( $label ) . ( ( $url ) ? md5( remove_query_arg( 'order', $url ) ) : '' );
	$inner = ( $url ) ? '<a href="' . esc_url( $url ) . '#' . $id . '">' . $label . '</a>' : $label;
	$sort  = ( ! $sort ) ? false : ( ( 'ASC' === $sort ) ? 'descending' : 'ascending' );
	$th    = ( $sort && ( $sortby === $sorted ) ) ? '<th scope="col" aria-sort="' . $sort . '">' : '<th scope="col">';

	$return = $th . $inner . '</th>';

	return $return;
}

/**
 * As of version 3.0.0, this only checks for 'my-calendar', to see if this plug-in already exists.
 *
 * @return array
 */
function mc_guess_calendar() {
	$return = array(
		'response' => false,
		'message'  => __( 'Calendar query was not able to run.', 'my-calendar' ),
	);
	global $wpdb;
	$has_uri = mc_get_uri( 'boolean' );
	$current = mc_get_uri();
	// check whether calendar page is a valid URL.
	if ( $has_uri && esc_url( $current ) ) {
		$response = wp_remote_head( $current );
		if ( ! is_wp_error( $response ) ) {
			$http = (string) $response['response']['code'];
			// Only modify the value if it's explicitly missing. Redirects or secured pages are fine.
			if ( '404' === $http ) {
				$current = '';
			}
		}
	}

	if ( ! $has_uri ) {
		$post_ID = $wpdb->get_var( "SELECT id FROM $wpdb->posts WHERE post_name LIKE '%my-calendar%' AND post_name NOT LIKE '%-my-calendar%' AND post_status = 'publish'" );
		if ( $post_ID ) {
			$link    = get_permalink( $post_ID );
			$content = get_post( $post_ID )->post_content;
			// if my-calendar exists but does not contain shortcode, add it.
			if ( ! has_shortcode( $content, 'my_calendar' ) ) {
				$content .= "\n\n[my_calendar id='my-calendar']";
				wp_update_post(
					array(
						'ID'           => $post_ID,
						'post_content' => $content,
					)
				);
			}
			update_option( 'mc_uri', $link );
			update_option( 'mc_uri_id', $post_ID );
			$return = array(
				'response' => true,
				'message'  => esc_html__( 'Is this your calendar page?', 'my-calendar' ) . ' <code>' . $link . '</code>',
			);

			return $return;
		} else {
			update_option( 'mc_uri', '' );
			update_option( 'mc_uri_id', '' );
			$return = array(
				'response' => false,
				'message'  => esc_html__( 'No valid calendar detected. Please provide a URL!', 'my-calendar' ),
			);

			return $return;
		}
	} else {
		$return = array(
			'response' => true,
			'message'  => esc_html__( 'Calendar installed.', 'my-calendar' ),
		);
	}

	return $return;
}

/**
 * Set up support form
 */
function mc_get_support_form() {
	global $current_user, $wpdb;
	$current_user = wp_get_current_user();
	// send fields for My Calendar.
	$version       = get_option( 'mc_version' );
	$mc_db_version = get_option( 'mc_db_version' );
	$mc_uri        = mc_get_uri();
	$mc_css        = get_option( 'mc_css_file' );

	// Pro license status.
	$license       = ( '' !== get_option( 'mcs_license_key', '' ) ) ? get_option( 'mcs_license_key' ) : '';
	$license_valid = get_option( 'mcs_license_key_valid' );
	$checked       = ( 'valid' === $license_valid ) ? true : false;

	if ( $license ) {
		$license = "
		License: $license, $license_valid";
	}
	// send fields for all plugins.
	$wp_version = get_bloginfo( 'version' );
	$home_url   = home_url();
	$wp_url     = site_url();
	$language   = get_bloginfo( 'language' );
	$charset    = get_bloginfo( 'charset' );
	// server.
	$php_version = phpversion();
	$db_version  = $wpdb->db_version();
	$admin_email = get_option( 'admin_email' );
	$db_time     = mc_ts( true );
	$db_type     = mc_get_db_type();
	// theme data.
	$theme         = wp_get_theme();
	$theme_name    = $theme->get( 'Name' );
	$theme_uri     = $theme->get( 'ThemeURI' );
	$theme_parent  = $theme->get( 'Template' );
	$theme_version = $theme->get( 'Version' );

	// plugin data.
	$plugins        = get_plugins();
	$plugins_string = '';

	foreach ( array_keys( $plugins ) as $key ) {
		if ( is_plugin_active( $key ) ) {
			$plugin          =& $plugins[ $key ];
			$plugin_name     = $plugin['Name'];
			$plugin_uri      = $plugin['PluginURI'];
			$plugin_version  = $plugin['Version'];
			$plugins_string .= "$plugin_name: $plugin_version; $plugin_uri\n";
		}
	}
	$data = "
================ Installation Data ====================
==My Calendar:==
Version: $version
DB Version: $mc_db_version
URI: $mc_uri
CSS: $mc_css$license
Requester Email: $current_user->user_email
Admin Email: $admin_email

==WordPress:==
Version: $wp_version
URL: $home_url
Install: $wp_url
Language: $language
Charset: $charset

==Extra info:==
PHP Version: $php_version
DB Version: $db_version
DB UTC Offset: $db_time
DB Type: $db_type
Server Software: $_SERVER[SERVER_SOFTWARE]
User Agent: $_SERVER[HTTP_USER_AGENT]

==Theme:==
Name: $theme_name
URI: $theme_uri
Parent: $theme_parent
Version: $theme_version

==Active Plugins:==
$plugins_string
	";
	if ( $checked ) {
		$request = '';
		if ( isset( $_POST['mc_support'] ) ) {
			$nonce = $_REQUEST['_wpnonce'];
			if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
				wp_die( 'Security check failed' );
			}
			$request = ( ! empty( $_POST['support_request'] ) ) ? stripslashes( $_POST['support_request'] ) : false;
			$subject = 'My Calendar Pro support request.';
			$message = $request . "\n\n" . $data;
			// Get the site domain and get rid of www. from pluggable.php.
			$sitename = strtolower( $_SERVER['SERVER_NAME'] );
			if ( 'www.' === substr( $sitename, 0, 4 ) ) {
				$sitename = substr( $sitename, 4 );
			}
			$from_email = 'wordpress@' . $sitename;
			$from       = "From: $current_user->username <$from_email>\r\nReply-to: $current_user->username <$current_user->user_email>\r\n";

			if ( ! $request ) {
				echo wp_kses_post( '<div class="message error"><p>' . __( 'Please describe your problem in detail. I\'m not psychic.', 'my-calendar' ) . '</p></div>' );
			} else {
				$sent = wp_mail( 'plugins@joedolson.com', $subject, $message, $from );
				if ( $sent ) {
					mc_show_notice( __( 'I\'ll get back to you as soon as I can.', 'my-calendar' ) . __( 'You should receive an automatic response to your request when I receive it. If you do not receive this notice, then either I did not receive your message or the email it was sent from was not a valid address.', 'my-calendar' ) );
				} else {
					// Translators: Support form URL.
					echo wp_kses_post( '<div class="message error"><p>' . __( "Sorry! I couldn't send that message. Here's the text of your request:", 'my-calendar' ) . '</p><p>' . sprintf( __( '<a href="%s">Contact me here</a>, instead', 'my-calendar' ), 'https://www.joedolson.com/contact/' ) . "</p><pre>$request</pre></div>" );
				}
			}
		}

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar-help' ) ); ?>">
			<div><input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>" /></div>
			<div>
			<code><?php echo esc_html( __( 'From:', 'my-calendar' ) . " \"$current_user->display_name\" &lt;$current_user->user_email&gt;" ); ?></code>
			</p>
			<p>
				<label for='support_request'>Support Request:</label><br /><textarea name='support_request' id='support_request' required aria-required='true' cols='80' rows='10' class='widefat'><?php echo esc_textarea( stripslashes( $request ) ); ?></textarea>
			</p>
			<p>
				<input type='submit' value='<?php echo esc_attr( __( 'Send Support Request', 'my-calendar' ) ); ?>' name='mc_support' class='button-primary' />
			</p>
			<p><?php esc_html_e( 'The following additional information will be sent with your support request:', 'my-calendar' ); ?></p>
			<div class="mc_support"><?php echo wp_kses_post( wpautop( $data ) ); ?></div>
			</div>
		</form>
		<?php
	} else {
		echo wp_kses_post( '<p><a href="https://wordpress.org/support/plugin/my-calendar/">' . __( 'Request support at the WordPress.org Support Forums', 'my-calendar' ) . '</a> &bull; <a href="https://www.joedolson.com/my-calendar/pro/">' . __( 'Upgrade to Pro for direct plugin support!', 'my-calendar' ) . '</a></p><div class="mc_support">' . wpautop( $data ) . '</div>' );
	}
}

add_action( 'init', 'mc_register_actions' );
/**
 * Register actions attached to My Calendar events, usable to add additional actions during those events.
 */
function mc_register_actions() {
	add_filter( 'mc_event_registration', 'mc_standard_event_registration', 10, 4 );
	add_filter( 'mc_datetime_inputs', 'mc_standard_datetime_input', 10, 4 );
	add_action( 'mc_transition_event', 'mc_tweet_approval', 10, 2 );
	add_action( 'mc_delete_event', 'mc_event_delete_post', 10, 2 );
	add_action( 'mc_mass_delete_events', 'mc_event_delete_posts', 10, 1 );
	add_action( 'parse_request', 'my_calendar_api' );
}

// Filters.
add_filter( 'post_updated_messages', 'mc_posttypes_messages' );
add_filter( 'tmp_grunion_allow_editor_view', '__return_false' );
add_filter( 'next_post_link', 'mc_next_post_link', 10, 2 );
add_filter( 'previous_post_link', 'mc_previous_post_link', 10, 2 );
add_filter( 'the_title', 'mc_the_title', 10, 2 );

// Actions.
add_action( 'init', 'mc_taxonomies', 0 );
add_action( 'init', 'mc_posttypes' );

add_action( 'load-options-permalink.php', 'mc_load_permalinks' );
/**
 * Add custom fields to permalinks settings page.
 */
function mc_load_permalinks() {
	if ( isset( $_POST['mc_cpt_base'] ) ) {
		update_option( 'mc_cpt_base', sanitize_text_field( $_POST['mc_cpt_base'] ) );
	}
	$opts = array( 'label_for' => 'mc_cpt_base' );
	// Add a settings field to the permalink page.
	add_settings_field( 'mc_cpt_base', __( 'My Calendar Events base' ), 'mc_field_callback', 'permalink', 'optional', $opts );

	if ( isset( $_POST['mc_location_cpt_base'] ) ) {
		update_option( 'mc_location_cpt_base', sanitize_text_field( $_POST['mc_location_cpt_base'] ) );
	}
	$opts = array( 'label_for' => 'mc_location_cpt_base' );
	// Add a settings field to the permalink page.
	add_settings_field( 'mc_location_cpt_base', __( 'My Calendar Locations base' ), 'mc_location_field_callback', 'permalink', 'optional', $opts );
}

/**
 * Change out previous post link for previous event.
 *
 * @param string $output Original link.
 * @param string $format Link anchor format.
 *
 * @return string
 */
function mc_previous_post_link( $output, $format ) {
	if ( mc_is_single_event() ) {
		$mc_id = (int) $_GET['mc_id'];
		$event = mc_adjacent_event( $mc_id, 'previous' );
		if ( empty( $event ) ) {
			return '';
		}
		remove_filter( 'the_title', 'mc_the_title', 10, 2 );
		$title = apply_filters( 'the_title', $event['title'], $event['post'] );
		add_filter( 'the_title', 'mc_the_title', 10, 2 );
		$link = add_query_arg( 'mc_id', $event['dateid'], $event['details_link'] );
		$date = ' <span class="mc-event-date">' . $event['date'] . '</span>';

		$output = str_replace( '%link', '<a href="' . $link . '" rel="next" class="mc-adjacent">' . $title . $date . '</a>', $format );
	}

	return $output;
}

/**
 * Change out next post link for next event.
 *
 * @param string $output Original link.
 * @param string $format Link anchor format.
 *
 * @return string
 */
function mc_next_post_link( $output, $format ) {
	if ( mc_is_single_event() ) {
		$mc_id = (int) $_GET['mc_id'];
		$event = mc_adjacent_event( $mc_id, 'next' );
		if ( empty( $event ) ) {
			return '';
		}
		remove_filter( 'the_title', 'mc_the_title', 10, 2 );
		$title = apply_filters( 'the_title', $event['title'], $event['post'] );
		add_filter( 'the_title', 'mc_the_title', 10, 2 );
		$link = add_query_arg( 'mc_id', $event['dateid'], $event['details_link'] );
		$date = ' <span class="mc-event-date">' . $event['date'] . '</span>';

		$output = str_replace( '%link', '<a href="' . $link . '" rel="next" class="mc-adjacent">' . $title . $date . '</a>', $format );
	}

	return $output;
}

/**
 * Replace title on individual event pages with viewed event value & config.
 *
 * @param string $title Original title.
 * @param int    $post_id Post ID.
 *
 * @return string new title string
 */
function mc_the_title( $title, $post_id = null ) {
	if ( is_singular( 'mc-events' ) && in_the_loop() ) {
		if ( $post_id ) {
			$event_id = ( isset( $_GET['mc_id'] ) && is_numeric( $_GET['mc_id'] ) ) ? $_GET['mc_id'] : get_post_meta( $post_id, '_mc_event_id', true );
			if ( is_numeric( $event_id ) ) {
				$event = mc_get_event( $event_id );
				if ( ! is_object( $event ) ) {
					$event = mc_get_first_event( $event_id );
				} else {
					$event_title = stripslashes( $event->event_title );
					if ( $event_title !== $title ) {
						$title = $event_title;
					}
				}
				if ( is_object( $event ) && property_exists( $event, 'category_icon' ) ) {
					$icon = mc_category_icon( $event );
				} else {
					$icon = '';
				}
				$template = mc_get_template( 'title_solo' );
				if ( '' === $template || '{title}' === $template ) {
					$title = $icon . ' ' . strip_tags( $title, mc_strip_tags() );
				} else {
					$data  = mc_create_tags( $event, $event_id );
					$title = mc_draw_template( $data, $template );
				}
			}
		}
	}

	return $title;
}

/**
 * Custom field callback for permalinks settings
 */
function mc_field_callback() {
	$value = ( '' !== get_option( 'mc_cpt_base', '' ) ) ? get_option( 'mc_cpt_base' ) : 'mc-events';
	echo '<input type="text" value="' . esc_attr( $value ) . '" name="mc_cpt_base" id="mc_cpt_base" class="regular-text" placeholder="mc-events" />';
}

/**
 * Custom field callback for permalinks settings
 */
function mc_location_field_callback() {
	$value = ( '' !== get_option( 'mc_locaton_cpt_base', '' ) ) ? get_option( 'mc__location_cpt_base' ) : 'mc-locations';
	echo '<input type="text" value="' . esc_attr( $value ) . '" name="mc_location_cpt_base" id="mc_location_cpt_base" class="regular-text" placeholder="mc-locations" />';
}

/**
 * Generate arguments for My Calendar post type.
 */
function mc_post_type() {
	$arguments = array(
		'public'              => apply_filters( 'mc_event_posts_public', true ),
		'publicly_queryable'  => true,
		// WARNING: Allowing the post type to be searchable will not provide a true event search, especially with respect to recurring events.
		// It will not search recurring events by date, only the post content from each event. Enable only if requirements for search are limited to post content.
		// Details: https://github.com/joedolson/my-calendar/issues/23.
		'exclude_from_search' => apply_filters( 'mc_event_exclude_from_search', true ),
		'show_ui'             => true,
		'show_in_menu'        => apply_filters( 'mc_show_custom_posts_in_menu', false ),
		'menu_icon'           => null,
		'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields' ),
	);

	$loc_arguments                        = $arguments;
	$loc_arguments['supports']            = array( 'title', 'custom-fields', 'thumbnail' );
	$loc_arguments['exclude_from_search'] = apply_filters( 'mc_location_exclude_from_search', true );

	$types = array(
		'mc-events'    => array(
			__( 'event', 'my-calendar' ),
			__( 'events', 'my-calendar' ),
			__( 'Event', 'my-calendar' ),
			__( 'Events', 'my-calendar' ),
			$arguments,
		),
		'mc-locations' => array(
			__( 'location', 'my-calendar' ),
			__( 'locations', 'my-calendar' ),
			__( 'Location', 'my-calendar' ),
			__( 'Locations', 'my-calendar' ),
			$loc_arguments,
		),
	);

	return $types;
}
/**
 * Register custom post types for events
 */
function mc_posttypes() {
	$types   = mc_post_type();
	$enabled = array( 'mc-events', 'mc-locations' );
	if ( is_array( $enabled ) ) {
		foreach ( $enabled as $key ) {
			$value  =& $types[ $key ];
			$labels = array(
				'name'               => $value[3],
				'singular_name'      => $value[2],
				'add_new'            => _x( 'Add New', 'Add new event', 'my-calendar' ),
				'add_new_item'       => __( 'Create New Event', 'my-calendar' ),
				'edit_item'          => __( 'Modify Event', 'my-calendar' ),
				'new_item'           => __( 'New Event', 'my-calendar' ),
				'view_item'          => __( 'View Event', 'my-calendar' ),
				'search_items'       => __( 'Search Events', 'my-calendar' ),
				'not_found'          => __( 'No event found', 'my-calendar' ),
				'not_found_in_trash' => __( 'No events found in Trash', 'my-calendar' ),
				'parent_item_colon'  => '',
			);
			$raw    = $value[4];
			$args   = array(
				'labels'              => $labels,
				'public'              => $raw['public'],
				'publicly_queryable'  => $raw['publicly_queryable'],
				'exclude_from_search' => $raw['exclude_from_search'],
				'show_ui'             => $raw['show_ui'],
				'show_in_menu'        => $raw['show_in_menu'],
				'menu_icon'           => ( null === $raw['menu_icon'] ) ? plugins_url( 'images', __FILE__ ) . '/icon.png' : $raw['menu_icon'],
				'query_var'           => true,
				'rewrite'             => array(
					'with_front' => false,
					'slug'       => apply_filters( 'mc_event_slug', $key ),
				),
				'hierarchical'        => false,
				'menu_position'       => 20,
				'supports'            => $raw['supports'],
			);
			register_post_type( $key, $args );
		}
	}
}

/**
 * Replace the slug with saved option.
 *
 * @param string $slug Base post type name.
 *
 * @return string New permalink base.
 */
function mc_filter_posttype_slug( $slug ) {
	if ( 'mc-events' === $slug ) {
		$slug = ( '' !== get_option( 'mc_cpt_base', '' ) ) ? get_option( 'mc_cpt_base' ) : $slug;
	}
	if ( 'mc-locations' === $slug ) {
		$slug = ( '' !== get_option( 'mc_location_cpt_base', '' ) ) ? get_option( 'mc_location_cpt_base' ) : $slug;
	}

	return $slug;
}
add_filter( 'mc_event_slug', 'mc_filter_posttype_slug' );

add_filter( 'the_posts', 'mc_close_comments' );
/**
 * Most people don't want comments open on events. This will automatically close them.
 *
 * @param array $posts Array of WP Post objects.
 *
 * @return array $posts
 */
function mc_close_comments( $posts ) {
	if ( is_admin() || ! is_single() || empty( $posts ) ) {
		return $posts;
	}

	if ( 'mc-events' === get_post_type( $posts[0]->ID ) ) {
		if ( apply_filters( 'mc_autoclose_comments', true ) && 'closed' !== $posts[0]->comment_status ) {
			$posts[0]->comment_status = 'closed';
			$posts[0]->ping_status    = 'closed';
			wp_update_post( $posts[0] );
		}
	}

	return $posts;
}

add_filter( 'default_content', 'mc_posttypes_defaults', 10, 2 );
/**
 * By default, disable comments on event posts on save
 *
 * @param string $post_content unused.
 * @param object $post WP Post object.
 *
 * @return $post_content;
 */
function mc_posttypes_defaults( $post_content, $post ) {
	if ( $post->post_type ) {
		switch ( $post->post_type ) {
			case 'mc-events':
				$post->comment_status = 'closed';
				break;
		}
	}

	return $post_content;
}

/**
 * Register taxonomies on My Calendar custom post types
 */
function mc_taxonomies() {
	$types   = mc_post_type();
	$enabled = array( 'mc-events' );
	if ( is_array( $enabled ) ) {
		foreach ( $enabled as $key ) {
			$value = $types[ $key ];
			register_taxonomy(
				'mc-event-category',
				// Internal name = machine-readable taxonomy name.
				array( $key ),
				array(
					'hierarchical' => true,
					'label'        => __( 'Event Categories', 'my-calendar' ),
					'query_var'    => true,
					'rewrite'      => array( 'slug' => apply_filters( 'mc_event_category_slug', 'mc-event-category' ) ),
				)
			);
		}
	}
}

/**
 * Custom post type strings
 *
 * @param array $messages default text.
 *
 * @return array Modified messages array.
 */
function mc_posttypes_messages( $messages ) {
	global $post, $post_ID;
	$types   = mc_post_type();
	$enabled = array( 'mc-events', 'mc-locations' );
	if ( is_array( $enabled ) ) {
		foreach ( $enabled as $key ) {
			$value            = $types[ $key ];
			$messages[ $key ] = array(
				0  => '', // Unused. Messages start at index 1.
				// Translators: URL to view event.
				1  => sprintf( __( 'Event updated. <a href="%s">View Event</a>' ), esc_url( get_permalink( $post_ID ) ) ),
				2  => __( 'Custom field updated.' ),
				3  => __( 'Custom field deleted.' ),
				4  => __( 'Event updated.' ),
				// Translators: %s: date and time of the revision.
				5  => isset( $_GET['revision'] ) ? sprintf( __( 'Event restored to revision from %s' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				// Translators: URL to view event.
				6  => sprintf( __( 'Event published. <a href="%s">View event</a>' ), esc_url( get_permalink( $post_ID ) ) ),
				7  => sprintf( __( 'Event saved.' ) ),
				// Translators: URL to preview event.
				8  => sprintf( __( 'Event submitted. <a target="_blank" href="%s">Preview event</a>' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
				// Translators: Date event scheduled to be published, URL to preview event.
				9  => sprintf( __( 'Event scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview event</a>' ), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
				// Translators: URL to preview event.
				10 => sprintf( __( 'Event draft updated. <a target="_blank" href="%s">Preview event</a>' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
			);
		}
	}

	return $messages;
}

add_action( 'admin_init', 'mc_dismiss_notice' );
/**
 * Dismiss admin notices
 */
function mc_dismiss_notice() {
	if ( isset( $_GET['dismiss'] ) && 'update' === $_GET['dismiss'] ) {
		$notice = ( isset( $_GET['notice'] ) ) ? sanitize_text_field( $_GET['notice'] ) : '';
		if ( $notice ) {
			update_option( 'mc_notice_' . $notice, 1 );
		}
	}
}

add_action( 'admin_notices', 'mc_update_notice' );
/**
 * Admin notices
 */
function mc_update_notice() {
	if ( current_user_can( 'manage_options' ) && isset( $_GET['page'] ) && stripos( $_GET['page'], 'my-calendar' ) !== false ) {
		if ( 'true' === get_option( 'mc_remote' ) ) {
			mc_show_notice( __( 'My Calendar is configured to retrieve events from a remote source.', 'my-calendar' ) . ' <a href="' . admin_url( 'admin.php?page=my-calendar-config' ) . '">' . __( 'Update Settings', 'my-calendar' ) . '</a>' );
		}
	}
}

/**
 * Allow CORS from subsites in multisite networks in subdomain setups.
 */
function mc_setup_cors_access() {
	$origin  = str_replace( array( 'http://', 'https://' ), '', get_http_origin() );
	$sites   = ( function_exists( 'get_sites' ) ) ? get_sites() : array();
	$allowed = apply_filters( 'mc_setup_allowed_sites', array(), $origin );
	if ( ! empty( $sites ) ) {
		foreach ( $sites as $site ) {
			$allowed[] = str_replace( array( 'http://', 'https://' ), '', get_home_url( $site->blog_id ) );
		}
	}
	if ( $origin && is_array( $allowed ) && in_array( $origin, $allowed, true ) ) {
		header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
		header( 'Access-Control-Allow-Methods: GET' );
		header( 'Access-Control-Allow-Credentials: true' );
	}
}
add_action( 'send_headers', 'mc_setup_cors_access' );

/**
 * Register post meta field used by calendar page manager metabox.
 */
function mc_register_meta() {
	register_post_meta(
		'page',
		'_mc_calendar',
		array(
			'show_in_rest' => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'shortcode' => array(
							'type' => 'string',
						),
					),
					'additionalProperties' => array(
						'type' => 'string',
					),
					'items'                => array(
						'type' => 'string',
					),
				),
			),
			'single'       => true,
			'type'         => 'array',
		)
	);
}
add_action( 'init', 'mc_register_meta' );

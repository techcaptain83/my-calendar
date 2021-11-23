<?php
/**
 * Construct shortcodes.
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
 * Create a shortcode for My Calendar.
 *
 * @param string $format Output type. Default is 'shortcode', can output values in array.
 */
function mc_generate( $format = 'shortcode' ) {
	if ( isset( $_POST['shortcode'] ) ) {
		$nonce = $_POST['_mc_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-generator' ) ) {
			wp_die( 'Invalid nonce' );
		}
		$string      = '';
		$templatekey = '';
		$append      = '';
		$output      = apply_filters( 'mc_shortcode_generator', false, $_POST );
		$array       = array();
		if ( ! $output ) {
			switch ( $_POST['shortcode'] ) {
				case 'main':
					$shortcode = 'my_calendar';
					break;
				case 'upcoming':
					$shortcode   = 'my_calendar_upcoming';
					$templatekey = 'Upcoming Events Shortcode';
					break;
				case 'today':
					$shortcode   = 'my_calendar_today';
					$templatekey = "Today's Events Shortcode";
					break;
				default:
					$shortcode = 'my_calendar';
			}
			$keys = array( 'category', 'ltype', 'lvalue', 'search', 'format', 'time', 'year', 'month', 'day', 'months', 'above', 'below', 'author', 'host', 'order', 'from', 'to', 'type', 'show_today', 'skip', 'after', 'before', 'template', 'fallback' );
			foreach ( $_POST as $key => $value ) {
				if ( in_array( $key, $keys, true ) ) {
					if ( 'template' === $key ) {
						$template = mc_create_template( $value, array( 'mc_template_key' => $templatekey ) );
						$v        = $template;
						$append   = "<a href='" . add_query_arg( 'mc_template', $template, admin_url( 'admin.php?page=my-calendar-design#my-calendar-templates' ) ) . "'>" . __( 'Edit Custom Template', 'my-calendar' ) . ' &rarr;</a>';
					} else {
						if ( is_array( $value ) ) {
							if ( in_array( 'all', $value, true ) ) {
								unset( $value[0] );
							}
							$v = implode( ',', $value );
						} else {
							$v = $value;
						}
					}
					if ( '' !== $v ) {
						$array[ $key ] = $v;
						$string       .= " $key=&quot;$v&quot;";
					}
				}
			}
			$output = esc_html( $shortcode . $string );
		}
		$return = "<div class='updated'><p><textarea readonly='readonly' class='large-text readonly'>[$output]</textarea>$append</p></div>";
		if ( 'shortcode' === $format ) {
			echo wp_kses( $return, mc_kses_elements() );
		} else {
			$array['shortcode'] = "[$output]";

			return $array;
		}
	}
}

/**
 * Form to create a shortcode
 *
 * @param string $type Type of shortcode to reproduce.
 */
function mc_generator( $type ) {
	?>
	<form action="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar-shortcodes' ) ) . '#mc_' . $type; ?>" method="POST" id="my-calendar-generate">
	<?php mc_calendar_generator_fields( false, $type ); ?>
	<p>
		<input type="submit" class="button-primary" name="generator" value="<?php esc_html_e( 'Generate Shortcode', 'my-calendar' ); ?>"/>
	</p>
	</form>
	<?php
}


/**
 * Display Shortcode Generator screen.
 */
function my_calendar_shortcodes() {
	?>

	<div class="wrap my-calendar-admin">
	<h1><?php esc_html_e( 'Generate Shortcodes', 'my-calendar' ); ?></h1>

	<div class="postbox-container jcd-wide">
	<div class="metabox-holder">

	<div class="ui-sortable meta-box-sortables" id="mc-generator">
		<div class="postbox">
			<h2 id="generator"><?php esc_html_e( 'My Calendar Shortcode Generator', 'my-calendar' ); ?></h2>

			<div class="inside mc-tabs">
				<?php mc_generate(); ?>
				<div class='tabs' role="tablist" data-default="mc_main">
					<button type="button" role="tab" aria-selected="false" id='tab_mc_main' aria-controls='mc_main'><?php esc_html_e( 'Main', 'my-calendar' ); ?></button>
					<button type="button" role="tab" aria-selected="false" id='tab_mc_upcoming' aria-controls='mc_upcoming'><?php esc_html_e( 'Upcoming', 'my-calendar' ); ?></a></button>
					<button type="button" role="tab" aria-selected="false" id='tab_mc_today' aria-controls='mc_today'><?php esc_html_e( 'Today', 'my-calendar' ); ?></button>
					<?php echo apply_filters( 'mc_generator_tabs', '' ); ?>
				</div>
				<div class='wptab mc_main' id='mc_main' aria-live='assertive' aria-labelledby='tab_mc_main' role="tabpanel">
					<?php mc_generator( 'main' ); ?>
				</div>
				<div class='wptab mc_upcoming' id='mc_upcoming' aria-live='assertive' aria-labelledby='tab_mc_upcoming' role="tabpanel">
					<?php mc_generator( 'upcoming' ); ?>
				</div>
				<div class='wptab mc_today' id='mc_today' aria-live='assertive' aria-labelledby='tab_mc_today' role="tabpanel">
					<?php mc_generator( 'today' ); ?>
				</div>
				<?php echo apply_filters( 'mc_generator_tab_content', '' ); ?>
			</div>
		</div>
	</div>

	</div>
	</div>
	<?php mc_show_sidebar(); ?>

	</div>
	<?php
}

<?php
/**
 * Manage My Calendar styles.
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
 * Generate stylesheet editor
 */
function my_calendar_style_edit() {
	$edit_files = true;
	$message    = '';
	if ( ! current_user_can( 'mc_edit_styles' ) ) {
		echo wp_kses_post( '<p>' . __( 'You do not have permission to customize styles on this site.', 'my-calendar' ) . '</p>' );
		return;
	}
	if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT === true ) {
		$edit_files = false;
		mc_show_error( __( 'File editing is disallowed in your WordPress installation. Edit your stylesheets offline.', 'my-calendar' ) );
	}
	if ( isset( $_POST['mc_edit_style'] ) || isset( $_POST['mc_reset_style'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( 'Security check failed' );
		}
		if ( isset( $_POST['mc_reset_style'] ) ) {
			if ( ! empty( $_POST['reset_styles'] ) ) {
				$stylefile        = mc_get_style_path();
				$styles           = mc_default_style();
				$wrote_old_styles = mc_write_styles( $stylefile, $styles );
				if ( $wrote_old_styles ) {
					$message = '<p>' . __( 'Stylesheet updated to match core version.', 'my-calendar' ) . '</p>';
				}
			}
		} else {
			$my_calendar_style = ( isset( $_POST['style'] ) ) ? stripcslashes( $_POST['style'] ) : false;
			$mc_css_file       = stripcslashes( $_POST['mc_css_file'] );

			if ( $edit_files ) {
				$stylefile    = mc_get_style_path( $mc_css_file );
				$wrote_styles = ( false !== $my_calendar_style ) ? mc_write_styles( $stylefile, $my_calendar_style ) : 'disabled';
			} else {
				$wrote_styles = false;
			}

			if ( 'disabled' === $wrote_styles ) {
				$message = '<p>' . __( 'Styles are disabled, and were not edited.', 'my-calendar' ) . '</p>';
			} else {
				$message = ( true === $wrote_styles ) ? '<p>' . __( 'The stylesheet has been updated.', 'my-calendar' ) . '</p>' : '<p><strong>' . __( 'Write Error! Please verify write permissions on the style file.', 'my-calendar' ) . '</strong></p>';
			}

			$mc_show_css = ( empty( $_POST['mc_show_css'] ) ) ? '' : stripcslashes( $_POST['mc_show_css'] );
			update_option( 'mc_show_css', $mc_show_css );
			$use_styles = ( empty( $_POST['use_styles'] ) ) ? '' : 'true';
			update_option( 'mc_use_styles', $use_styles );

			if ( ! empty( $_POST['style_vars'] ) ) {
				$styles = get_option( 'mc_style_vars' );
				if ( isset( $_POST['new_style_var'] ) ) {
					$key = $_POST['new_style_var']['key'];
					$val = $_POST['new_style_var']['val'];
					if ( $key && $val ) {
						if ( 0 !== strpos( $key, '--' ) ) {
							$key = '--' . $key;
						}
						$styles[ $key ] = $val;
					}
				}
				foreach ( $_POST['style_vars'] as $key => $value ) {
					if ( '' !== trim( $value ) ) {
						$styles[ $key ] = $value;
					}
				}
				if ( isset( $_POST['delete_var'] ) ) {
					$delete = $_POST['delete_var'];
					foreach ( $delete as $del ) {
						unset( $styles[ $del ] );
					}
				}
				update_option( 'mc_style_vars', $styles );
			}

			$message .= ' <strong>' . __( 'Style Settings Saved', 'my-calendar' ) . '.</strong>';
		}
		mc_show_notice( $message );
	}
	if ( isset( $_POST['mc_choose_style'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( 'Security check failed' );
		}
		$mc_css_file = stripcslashes( $_POST['mc_css_file'] );

		update_option( 'mc_css_file', $mc_css_file );
		$message = '<p><strong>' . __( 'New theme selected.', 'my-calendar' ) . '</strong></p>';
		echo wp_kses_post( "<div id='message' class='updated fade'>$message</div>" );
	}

	$mc_show_css = get_option( 'mc_show_css' );
	$stylefile   = mc_get_style_path();
	if ( $stylefile ) {
		$f                 = fopen( $stylefile, 'r' );
		$size              = ( 0 === filesize( $stylefile ) ) ? 1 : filesize( $stylefile );
		$file              = fread( $f, $size );
		$my_calendar_style = $file;
		fclose( $f );
		$mc_current_style = mc_default_style();
	} else {
		$mc_current_style  = '';
		$my_calendar_style = __( 'Sorry. The file you are looking for doesn\'t appear to exist. Please check your file name and location!', 'my-calendar' );
	}
	$left_string  = normalize_whitespace( $my_calendar_style );
	$right_string = normalize_whitespace( $mc_current_style );
	if ( $right_string ) { // If right string is blank, there is no default.
		if ( isset( $_GET['diff'] ) ) {
			?>
			<div id="diff">
			<div class="reset-styles notice">
				<div class="faux-p">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar-design' ) ); ?>" class="inline-form">
						<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
						<input type="hidden" value="true" name="mc_reset_style"/>
						<input type="hidden" name="mc_css_file" value="<?php echo esc_attr( get_option( 'mc_css_file' ) ); ?>"/>
						<input type="checkbox" id="reset_styles" name="reset_styles" <?php echo esc_attr( ( mc_is_custom_style( get_option( 'mc_css_file' ) ) ) ? 'disabled' : '' ); ?> /> <label for="reset_styles"><?php esc_html_e( 'Reset stylesheet to match core version', 'my-calendar' ); ?></label>
						<input type="submit" name="save" class="button-primary button-adjust" value="<?php esc_attr_e( 'Reset Styles', 'my-calendar' ); ?>" />
					</form>
					<a class="button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar-design' ) ); ?>"><?php esc_html_e( 'Return to editing', 'my-calendar' ); ?></a>
				</div>
			</div>
			<?php
			$diff = wp_text_diff(
				$left_string,
				$right_string,
				array(
					'title'       => __( 'Comparing Your Style with latest installed version of My Calendar', 'my-calendar' ),
					'title_right' => __( 'Latest (from plugin)', 'my-calendar' ),
					'title_left'  => __( 'Current (in use)', 'my-calendar' ),
				)
			);
			echo wp_kses_post( $diff );
			?>
			</div>
			<?php
		}
		if ( trim( $left_string ) !== trim( $right_string ) && ! isset( $_GET['diff'] ) ) {
			mc_show_error( __( 'There have been updates to the stylesheet.', 'my-calendar' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=my-calendar-design&diff' ) ) . '">' . __( 'Compare Your Stylesheet with latest installed version of My Calendar.', 'my-calendar' ) . '</a>' );
		}
	}
	echo mc_stylesheet_selector();
	if ( ! isset( $_GET['diff'] ) ) {
		$file = get_option( 'mc_css_file' );
		?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar-design' ) ); ?>">
		<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>" />
		<input type="hidden" value="true" name="mc_edit_style" />
		<input type="hidden" name="mc_css_file" value="<?php echo esc_attr( $file ); ?>" />
		<fieldset style="position:relative;">
			<legend><?php esc_html_e( 'CSS Style Options', 'my-calendar' ); ?></legend>
			<p>
				<label for="mc_show_css"><?php esc_html_e( 'Load CSS only on selected pages', 'my-calendar' ); ?></label>
				<input type="text" id="mc_show_css" name="mc_show_css" value="<?php echo esc_attr( $mc_show_css ); ?>" aria-describedby="mc_css_info" /> <span id="mc_css_info"><i class="dashicons dashicons-editor-help" aria-hidden="true"></i><?php esc_html_e( 'Comma-separated post IDs', 'my-calendar' ); ?></span>
			</p>
			<p>
				<input type="checkbox" id="use_styles" name="use_styles" <?php mc_is_checked( 'mc_use_styles', 'true' ); ?> />
				<label for="use_styles"><?php esc_html_e( 'Disable My Calendar CSS', 'my-calendar' ); ?></label>
			</p>
			<?php
			if ( mc_is_custom_style( get_option( 'mc_css_file' ) ) ) {
				echo wp_kses_post( '<div class="notice"><p class="mc-editor-not-available">' . __( 'The editor is not available for custom CSS files. Edit your custom CSS locally, then upload your changes.', 'my-calendar' ) . '</p></div>' );
			} else {
				$disabled = ( $edit_files || get_option( 'mc_use_styles' ) === 'true' ) ? '' : ' disabled="disabled"';
				?>
				<label for="style">
				<?php
				// Translators: file name being edited.
				echo sprintf( esc_html__( 'Edit %s', 'my-calendar' ), '<code>' . $file . '</code>' );
				?>
				</label><br/><textarea <?php echo esc_attr( $disabled ); ?> class="style-editor" id="style" name="style" rows="30" cols="80"><?php echo esc_textarea( $my_calendar_style ); ?></textarea>
				<?php
			}
			?>
			<fieldset class="mc-css-variables">
				<legend><?php esc_html_e( 'CSS Variables', 'my-calendar' ); ?></legend>
				<p>
			<?php esc_html_e( 'Change the primary, secondary, and highlight colors.', 'my-calendar' ); ?>
				</p>
			<?php
			$output = '';
			$styles = get_option( 'mc_style_vars' );
			foreach ( $styles as $var => $style ) {
				$var_id = 'mc' . sanitize_key( $var );
				if ( ! in_array( $var, array( '--primary-dark', '--primary-light', '--secondary-light', '--secondary-dark', '--highlight-dark', '--highlight-light' ), true ) ) {
					// Translators: CSS variable name.
					$delete = " <input type='checkbox' id='delete_var_$var_id' name='delete_var[]' value='" . esc_attr( $var ) . "' /><label for='delete_var_$var_id'>" . sprintf( esc_html__( 'Delete %s', 'my-calendar' ), '<span class="screen-reader-text">' . $var . '</span>' ) . '</label>';
				} else {
					$delete = '';
				}
				$output .= "<li><label for='$var_id'>" . esc_html( $var ) . "</label> <input class='mc-color-input' type='text' id='$var_id' name='style_vars[$var]' value='" . esc_attr( $style ) . "' />$delete</li>";
			}
			if ( $output ) {
				echo wp_kses( "<ul class='checkboxes'>$output</ul>", mc_kses_elements() );
			}
			?>
				<p>
					<label for='new_style_var_key'><?php esc_html_e( 'New variable:', 'my-calendar' ); ?></label>
					<input type='text' name='new_style_var[key]' id='new_style_var_key' /> <label for='new_style_var_val'><?php esc_html_e( 'Value:', 'my-calendar' ); ?></label>
					<input type='text' name='new_style_var[val]' id='new_style_var_val' />
				</p>
			</fieldset>
			<p>
				<input type="submit" name="save" class="button-primary button-adjust" value="<?php esc_attr_e( 'Save Changes', 'my-calendar' ); ?>" />
			</p>
		</fieldset>
	</form>
		<?php
	}
}

/**
 * Display color contrast array of custom variables.
 *
 * @return string
 */
function mc_display_contrast_variables() {
	$styles = get_option( 'mc_style_vars' );
	$comp   = $styles;
	$body   = '';
	$head   = '<th>' . __( 'Variable', 'my-calendar' ) . '</th>';
	foreach ( $styles as $var => $color ) {
		$head .= '<th scope="col">' . str_replace( '--', '', $var ) . '</th>';
		$row   = '<tr><th scope="row">' . str_replace( '--', '', $var ) . '</th>';
		foreach ( $comp as $var => $c ) {
			$compare = ( $color === $c ) ? '' : mc_test_contrast( $color, $c );
			// Translators: variable name.
			$row .= '<td><span class="comparison">' . sprintf( esc_html__( 'with %s:', 'my-calendar' ), str_replace( '--', '', $var ) ) . ' </span>' . $compare . '</td>';
		}
		$row  .= '</tr>';
		$body .= $row;
	}
	$header = '<thead><tr>' . $head . '</tr></thead>';
	$body   = '<tbody>' . $body . '</tbody>';

	$output = '<table class="mc-contrast-table striped"><caption>' . __( 'Accessible Color Combinations', 'my-calendar' ) . '</caption>' . $header . $body . '</table>';

	return $output;
}

/**
 * Test contrast and return output.
 *
 * @param string $color1 Color one.
 * @param string $color2 Color two.
 *
 * @return string
 */
function mc_test_contrast( $color1, $color2 ) {
	$colors   = mc_contrast( $color1, $color2 );
	$contrast = mc_luminosity( $colors['red1'], $colors['red2'], $colors['green1'], $colors['green2'], $colors['blue1'], $colors['blue2'] );
	$text     = '<p>' . __( 'Meets WCAG', 'my-calendar' ) . '</p>';
	$class    = '';
	if ( $contrast < 3.0 ) {
		return '<span>invalid</span>';
	}
	if ( $contrast < 4.5 && $contrast > 3.0 ) {
		$class = 'large-text';
		$text  = '<p>' . __( 'Large text only', 'my-calendar' ) . '</p>';
	}
	return '<div class="' . $class . '" style="color:' . $color1 . '; background: ' . $color2 . '">' . $contrast . $text . '</div>';
}

/**
 * Display stylesheet selector as added component in sidebar.
 *
 * @return string
 */
function mc_stylesheet_selector() {
	$dir              = plugin_dir_path( __FILE__ );
	$options          = '';
	$return           = '
	<div class="style-selector">
	<form method="post" action="' . esc_url( admin_url( 'admin.php?page=my-calendar-design' ) ) . '">
		<input type="hidden" name="_wpnonce" value="' . wp_create_nonce( 'my-calendar-nonce' ) . '"/><input type="hidden" value="true" name="mc_choose_style"/>';
	$custom_directory = str_replace( '/my-calendar/', '', $dir ) . '/my-calendar-custom/styles/';
	$directory        = dirname( __FILE__ ) . '/styles/';
	$files            = mc_css_list( $custom_directory );
	if ( ! empty( $files ) ) {
		$options .= '<optgroup label="' . __( 'Your Custom Stylesheets', 'my-calendar' ) . '">';
		foreach ( $files as $value ) {
			$test     = 'mc_custom_' . $value;
			$filepath = mc_get_style_path( $test );
			$path     = pathinfo( $filepath );
			if ( 'css' === $path['extension'] ) {
				$selected = ( get_option( 'mc_css_file' ) === $test ) ? ' selected="selected"' : '';
				$options .= "<option value='mc_custom_$value'$selected>$value</option>\n";
			}
		}
		$options .= '</optgroup>';
	}
	$files    = mc_css_list( $directory );
	$options .= '<optgroup label="' . __( 'Installed Stylesheets', 'my-calendar' ) . '">';
	foreach ( $files as $value ) {
		$filepath = mc_get_style_path( $value );
		$path     = pathinfo( $filepath );
		if ( 'css' === $path['extension'] ) {
			$selected = ( get_option( 'mc_css_file' ) === $value ) ? ' selected="selected"' : '';
			$options .= "<option value='$value'$selected>$value</option>\n";
		}
	}
	$options .= '</optgroup>';
	$return  .= '
		<fieldset>
			<p>
				<label for="mc_css_file">' . __( 'Select My Calendar Theme', 'my-calendar' ) . '</label><br />
				<select name="mc_css_file" id="mc_css_file">' . $options . '</select>
			</p>
			<p>
				<input type="submit" name="save" class="button-primary" value="' . __( 'Choose Style', 'my-calendar' ) . '"/>
			</p>
		</fieldset>
	</form>';
	$link     = add_query_arg( 'mcpreview', get_option( 'mc_css_file' ), mc_get_uri() );
	$return  .= '<a href="' . esc_url( $link ) . '" class="preview-link" data-css="' . esc_attr( get_option( 'mc_css_file' ) ) . '">' . __( 'Preview Stylesheet', 'my-calendar' ) . '</a></div>';

	return $return;
}

/**
 * Get path for given filename or current selected stylesheet.
 *
 * @param string $filename File name.
 * @param string $type path or url.
 *
 * @return mixed string/boolean
 */
function mc_get_style_path( $filename = false, $type = 'path' ) {
	$url = plugin_dir_url( __FILE__ );
	$dir = plugin_dir_path( __FILE__ );
	if ( ! $filename ) {
		$filename = get_option( 'mc_css_file' );
	}
	if ( ! $filename ) {
		// If no value is saved, return default.
		$filename = 'twentytwentyone.css';
	}
	if ( 0 === strpos( $filename, 'mc_custom_' ) ) {
		$filename  = str_replace( 'mc_custom_', '', $filename );
		$stylefile = ( 'path' === $type ) ? str_replace( '/my-calendar/', '', $dir ) . '/my-calendar-custom/styles/' . $filename : str_replace( '/my-calendar/', '', $url ) . '/my-calendar-custom/styles/' . $filename;
	} else {
		$stylefile = ( 'path' === $type ) ? dirname( __FILE__ ) . '/styles/' . $filename : plugins_url( 'styles', __FILE__ ) . '/' . $filename;
	}
	if ( 'path' === $type ) {
		if ( is_file( $stylefile ) ) {
			return $stylefile;
		} else {
			return false;
		}
	} else {
		return $stylefile;
	}
}

/**
 * Fetch the styles for the current selected style
 *
 * @param string $filename File name.
 * @param string $return content or filename.
 *
 * @return string
 */
function mc_default_style( $filename = false, $return = 'content' ) {
	if ( ! $filename ) {
		$mc_css_file = get_option( 'mc_css_file' );
	} else {
		$mc_css_file = $filename;
	}
	$mc_current_file = dirname( __FILE__ ) . '/templates/' . $mc_css_file;
	if ( file_exists( $mc_current_file ) ) {
		$f                = fopen( $mc_current_file, 'r' );
		$file             = fread( $f, filesize( $mc_current_file ) );
		$mc_current_style = $file;
		fclose( $f );
		switch ( $return ) {
			case 'content':
				return $mc_current_style;
				break;
			case 'path':
				return $mc_current_file;
				break;
			case 'both':
				return array( $mc_current_file, $mc_current_style );
				break;
		}
	}

	return '';
}

/**
 * List CSS files in a directory
 *
 * @param string $directory File directory.
 *
 * @return array list of CSS files
 */
function mc_css_list( $directory ) {
	if ( ! file_exists( $directory ) ) {
		return array();
	}
	$results = array();
	$handler = opendir( $directory );
	// Keep going until all files in directory have been read.
	while ( false !== ( $file = readdir( $handler ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		// If $file isn't this directory or parent, add it to the results array.
		if ( '.' !== $file && '..' !== $file ) {
			$results[] = $file;
		}
	}
	closedir( $handler );
	sort( $results, SORT_STRING );

	return $results;
}

/**
 * Write updated styles to file
 *
 * @param string $file File to write to.
 * @param string $style New styles to write.
 *
 * @return boolean;
 */
function mc_write_styles( $file, $style ) {
	if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT === true ) {
		return false;
	}

	$standard = dirname( __FILE__ ) . '/styles/';
	$files    = mc_css_list( $standard );
	foreach ( $files as $f ) {
		$filepath = mc_get_style_path( $f );
		$path     = pathinfo( $filepath );
		if ( 'css' === $path['extension'] ) {
			$styles_whitelist[] = $filepath;
		}
	}

	if ( in_array( $file, $styles_whitelist, true ) ) {
		if ( function_exists( 'wp_is_writable' ) ) {
			$is_writable = wp_is_writable( $file );
		} else {
			$is_writable = is_writeable( $file );
		}
		if ( $is_writable ) {
			$f = fopen( $file, 'w+' );
			fwrite( $f, $style ); // number of bytes to write, max.
			fclose( $f );

			return true;
		} else {
			return false;
		}
	}
	return false;
}

add_action(
	'admin_enqueue_scripts',
	function() {
		if ( ! function_exists( 'wp_enqueue_code_editor' ) ) {
			return;
		}

		if ( sanitize_title( __( 'My Calendar', 'my-calendar' ) ) . '_page_my-calendar-design' !== get_current_screen()->id ) {
			return;
		}

		// Enqueue code editor and settings for manipulating HTML.
		$settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

		// Bail if user disabled CodeMirror.
		if ( false === $settings ) {
			return;
		}

		wp_add_inline_script(
			'code-editor',
			sprintf(
				'jQuery( function() { wp.codeEditor.initialize( "style", %s ); } );',
				wp_json_encode( $settings )
			)
		);
	}
);


/**
 * Measure the relative luminosity between two RGB values.
 *
 * @param int $r Red value 1.
 * @param int $r2 Red value 2.
 * @param int $g Green value 1.
 * @param int $g2 Green value 2.
 * @param int $b Blue value 1.
 * @param int $b2 Blue value 2.
 *
 * @return luminosity ratio.
 */
function mc_luminosity( $r, $r2, $g, $g2, $b, $b2 ) {
	$rs_rgb = $r / 255;
	$gs_rgb = $g / 255;
	$bs_rgb = $b / 255;
	$r_new  = ( $rs_rgb <= 0.03928 ) ? $rs_rgb / 12.92 : pow( ( $rs_rgb + 0.055 ) / 1.055, 2.4 );
	$g_new  = ( $gs_rgb <= 0.03928 ) ? $gs_rgb / 12.92 : pow( ( $gs_rgb + 0.055 ) / 1.055, 2.4 );
	$b_new  = ( $bs_rgb <= 0.03928 ) ? $bs_rgb / 12.92 : pow( ( $bs_rgb + 0.055 ) / 1.055, 2.4 );

	$rs_rgb2 = $r2 / 255;
	$gs_rgb2 = $g2 / 255;
	$bs_rgb2 = $b2 / 255;
	$r2_new  = ( $rs_rgb2 <= 0.03928 ) ? $rs_rgb2 / 12.92 : pow( ( $rs_rgb2 + 0.055 ) / 1.055, 2.4 );
	$g2_new  = ( $gs_rgb2 <= 0.03928 ) ? $gs_rgb2 / 12.92 : pow( ( $gs_rgb2 + 0.055 ) / 1.055, 2.4 );
	$b2_new  = ( $bs_rgb2 <= 0.03928 ) ? $bs_rgb2 / 12.92 : pow( ( $bs_rgb2 + 0.055 ) / 1.055, 2.4 );

	if ( $r + $g + $b <= $r2 + $g2 + $b2 ) {
		$l2 = ( .2126 * $r_new + 0.7152 * $g_new + 0.0722 * $b_new );
		$l1 = ( .2126 * $r2_new + 0.7152 * $b2_new + 0.0722 * $b2_new );
	} else {
		$l1 = ( .2126 * $r_new + 0.7152 * $g_new + 0.0722 * $b_new );
		$l2 = ( .2126 * $r2_new + 0.7152 * $g2_new + 0.0722 * $b2_new );
	}
	$luminosity = round( ( $l1 + 0.05 ) / ( $l2 + 0.05 ), 2 );

	return $luminosity;
}

/**
 * Convert an RGB value to a HEX value.
 *
 * @param int $r Red value.
 * @param int $g Green value.
 * @param int $b Blue value.
 *
 * @return Hexadecimal color equivalent.
 */
function mc_rgb2hex( $r, $g = - 1, $b = - 1 ) {
	if ( is_array( $r ) && sizeof( $r ) === 3 ) {
		list( $r, $g, $b ) = $r;
	}
	$r = intval( $r );
	$g = intval( $g );
	$b = intval( $b );

	$r = dechex( $r < 0 ? 0 : ( $r > 255 ? 255 : $r ) );
	$g = dechex( $g < 0 ? 0 : ( $g > 255 ? 255 : $g ) );
	$b = dechex( $b < 0 ? 0 : ( $b > 255 ? 255 : $b ) );

	$color  = ( strlen( $r ) < 2 ? '0' : '' ) . $r;
	$color .= ( strlen( $g ) < 2 ? '0' : '' ) . $g;
	$color .= ( strlen( $b ) < 2 ? '0' : '' ) . $b;

	return '#' . $color;
}

/**
 * Convert a Hexadecimal color value to RGB.
 *
 * @param string $color Hexadecimal value for a color.
 *
 * @return array of RGB values in R,G,B order.
 */
function mc_hex2rgb( $color ) {
	$color = str_replace( '#', '', $color );
	if ( strlen( $color ) !== 6 ) {
		return array( 0, 0, 0 );
	}
	$rgb = array();
	for ( $x = 0; $x < 3; $x ++ ) {
		$rgb[ $x ] = hexdec( substr( $color, ( 2 * $x ), 2 ) );
	}

	return $rgb;
}

/**
 * Calculate the luminosity ratio between two color values.
 *
 * @param string $color1 First color.
 * @param string $color2 Second color.
 *
 * @return array
 */
function mc_contrast( $color1, $color2 ) {
	$fore_color = $color1;
	if ( '#' === $fore_color[0] ) {
		$fore_color = str_replace( '#', '', $fore_color );
	}
	if ( 3 === strlen( $fore_color ) ) {
		$color6char  = $fore_color[0] . $fore_color[0];
		$color6char .= $fore_color[1] . $fore_color[1];
		$color6char .= $fore_color[2] . $fore_color[2];
		$fore_color  = $color6char;
	}
	if ( preg_match( '/^#?([0-9a-f]{1,2}){3}$/i', $fore_color ) ) {
		$echo_hex_fore = str_replace( '#', '', $fore_color );
	} else {
		$echo_hex_fore = 'FFFFFF';
	}
	$back_color = $color2;
	if ( '#' === $back_color[0] ) {
		$back_color = str_replace( '#', '', $back_color );
	}
	if ( 3 === strlen( $back_color ) ) {
		$color6char  = $back_color[0] . $back_color[0];
		$color6char .= $back_color[1] . $back_color[1];
		$color6char .= $back_color[2] . $back_color[2];
		$back_color  = $color6char;
	}
	if ( preg_match( '/^#?([0-9a-f]{1,2}){3}$/i', $back_color ) ) {
		$echo_hex_back = str_replace( '#', '', $back_color );
	} else {
		$echo_hex_back = 'FFFFFF';
	}
	$color  = mc_hex2rgb( $echo_hex_fore );
	$color2 = mc_hex2rgb( $echo_hex_back );
	$rfore  = $color[0];
	$gfore  = $color[1];
	$bfore  = $color[2];
	$rback  = $color2[0];
	$gback  = $color2[1];
	$bback  = $color2[2];
	$colors = array(
		'hex1'   => $echo_hex_fore,
		'hex2'   => $echo_hex_back,
		'red1'   => $rfore,
		'green1' => $gfore,
		'blue1'  => $bfore,
		'red2'   => $rback,
		'green2' => $gback,
		'blue2'  => $bback,
	);

	return $colors;
}

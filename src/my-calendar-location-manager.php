<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly



function my_calendar_manage_locations() {
	?>
	<div class="wrap my-calendar-admin">
	<?php my_calendar_check_db();
	// We do some checking to see what we're doing
	mc_mass_delete_locations();
	if ( ! empty( $_POST ) && ( ! isset( $_POST['mc_locations'] ) && ! isset( $_POST['mass_delete'] ) ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
	}
	?>
		<h1 class="wp-heading-inline"><?php _e( 'Manage Locations', 'my-calendar' ); ?></h1>
		<a href="<?php echo admin_url( "admin.php?page=my-calendar-location-manager" ); ?>" class="page-title-action"><?php _e( 'Add New', 'my-calendar' ); ?></a> 
		<hr class="wp-header-end">		
	<div class="postbox-container jcd-wide">
		<div class="metabox-holder">	
			<div class="ui-sortable meta-box-sortables">
				<div class="postbox">
					<h2><?php _e( 'Manage Locations', 'my-calendar' ); ?></h2>

					<div class="inside">
						<?php mc_manage_locations(); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
		<?php mc_show_sidebar(); ?>
	</div>			
	<?php
}

function mc_mass_delete_locations() {
	global $wpdb;
	// mass delete locations
	if ( ! empty( $_POST['mass_edit'] ) && isset( $_POST['mass_delete'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
		$locations = $_POST['mass_edit'];
		$i         = $total = 0;
		$deleted   = $ids = array();
		foreach ( $locations as $value ) {
			$total     = count( $locations );
			$ids[]     = (int) $value;
			$deleted[] = $value;
			$i ++;
		}
		$statement = implode( ',', $ids );
		$sql       = 'DELETE FROM ' . my_calendar_locations_table() . " WHERE location_id IN ($statement)";
		$result    = $wpdb->query( $sql );
		if ( $result !== 0 && $result !== false ) {
			// argument: array of event IDs
			do_action( 'mc_mass_delete_locations', $deleted );
			$message = "<div class='updated'><p>" . sprintf( __( '%1$d locations deleted successfully out of %2$d selected', 'my-calendar' ), $i, $total ) . "</p></div>";
		} else {
			$message = "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong>" . __( 'Your locations have not been deleted. Please investigate.', 'my-calendar' ) . "</p></div>";
		}
		echo $message;
	}
}

function mc_manage_locations() {
	global $wpdb;
	$orderby = 'location_label';
	
	if ( isset( $_GET['orderby'] ) ) {
		switch( $_GET['orderby'] ) {
			case 'city' : $orderby = 'location_city'; break;
			case 'state' : $orderby = 'location_state'; break;
			case 'id' : $orderby = 'location_id'; break;
			default : $orderby = 'location_label';
		}
	}
	// pull the locations from the database
	$items_per_page = 50;
	$search         = '';
	$current        = empty( $_GET['paged'] ) ? 1 : intval( $_GET['paged'] );
	if ( isset( $_POST['mcl'] ) ) {
		$query = $_POST['mcl'];
		$db_type = mc_get_db_type();
		if ( $query != '' ) {
			if ( $db_type == 'MyISAM' ) {
				$search = " WHERE MATCH(" . apply_filters( 'mc_search_fields', 'location_label,location_city,location_state,location_region,location_street,location_street2,location_phone' ) . ") AGAINST ( '$query' IN BOOLEAN MODE ) ";
			} else {
				$search = " WHERE location_label LIKE '%$query%' OR
							location_city LIKE '%$query%' OR
							location_state LIKE '%$query%' OR
							location_region LIKE '%$query%' OR
							location_street LIKE '%$query%' OR
							location_street2 LIKE '%$query%' OR
							location_phone LIKE '%$query%' ";
			}
		} else {
			$search = '';
		}		
	}
		
	$locations      = $wpdb->get_results( "SELECT SQL_CALC_FOUND_ROWS * FROM " . my_calendar_locations_table() . " $search ORDER BY $orderby ASC LIMIT " . ( ( $current - 1 ) * $items_per_page ) . ", " . $items_per_page );
	$found_rows     = $wpdb->get_col( "SELECT FOUND_ROWS();" );
	$items          = $found_rows[0];

	$num_pages = ceil( $items / $items_per_page );
	if ( $num_pages > 1 ) {
		$page_links = paginate_links( array(
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'prev_text' => __( '&laquo; Previous<span class="screen-reader-text"> Locations</span>', 'my-calendar' ),
			'next_text' => __( 'Next<span class="screen-reader-text"> Locations</span> &raquo;', 'my-calendar' ),
			'total'     => $num_pages,
			'current'   => $current,
			'mid_size'  => 1
		) );
		printf( "<div class='tablenav'><div class='tablenav-pages'>%s</div></div>", $page_links );
	}

	if ( ! empty( $locations ) ) {
		?>
		<div class='mc-search'>
			<form action="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar-location-manager' ) ); ?>" method="post">
				<div><input type="hidden" name="_wpnonce"
				            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
				</div>
				<div>
					<label for="mc_search" class='screen-reader-text'><?php _e( 'Search', 'my-calendar' ); ?></label>
					<input type='text' role='search' name='mcl' id='mc_search'
					       value='<?php if ( isset( $_POST['mcl'] ) ) {
						       esc_attr_e( $_POST['mcl'] );
					       } ?>'/> <input type='submit' value='<?php _e( 'Search Locations', 'my-calendar' ); ?>'
					                      class='button-secondary'/>
				</div>
			</form>
		</div>		
	<form action="<?php echo esc_url( add_query_arg( $_GET, admin_url( 'admin.php' ) ) ); ?>" method="post">
		<div><input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
		<div class='mc-actions'>
			<input type="submit" class="button-secondary delete" name="mass_delete"
			       value="<?php _e( 'Delete locations', 'my-calendar' ); ?>"/>
		</div>
		<table class="widefat page" id="my-calendar-admin-table">
			<thead>
			<tr>
				<th scope="col"><a href='<?php echo add_query_arg( array( 'paged' => $current, 'orderby' => 'id' ), admin_url( 'admin.php?page=my-calendar-location-manager' ) ); ?>'><?php _e( 'ID', 'my-calendar' ) ?></a></th>
				<th scope="col"><a href='<?php echo add_query_arg( array( 'paged' => $current, 'orderby' => 'location' ), admin_url( 'admin.php?page=my-calendar-location-manager' ) ); ?>'><?php _e( 'Location', 'my-calendar' ) ?></th>
				<th scope="col"><a href='<?php echo add_query_arg( array( 'paged' => $current, 'orderby' => 'city' ), admin_url( 'admin.php?page=my-calendar-location-manager' ) ); ?>'><?php _e( 'City', 'my-calendar' ) ?></th>
				<th scope="col"><a href='<?php echo add_query_arg( array( 'paged' => $current, 'orderby' => 'state' ), admin_url( 'admin.php?page=my-calendar-location-manager' ) ); ?>'><?php _e( 'State/Province', 'my-calendar' ) ?></th>
				<th scope="col"><?php _e( 'Edit', 'my-calendar' ) ?></th>
				<th scope="col"><?php _e( 'Delete', 'my-calendar' ) ?></th>
			</tr>
			</thead>
			<?php
			$class = '';	
			foreach ( $locations as $location ) {
				$class = ( $class == 'alternate' ) ? '' : 'alternate'; ?>
				<tr class="<?php echo $class; ?>">
					<th scope="row"><input type="checkbox" value="<?php echo $location->location_id; ?>"
					                       name="mass_edit[]" id="mc<?php echo $location->location_id; ?>"/> <label
							for="mc<?php echo $location->location_id; ?>"><?php echo $location->location_id; ?></label>
					</th>
					<td><?php echo mc_hcard( $location, 'true', 'false', 'location' ); ?></td>
					<td><?php esc_html_e( $location->location_city ); ?></td>
					<td><?php esc_html_e( $location->location_state ); ?></td>
					<td>
						<a href="<?php echo admin_url( "admin.php?page=my-calendar-locations&amp;mode=edit&amp;location_id=$location->location_id" ); ?>"
						   class='edit'><?php _e( 'Edit', 'my-calendar' ); ?></a></td>
					<td>
						<a href="<?php echo admin_url( "admin.php?page=my-calendar-locations&amp;mode=delete&amp;location_id=$location->location_id" ); ?>" class="delete" onclick="return confirm('<?php _e( 'Are you sure you want to delete this location?', 'my-calendar' ); ?>')"><?php _e( 'Delete', 'my-calendar' ); ?></a>
					</td>
				</tr>
			<?php } ?>
		</table>
		<p>
			<input type="submit" class="button-secondary delete" name="mass_delete" value="<?php _e( 'Delete locations', 'my-calendar' ); ?>" />
		</p>
		</form><?php
	} else {
		echo '<p>' . __( 'There are no locations in the database yet!', 'my-calendar' ) . '</p>';
	}
}
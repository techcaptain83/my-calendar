<?php
/**
 * Event Manager. Listing and organization of events.
 *
 * @category Events
 * @package  My Calendar
 * @author   Joe Dolson
 * @license  GPLv2 or later
 * @link     https://www.joedolson.com/my-calendar/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle a bulk action.
 *
 * @param string $action type of action.
 * @param array  $events Optional. Array of event IDs to act on.
 *
 * @return array bulk action details.
 */
function mc_bulk_action( $action, $events = array() ) {
	global $wpdb;
	$events  = ( empty( $events ) ) ? $_POST['mass_edit'] : $events;
	$i       = 0;
	$total   = 0;
	$ids     = array();
	$prepare = array();

	foreach ( $events as $value ) {
		$value = (int) $value;
		$total = count( $events );
		if ( 'delete' === $action ) {
			$result = $wpdb->get_results( $wpdb->prepare( 'SELECT event_author FROM ' . my_calendar_table() . ' WHERE event_id = %d', $value ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( mc_can_edit_event( $value ) ) {
				$occurrences = 'DELETE FROM ' . my_calendar_event_table() . ' WHERE occur_event_id = %d';
				$wpdb->query( $wpdb->prepare( $occurrences, $value ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$ids[]     = (int) $value;
				$prepare[] = '%d';
				$i ++;
			}
		}
		if ( 'delete' !== $action && current_user_can( 'mc_approve_events' ) ) {
			$ids[]     = (int) $value;
			$prepare[] = '%d';
			$i ++;
		}
	}
	$prepared = implode( ',', $prepare );

	switch ( $action ) {
		case 'delete':
			$sql = 'DELETE FROM ' . my_calendar_table() . ' WHERE event_id IN (' . $prepared . ')';
			break;
		case 'unarchive':
			$sql = 'UPDATE ' . my_calendar_table() . ' SET event_status = 1 WHERE event_id IN (' . $prepared . ')';
			break;
		case 'archive':
			$sql = 'UPDATE ' . my_calendar_table() . ' SET event_status = 0 WHERE event_id IN (' . $prepared . ')';
			break;
		case 'approve': // Synonymous with publish.
			$sql = 'UPDATE ' . my_calendar_table() . ' SET event_approved = 1 WHERE event_id IN (' . $prepared . ')';
			break;
		case 'draft':
			$sql = 'UPDATE ' . my_calendar_table() . ' SET event_approved = 0 WHERE event_id IN (' . $prepared . ')';
			break;
		case 'trash':
			$sql = 'UPDATE ' . my_calendar_table() . ' SET event_approved = 2 WHERE event_id IN (' . $prepared . ')';
			break;
		case 'unspam':
			$sql = 'UPDATE ' . my_calendar_table() . ' SET event_flagged = 0 WHERE event_id IN (' . $prepared . ')';
			// send notifications.
			foreach ( $ids as $id ) {
				$post_ID   = mc_get_event_post( $id );
				$submitter = get_post_meta( $post_ID, '_submitter_details', true );
				if ( is_array( $submitter ) && ! empty( $submitter ) ) {
					$name  = $submitter['first_name'] . ' ' . $submitter['last_name'];
					$email = $submitter['email'];
					do_action( 'mcs_complete_submission', $name, $email, $id, 'edit' );
				}
			}
			break;
	}
	/**
	 * Add custom bulk actions.
	 *
	 * @param string $action Declared action.
	 * @param array  $ids Array of event IDs being requested.
	 */
	do_action( 'mc_bulk_actions', $action, $ids );

	$result = $wpdb->query( $wpdb->prepare( $sql, $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	mc_update_count_cache();
	$results = array(
		'count'  => $i,
		'total'  => $total,
		'ids'    => $ids,
		'result' => $result,
	);

	return mc_bulk_message( $results, $action );
}

/**
 * Generate a notification for bulk actions.
 *
 * @param array  $results of bulk action.
 * @param string $action Type of action.
 *
 * @return string message
 */
function mc_bulk_message( $results, $action ) {
	$count  = $results['count'];
	$total  = $results['total'];
	$ids    = $results['ids'];
	$result = $results['result'];

	switch ( $action ) {
		case 'delete':
			// Translators: Number of events deleted, number selected.
			$success = __( '%1$d events deleted successfully out of %2$d selected.', 'my-calendar' );
			$error   = __( 'Your events have not been deleted. Please investigate.', 'my-calendar' );
			break;
		case 'trash':
			// Translators: Number of events trashed, number of events selected.
			$success = __( '%1$d events trashed successfully out of %2$d selected.', 'my-calendar' );
			$error   = __( 'Your events have not been trashed. Please investigate.', 'my-calendar' );
			break;
		case 'approve':
			// Translators: Number of events published, number of events selected.
			$success = __( '%1$d events published out of %2$d selected.', 'my-calendar' );
			$error   = __( 'Your events have not been published. Were these events already published? Please investigate.', 'my-calendar' );
			break;
		case 'draft':
			// Translators: Number of events converted to draft, number of events selected.
			$success = __( '%1$d events switched to drafts out of %2$d selected.', 'my-calendar' );
			$error   = __( 'Your events have not been switched to drafts. Were these events already drafts? Please investigate.', 'my-calendar' );
			break;
		case 'archive':
			// Translators: Number of events archived, number of events selected.
			$success = __( '%1$d events archived successfully out of %2$d selected.', 'my-calendar' );
			$error   = __( 'Your events have not been archived. Please investigate.', 'my-calendar' );
			break;
		case 'unarchive':
			// Translators: Number of events removed from archive, number of events selected.
			$success = __( '%1$d events removed from archive successfully out of %2$d selected.', 'my-calendar' );
			$error   = __( 'Your events have not been removed from the archive. Were these events already archived? Please investigate.', 'my-calendar' );
			break;
		case 'unspam':
			// Translators: Number of events removed from archive, number of events selected.
			$success = __( '%1$d events successfully unmarked as spam out of %2$d selected.', 'my-calendar' );
			$error   = __( 'Your events were not removed from spam. Please investigate.', 'my-calendar' );
			break;
	}

	if ( 0 !== $result && false !== $result ) {
		$diff = 0;
		if ( $result < $count ) {
			$diff = ( $count - $result );
			// Translators: Sprintf as a 3rd argument if this string is appended to prior error. # of unchanged events.
			$success .= ' ' . _n( '%3$d event was not changed in that update.', '%3$d events were not changed in that update.', $diff, 'my-calendar' );
		}
		do_action( 'mc_mass_' . $action . '_events', $ids );
		$message = mc_show_notice( sprintf( $success, $result, $total, $diff ) );
	} else {
		$message = mc_show_error( $error, false );
	}

	return $message;
}

/**
 * Generate form for listing events that are editable by current user
 */
function my_calendar_manage() {
	my_calendar_check();
	global $wpdb;
	if ( isset( $_GET['mode'] ) && 'delete' === $_GET['mode'] ) {
		$event_id = ( isset( $_GET['event_id'] ) ) ? absint( $_GET['event_id'] ) : false;
		$result   = $wpdb->get_results( $wpdb->prepare( 'SELECT event_title, event_author FROM ' . my_calendar_table() . ' WHERE event_id=%d', $event_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( mc_can_edit_event( $event_id ) ) {
			if ( isset( $_GET['date'] ) ) {
				$event_instance = (int) $_GET['date'];
				$inst           = $wpdb->get_var( $wpdb->prepare( 'SELECT occur_begin FROM ' . my_calendar_event_table() . ' WHERE occur_id=%d', $event_instance ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$instance_date  = '(' . mc_date( 'Y-m-d', mc_strtotime( $inst ), false ) . ')';
			} else {
				$instance_date = '';
			} ?>
			<div class="error">
				<form action="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar-manage' ) ); ?>" method="post">
					<p><strong><?php esc_html_e( 'Delete Event', 'my-calendar' ); ?>:</strong> <?php esc_html_e( 'Are you sure you want to delete this event?', 'my-calendar' ); ?>
						<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
						<input type="hidden" value="delete" name="event_action" />
						<?php
						if ( ! empty( $_GET['date'] ) ) {
							?>
						<input type="hidden" name="event_instance" value="<?php echo (int) $_GET['date']; ?>"/>
							<?php
						}
						if ( isset( $_GET['ref'] ) ) {
							?>
						<input type="hidden" name="ref" value="<?php echo esc_url( $_GET['ref'] ); ?>" />
							<?php
						}
						?>
						<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>"/>
						<?php
							$event_info = ' &quot;' . stripslashes( $result[0]['event_title'] ) . "&quot; $instance_date";
							// Translators: Title & date of event to delete.
							$delete_text = sprintf( __( 'Delete %s', 'my-calendar' ), $event_info );
						?>
						<input type="submit" name="submit" class="button-secondary delete" value="<?php echo esc_attr( $delete_text ); ?>"/>
				</form>
			</div>
			<?php
		} else {
			mc_show_error( __( 'You do not have permission to delete that event.', 'my-calendar' ) );
		}
	}

	// Approve and show an Event ...originally by Roland.
	if ( isset( $_GET['mode'] ) && 'publish' === $_GET['mode'] ) {
		if ( current_user_can( 'mc_approve_events' ) ) {
			$event_id = absint( $_GET['event_id'] );
			$wpdb->get_results( $wpdb->prepare( 'UPDATE ' . my_calendar_table() . ' SET event_approved = 1 WHERE event_id=%d', $event_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			mc_update_count_cache();
		} else {
			mc_show_error( __( 'You do not have permission to approve that event.', 'my-calendar' ) );
		}
	}

	// Reject and hide an Event ...by Roland.
	if ( isset( $_GET['mode'] ) && 'reject' === $_GET['mode'] ) {
		if ( current_user_can( 'mc_approve_events' ) ) {
			$event_id = absint( $_GET['event_id'] );
			$wpdb->get_results( $wpdb->prepare( 'UPDATE ' . my_calendar_table() . ' SET event_approved = 2 WHERE event_id=%d', $event_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			mc_update_count_cache();
		} else {
			mc_show_error( __( 'You do not have permission to trash that event.', 'my-calendar' ) );
		}
	}

	if ( ! empty( $_POST['mc_bulk_actions'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( 'Security check failed' );
		}
		if ( isset( $_POST['mc_bulk_actions'] ) ) {
			$action  = $_POST['mc_bulk_actions'];
			$results = '';
			if ( 'mass_delete' === $action ) {
				$results = mc_bulk_action( 'delete' );
			}

			if ( 'mass_trash' === $action ) {
				$results = mc_bulk_action( 'trash' );
			}

			if ( 'mass_publish' === $action ) {
				$results = mc_bulk_action( 'approve' );
			}

			if ( 'mass_draft' === $action ) {
				$results = mc_bulk_action( 'draft' );
			}

			if ( 'mass_archive' === $action ) {
				$results = mc_bulk_action( 'archive' );
			}

			if ( 'mass_undo_archive' === $action ) {
				$results = mc_bulk_action( 'unarchive' );
			}

			if ( 'mass_not_spam' === $action ) {
				$results = mc_bulk_action( 'unspam' );
			}

			echo wp_kses_post( $results );
		}
	}
	?>
	<div class='wrap my-calendar-admin'>
		<h1 id="mc-manage" class="wp-heading-inline"><?php esc_html_e( 'Events', 'my-calendar' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'my-calendar' ); ?></a>
		<hr class="wp-header-end">
		<div class="mc-tablinks">
			<a href="#my-calendar-admin-table" aria-current="page"><?php esc_html_e( 'My Events', 'my-calendar' ); ?></strong>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar-manage&groups=true' ) ); ?>"><?php esc_html_e( 'Event Groups', 'my-calendar' ); ?></a>
		</div>
		<div class="postbox-container jcd-wide">
			<div class="metabox-holder">
				<div class="ui-sortable meta-box-sortables">
					<div class="postbox">
						<h2 class="mc-heading-inline"><?php esc_html_e( 'My Events', 'my-calendar' ); ?></h2>
							<?php
								$grid     = ( 'grid' === get_option( 'mc_default_admin_view' ) ) ? true : false;
								$grid_url = admin_url( 'admin.php?page=my-calendar-manage&view=grid' );
								$list_url = admin_url( 'admin.php?page=my-calendar-manage&view=list' );
							?>
						<ul class="mc-admin-mode">
							<li><span class="dashicons dashicons-calendar" aria-hidden="true"></span><a <?php echo ( $grid ) ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url( $grid_url ); ?>"><?php esc_html_e( 'Grid View', 'my-calendar' ); ?></a></li>
							<li><span class="dashicons dashicons-list-view" aria-hidden="true"></span><a <?php echo ( $grid ) ? '' : 'aria-current="true"'; ?>  href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'List View', 'my-calendar' ); ?></a></li>
						</ul>

						<div class="inside">
							<?php
							if ( $grid ) {
								$calendar = array(
									'name'     => 'admin',
									'format'   => 'calendar',
									'category' => 'all',
									'time'     => 'month',
									'id'       => 'mc-admin-view',
									'below'    => 'categories,locations,access',
									'above'    => 'nav,jump,search',
								);
								if ( mc_count_locations() > 200 ) {
									$calendar['below'] = 'categories,access';
								}
								apply_filters( 'mc_filter_admin_grid_args', $calendar );
								echo my_calendar( $calendar );
							} else {
								mc_list_events();
							}
							?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php
		$problems = mc_list_problems();
		mc_show_sidebar( '', $problems );
		?>
	</div>
	<?php
}

/**
 * Generate screens for editing and managing events & event groups.
 */
function my_calendar_manage_screen() {
	if ( ! isset( $_GET['groups'] ) ) {
		my_calendar_manage();
	} else {
		my_calendar_group_edit();
	}
}

/**
 * Show bulk actions dropdown in event manager.
 *
 * @return string
 */
function mc_show_bulk_actions() {
	$bulk_actions = array(
		'mass_publish'      => __( 'Publish', 'my-calendar' ),
		'mass_not_spam'     => __( 'Not spam', 'my-calendar' ),
		'mass_draft'        => __( 'Switch to Draft', 'my-calendar' ),
		'mass_trash'        => __( 'Trash', 'my-calendar' ),
		'mass_archive'      => __( 'Archive', 'my-calendar' ),
		'mass_undo_archive' => __( 'Remove from Archive', 'my-calendar' ),
		'mass_delete'       => __( 'Delete', 'my-calendar' ),
	);

	if ( ! current_user_can( 'mc_approve_events' ) || isset( $_GET['limit'] ) && 'published' === $_GET['limit'] ) {
		unset( $bulk_actions['mass_publish'] );
	}
	if ( ! current_user_can( 'mc_manage_events' ) || isset( $_GET['limit'] ) && 'trashed' === $_GET['limit'] ) {
		unset( $bulk_actions['mass_trash'] );
	}
	if ( isset( $_GET['limit'] ) && 'draft' === $_GET['limit'] ) {
		unset( $bulk_actions['mass_draft'] );
	}
	if ( isset( $_GET['restrict'] ) && 'archived' === $_GET['restrict'] ) {
		unset( $bulk_actions['mass_archive'] );
	} else {
		unset( $bulk_actions['mass_undo_archive'] );
	}
	if ( ! ( isset( $_GET['restrict'] ) && 'flagged' === $_GET['restrict'] ) ) {
		unset( $bulk_actions['mass_not_spam'] );
	}

	/**
	 * Filter Event manager bulk actions.
	 *
	 * @param array $bulk_actions Array of bulk actions currently available.
	 *
	 * @return array
	 */
	$bulk_actions = apply_filters( 'mc_bulk_actions', $bulk_actions );
	$options      = '';
	foreach ( $bulk_actions as $action => $label ) {
		$options .= '<option value="' . $action . '">' . $label . '</option>';
	}

	return $options;
}

/**
 * Used on the manage events admin page to display a list of events
 */
function mc_list_events() {
	global $wpdb;
	if ( current_user_can( 'mc_approve_events' ) || current_user_can( 'mc_manage_events' ) || current_user_can( 'mc_add_events' ) ) {

		$action   = ! empty( $_POST['event_action'] ) ? $_POST['event_action'] : '';
		$event_id = ! empty( $_POST['event_id'] ) ? $_POST['event_id'] : '';
		if ( 'delete' === $action ) {
			$message = mc_delete_event( $event_id );
			echo wp_kses_post( $message );
		}

		if ( isset( $_GET['order'] ) ) {
			$sortdir = ( isset( $_GET['order'] ) && 'ASC' === $_GET['order'] ) ? 'ASC' : 'default';
			$sortdir = ( isset( $_GET['order'] ) && 'DESC' === $_GET['order'] ) ? 'DESC' : $sortdir;
		} else {
			$sortdir = 'default';
		}

		$default_direction = ( '' === get_option( 'mc_default_direction', '' ) ) ? 'ASC' : get_option( 'mc_default_direction' );
		$sortbydirection   = ( 'default' === $sortdir ) ? $default_direction : $sortdir;

		$sortby = ( isset( $_GET['sort'] ) ) ? $_GET['sort'] : (int) get_option( 'mc_default_sort' );
		if ( empty( $sortby ) ) {
			$sortbyvalue = 'event_begin';
		} else {
			switch ( $sortby ) {
				case 1:
					$sortbyvalue = 'event_ID';
					break;
				case 2:
				case 3:
					$sortbyvalue = 'event_title';
					break;
				case 4:
					$sortbyvalue = "event_begin $sortbydirection, event_time";
					break;
				case 5:
					$sortbyvalue = 'event_author';
					break;
				case 6:
					$sortbyvalue = 'event_category';
					break;
				case 7:
					$sortbyvalue = 'event_label';
					break;
				default:
					$sortbyvalue = "event_begin $sortbydirection, event_time";
			}
		}
		$sort          = ( 'DESC' === $sortbydirection ) ? 'ASC' : 'DESC';
		$allow_filters = true;
		$status        = ( isset( $_GET['limit'] ) ) ? $_GET['limit'] : '';
		$restrict      = ( isset( $_GET['restrict'] ) ) ? $_GET['restrict'] : 'all';
		switch ( $status ) {
			case 'all':
				$limit = '';
				break;
			case 'draft':
				$limit = 'WHERE event_approved = 0';
				break;
			case 'published':
				$limit = 'WHERE event_approved = 1';
				break;
			case 'trashed':
				$limit = 'WHERE event_approved = 2';
				break;
			default:
				$limit = 'WHERE event_approved != 2';
		}
		switch ( $restrict ) {
			case 'all':
				$filter = '';
				break;
			case 'where':
				$filter   = ( isset( $_GET['filter'] ) ) ? $_GET['filter'] : '';
				$restrict = 'event_label';
				break;
			case 'author':
				$filter   = ( isset( $_GET['filter'] ) ) ? (int) $_GET['filter'] : '';
				$restrict = 'event_author';
				break;
			case 'category':
				$filter   = ( isset( $_GET['filter'] ) ) ? (int) $_GET['filter'] : '';
				$restrict = 'event_category';
				break;
			case 'flagged':
				$filter   = ( isset( $_GET['filter'] ) ) ? (int) $_GET['filter'] : '';
				$restrict = 'event_flagged';
				break;
			default:
				$filter = '';
		}
		if ( ! current_user_can( 'mc_manage_events' ) && ! current_user_can( 'mc_approve_events' ) ) {
			$restrict      = 'event_author';
			$filter        = get_current_user_id();
			$allow_filters = false;
		}
		$filter = esc_sql( urldecode( $filter ) );
		if ( 'event_label' === $restrict ) {
			$filter = "'$filter'";
		}
		$join = '';
		if ( 'event_category' === $restrict ) {
			$cat_limit       = mc_select_category( $filter );
			$join            = ( isset( $cat_limit[0] ) ) ? $cat_limit[0] : '';
			$select_category = ( isset( $cat_limit[1] ) ) ? $cat_limit[1] : '';
			$limit          .= ' ' . $select_category;
		}
		if ( '' === $limit && '' !== $filter ) {
			$limit = "WHERE $restrict = $filter";
		} elseif ( '' !== $limit && '' !== $filter && 'event_category' !== $restrict ) {
			$limit .= " AND $restrict = $filter";
		}
		if ( '' === $filter || ! $allow_filters ) {
			$filtered = '';
		} else {
			$filtered = "<a class='mc-clear-filters' href='" . admin_url( 'admin.php?page=my-calendar-manage' ) . "'><span class='dashicons dashicons-no' aria-hidden='true'></span> " . __( 'Clear filters', 'my-calendar' ) . '</a>';
		}
		$current        = empty( $_GET['paged'] ) ? 1 : intval( $_GET['paged'] );
		$user           = get_current_user_id();
		$screen         = get_current_screen();
		$option         = $screen->get_option( 'per_page', 'option' );
		$items_per_page = get_user_meta( $user, $option, true );
		if ( empty( $items_per_page ) || $items_per_page < 1 ) {
			$items_per_page = $screen->get_option( 'per_page', 'default' );
		}
		// Default limits.
		if ( '' === $limit ) {
			$limit .= ( 'event_flagged' !== $restrict ) ? ' WHERE event_flagged = 0' : '';
		} else {
			$limit .= ( 'event_flagged' !== $restrict ) ? ' AND event_flagged = 0' : '';
		}
		if ( isset( $_POST['mcs'] ) || isset( $_GET['mcs'] ) ) {
			$query  = $_REQUEST['mcs'];
			$limit .= mc_prepare_search_query( $query );
		}
		$query_limit = ( ( $current - 1 ) * $items_per_page );
		$limit      .= ( 'archived' !== $restrict ) ? ' AND e.event_status = 1' : ' AND e.event_status = 0';
		if ( 'event_category' !== $sortbyvalue ) {
			$events = $wpdb->get_results( $wpdb->prepare( 'SELECT SQL_CALC_FOUND_ROWS e.event_id FROM ' . my_calendar_table() . " AS e $join $limit ORDER BY $sortbyvalue $sortbydirection " . 'LIMIT %d, %d', $query_limit, $items_per_page ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$limit  = str_replace( array( 'WHERE ' ), '', $limit );
			$limit  = ( strpos( $limit, 'AND' ) === 0 ) ? $limit : 'AND ' . $limit;
			$events = $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT SQL_CALC_FOUND_ROWS e.event_id FROM ' . my_calendar_table() . ' AS e ' . $join . ' JOIN ' . my_calendar_categories_table() . " AS c WHERE e.event_category = c.category_id $limit ORDER BY c.category_name $sortbydirection " . 'LIMIT %d, %d', $query_limit, $items_per_page ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		}
		$found_rows = $wpdb->get_col( 'SELECT FOUND_ROWS();' );
		$items      = $found_rows[0];
		$num_pages  = ceil( $items / $items_per_page );
		if ( $num_pages > 1 ) {
			$page_links = paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'prev_text' => __( '&laquo; Previous<span class="screen-reader-text"> Events</span>', 'my-calendar' ),
					'next_text' => __( 'Next<span class="screen-reader-text"> Events</span> &raquo;', 'my-calendar' ),
					'total'     => $num_pages,
					'current'   => $current,
					'mid_size'  => 2,
				)
			);
			printf( "<div class='tablenav'><div class='tablenav-pages'>%s</div></div>", $page_links );
		}
		$status_links = mc_status_links( $allow_filters );
		$search_text  = ( isset( $_POST['mcs'] ) ) ? $_POST['mcs'] : '';
		echo wp_kses( $filtered, mc_kses_elements() );
		?>
		<div class="mc-admin-header">
			<?php echo wp_kses( $status_links, mc_kses_elements() ); ?>
			<div class='mc-search'>
				<form action="<?php echo esc_url( add_query_arg( $_GET, admin_url( 'admin.php' ) ) ); ?>" method="post">
					<div><input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
					</div>
					<div>
						<label for="mc_search" class='screen-reader-text'><?php esc_html_e( 'Search Events', 'my-calendar' ); ?></label>
						<input type='text' role='search' name='mcs' id='mc_search' value='<?php echo esc_attr( $search_text ); ?>' />
						<input type='submit' value='<?php echo esc_attr( __( 'Search', 'my-calendar' ) ); ?>' class='button-secondary' />
					</div>
				</form>
			</div>
		</div>
		<?php
		if ( ! empty( $events ) ) {
			?>
			<form action="<?php echo esc_url( add_query_arg( $_GET, admin_url( 'admin.php' ) ) ); ?>" method="post">
				<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>" />
				<div class='mc-actions'>
					<label for="mc_bulk_actions" class="screen-reader-text"><?php esc_html_e( 'Bulk actions', 'my-calendar' ); ?></label>
					<select name="mc_bulk_actions" id="mc_bulk_actions">
						<option value=""><?php esc_html_e( 'Bulk actions', 'my-calendar' ); ?></option>
						<?php echo mc_show_bulk_actions(); ?>
					</select>
					<input type="submit" class="button-secondary" value="<?php echo esc_attr( __( 'Apply', 'my-calendar' ) ); ?>" />
					<div><input type='checkbox' class='selectall' id='mass_edit' data-action="mass_edit" /> <label for='mass_edit'><?php esc_html_e( 'Check all', 'my-calendar' ); ?></label></div>
				</div>

			<table class="widefat striped wp-list-table" id="my-calendar-admin-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'Event list. Use column headers to sort.', 'my-calendar' ); ?></caption>
				<thead>
					<tr>
					<?php
					$admin_url = admin_url( "admin.php?page=my-calendar-manage&order=$sort&paged=$current" );
					$url       = add_query_arg( 'sort', '1', $admin_url );
					$col_head  = mc_table_header( __( 'ID', 'my-calendar' ), $sort, $sortby, '1', $url );
					$url       = add_query_arg( 'sort', '2', $admin_url );
					$col_head .= mc_table_header( __( 'Title', 'my-calendar' ), $sort, $sortby, '2', $url );
					$url       = add_query_arg( 'sort', '7', $admin_url );
					$col_head .= mc_table_header( __( 'Location', 'my-calendar' ), $sort, $sortby, '7', $url );
					$url       = add_query_arg( 'sort', '4', $admin_url );
					$col_head .= mc_table_header( __( 'Date/Time', 'my-calendar' ), $sort, $sortby, '4', $url );
					$url       = add_query_arg( 'sort', '5', $admin_url );
					$col_head .= mc_table_header( __( 'Author', 'my-calendar' ), $sort, $sortby, '5', $url );
					$url       = add_query_arg( 'sort', '6', $admin_url );
					$col_head .= mc_table_header( __( 'Category', 'my-calendar' ), $sort, $sortby, '6', $url );
					echo mc_kses_post( $col_head );
					?>
					</tr>
				</thead>
				<tbody>
				<?php
				$class = '';

				foreach ( array_keys( $events ) as $key ) {
					$e       =& $events[ $key ];
					$event   = mc_get_first_event( $e->event_id );
					$invalid = false;
					if ( ! is_object( $event ) ) {
						$event   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . my_calendar_table() . ' WHERE event_id = %d', $e->event_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$invalid = true;
					}
					$class   = ( $invalid ) ? 'invalid' : '';
					$pending = ( 0 === (int) $event->event_approved ) ? 'pending' : '';
					$trashed = ( 2 === (int) $event->event_approved ) ? 'trashed' : '';
					$author  = ( 0 !== (int) $event->event_author ) ? get_userdata( $event->event_author ) : 'Public Submitter';

					if ( 1 === (int) $event->event_flagged && ( isset( $_GET['restrict'] ) && 'flagged' === $_GET['restrict'] ) ) {
						$spam       = 'spam';
						$pending    = '';
						$spam_label = '<strong>' . esc_html__( 'Possible spam', 'my-calendar' ) . ':</strong> ';
					} else {
						$spam       = '';
						$spam_label = '';
					}

					$trash    = ( '' !== $trashed ) ? ' - ' . __( 'Trash', 'my-calendar' ) : '';
					$draft    = ( '' !== $pending ) ? ' - ' . __( 'Draft', 'my-calendar' ) : '';
					$inv      = ( $invalid ) ? ' - ' . __( 'Invalid Event', 'my-calendar' ) : '';
					$private  = ( mc_private_event( $event, false ) ) ? ' - ' . __( 'Private', 'my-calendar' ) : '';
					$check    = mc_test_occurrence_overlap( $event, true );
					$problem  = ( '' !== $check ) ? 'problem' : '';
					$edit_url = admin_url( "admin.php?page=my-calendar&amp;mode=edit&amp;event_id=$event->event_id" );
					$copy_url = admin_url( "admin.php?page=my-calendar&amp;mode=copy&amp;event_id=$event->event_id" );
					if ( ! $invalid ) {
						$view_url = mc_get_details_link( $event );
					} else {
						$view_url = '';
					}
					$group_url  = admin_url( "admin.php?page=my-calendar-manage&amp;groups=true&amp;mode=edit&amp;event_id=$event->event_id&amp;group_id=$event->event_group_id" );
					$delete_url = admin_url( "admin.php?page=my-calendar-manage&amp;mode=delete&amp;event_id=$event->event_id" );
					$can_edit   = mc_can_edit_event( $event );
					if ( current_user_can( 'mc_manage_events' ) || current_user_can( 'mc_approve_events' ) || $can_edit ) {
						?>
						<tr class="<?php echo sanitize_html_class( "$class $spam $pending $trashed $problem" ); ?>">
							<th scope="row">
								<input type="checkbox" value="<?php echo absint( $event->event_id ); ?>" name="mass_edit[]" id="mc<?php echo $event->event_id; ?>" aria-describedby='event<?php echo absint( $event->event_id ); ?>' />
								<label for="mc<?php echo absint( $event->event_id ); ?>">
								<?php
								// Translators: Event ID.
								printf( __( "<span class='screen-reader-text'>Select event </span>%d", 'my-calendar' ), absint( $event->event_id ) );
								?>
								</label>
							</th>
							<td>
								<strong>
								<?php
								if ( $can_edit ) {
									?>
									<a href="<?php echo esc_url( $edit_url ); ?>" class='edit'><span class="dashicons dashicons-edit" aria-hidden="true"></span>
									<?php
								}
								echo $spam_label;
								echo '<span id="event' . absint( $event->event_id ) . '">' . esc_html( stripslashes( $event->event_title ) ) . '</span>';
								if ( $can_edit ) {
									echo '</a>';
									if ( '' !== $check ) {
										// Translators: URL to edit event.
										echo wp_kses_post( '<br /><strong class="error">' . sprintf( __( 'There is a problem with this event. <a href="%s">Edit</a>', 'my-calendar' ), esc_url( $edit_url ) ) . '</strong>' );
									}
								}
								echo wp_kses_post( $private . $trash . $draft . $inv );
								?>
								</strong>

								<div class='row-actions'>
									<?php
									if ( mc_event_published( $event ) ) {
										?>
										<a href="<?php echo esc_url( $view_url ); ?>" class='view' aria-describedby='event<?php echo absint( $event->event_id ); ?>'><?php esc_html_e( 'View', 'my-calendar' ); ?></a> |
										<?php
									} elseif ( current_user_can( 'mc_manage_events' ) ) {
										?>
										<a href="<?php echo esc_url( add_query_arg( 'preview', 'true', $view_url ) ); ?>" class='view' aria-describedby='event<?php echo absint( $event->event_id ); ?>'><?php esc_html_e( 'Preview', 'my-calendar' ); ?></a> |
										<?php
									}
									if ( $can_edit ) {
										?>
										<a href="<?php echo esc_url( $copy_url ); ?>" class='copy' aria-describedby='event<?php echo absint( $event->event_id ); ?>'><?php esc_html_e( 'Copy', 'my-calendar' ); ?></a>
										<?php
									}
									if ( $can_edit ) {
										if ( mc_event_is_grouped( $event->event_group_id ) ) {
											?>
											| <a href="<?php echo esc_url( $group_url ); ?>" class='edit group' aria-describedby='event<?php echo absint( $event->event_id ); ?>'><?php esc_html_e( 'Edit Group', 'my-calendar' ); ?></a>
											<?php
										}
										?>
										| <a href="<?php echo esc_url( $delete_url ); ?>" class="delete" aria-describedby='event<?php echo absint( $event->event_id ); ?>'><?php esc_html_e( 'Delete', 'my-calendar' ); ?></a>
										<?php
									} else {
										_e( 'Not editable.', 'my-calendar' );
									}
									?>
									|
									<?php
									if ( current_user_can( 'mc_approve_events' ) && $can_edit ) {
										if ( 1 === (int) $event->event_approved ) {
											$mo = 'reject';
											$te = __( 'Trash', 'my-calendar' );
										} else {
											$mo = 'publish';
											$te = __( 'Publish', 'my-calendar' );
										}
										?>
										<a href="<?php echo esc_url( admin_url( "admin.php?page=my-calendar-manage&amp;mode=$mo&amp;event_id=$event->event_id" ) ); ?>" class='<?php echo esc_attr( $mo ); ?>' aria-describedby='event<?php echo absint( $event->event_id ); ?>'><?php echo esc_html( $te ); ?></a>
										<?php
									} else {
										switch ( $event->event_approved ) {
											case 1:
												_e( 'Published', 'my-calendar' );
												break;
											case 2:
												_e( 'Trashed', 'my-calendar' );
												break;
											default:
												_e( 'Awaiting Approval', 'my-calendar' );
										}
									}
									?>
								</div>
							</td>
							<td>
								<?php
								if ( property_exists( $event, 'location' ) && is_object( $event->location ) ) {
									$elabel = $event->location->location_label;
								} else {
									$elabel = $event->event_label;
								}
								if ( '' !== $elabel ) {
									?>
								<a class='mc_filter' href='<?php echo esc_url( mc_admin_url( 'admin.php?page=my-calendar-manage&amp;filter=' . urlencode( $elabel ) . '&amp;restrict=where' ) ); ?>'><span class="screen-reader-text"><?php esc_html_e( 'Show only: ', 'my-calendar' ); ?></span><?php echo esc_html( stripslashes( $elabel ) ); ?></a>
									<?php
								}
								?>
							</td>
							<td>
							<?php
							if ( '23:59:59' !== $event->event_endtime ) {
								$event_time = date_i18n( mc_time_format(), mc_strtotime( $event->event_time ) );
							} else {
								$event_time = mc_notime_label( $event );
							}
							$begin = date_i18n( mc_date_format(), mc_strtotime( $event->event_begin ) );
							echo esc_html( "$begin, $event_time" );
							?>
								<div class="recurs">
									<?php echo wp_kses_post( mc_recur_string( $event ) ); ?>
								</div>
							</td>
							<?php
							$auth   = ( is_object( $author ) ) ? $author->ID : 0;
							$filter = mc_admin_url( "admin.php?page=my-calendar-manage&amp;filter=$auth&amp;restrict=author" );
							$author = ( is_object( $author ) ? $author->display_name : $author );
							?>
							<td>
								<a class='mc_filter' href="<?php echo esc_url( $filter ); ?>">
									<span class="screen-reader-text"><?php esc_html_e( 'Show only: ', 'my-calendar' ); ?></span><?php echo esc_html( $author ); ?>
								</a>
							</td>
							<td>
							<?php echo mc_admin_category_list( $event ); ?>
							</td>
						</tr>
						<?php
					}
				}
				?>
				</tbody>
			</table>
			<div class="mc-actions">
				<label for="mc_bulk_actions_footer" class="screen-reader-text"><?php esc_html_e( 'Bulk actions', 'my-calendar' ); ?></label>
				<select name="mc_bulk_actions" id="mc_bulk_actions_footer">
					<option value=""><?php esc_html_e( 'Bulk actions', 'my-calendar' ); ?></option>
					<?php echo mc_show_bulk_actions(); ?>
				</select>
				<input type="submit" class="button-secondary" value="<?php echo esc_attr( __( 'Apply', 'my-calendar' ) ); ?>" />
				<input type='checkbox' class='selectall' id='mass_edit_footer' data-action="mass_edit" /> <label for='mass_edit_footer'><?php esc_html_e( 'Check all', 'my-calendar' ); ?></label>
			</div>
		</form>
		<div class='mc-admin-footer'>
			<?php
			$status_links = mc_status_links( $allow_filters );
			echo wp_kses( $status_links . $filtered, mc_kses_elements() );
			?>
			<div class='mc-search'>
			<form action="<?php echo esc_url( add_query_arg( $_GET, admin_url( 'admin.php' ) ) ); ?>" method="post">
				<div><input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
				</div>
				<div>
					<label for="mc_search_footer" class='screen-reader-text'><?php esc_html_e( 'Search Events', 'my-calendar' ); ?></label>
					<input type='text' role='search' name='mcs' id='mc_search_footer' value='<?php echo ( isset( $_POST['mcs'] ) ? esc_attr( $_POST['mcs'] ) : '' ); ?>' />
					<input type='submit' value='<?php echo esc_attr( __( 'Search', 'my-calendar' ) ); ?>' class='button-secondary'/>
				</div>
			</form>
			</div>
		</div>
			<?php
		} else {
			if ( isset( $_POST['mcs'] ) ) {
				echo '<p>' . esc_html__( 'No results found for your search query.', 'my-calendar' ) . '</p>';
			}
			if ( ! isset( $_GET['restrict'] ) && ( ! isset( $_GET['limit'] ) || isset( $_GET['limit'] ) && 'all' === $_GET['limit'] ) ) {
				?>
				<p class='mc-create-event'><a href="<?php echo esc_url( admin_url( 'admin.php?page=my-calendar' ) ); ?>" class="button button-hero"><?php esc_html_e( 'Create an event', 'my-calendar' ); ?></a></p>
				<?php
			} else {
				?>
				<p class='mc-none'><?php esc_html_e( 'No events found.', 'my-calendar' ); ?></p>
				<?php
			}
		}
	}
}

/**
 * Get next available group ID
 *
 * @return int
 */
function mc_group_id() {
	global $wpdb;
	$result = $wpdb->get_var( 'SELECT MAX(event_id) FROM ' . my_calendar_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$next   = $result + 1;

	return $next;
}

/**
 * Check whether an event is a member of a group
 *
 * @param int $group_id Event Group ID.
 *
 * @return boolean
 */
function mc_event_is_grouped( $group_id ) {
	global $wpdb;
	if ( 0 === (int) $group_id ) {
		return false;
	} else {
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT count( event_group_id ) FROM ' . my_calendar_table() . ' WHERE event_group_id = %d', $group_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $value > 1 ) {

			return true;
		} else {

			return false;
		}
	}
}

/**
 * Can the current user edit this category?
 *
 * @param int $category Category ID.
 * @param int $user User ID.
 *
 * @return boolean
 */
function mc_can_edit_category( $category, $user ) {
	$permissions = get_user_meta( $user, 'mc_user_permissions', true );
	$permissions = apply_filters( 'mc_user_permissions', $permissions, $category, $user );

	if ( ( ! $permissions || empty( $permissions ) ) || in_array( 'all', $permissions, true ) || in_array( $category, $permissions, true ) || current_user_can( 'manage_options' ) ) {
		return true;
	}

	return false;
}

/**
 * Unless an admin, authors can only edit their own events if they don't have mc_manage_events capabilities.
 *
 * @param object|boolean $event Event object.
 * @param string         $datatype 'event' or 'instance'.
 *
 * @return boolean
 */
function mc_can_edit_event( $event = false, $datatype = 'event' ) {
	global $wpdb;
	if ( ! $event ) {

		return false;
	}

	$api = apply_filters( 'mc_api_can_edit_event', false, $event );
	if ( $api ) {

		return $api;
	}

	if ( ! is_user_logged_in() ) {

		return false;
	}

	if ( is_object( $event ) ) {
		$event_id     = $event->event_id;
		$event_author = $event->event_author;
	} elseif ( is_int( $event ) ) {
		$event_id = $event;
		if ( 'event' === $datatype ) {
			$event = mc_get_first_event( $event );
			if ( ! is_object( $event ) ) {
				$event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . my_calendar_table() . ' WHERE event_id=%d LIMIT 1', $event_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		} else {
			$event = mc_get_event( $event_id );
		}
		$event_author = $event->event_author;
	} else {
		// What is the case where the event is neither an object, int, or falsey? Hmm.
		$event_author = wp_get_current_user()->ID;
		$event_id     = $event;
	}

	$current_user    = wp_get_current_user();
	$user            = $current_user->ID;
	$categories      = mc_get_categories( $event_id );
	$has_permissions = true;
	if ( is_array( $categories ) ) {
		foreach ( $categories as $cat ) {
			// If user doesn't have access to all relevant categories, prevent editing.
			if ( ! $has_permissions ) {
				continue;
			}
			$has_permissions = mc_can_edit_category( $cat, $user );
		}
	}
	$return = false;

	if ( ( current_user_can( 'mc_manage_events' ) && $has_permissions ) || ( $user === (int) $event_author ) ) {

		$return = true;
	}

	return apply_filters( 'mc_can_edit_event', $return, $event_id );
}

/**
 * Determine max values to increment
 *
 * @param string $recur Type of recurrence.
 */
function _mc_increment_values( $recur ) {
	switch ( $recur ) {
		case 'S': // Single.
			return 0;
			break;
		case 'D': // Daily.
			return 500;
			break;
		case 'E': // Weekdays.
			return 400;
			break;
		case 'W': // Weekly.
			return 240;
			break;
		case 'B': // Biweekly.
			return 240;
			break;
		case 'M': // Monthly.
		case 'U':
			return 240;
			break;
		case 'Y':
			return 50;
			break;
		default:
			false;
	}
}

/**
 * Deletes all instances of an event without deleting the event details. Sets stage for rebuilding event instances.
 *
 * @param int $id Event ID.
 */
function mc_delete_instances( $id ) {
	global $wpdb;
	$id = (int) $id;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . my_calendar_event_table() . ' WHERE occur_event_id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	// After bulk deletion, optimize table.
	$wpdb->query( 'OPTIMIZE TABLE ' . my_calendar_event_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}


/**
 * Check for events with known occurrence overlap problems.
 */
function mc_list_problems() {
	$events   = get_posts(
		array(
			'post_type'  => 'mc-events',
			'meta_key'   => '_occurrence_overlap',
			'meta_value' => 'false',
		)
	);
	$list     = array();
	$problems = array();

	if ( is_array( $events ) && count( $events ) > 0 ) {
		foreach ( $events as $event ) {
			$event_id  = get_post_meta( $event->ID, '_mc_event_id', true );
			$event_url = admin_url( 'admin.php?page=my-calendar&mode=edit&event_id=' . absint( $event_id ) );
			$list[]    = '<a href="' . esc_url( $event_url ) . '">' . esc_html( $event->post_title ) . '</a>';
		}
	}

	if ( ! empty( $list ) ) {
		$problems = array( 'Problem Events' => '<ul><li>' . implode( '</li><li>', $list ) . '</li></ul>' );
	}

	return $problems;
}

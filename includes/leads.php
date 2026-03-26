<?php
/**
 * Leads storage: table creation and helper for Epoxy quote leads.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lead table name.
 */
function efex_leads_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'efex_epoxy_quote_leads';
}

/**
 * Create/Update leads table using dbDelta.
 *
 * @param string $table_name
 */
function efex_maybe_create_leads_table( $table_name ) {
	global $wpdb;

	$charset = $wpdb->get_charset_collate();
	$sql      = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		first_name varchar(128) NOT NULL DEFAULT '',
		last_name varchar(128) NOT NULL DEFAULT '',
		phone varchar(32) NOT NULL DEFAULT '',
		email varchar(128) NOT NULL DEFAULT '',
		zip varchar(16) DEFAULT NULL,

		situation varchar(32) DEFAULT NULL,
		installation_area varchar(255) DEFAULT NULL,
		timeframe varchar(64) DEFAULT NULL,
		consented tinyint(1) NOT NULL DEFAULT 0,

		trustedform_cert_url varchar(512) DEFAULT NULL,
		send_to_crm tinyint(1) NOT NULL DEFAULT 0,
		notes text DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,

		PRIMARY KEY (id)
	) $charset;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

add_action( 'admin_menu', 'efex_leads_menu' );

function efex_leads_menu() {
	add_menu_page(
		'Epoxy Quote Leads',
		'Leads',
		'manage_options',
		'efex-leads',
		'efex_display_leads_page',
		'dashicons-list-view',
		6
	);

	add_submenu_page(
		null,
		'View Lead',
		'View Lead',
		'manage_options',
		'efex-view-lead',
		'efex_view_lead_page'
	);
}

function efex_display_leads_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$table_name = efex_leads_table_name();
	efex_maybe_create_leads_table( $table_name );

	if (
		isset( $_POST['action'] ) &&
		'efex-bulk-delete' === $_POST['action'] &&
		! empty( $_POST['lead'] )
	) {
		check_admin_referer( 'efex-bulk-delete' );

		$ids            = array_map( 'intval', (array) $_POST['lead'] );
		$placeholders  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$delete_query  = $wpdb->prepare( "DELETE FROM {$table_name} WHERE id IN ($placeholders)", $ids );
		$wpdb->query( $delete_query );

		echo '<div class="notice notice-success is-dismissible"><p>Selected leads deleted.</p></div>';
	}

	$limit         = 10;
	$total_records = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$total_pages   = max( 1, (int) ceil( $total_records / $limit ) );

	$current_page = isset( $_GET['page_number'] ) ? max( 1, (int) $_GET['page_number'] ) : 1;
	$current_page = min( $current_page, $total_pages );
	$offset        = ( $current_page - 1 ) * $limit;

	$leads = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d",
			$limit,
			$offset
		)
	);

	echo '<form method="post">';
	echo '<div class="wrap">';
	echo '<h1 class="wp-heading-inline">Epoxy Quote Leads</h1>';

	// Export button.
	$export_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=efex_export_leads' ),
		'efex_export_leads'
	);
	echo '<div class="alignleft actions" style="float:right">';
	echo '<a href="' . esc_url( $export_url ) . '" class="button action">Export CSV</a>';
	echo '</div>';

	echo '<hr class="wp-header-end">';

	// Pagination.
	$start = $total_records ? ( $current_page - 1 ) * $limit + 1 : 0;
	$end   = min( $current_page * $limit, $total_records );
	echo '<div class="tablenav top"><div class="tablenav-pages"><span class="displaying-num">' . esc_html( sprintf( '%d–%d of %d', $start, $end, $total_records ) ) . '</span>';

	if ( $total_pages > 1 ) {
		$base = admin_url( 'admin.php?page=efex-leads' );
		echo ' <span class="pagination-links">';
		if ( $current_page > 1 ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'page_number', $current_page - 1, $base ) ) . '">&lsaquo;</a> ';
		}
		echo '<span class="paging-input">' . esc_html( $current_page ) . ' of ' . esc_html( $total_pages ) . '</span> ';
		if ( $current_page < $total_pages ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'page_number', $current_page + 1, $base ) ) . '">&rsaquo;</a>';
		}
		echo '</span>';
	}
	echo '</div></div>';

	// Table.
	echo '<table class="wp-list-table widefat fixed striped">';
	echo '<thead><tr>';
	echo '<th class="check-column"><input type="checkbox" id="efex-cb-all"></th>';
	echo '<th>ID</th>';
	echo '<th>First Name</th>';
	echo '<th>Last Name</th>';
	echo '<th>Email</th>';
	echo '<th>ZIP</th>';
	echo '<th>Situation</th>';
	echo '<th>Area</th>';
	echo '<th>Timeframe</th>';
	echo '<th>CRM</th>';
	echo '<th>Actions</th>';
	echo '</tr></thead><tbody>';

	if ( $leads ) {
		$situation_labels = array(
			'homeowner' => 'I am the homeowner',
			'renter' => 'I rent this home',
			'rental_owner' => 'This is a rental property that I own',
			'commercial_owner' => 'This is for a commercial business I own',
		);

		foreach ( $leads as $lead ) {
			$crm = (int) $lead->send_to_crm === 1 ? 'Yes' : 'No';
			$situation_value = $lead->situation ?? '';
			$situation_label = isset( $situation_labels[ $situation_value ] ) ? $situation_labels[ $situation_value ] : $situation_value;

			echo '<tr>';
			echo '<th scope="row" class="check-column"><input type="checkbox" name="lead[]" value="' . esc_attr( $lead->id ) . '"></th>';
			echo '<td>' . esc_html( $lead->id ) . '</td>';
			echo '<td>' . esc_html( $lead->first_name ) . '</td>';
			echo '<td>' . esc_html( $lead->last_name ) . '</td>';
			echo '<td>' . esc_html( $lead->email ) . '</td>';
			echo '<td>' . esc_html( $lead->zip ) . '</td>';
			echo '<td>' . esc_html( $situation_label ) . '</td>';
			echo '<td>' . esc_html( $lead->installation_area ) . '</td>';
			echo '<td>' . esc_html( $lead->timeframe ) . '</td>';
			echo '<td>' . esc_html( $crm ) . '</td>';
			echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=efex-view-lead&lead_id=' . $lead->id ) ) . '">View</a></td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="11">No leads found.</td></tr>';
	}

	echo '</tbody></table>';

	echo '<div class="tablenav bottom"><div class="alignleft actions">';
	wp_nonce_field( 'efex-bulk-delete' );
	echo '<input type="hidden" name="action" value="efex-bulk-delete">';
	echo '<input type="submit" class="button action" value="Delete Selected">';
	echo '</div></div>';

	echo '</div></form>';

	?>
	<script>
	jQuery(function($) {
		$('#efex-cb-all').on('change', function() {
			$('input[name="lead[]"]').prop('checked', this.checked);
		});
	});
	</script>
	<?php
}

function efex_view_lead_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_GET['lead_id'] ) ) {
		echo '<div class="wrap"><p>Invalid request.</p></div>';
		return;
	}

	global $wpdb;
	$table_name = efex_leads_table_name();
	$lead_id    = (int) $_GET['lead_id'];
	$lead       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $lead_id ) );

	if ( ! $lead ) {
		echo '<div class="wrap"><p>Lead not found.</p></div>';
		return;
	}

	$situation_labels = array(
		'homeowner' => 'I am the homeowner',
		'renter' => 'I rent this home',
		'rental_owner' => 'This is a rental property that I own',
		'commercial_owner' => 'This is for a commercial business I own',
	);
	$situation_value = $lead->situation ?? '';
	$situation_label = isset( $situation_labels[ $situation_value ] ) ? $situation_labels[ $situation_value ] : $situation_value;

	echo '<div class="wrap">';
	echo '<h1>Lead #' . esc_html( $lead->id ) . '</h1><hr />';
	echo '<table class="wp-list-table widefat fixed striped">';

	$rows = array(
		'First Name' => $lead->first_name,
		'Last Name'  => $lead->last_name,
		'Phone'      => $lead->phone,
		'Email'      => $lead->email,
		'ZIP'        => $lead->zip,
		'Situation'  => $situation_label,
		'Area'       => $lead->installation_area,
		'Timeframe'  => $lead->timeframe,
		'Consented'  => ( (int) $lead->consented === 1 ) ? 'Yes' : 'No',
		'Send to CRM' => ( (int) $lead->send_to_crm === 1 ) ? 'Yes' : 'No',
		'TrustedForm URL' => $lead->trustedform_cert_url,
		'Created' => $lead->created_at,
	);

	foreach ( $rows as $label => $val ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
	}

	echo '<tr><th scope="row">Notes</th><td><pre style="white-space:pre-wrap; margin:0;">' . esc_html( $lead->notes ) . '</pre></td></tr>';

	echo '</table>';
	echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=efex-leads' ) ) . '" class="button">Back to Leads</a></p>';
	echo '</div>';
}

function efex_export_leads_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	check_admin_referer( 'efex_export_leads' );

	global $wpdb;
	$table_name = efex_leads_table_name();
	$leads      = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="epoxy-quote-leads.csv"' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );

	fputcsv(
		$out,
		array(
			'ID',
			'First Name',
			'Last Name',
			'Phone',
			'Email',
			'ZIP',
			'Situation',
			'Installation Area',
			'Timeframe',
			'Consented',
			'Send to CRM',
			'TrustedForm URL',
			'Notes',
			'Created At',
		)
	);

	foreach ( $leads as $row ) {
		fputcsv(
			$out,
			array(
				$row['id'],
				$row['first_name'],
				$row['last_name'],
				$row['phone'],
				$row['email'],
				$row['zip'],
				$row['situation'],
				$row['installation_area'],
				$row['timeframe'],
				(int) $row['consented'],
				(int) $row['send_to_crm'],
				$row['trustedform_cert_url'],
				$row['notes'],
				$row['created_at'],
			)
		);
	}

	fclose( $out );
	exit;
}

add_action( 'admin_post_efex_export_leads', 'efex_export_leads_handler' );


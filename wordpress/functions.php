<?php
/**
 * Noble Finance Solutions theme functions.
 * Built by Moses Ndovie.
 *
 * - Receives Financial Protection Health Check submissions from the page.
 * - Emails the client a PDF of their results and sends Maunty a copy with the client's details.
 * - Saves every submission under "Health Check Leads" in WP Admin (with CSV export) for follow-up.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_theme_support( 'title-tag' );

/** Where new-lead notifications go. Change here if Maunty wants a different inbox. */
define( 'NFS_NOTIFY_EMAIL', 'contact@noblefinancesolutions.com' );
define( 'NFS_FROM_EMAIL', 'contact@noblefinancesolutions.com' );
define( 'NFS_FROM_NAME', 'Noble Finance Solutions' );

const NFS_AREAS = array( 'Emergency Savings', 'Income Protection', 'Life Insurance', 'Debt & Housing', 'Retirement Readiness' );

/* ------------------------------------------------------------------ */
/* Leads stored in WP Admin                                            */
/* ------------------------------------------------------------------ */
add_action( 'init', function () {
	register_post_type( 'nfs_lead', array(
		'labels'          => array(
			'name'          => 'Health Check Leads',
			'singular_name' => 'Health Check Lead',
			'menu_name'     => 'Health Check Leads',
			'edit_item'     => 'Lead details',
			'search_items'  => 'Search leads',
			'not_found'     => 'No leads yet.',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_position'   => 3,
		'menu_icon'       => 'dashicons-heart',
		'supports'        => array( 'title', 'editor' ),
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
	) );
} );

add_filter( 'manage_nfs_lead_posts_columns', function () {
	return array(
		'cb'        => '<input type="checkbox" />',
		'title'     => 'Name',
		'nfs_email' => 'Email',
		'nfs_phone' => 'Phone',
		'nfs_score' => 'Score',
		'nfs_focus' => 'Lowest area',
		'nfs_goal'  => 'Top priority',
		'date'      => 'Date',
	);
} );

add_action( 'manage_nfs_lead_posts_custom_column', function ( $col, $id ) {
	switch ( $col ) {
		case 'nfs_email':
			$e = get_post_meta( $id, '_nfs_email', true );
			echo '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>';
			break;
		case 'nfs_phone':
			$p = get_post_meta( $id, '_nfs_phone', true );
			echo $p ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $p ) ) . '">' . esc_html( $p ) . '</a>' : '&mdash;';
			break;
		case 'nfs_score':
			echo esc_html( get_post_meta( $id, '_nfs_score', true ) . '/100' );
			break;
		case 'nfs_focus':
			echo esc_html( get_post_meta( $id, '_nfs_focus', true ) );
			break;
		case 'nfs_goal':
			echo esc_html( get_post_meta( $id, '_nfs_goal', true ) );
			break;
	}
}, 10, 2 );

/* CSV export button on the leads list */
add_action( 'restrict_manage_posts', function ( $type ) {
	if ( 'nfs_lead' !== $type || ! current_user_can( 'edit_posts' ) ) { return; }
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=nfs_export_leads' ), 'nfs_export' );
	echo '<a href="' . esc_url( $url ) . '" class="button" style="margin-left:6px">Export all to CSV</a>';
} );

add_action( 'admin_post_nfs_export_leads', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'nfs_export' ) ) { wp_die( 'Not allowed.' ); }
	$leads = get_posts( array( 'post_type' => 'nfs_lead', 'post_status' => 'any', 'numberposts' => -1 ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=health-check-leads-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array_merge( array( 'Date', 'Name', 'Email', 'Phone', 'Score', 'Result', 'Lowest area', 'Top priority' ), NFS_AREAS ) );
	foreach ( $leads as $l ) {
		$cats = (array) json_decode( (string) get_post_meta( $l->ID, '_nfs_cats', true ), true );
		$row  = array(
			get_the_date( 'Y-m-d H:i', $l ),
			get_post_meta( $l->ID, '_nfs_name', true ),
			get_post_meta( $l->ID, '_nfs_email', true ),
			get_post_meta( $l->ID, '_nfs_phone', true ),
			get_post_meta( $l->ID, '_nfs_score', true ),
			get_post_meta( $l->ID, '_nfs_tier', true ),
			get_post_meta( $l->ID, '_nfs_focus', true ),
			get_post_meta( $l->ID, '_nfs_goal', true ),
		);
		foreach ( NFS_AREAS as $i => $unused ) { $row[] = isset( $cats[ $i ] ) ? (int) $cats[ $i ] : ''; }
		// Neutralise spreadsheet formulas in user-entered fields.
		$row = array_map( function ( $v ) { return preg_match( '/^[=+\-@]/', (string) $v ) ? "'" . $v : $v; }, $row );
		fputcsv( $out, $row );
	}
	fclose( $out );
	exit;
} );

/* ------------------------------------------------------------------ */
/* Submission endpoint                                                  */
/* ------------------------------------------------------------------ */
add_action( 'wp_ajax_nfs_checkup', 'nfs_handle_checkup' );
add_action( 'wp_ajax_nopriv_nfs_checkup', 'nfs_handle_checkup' );

function nfs_fail( $msg, $code = 400 ) {
	wp_send_json_error( array( 'msg' => $msg ), $code );
}

function nfs_handle_checkup() {
	// Spam protection: hidden honeypot field and a minimum completion time.
	if ( ! empty( $_POST['website'] ) || (int) ( $_POST['elapsed'] ?? 0 ) < 3 ) {
		wp_send_json_success( array( 'sent' => true ) );
	}

	// Rate limit: 5 submissions per hour per visitor.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'nfs_rl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) { nfs_fail( 'too many requests, please try again later', 429 ); }
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$phone   = preg_replace( '/[^0-9+()\-. ]/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
	$consent = ! empty( $_POST['consent'] );
	$score   = max( 0, min( 100, (int) ( $_POST['score'] ?? 0 ) ) );
	$tier    = sanitize_text_field( wp_unslash( $_POST['tier'] ?? '' ) );
	$cats    = json_decode( wp_unslash( $_POST['cats'] ?? '[]' ), true );
	$goal    = sanitize_text_field( wp_unslash( $_POST['goal'] ?? '' ) );
	$qa_raw  = json_decode( wp_unslash( $_POST['qa'] ?? '[]' ), true );
	$qa      = array();
	if ( is_array( $qa_raw ) ) {
		foreach ( array_slice( $qa_raw, 0, 12 ) as $pair ) {
			if ( is_array( $pair ) && count( $pair ) === 2 ) {
				$qa[] = array( sanitize_text_field( substr( (string) $pair[0], 0, 300 ) ), sanitize_text_field( substr( (string) $pair[1], 0, 600 ) ) );
			}
		}
	}

	if ( strlen( $name ) < 2 || strlen( $name ) > 100 ) { nfs_fail( 'please enter your name' ); }
	if ( ! is_email( $email ) ) { nfs_fail( 'please enter a valid email' ); }
	if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 10 ) { nfs_fail( 'please enter a phone number' ); }
	if ( ! $consent ) { nfs_fail( 'consent is required' ); }
	if ( ! is_array( $cats ) || count( $cats ) !== 5 ) { nfs_fail( 'invalid results' ); }
	$cats    = array_map( function ( $v ) { return max( 0, min( 20, (int) $v ) ); }, array_values( $cats ) );

	// The PDF report generated in the visitor's browser.
	$raw = (string) wp_unslash( $_POST['pdf'] ?? '' );
	$raw = preg_replace( '#^data:application/pdf;[^,]*,#', '', $raw );
	$pdf = base64_decode( $raw, true );
	if ( ! $pdf || strlen( $pdf ) > 2 * MB_IN_BYTES || strncmp( $pdf, '%PDF', 4 ) !== 0 ) { nfs_fail( 'invalid report file' ); }

	$focus_i = array_search( min( $cats ), $cats, true );
	$focus   = min( $cats ) < 20 ? NFS_AREAS[ $focus_i ] : 'None';

	// Save the lead first so it is never lost, even if email fails.
	$lines = array();
	foreach ( NFS_AREAS as $i => $area ) { $lines[] = $area . ': ' . $cats[ $i ] . '/20'; }
	$answers_txt = '';
	foreach ( $qa as $i => $pair ) { $answers_txt .= ( $i + 1 ) . '. ' . $pair[0] . "\n   " . $pair[1] . "\n"; }
	$summary = "Name: $name\nEmail: $email\nPhone: $phone\nScore: $score/100 ($tier)\nLowest area: $focus\nTop priority: $goal\n\n" . implode( "\n", $lines ) . "\n\nANSWERS\n" . $answers_txt;

	$lead_id = wp_insert_post( array(
		'post_type'    => 'nfs_lead',
		'post_status'  => 'private',
		'post_title'   => $name . ' (' . $score . '/100)',
		'post_content' => $summary,
	) );
	if ( $lead_id && ! is_wp_error( $lead_id ) ) {
		update_post_meta( $lead_id, '_nfs_name', $name );
		update_post_meta( $lead_id, '_nfs_email', $email );
		update_post_meta( $lead_id, '_nfs_phone', $phone );
		update_post_meta( $lead_id, '_nfs_score', $score );
		update_post_meta( $lead_id, '_nfs_tier', $tier );
		update_post_meta( $lead_id, '_nfs_focus', $focus );
		update_post_meta( $lead_id, '_nfs_cats', wp_json_encode( $cats ) );
		update_post_meta( $lead_id, '_nfs_goal', $goal );
		update_post_meta( $lead_id, '_nfs_answers', wp_json_encode( $qa ) );
	}

	// Write the PDF to a temporary file to attach it.
	$dir  = trailingslashit( get_temp_dir() ) . 'nfs-' . wp_generate_password( 12, false );
	wp_mkdir_p( $dir );
	$safe = preg_replace( '/[^A-Za-z0-9]+/', '-', $name );
	$file = $dir . '/Noble-Checkup-' . trim( $safe, '-' ) . '.pdf';
	file_put_contents( $file, $pdf );

	$first   = strtok( $name, ' ' );
	$headers = array(
		'From: ' . NFS_FROM_NAME . ' <' . NFS_FROM_EMAIL . '>',
		'Reply-To: Maunty Wright <' . NFS_NOTIFY_EMAIL . '>',
	);
	$client_body =
		"Hi $first,\n\n" .
		"Thank you for taking the Financial Protection & Retirement Readiness Checkup. Your score is $score out of 100 ($tier).\n\n" .
		"Your personal results report is attached as a PDF. It shows how you scored in each of the five areas, which ones may deserve a closer look, and a copy of your answers.\n\n" .
		"If you'd like to talk through your results, simply reply to this email or call me at (539) 237-7273. There is no cost and no obligation.\n\n" .
		"Maunty Wright\nLife Insurance Broker & Finance Professional\nNoble Finance Solutions\nnoblefinancesolutions.com\n\n" .
		"This checkup is for educational purposes and provides a general snapshot based on your responses. It is not a comprehensive financial, insurance, investment, tax, or legal analysis.";
	$sent_client = wp_mail( $email, 'Your Financial Protection Checkup results', $client_body, $headers, array( $file ) );

	$admin_headers = array(
		'From: ' . NFS_FROM_NAME . ' Website <' . NFS_FROM_EMAIL . '>',
		'Reply-To: ' . $name . ' <' . $email . '>',
	);
	$admin_body = "A new Financial Protection & Retirement Readiness Checkup was completed on your website.\n\n" . $summary .
		"\n\nThey submitted their details to receive results and a follow-up about their Financial Protection Review. Their PDF report is attached.\n" .
		"See all leads: " . admin_url( 'edit.php?post_type=nfs_lead' );
	$sent_admin = wp_mail( NFS_NOTIFY_EMAIL, "New Checkup lead: $name ($score/100)", $admin_body, $admin_headers, array( $file ) );

	@unlink( $file );
	@rmdir( $dir );

	if ( $lead_id && ! is_wp_error( $lead_id ) ) {
		update_post_meta( $lead_id, '_nfs_email_sent', $sent_client ? 'yes' : 'no' );
	}
	if ( ! $sent_client ) {
		nfs_fail( 'email service unavailable', 500 );
	}
	wp_send_json_success( array( 'sent' => true, 'notified' => (bool) $sent_admin ) );
}

<?php
/**
 * Admin class — registers the admin menu, settings, and asset loading.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Ivory_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu'   ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets'  ] );

        // Admin AJAX handlers.
        add_action( 'wp_ajax_ivory_block_dates',          [ $this, 'ajax_block_dates'          ] );
        add_action( 'wp_ajax_ivory_unblock_dates',         [ $this, 'ajax_unblock_dates'         ] );
        add_action( 'wp_ajax_ivory_add_ical_feed',         [ $this, 'ajax_add_ical_feed'         ] );
        add_action( 'wp_ajax_ivory_remove_ical_feed',      [ $this, 'ajax_remove_ical_feed'      ] );
        add_action( 'wp_ajax_ivory_sync_ical_feed',        [ $this, 'ajax_sync_ical_feed'        ] );
        add_action( 'wp_ajax_ivory_manual_booking',        [ $this, 'ajax_manual_booking'        ] );
        add_action( 'wp_ajax_ivory_update_booking_status', [ $this, 'ajax_update_booking_status' ] );

        // Secure file streaming (uses admin_post_ so we can stream binary without JSON).
        add_action( 'admin_post_ivory_view_id', [ $this, 'stream_id_file' ] );

        // Test email delivery.
        add_action( 'wp_ajax_ivory_test_email', [ $this, 'ajax_test_email' ] );
    }

    // ─── Menu ─────────────────────────────────────────────────────────────────

    public function register_menu(): void {
        add_menu_page(
            __( 'Ivory Booking', 'ivory-booking' ),
            __( 'Ivory Booking', 'ivory-booking' ),
            'manage_options',
            'ivory-bookings',
            [ $this, 'page_bookings' ],
            'dashicons-calendar-alt',
            26
        );

        add_submenu_page(
            'ivory-bookings',
            __( 'All Bookings', 'ivory-booking' ),
            __( 'All Bookings', 'ivory-booking' ),
            'manage_options',
            'ivory-bookings',
            [ $this, 'page_bookings' ]
        );

        add_submenu_page(
            'ivory-bookings',
            __( 'Calendar', 'ivory-booking' ),
            __( 'Calendar', 'ivory-booking' ),
            'manage_options',
            'ivory-calendar',
            [ $this, 'page_calendar' ]
        );

        add_submenu_page(
            'ivory-bookings',
            __( 'iCal Sync', 'ivory-booking' ),
            __( 'iCal Sync', 'ivory-booking' ),
            'manage_options',
            'ivory-ical',
            [ $this, 'page_ical' ]
        );

        add_submenu_page(
            'ivory-bookings',
            __( 'Settings', 'ivory-booking' ),
            __( 'Settings', 'ivory-booking' ),
            'manage_options',
            'ivory-booking-settings',
            [ $this, 'page_settings' ]
        );
    }

    // ─── Page Callbacks ───────────────────────────────────────────────────────

    public function page_bookings(): void {
        include IVORY_PLUGIN_DIR . 'admin/views/bookings-list.php';
    }

    public function page_calendar(): void {
        include IVORY_PLUGIN_DIR . 'admin/views/calendar.php';
    }

    public function page_ical(): void {
        include IVORY_PLUGIN_DIR . 'admin/views/ical-sync.php';
    }

    public function page_settings(): void {
        include IVORY_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // ─── Settings Registration ────────────────────────────────────────────────

    public function register_settings(): void {
        $simple_options = [
            'ivory_paystack_public_key',
            'ivory_nightly_rate',
            'ivory_checkin_time',
            'ivory_checkout_time',
            'ivory_admin_email',
        ];

        foreach ( $simple_options as $opt ) {
            register_setting( 'ivory_booking_settings', $opt, [ 'sanitize_callback' => 'sanitize_text_field' ] );
        }

        // Secret key is handled separately (encrypted storage).
        register_setting( 'ivory_booking_settings', 'ivory_paystack_secret_key_raw', [
            'sanitize_callback' => function ( string $val ): string {
                // Don't save the raw key in wp_options — encrypt it.
                if ( ! empty( $val ) && strpos( $val, 'sk_' ) === 0 ) {
                    Ivory_Paystack::save_secret_key( $val );
                }
                return ''; // Always return empty so the raw key is never stored.
            },
        ] );
    }

    // ─── Asset Enqueueing ─────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'ivory' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'ivory-admin',
            IVORY_PLUGIN_URL . 'admin/assets/admin.css',
            [],
            IVORY_VERSION
        );

        wp_enqueue_script(
            'ivory-admin',
            IVORY_PLUGIN_URL . 'admin/assets/admin.js',
            [],
            IVORY_VERSION,
            true
        );

        wp_localize_script( 'ivory-admin', 'IvoryAdmin', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'ivory_admin_nonce' ),
            'restBase'   => esc_url_raw( rest_url( 'ivory/v1/' ) ),
            'restNonce'  => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    // ─── AJAX: Block Dates ────────────────────────────────────────────────────

    public function ajax_block_dates(): void {
        check_ajax_referer( 'ivory_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $start  = sanitize_text_field( $_POST['start']  ?? '' );
        $end    = sanitize_text_field( $_POST['end']    ?? '' );
        $reason = sanitize_text_field( $_POST['reason'] ?? '' );

        if ( ! $start || ! $end ) wp_send_json_error( 'Missing dates.' );

        $ok = Ivory_Database::insert_block( $start, $end, $reason );
        $ok ? wp_send_json_success( 'Dates blocked.' ) : wp_send_json_error( 'DB error.' );
    }

    // ─── AJAX: Unblock Dates ──────────────────────────────────────────────────

    public function ajax_unblock_dates(): void {
        check_ajax_referer( 'ivory_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $id = (int) ( $_POST['block_id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'Missing block ID.' );

        $ok = Ivory_Database::delete_block( $id );
        $ok ? wp_send_json_success( 'Block removed.' ) : wp_send_json_error( 'DB error.' );
    }

    // ─── AJAX: iCal Feed ──────────────────────────────────────────────────────

    public function ajax_add_ical_feed(): void {
        check_ajax_referer( 'ivory_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $url   = esc_url_raw( $_POST['url']   ?? '' );
        $label = sanitize_text_field( $_POST['label'] ?? '' );

        if ( ! $url ) wp_send_json_error( 'Invalid URL.' );

        $feeds   = get_option( 'ivory_ical_import_feeds', [] );
        $feeds[] = [
            'url'         => $url,
            'label'       => $label ?: $url,
            'last_synced' => null,
            'status'      => 'pending',
            'count'       => 0,
        ];
        update_option( 'ivory_ical_import_feeds', $feeds );

        // Trigger immediate sync.
        Ivory_iCal::import( $url );

        wp_send_json_success( 'Feed added and synced.' );
    }

    public function ajax_remove_ical_feed(): void {
        check_ajax_referer( 'ivory_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $url   = esc_url_raw( $_POST['url'] ?? '' );
        $feeds = get_option( 'ivory_ical_import_feeds', [] );
        $feeds = array_filter( $feeds, fn( $f ) => $f['url'] !== $url );
        $feeds = array_values( $feeds );
        update_option( 'ivory_ical_import_feeds', $feeds );

        // Remove the blocks sourced from this feed.
        $source = 'ical_' . md5( $url );
        Ivory_Database::replace_ical_blocks( $source, [] );

        wp_send_json_success( 'Feed removed.' );
    }

    public function ajax_sync_ical_feed(): void {
        check_ajax_referer( 'ivory_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $url    = esc_url_raw( $_POST['url'] ?? '' );
        $result = Ivory_iCal::import( $url );

        $result['success'] ? wp_send_json_success( $result ) : wp_send_json_error( $result['message'] );
    }

    // ─── AJAX: Manual Booking ─────────────────────────────────────────────────

    public function ajax_manual_booking(): void {
        check_ajax_referer( 'ivory_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $data = [
            'name'    => sanitize_text_field( $_POST['name']    ?? '' ),
            'email'   => sanitize_email(      $_POST['email']   ?? '' ),
            'phone'   => sanitize_text_field( $_POST['phone']   ?? '' ),
            'checkin' => sanitize_text_field( $_POST['checkin'] ?? '' ),
            'checkout'=> sanitize_text_field( $_POST['checkout']?? '' ),
            'guests'  => (int) ( $_POST['guests'] ?? 2 ),
        ];

        $result = Ivory_Booking::create_manual_booking( $data );

        $result['success']
            ? wp_send_json_success( [ 'reference' => $result['reference'] ] )
            : wp_send_json_error( 'Could not create booking. Check dates for conflicts.' );
    }

    // ─── AJAX: Update Booking Status ──────────────────────────────────────────

    public function ajax_update_booking_status(): void {
        check_ajax_referer( 'ivory_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $reference  = sanitize_text_field( $_POST['reference'] ?? '' );
        $new_status = sanitize_text_field( $_POST['status']    ?? '' );

        $allowed = [ 'cancelled', 'completed' ];
        if ( ! $reference || ! in_array( $new_status, $allowed, true ) ) {
            wp_send_json_error( 'Invalid request.' );
            return;
        }

        $ok = Ivory_Database::update_booking_status( $reference, $new_status );
        if ( ! $ok ) {
            wp_send_json_error( 'Could not update booking status. It may have already been changed.' );
            return;
        }

        // Fire the appropriate email notification.
        $booking = Ivory_Database::get_booking_by_reference( $reference );
        if ( $booking ) {
            if ( $new_status === 'cancelled' ) {
                Ivory_Email::send_cancellation_notice( $booking );
            } elseif ( $new_status === 'completed' ) {
                Ivory_Email::send_completion_notice( $booking );
            }
        }

        wp_send_json_success( [
            'new_status' => $new_status,
            'label'      => ucfirst( $new_status ),
        ] );
    }

    // ─── AJAX: Test Email ─────────────────────────────────────────────────────

    public function ajax_test_email(): void {
        check_ajax_referer( 'ivory_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
            return;
        }

        $sent = Ivory_Email::send_test_email();

        if ( $sent ) {
            wp_send_json_success( 'Test email sent successfully.' );
        } else {
            // Retrieve the last logged error from the PHP error log if possible.
            wp_send_json_error(
                'wp_mail() failed. Check that an SMTP plugin (e.g. WP Mail SMTP) is installed and configured, ' .
                'then check your server error log for the exact error from Ivory Booking.'
            );
        }
    }

    // ─── Secure ID File Stream ────────────────────────────────────────────────

    /**
     * Streams a government ID file to the browser after verifying admin access.
     * Hooked to admin_post_ivory_view_id.
     */
    public function stream_id_file(): void {
        // Capability check — must be done before any output.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'ivory-booking' ), '', [ 'response' => 403 ] );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ivory_view_id' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ivory-booking' ), '', [ 'response' => 403 ] );
        }

        $reference = sanitize_text_field( $_GET['ref'] ?? '' );
        if ( ! $reference ) {
            wp_die( esc_html__( 'Missing booking reference.', 'ivory-booking' ), '', [ 'response' => 400 ] );
        }

        $booking = Ivory_Database::get_booking_by_reference( $reference );
        if ( ! $booking || empty( $booking['id_file_path'] ) ) {
            wp_die( esc_html__( 'No ID on file for this booking.', 'ivory-booking' ), '', [ 'response' => 404 ] );
        }

        $file_path = $booking['id_file_path'];

        // Only allow files within the uploads directory to prevent path traversal.
        $upload_dir = wp_upload_dir();
        $real_file  = realpath( $file_path );
        $real_base  = realpath( $upload_dir['basedir'] );

        if ( ! $real_file || ! $real_base || strpos( $real_file, $real_base ) !== 0 ) {
            wp_die( esc_html__( 'Invalid file path.', 'ivory-booking' ), '', [ 'response' => 403 ] );
        }

        if ( ! file_exists( $real_file ) || ! is_readable( $real_file ) ) {
            wp_die( esc_html__( 'File not found.', 'ivory-booking' ), '', [ 'response' => 404 ] );
        }

        // Detect MIME type.
        $finfo     = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $real_file );
        finfo_close( $finfo );

        // Allowlist safe MIME types only.
        $allowed_mime = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf' ];
        if ( ! in_array( $mime_type, $allowed_mime, true ) ) {
            wp_die( esc_html__( 'Unsupported file type.', 'ivory-booking' ), '', [ 'response' => 415 ] );
        }

        // Stream the file.
        nocache_headers();
        header( 'Content-Type: ' . $mime_type );
        header( 'Content-Length: ' . filesize( $real_file ) );
        header( 'Content-Disposition: inline; filename="' . basename( $real_file ) . '"' );

        // Turn off output buffering so the binary data flows cleanly.
        if ( ob_get_level() ) {
            ob_end_clean();
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        readfile( $real_file );
        exit;
    }
}

<?php
/**
 * Runs when the plugin is deleted from WP Admin → Plugins → Delete.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit; // Prevent direct access.
}

// Load just what's needed.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';

// Drop all custom tables and options.
Ivory_Database::uninstall();

// Delete auto-created pages.
$page_options = [
    'ivory_booking_page_id',
    'ivory_checkout_page_id',
    'ivory_confirmation_page_id',
];

foreach ( $page_options as $opt ) {
    $page_id = (int) get_option( $opt );
    if ( $page_id ) {
        wp_delete_post( $page_id, true );
    }
}

// Clear any remaining cron events.
$hooks = [ 'ivory_purge_expired_locks', 'ivory_ical_sync' ];
foreach ( $hooks as $hook ) {
    $ts = wp_next_scheduled( $hook );
    if ( $ts ) {
        wp_unschedule_event( $ts, $hook );
    }
}

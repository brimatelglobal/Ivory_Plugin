<?php
/**
 * Plugin Name:       Ivory Booking
 * Plugin URI:        https://ivory.brimatel.com
 * Description:       A bespoke booking system for The Ivory Apartment — a single-unit luxury short-let in Surulere, Lagos. Calendar-first UX, Paystack payments, iCal sync, and a full admin dashboard. No page builder required.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Ivory Apartments
 * Author URI:        https://ivory.brimatel.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ivory-booking
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// ─── Plugin Constants ────────────────────────────────────────────────────────

define( 'IVORY_VERSION',     '1.1.0' );
define( 'IVORY_PLUGIN_FILE', __FILE__ );
define( 'IVORY_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'IVORY_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'IVORY_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// ─── Autoloader ──────────────────────────────────────────────────────────────

spl_autoload_register( function ( string $class_name ): void {
    // Only handle our own classes.
    if ( strpos( $class_name, 'Ivory_' ) !== 0 ) {
        return;
    }

    // Map class name → file path.
    // Ivory_Database  → includes/class-database.php
    // Ivory_Admin     → admin/class-admin.php
    // Ivory_Public    → public/class-public.php
    $suffix = strtolower( str_replace( [ 'Ivory_', '_' ], [ '', '-' ], $class_name ) );

    $locations = [
        IVORY_PLUGIN_DIR . "includes/class-{$suffix}.php",
        IVORY_PLUGIN_DIR . "admin/class-{$suffix}.php",
        IVORY_PLUGIN_DIR . "public/class-{$suffix}.php",
    ];

    foreach ( $locations as $file ) {
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
} );

// ─── Activation / Deactivation / Uninstall ───────────────────────────────────

register_activation_hook( __FILE__, 'ivory_activate' );
register_deactivation_hook( __FILE__, 'ivory_deactivate' );

function ivory_activate(): void {
    Ivory_Database::install();
    Ivory_Cron::schedule_events();
    ivory_create_pages();
    ivory_generate_ical_token();
    set_transient( 'ivory_activated_notice', true, 30 );
    flush_rewrite_rules();
}

function ivory_deactivate(): void {
    Ivory_Cron::clear_events();
    flush_rewrite_rules();
}

/**
 * Auto-create the two guest-facing pages on first activation.
 * Each page gets the corresponding shortcode as its content.
 */
function ivory_create_pages(): void {
    $pages = [
        [
            'title'     => 'Book Your Stay',
            'slug'      => 'ivory-book',
            'shortcode' => '[ivory_booking]',
            'option'    => 'ivory_booking_page_id',
        ],
        [
            'title'     => 'Checkout',
            'slug'      => 'ivory-checkout',
            'shortcode' => '[ivory_checkout]',
            'option'    => 'ivory_checkout_page_id',
        ],
        [
            'title'     => 'Booking Confirmed',
            'slug'      => 'ivory-confirmation',
            'shortcode' => '[ivory_confirmation]',
            'option'    => 'ivory_confirmation_page_id',
        ],
    ];

    foreach ( $pages as $page_def ) {
        $existing_id = (int) get_option( $page_def['option'] );

        // Skip if page already exists and is published.
        if ( $existing_id && get_post_status( $existing_id ) === 'publish' ) {
            continue;
        }

        $page_id = wp_insert_post( [
            'post_title'   => $page_def['title'],
            'post_name'    => $page_def['slug'],
            'post_content' => $page_def['shortcode'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id() ?: 1,
        ] );

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( $page_def['option'], $page_id );
        }
    }
}

/**
 * Generate a random secret token for the iCal export URL, stored once.
 */
function ivory_generate_ical_token(): void {
    if ( ! get_option( 'ivory_ical_token' ) ) {
        update_option( 'ivory_ical_token', wp_generate_password( 32, false ) );
    }
}

// ─── Admin Setup Notice ───────────────────────────────────────────────────────

add_action( 'admin_notices', function (): void {
    if ( ! get_transient( 'ivory_activated_notice' ) ) {
        return;
    }
    delete_transient( 'ivory_activated_notice' );

    $settings_url = admin_url( 'admin.php?page=ivory-booking-settings' );
    printf(
        '<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
        esc_html__( '✅ Ivory Booking activated! Enter your Paystack keys to go live:', 'ivory-booking' ),
        esc_url( $settings_url ),
        esc_html__( 'Open Settings →', 'ivory-booking' )
    );
} );

// ─── iCal Export (template_redirect) ─────────────────────────────────────────

add_action( 'template_redirect', function (): void {
    if ( ! isset( $_GET['ivory_ical'] ) ) {
        return;
    }

    $token         = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
    $stored_token  = get_option( 'ivory_ical_token', '' );

    if ( ! hash_equals( $stored_token, $token ) ) {
        wp_die( 'Unauthorized', 403 );
    }

    Ivory_iCal::export_and_send();
    exit;
} );

// ─── Bootstrap ───────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', function (): void {
    // DB schema upgrade check — dbDelta adds new columns safely on existing installs.
    if ( get_option( 'ivory_db_version' ) !== IVORY_VERSION ) {
        Ivory_Database::install();
        update_option( 'ivory_db_version', IVORY_VERSION );
    }

    // Load text domain.
    load_plugin_textdomain( 'ivory-booking', false, IVORY_PLUGIN_DIR . 'languages' );

    // Boot admin or public class depending on context.
    if ( is_admin() ) {
        new Ivory_Admin();
    }

    // Public class always boots (REST API needs it even on admin requests).
    new Ivory_Public();
    new Ivory_Cron();
} );

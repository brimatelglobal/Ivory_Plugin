<?php
/**
 * WP-Cron event registration and callbacks.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ivory_Cron {

    private const LOCK_EXPIRY_HOOK = 'ivory_purge_expired_locks';
    private const ICAL_SYNC_HOOK   = 'ivory_ical_sync';

    public function __construct() {
        add_action( self::LOCK_EXPIRY_HOOK, [ __CLASS__, 'run_lock_expiry' ] );
        add_action( self::ICAL_SYNC_HOOK,   [ __CLASS__, 'run_ical_sync'   ] );
    }

    // ─── Schedule Events on Activation ────────────────────────────────────────

    public static function schedule_events(): void {
        // Purge expired date locks every 5 minutes.
        if ( ! wp_next_scheduled( self::LOCK_EXPIRY_HOOK ) ) {
            wp_schedule_event( time(), 'ivory_five_minutes', self::LOCK_EXPIRY_HOOK );
        }

        // Sync all iCal feeds every 2 hours.
        if ( ! wp_next_scheduled( self::ICAL_SYNC_HOOK ) ) {
            wp_schedule_event( time(), 'ivory_two_hours', self::ICAL_SYNC_HOOK );
        }
    }

    // ─── Clear Events on Deactivation ─────────────────────────────────────────

    public static function clear_events(): void {
        $timestamp = wp_next_scheduled( self::LOCK_EXPIRY_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::LOCK_EXPIRY_HOOK );
        }

        $timestamp = wp_next_scheduled( self::ICAL_SYNC_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::ICAL_SYNC_HOOK );
        }
    }

    // ─── Register Custom Cron Intervals ───────────────────────────────────────

    public static function register_intervals(): void {
        add_filter( 'cron_schedules', static function ( array $schedules ): array {
            $schedules['ivory_five_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __( 'Every 5 Minutes (Ivory Booking)', 'ivory-booking' ),
            ];
            $schedules['ivory_two_hours'] = [
                'interval' => 2 * HOUR_IN_SECONDS,
                'display'  => __( 'Every 2 Hours (Ivory Booking)', 'ivory-booking' ),
            ];
            return $schedules;
        } );
    }

    // ─── Cron Callbacks ───────────────────────────────────────────────────────

    public static function run_lock_expiry(): void {
        Ivory_Database::purge_expired_pending_bookings();
    }

    public static function run_ical_sync(): void {
        Ivory_iCal::sync_all();
    }
}

// Register intervals early (must be before cron schedules are checked).
Ivory_Cron::register_intervals();

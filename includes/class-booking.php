<?php
/**
 * Core booking logic — create, confirm, cancel, and manage date locks.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ivory_Booking {

    // ─── Date Lock ────────────────────────────────────────────────────────────

    /**
     * Attempt to place a temporary lock on the requested dates.
     * Uses a MySQL transaction + SELECT FOR UPDATE to be fully atomic.
     *
     * @return array{success: bool, token: string, message: string}
     */
    public static function create_lock( string $checkin, string $checkout ): array {
        global $wpdb;

        // Validate dates.
        if ( ! self::valid_dates( $checkin, $checkout ) ) {
            return [ 'success' => false, 'token' => '', 'message' => __( 'Invalid date range.', 'ivory-booking' ) ];
        }

        // Start transaction.
        $wpdb->query( 'START TRANSACTION' );

        // Check availability (reads inside the transaction see the freshest state).
        $available = Ivory_Database::is_range_available( $checkin, $checkout );

        if ( ! $available ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'success' => false, 'token' => '', 'message' => __( 'Sorry, those dates are no longer available.', 'ivory-booking' ) ];
        }

        // Generate a unique session token. All timestamps are WAT (Africa/Lagos, UTC+1).
        $token      = bin2hex( random_bytes( 32 ) );
        $expires_at = wp_date( 'Y-m-d H:i:s', time() + 90 * MINUTE_IN_SECONDS );

        $inserted = $wpdb->insert(
            Ivory_Database::table_locks(),
            [
                'checkin_date'  => $checkin,
                'checkout_date' => $checkout,
                'session_token' => $token,
                'expires_at'    => $expires_at,
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return [ 'success' => false, 'token' => '', 'message' => __( 'Could not lock dates. Please try again.', 'ivory-booking' ) ];
        }

        $wpdb->query( 'COMMIT' );

        return [
            'success'    => true,
            'token'      => $token,
            'expires_at' => $expires_at,
            'message'    => __( 'Dates locked successfully.', 'ivory-booking' ),
        ];
    }

    /**
     * Validate that a lock token exists and hasn't expired.
     */
    public static function validate_lock( string $token, string $checkin, string $checkout ): bool {
        global $wpdb;

        $now = wp_date( 'Y-m-d H:i:s' ); // current WAT — no reliance on MySQL timezone
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM %i
                 WHERE session_token = %s
                   AND checkin_date  = %s
                   AND checkout_date = %s
                   AND expires_at    > %s
                 LIMIT 1",
                Ivory_Database::table_locks(),
                $token,
                $checkin,
                $checkout,
                $now
            )
        );

        return ! is_null( $row );
    }

    /**
     * Consume (delete) a lock after a booking is created.
     */
    private static function consume_lock( string $token ): void {
        global $wpdb;
        $wpdb->delete(
            Ivory_Database::table_locks(),
            [ 'session_token' => $token ],
            [ '%s' ]
        );
    }

    // ─── Booking Creation ─────────────────────────────────────────────────────

    /**
     * Initialize a new pending booking.
     * Acts as a lock until payment is confirmed or it expires.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, reference: string, booking_id: int, message: string}
     */
    public static function init_booking( array $data ): array {
        global $wpdb;

        $checkin  = sanitize_text_field( $data['checkin']  ?? '' );
        $checkout = sanitize_text_field( $data['checkout'] ?? '' );
        $existing_ref = sanitize_text_field( $data['existing_reference'] ?? '' );

        // If reusing an existing pending booking, verify it exists.
        $existing_booking = null;
        $is_update        = false;
        if ( $existing_ref ) {
            $existing_booking = Ivory_Database::get_booking_by_reference( $existing_ref );
            if ( ! $existing_booking || $existing_booking['status'] !== 'pending' ) {
                $existing_ref = ''; // Invalid or already confirmed, treat as new
            } else {
                $is_update = true;
            }
        }

        if ( ! Ivory_Database::is_range_available( $checkin, $checkout, $existing_ref ) ) {
            return [
                'success'    => false,
                'reference'  => '',
                'booking_id' => 0,
                'message'    => __( 'These dates are no longer available. Please select different dates.', 'ivory-booking' ),
            ];
        }

        $guests = max( 1, min( 2, (int) ( $data['guests'] ?? 2 ) ) );
        $nights = self::calculate_nights( $checkin, $checkout );
        $rate   = (float) get_option( 'ivory_nightly_rate', 60000 );
        $total  = $nights * $rate;

        $wpdb->query( 'START TRANSACTION' );

        $update_data = [
            'guest_name'    => sanitize_text_field( $data['name']          ?? '' ),
            'guest_email'   => sanitize_email( $data['email']              ?? '' ),
            'guest_phone'   => sanitize_text_field( $data['phone']         ?? '' ),
            'checkin_date'  => $checkin,
            'checkout_date' => $checkout,
            'nights'        => $nights,
            'guests'        => $guests,
            'total_amount'  => $total,
            'special_req'       => sanitize_textarea_field( $data['special_req'] ?? '' ),
            'address'           => sanitize_text_field( $data['address']      ?? '' ),
            'occupation'        => sanitize_text_field( $data['occupation']       ?? '' ),
            'next_of_kin'       => sanitize_text_field( $data['next_of_kin']      ?? '' ),
            'next_of_kin_phone' => sanitize_text_field( $data['next_of_kin_phone'] ?? '' ),
            'booking_reason'    => sanitize_text_field( $data['booking_reason'] ?? '' ),
        ];

        if ( ! empty( $data['id_file_path'] ) ) {
            $update_data['id_file_path'] = sanitize_text_field( $data['id_file_path'] );
        }

        if ( $existing_ref ) {
            // Update the existing pending booking
            $wpdb->update(
                Ivory_Database::table_bookings(),
                $update_data,
                [ 'reference' => $existing_ref ],
                [ '%s','%s','%s','%s','%s','%d','%d','%f','%s','%s','%s','%s','%s','%s','%s' ],
                [ '%s' ]
            );
            $booking_id = $existing_booking['id'];
            $reference  = $existing_ref;
        } else {
            // Create a new pending booking
            $placeholder = 'IVY-PENDING-' . wp_generate_password( 8, false );
            $insert_data = array_merge( $update_data, [
                'reference' => $placeholder,
                'status'    => 'pending',
                'source'    => 'website',
            ] );

            $inserted = $wpdb->insert(
                Ivory_Database::table_bookings(),
                $insert_data,
                [ '%s','%s','%s','%s','%s','%d','%d','%f','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
            );

            if ( ! $inserted ) {
                $wpdb->query( 'ROLLBACK' );
                return [
                    'success'    => false,
                    'reference'  => '',
                    'booking_id' => 0,
                    'message'    => __( 'Could not save booking. Please try again.', 'ivory-booking' ),
                ];
            }

            $booking_id = $wpdb->insert_id;
            $year       = (int) wp_date( 'Y' );
            $reference  = sprintf( 'IVY-%d-%05d', $year, $booking_id );

            $wpdb->update(
                Ivory_Database::table_bookings(),
                [ 'reference' => $reference ],
                [ 'id'        => $booking_id ],
                [ '%s' ],
                [ '%d' ]
            );
        }

        $wpdb->query( 'COMMIT' );

        return [
            'success'      => true,
            'reference'    => $reference,
            'booking_id'   => $booking_id,
            'has_conflict' => $has_conflict,
            'is_update'    => $is_update,
            'message'      => __( 'Booking created. Awaiting payment.', 'ivory-booking' ),
        ];
    }

    // ─── Confirm Booking (after payment) ──────────────────────────────────────

    /**
     * Mark a booking as confirmed and fire notification emails.
     */
    public static function confirm_booking( string $reference, string $paystack_ref ): bool {
        global $wpdb;

        // Accept 'pending' (normal flow), 'conflict' (expired-lock fallback),
        // and 'cancelled' (in case a bank transfer payment arrives after the 90-minute cron expiration)
        // so emails and payment confirmations are never silently dropped.
        $booking = Ivory_Database::get_booking_by_reference( $reference );
        if ( ! $booking ) {
            return false;
        }

        $allowed_statuses = [ 'pending', 'conflict', 'cancelled' ];
        if ( ! in_array( $booking['status'], $allowed_statuses, true ) ) {
            // Already confirmed or completed — do not double-send.
            return false;
        }

        $updated = $wpdb->update(
            Ivory_Database::table_bookings(),
            [
                'status'      => 'confirmed',
                'payment_ref' => $paystack_ref,
            ],
            [ 'reference' => $reference ],
            [ '%s', '%s' ],
            [ '%s' ]
        );

        if ( $updated === false ) {
            return false;
        }

        // Re-fetch so guest_confirmation email has the updated status + payment_ref.
        $booking = Ivory_Database::get_booking_by_reference( $reference );
        if ( $booking ) {
            Ivory_Email::send_guest_confirmation( $booking );
            Ivory_Email::send_admin_notification( $booking );
        }

        return true;
    }

    // ─── Manual Booking (Admin) ───────────────────────────────────────────────

    /**
     * Insert a booking directly from the admin (no payment needed).
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, reference: string}
     */
    public static function create_manual_booking( array $data ): array {
        global $wpdb;

        $checkin  = sanitize_text_field( $data['checkin']  ?? '' );
        $checkout = sanitize_text_field( $data['checkout'] ?? '' );

        if ( ! self::valid_dates( $checkin, $checkout ) ) {
            return [ 'success' => false, 'reference' => '' ];
        }

        if ( ! Ivory_Database::is_range_available( $checkin, $checkout ) ) {
            return [ 'success' => false, 'reference' => '' ];
        }

        $nights    = self::calculate_nights( $checkin, $checkout );
        $rate      = (float) get_option( 'ivory_nightly_rate', 60000 );
        $total     = $nights * $rate;
        $reference = self::generate_reference();

        $wpdb->insert(
            Ivory_Database::table_bookings(),
            [
                'reference'     => $reference,
                'guest_name'    => sanitize_text_field( $data['name']   ?? 'Manual Entry' ),
                'guest_email'   => sanitize_email( $data['email']       ?? '' ),
                'guest_phone'   => sanitize_text_field( $data['phone']  ?? '' ),
                'checkin_date'  => $checkin,
                'checkout_date' => $checkout,
                'nights'        => $nights,
                'guests'        => max( 1, min( 2, (int) ( $data['guests'] ?? 2 ) ) ),
                'total_amount'  => $total,
                'status'        => 'confirmed',
                'source'        => 'manual',
            ],
            [ '%s','%s','%s','%s','%s','%s','%d','%d','%f','%s','%s' ]
        );

        return [ 'success' => (bool) $wpdb->insert_id, 'reference' => $reference ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function valid_dates( string $checkin, string $checkout ): bool {
        if ( empty( $checkin ) || empty( $checkout ) ) {
            return false;
        }
        $ci = \DateTimeImmutable::createFromFormat( 'Y-m-d', $checkin );
        $co = \DateTimeImmutable::createFromFormat( 'Y-m-d', $checkout );

        if ( ! $ci || ! $co ) {
            return false;
        }

        $today = new \DateTimeImmutable( 'today' );

        return $ci >= $today && $co > $ci;
    }

    public static function calculate_nights( string $checkin, string $checkout ): int {
        $ci = new \DateTimeImmutable( $checkin );
        $co = new \DateTimeImmutable( $checkout );
        return max( 1, (int) $ci->diff( $co )->days );
    }

    /**
     * Generate a unique booking reference like IVY-2026-00042.
     */
    private static function generate_reference(): string {
        global $wpdb;

        $year  = (int) wp_date( 'Y' ); // WAT year
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE YEAR(created_at) = %d",
                Ivory_Database::table_bookings(),
                $year
            )
        );

        return sprintf( 'IVY-%d-%05d', $year, $count + 1 );
    }
}

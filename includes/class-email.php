<?php
/**
 * Email notifications — guest confirmation and admin alert.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ivory_Email {

    // ─── Guest Confirmation ───────────────────────────────────────────────────

    /**
     * Send a booking confirmation email to the guest.
     *
     * @param array<string, mixed> $booking
     */
    public static function send_guest_confirmation( array $booking ): void {
        $to      = $booking['guest_email'];
        $subject = sprintf(
            __( 'Your booking is confirmed — %s', 'ivory-booking' ),
            $booking['reference']
        );
        $body    = self::guest_confirmation_html( $booking );

        self::send( $to, $subject, $body );
    }

    // ─── Admin Notification ───────────────────────────────────────────────────

    /**
     * Notify the host/admin when a new booking is confirmed.
     *
     * @param array<string, mixed> $booking
     */
    public static function send_admin_notification( array $booking ): void {
        $subject = sprintf(
            __( 'New booking received — %s', 'ivory-booking' ),
            $booking['reference']
        );
        self::send_to_admins( $subject, self::admin_notification_html( $booking ) );
    }

    // ─── Cancellation Notice ──────────────────────────────────────────────────

    /**
     * Send a booking cancellation email to the guest.
     *
     * @param array<string, mixed> $booking
     */
    public static function send_cancellation_notice( array $booking ): void {
        $to      = $booking['guest_email'];
        $subject = sprintf(
            __( 'Booking Cancelled — %s', 'ivory-booking' ),
            $booking['reference']
        );
        $body    = self::cancellation_html( $booking );

        self::send( $to, $subject, $body );
    }

    // ─── Completion Notice ────────────────────────────────────────────────────

    /**
     * Send a "thank you for staying" email to the guest.
     *
     * @param array<string, mixed> $booking
     */
    public static function send_completion_notice( array $booking ): void {
        $to      = $booking['guest_email'];
        $subject = sprintf(
            __( 'Thank you for staying — %s', 'ivory-booking' ),
            $booking['reference']
        );
        $body    = self::completion_html( $booking );

        self::send( $to, $subject, $body );
    }

    // ─── Conflict Alert (Admin Only) ──────────────────────────────────────────

    /**
     * Urgent admin alert: payment captured but dates have a conflict.
     * Fired when a guest pays after their lock expired AND another booking
     * already covers those dates. Admin must resolve manually.
     *
     * @param array<string, mixed> $booking
     */
    public static function send_conflict_alert( array $booking ): void {
        $subject = sprintf(
            '🚨 URGENT: Payment conflict on booking %s — manual action required',
            $booking['reference']
        );
        self::send_to_admins( $subject, self::conflict_alert_html( $booking ) );
    }

    // ─── Pending Booking Alert (Admin) ────────────────────────────────────────

    /**
     * Fires the moment a guest locks dates and opens the Paystack payment screen.
     * Lets admin follow up if the guest abandons before completing payment.
     *
     * @param array<string, mixed> $data
     */
    public static function send_pending_booking_alert( array $data ): void {
        $subject = sprintf(
            '⏳ Booking attempt started — %s to %s',
            $data['checkin_date']  ?? '?',
            $data['checkout_date'] ?? '?'
        );
        self::send_to_admins( $subject, self::pending_booking_alert_html( $data ) );
    }


    // ─── HTML Templates ───────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $booking
     */
    private static function guest_confirmation_html( array $booking ): string {
        $site_name    = get_bloginfo( 'name' );
        $checkin_time = get_option( 'ivory_checkin_time', '2:00 PM' );
        $checkout_time = get_option( 'ivory_checkout_time', '12:00 PM' );
        $rate         = number_format( (float) get_option( 'ivory_nightly_rate', 60000 ), 2 );
        $total        = number_format( (float) $booking['total_amount'], 2 );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <style>
            body { margin:0; padding:0; font-family: 'Segoe UI', Arial, sans-serif; background:#f5f4f0; color:#0a250e; }
            .wrap { max-width:580px; margin:40px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
            .header { background:#0F3714; padding:36px 40px; text-align:center; }
            .header h1 { margin:0; color:#D3AD63; font-size:22px; letter-spacing:0.04em; }
            .header p { margin:6px 0 0; color:rgba(255,255,255,0.75); font-size:13px; }
            .body { padding:36px 40px; }
            .ref-badge { display:inline-block; background:#D3AD63; color:#0F3714; font-weight:700; font-size:18px; letter-spacing:0.08em; padding:10px 24px; border-radius:6px; margin-bottom:24px; }
            .detail-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #ece8df; font-size:14px; }
            .detail-row:last-child { border-bottom:none; }
            .detail-label { color:#856637; font-weight:600; }
            .rules { background:#f5f4f0; border-radius:8px; padding:16px 20px; margin-top:24px; font-size:13px; }
            .rules h3 { margin:0 0 8px; font-size:14px; color:#0F3714; }
            .rules ul { margin:0; padding-left:18px; }
            .rules ul li { margin-bottom:4px; color:#555; }
            .footer { background:#0F3714; padding:20px 40px; text-align:center; font-size:12px; color:rgba(255,255,255,0.5); }
            .footer a { color:#D3AD63; text-decoration:none; }
        </style>
        </head>
        <body>
        <div class="wrap">
            <div class="header">
                <h1>THE IVORY APARTMENT</h1>
                <p>Booking Confirmation</p>
            </div>
            <div class="body">
                <p>Hi <strong><?php echo esc_html( $booking['guest_name'] ); ?></strong>, your stay is confirmed. We look forward to welcoming you.</p>
                <div><span class="ref-badge"><?php echo esc_html( $booking['reference'] ); ?></span></div>

                <div class="detail-row">
                    <span class="detail-label">Check-in</span>
                    <span><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $booking['checkin_date'] ) ) ); ?> &mdash; after <?php echo esc_html( $checkin_time ); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-out</span>
                    <span><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $booking['checkout_date'] ) ) ); ?> &mdash; by <?php echo esc_html( $checkout_time ); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration</span>
                    <span><?php echo (int) $booking['nights']; ?> night<?php echo (int) $booking['nights'] !== 1 ? 's' : ''; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Guests</span>
                    <span><?php echo (int) $booking['guests']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Rate</span>
                    <span>&#8358;<?php echo esc_html( $rate ); ?> / night</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><strong>Total Paid</strong></span>
                    <span><strong>&#8358;<?php echo esc_html( $total ); ?></strong></span>
                </div>


                <div class="rules">
                    <h3>🏡 Ivory Apartment Rules</h3>
                    <ul>
                        <li>No smoking</li>
                        <li>No parties</li>
                        <li>No overcrowding</li>
                        <li>Kindly notify us at least 6 hours before your checkout time if you would like an extension or to check out.</li>
                        <li>A valid ID (Voter&#39;s Card, NIN slip/card, or International Passport) must be presented before entry.</li>
                    </ul>
                </div>

                <p style="margin-top:24px; font-size:14px; color:#555;">
                    The apartment is located at <strong>40, Karimu Street, Ojuelegba, Surulere, Lagos</strong>.
                    If you have any questions before your arrival, reply to this email.
                </p>
            </div>
            <div class="footer">
                &copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $site_name ); ?> &mdash;
                <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_url( home_url() ); ?></a>
            </div>
        </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * @param array<string, mixed> $booking
     */
    private static function admin_notification_html( array $booking ): string {
        $detail_url = admin_url( 'admin.php?page=ivory-bookings&view=detail&ref=' . $booking['reference'] );
        $total      = number_format( (float) $booking['total_amount'], 2 );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #222; }
            .wrap { max-width: 500px; margin: 30px auto; }
            h2 { color: #0F3714; }
            table { width: 100%; border-collapse: collapse; margin-top: 16px; }
            td { padding: 10px 12px; border-bottom: 1px solid #e0e0e0; font-size: 14px; }
            td:first-child { font-weight: 600; color: #856637; width: 140px; }
            .btn { display:inline-block; margin-top:20px; padding:12px 24px; background:#0F3714; color:#fff; text-decoration:none; border-radius:6px; font-size:14px; }
        </style>
        </head>
        <body>
        <div class="wrap">
            <h2>New Booking: <?php echo esc_html( $booking['reference'] ); ?></h2>
            <p>A new booking has been confirmed on The Ivory Apartment.</p>
            <table>
                <tr><td>Guest</td><td><?php echo esc_html( $booking['guest_name'] ); ?></td></tr>
                <tr><td>Email</td><td><?php echo esc_html( $booking['guest_email'] ); ?></td></tr>
                <tr><td>Phone</td><td><?php echo esc_html( $booking['guest_phone'] ); ?></td></tr>
                <tr><td>Check-in</td><td><?php echo esc_html( $booking['checkin_date'] ); ?></td></tr>
                <tr><td>Check-out</td><td><?php echo esc_html( $booking['checkout_date'] ); ?></td></tr>
                <tr><td>Nights</td><td><?php echo (int) $booking['nights']; ?></td></tr>
                <tr><td>Guests</td><td><?php echo (int) $booking['guests']; ?></td></tr>
                <tr><td>Total</td><td>&#8358;<?php echo esc_html( $total ); ?></td></tr>
                <tr><td>Paystack Ref</td><td><?php echo esc_html( $booking['payment_ref'] ?? '—' ); ?></td></tr>
            </table>
            <a href="<?php echo esc_url( $detail_url ); ?>" class="btn">View Booking in Dashboard →</a>
        </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * @param array<string, mixed> $booking
     */
    private static function cancellation_html( array $booking ): string {
        $site_name = get_bloginfo( 'name' );
        $total     = number_format( (float) $booking['total_amount'], 2 );
        $booking_url = get_permalink( get_option( 'ivory_booking_page_id' ) );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <style>
            body { margin:0; padding:0; font-family: 'Segoe UI', Arial, sans-serif; background:#f5f4f0; color:#0a250e; }
            .wrap { max-width:580px; margin:40px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
            .header { background:#0F3714; padding:36px 40px; text-align:center; }
            .header h1 { margin:0; color:#D3AD63; font-size:22px; letter-spacing:0.04em; }
            .header p { margin:6px 0 0; color:rgba(255,255,255,0.75); font-size:13px; }
            .body { padding:36px 40px; }
            .ref-badge { display:inline-block; background:#e8e0d5; color:#0F3714; font-weight:700; font-size:18px; letter-spacing:0.08em; padding:10px 24px; border-radius:6px; margin-bottom:24px; text-decoration: line-through; opacity: 0.7; }
            .notice-box { background:#fff8f0; border:1px solid #f0d9bc; border-radius:8px; padding:16px 20px; margin-bottom:24px; font-size:14px; color:#7a4a1a; }
            .detail-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #ece8df; font-size:14px; }
            .detail-row:last-child { border-bottom:none; }
            .detail-label { color:#856637; font-weight:600; }
            .btn { display:inline-block; padding:12px 28px; background:#0F3714; color:#fff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:600; margin-top:20px; }
            .footer { background:#0F3714; padding:20px 40px; text-align:center; font-size:12px; color:rgba(255,255,255,0.5); }
            .footer a { color:#D3AD63; text-decoration:none; }
        </style>
        </head>
        <body>
        <div class="wrap">
            <div class="header">
                <h1>THE IVORY APARTMENT</h1>
                <p>Booking Cancellation</p>
            </div>
            <div class="body">
                <p>Hi <strong><?php echo esc_html( $booking['guest_name'] ); ?></strong>,</p>
                <p style="margin-top:8px;">We regret to inform you that the following booking has been cancelled by the host. We sincerely apologise for any inconvenience caused.</p>

                <div><span class="ref-badge"><?php echo esc_html( $booking['reference'] ); ?></span></div>

                <div class="notice-box">
                    ⚠️ <?php esc_html_e( 'This booking has been cancelled. Please refer to our cancellation policy for refund eligibility information.', 'ivory-booking' ); ?>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Check-in</span>
                    <span><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $booking['checkin_date'] ) ) ); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-out</span>
                    <span><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $booking['checkout_date'] ) ) ); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration</span>
                    <span><?php echo (int) $booking['nights']; ?> night<?php echo (int) $booking['nights'] !== 1 ? 's' : ''; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount</span>
                    <span>&#8358;<?php echo esc_html( $total ); ?></span>
                </div>

                <p style="margin-top:24px; font-size:14px; color:#555;">
                    If you have any questions regarding this cancellation or wish to make a new booking, please don't hesitate to contact us or visit our website.
                </p>

                <?php if ( $booking_url ) : ?>
                <a href="<?php echo esc_url( $booking_url ); ?>" class="btn">Book Again →</a>
                <?php endif; ?>
            </div>
            <div class="footer">
                &copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $site_name ); ?> &mdash;
                <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_url( home_url() ); ?></a>
            </div>
        </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * @param array<string, mixed> $booking
     */
    private static function completion_html( array $booking ): string {
        $site_name    = get_bloginfo( 'name' );
        $booking_url  = get_permalink( get_option( 'ivory_booking_page_id' ) );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <style>
            body { margin:0; padding:0; font-family: 'Segoe UI', Arial, sans-serif; background:#f5f4f0; color:#0a250e; }
            .wrap { max-width:580px; margin:40px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
            .header { background:#0F3714; padding:36px 40px; text-align:center; }
            .header h1 { margin:0; color:#D3AD63; font-size:22px; letter-spacing:0.04em; }
            .header p { margin:6px 0 0; color:rgba(255,255,255,0.75); font-size:13px; }
            .body { padding:36px 40px; }
            .ref-badge { display:inline-block; background:#D3AD63; color:#0F3714; font-weight:700; font-size:18px; letter-spacing:0.08em; padding:10px 24px; border-radius:6px; margin-bottom:24px; }
            .thank-box { background:#f0f9f2; border:1px solid #c3e6cb; border-radius:8px; padding:16px 20px; margin-bottom:24px; font-size:14px; color:#1e5c30; text-align:center; }
            .thank-box .emoji { font-size:28px; display:block; margin-bottom:8px; }
            .detail-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #ece8df; font-size:14px; }
            .detail-row:last-child { border-bottom:none; }
            .detail-label { color:#856637; font-weight:600; }
            .btn { display:inline-block; padding:12px 28px; background:#0F3714; color:#fff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:600; margin-top:20px; }
            .footer { background:#0F3714; padding:20px 40px; text-align:center; font-size:12px; color:rgba(255,255,255,0.5); }
            .footer a { color:#D3AD63; text-decoration:none; }
        </style>
        </head>
        <body>
        <div class="wrap">
            <div class="header">
                <h1>THE IVORY APARTMENT</h1>
                <p>Thank You for Staying</p>
            </div>
            <div class="body">
                <p>Hi <strong><?php echo esc_html( $booking['guest_name'] ); ?></strong>,</p>

                <div class="thank-box">
                    <span class="emoji">🌟</span>
                    <?php esc_html_e( 'Thank you for choosing The Ivory Apartment. It was a pleasure hosting you — we hope to welcome you back soon!', 'ivory-booking' ); ?>
                </div>

                <div><span class="ref-badge"><?php echo esc_html( $booking['reference'] ); ?></span></div>

                <div class="detail-row">
                    <span class="detail-label">Check-in</span>
                    <span><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $booking['checkin_date'] ) ) ); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Check-out</span>
                    <span><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $booking['checkout_date'] ) ) ); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration</span>
                    <span><?php echo (int) $booking['nights']; ?> night<?php echo (int) $booking['nights'] !== 1 ? 's' : ''; ?></span>
                </div>

                <p style="margin-top:24px; font-size:14px; color:#555;">
                    If you enjoyed your stay, we would love to hear from you — a review or a kind word goes a long way. And whenever you plan your next visit to Lagos, remember we would be delighted to host you again.
                </p>

                <?php if ( $booking_url ) : ?>
                <a href="<?php echo esc_url( $booking_url ); ?>" class="btn">Book Your Next Stay →</a>
                <?php endif; ?>
            </div>
            <div class="footer">
                &copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $site_name ); ?> &mdash;
                <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_url( home_url() ); ?></a>
            </div>
        </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * @param array<string, mixed> $booking
     */
    private static function conflict_alert_html( array $booking ): string {
        $admin_url = admin_url( 'admin.php?page=ivory-bookings' );
        $total     = number_format( (float) ( $booking['total_amount'] ?? 0 ), 2 );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #222; margin: 0; padding: 0; background: #fff5f5; }
            .wrap { max-width: 560px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; border: 2px solid #e53e3e; }
            .header { background: #e53e3e; padding: 28px 32px; text-align: center; }
            .header h1 { margin: 0; color: #fff; font-size: 20px; letter-spacing: 0.03em; }
            .header p { margin: 6px 0 0; color: rgba(255,255,255,0.85); font-size: 13px; }
            .body { padding: 28px 32px; }
            .alert-box { background: #fff5f5; border: 1px solid #feb2b2; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; font-size: 14px; color: #c53030; font-weight: 600; }
            table { width: 100%; border-collapse: collapse; margin-top: 16px; }
            td { padding: 10px 12px; border-bottom: 1px solid #e0e0e0; font-size: 14px; }
            td:first-child { font-weight: 600; color: #856637; width: 150px; }
            .btn { display: inline-block; margin-top: 24px; padding: 13px 28px; background: #e53e3e; color: #fff; text-decoration: none; border-radius: 7px; font-size: 14px; font-weight: 700; }
            .footer { background: #1a202c; padding: 16px 32px; text-align: center; font-size: 12px; color: rgba(255,255,255,0.5); }
        </style>
        </head>
        <body>
        <div class="wrap">
            <div class="header">
                <h1>🚨 PAYMENT CONFLICT DETECTED</h1>
                <p>The Ivory Apartment — Booking System</p>
            </div>
            <div class="body">
                <div class="alert-box">
                    A guest's payment lock had expired, but their payment went through successfully.
                    The requested dates may overlap with an existing booking.
                    <strong>Please review and resolve immediately.</strong>
                </div>

                <table>
                    <tr><td>Reference</td><td><?php echo esc_html( $booking['reference'] ); ?></td></tr>
                    <tr><td>Guest</td><td><?php echo esc_html( $booking['guest_name'] ?? '—' ); ?></td></tr>
                    <tr><td>Email</td><td><?php echo esc_html( $booking['guest_email'] ?? '—' ); ?></td></tr>
                    <tr><td>Phone</td><td><?php echo esc_html( $booking['guest_phone'] ?? '—' ); ?></td></tr>
                    <tr><td>Check-in</td><td><?php echo esc_html( $booking['checkin_date'] ); ?></td></tr>
                    <tr><td>Check-out</td><td><?php echo esc_html( $booking['checkout_date'] ); ?></td></tr>
                    <tr><td>Amount Paid</td><td>&#8358;<?php echo esc_html( $total ); ?></td></tr>
                    <tr><td>Paystack Ref</td><td><?php echo esc_html( $booking['paystack_ref'] ?? '—' ); ?></td></tr>
                    <tr><td>Status</td><td><strong style="color:#e53e3e;">CONFLICT — needs manual review</strong></td></tr>
                </table>

                <a href="<?php echo esc_url( $admin_url ); ?>" class="btn">Open Admin Dashboard →</a>

                <p style="margin-top:20px; font-size:13px; color:#777;">
                    The booking has been saved in the system with status <strong>conflict</strong>.
                    Contact the guest to resolve — either confirm their booking (if dates can be freed)
                    or arrange a full refund via Paystack.
                </p>
            </div>
            <div class="footer">Ivory Booking System — automated alert</div>
        </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    // ─── Send Helpers ─────────────────────────────────────────────────────────

    private static function send( string $to, string $subject, string $html_body ): bool {
        $from    = get_option( 'ivory_admin_email', get_option( 'admin_email' ) );
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: The Ivory Apartment <' . $from . '>',
        ];

        // Capture any mailer error for logging.
        $last_error = null;
        $listener   = static function ( WP_Error $error ) use ( &$last_error ): void {
            $last_error = $error;
        };
        add_action( 'wp_mail_failed', $listener );

        $sent = wp_mail( $to, $subject, $html_body, $headers );

        remove_action( 'wp_mail_failed', $listener );

        if ( ! $sent ) {
            $msg = $last_error ? $last_error->get_error_message() : 'wp_mail() returned false — no SMTP error captured.';
            error_log( "[Ivory Booking] Email FAILED | To: {$to} | Subject: {$subject} | Error: {$msg}" );
        }

        return $sent;
    }

    /**
     * Send an email to all admin inboxes:
     *   • Primary: ivory_admin_email option (or WP admin email fallback)
     *   • CC:      covaspaces@gmail.com, az.brimatel@gmail.com
     */
    private static function send_to_admins( string $subject, string $html_body ): bool {
        $primary = get_option( 'ivory_admin_email', get_option( 'admin_email' ) );
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: The Ivory Apartment <' . $primary . '>',
            'Cc: covaspaces@gmail.com, az.brimatel@gmail.com',
        ];

        $last_error = null;
        $listener   = static function ( WP_Error $error ) use ( &$last_error ): void {
            $last_error = $error;
        };
        add_action( 'wp_mail_failed', $listener );

        $sent = wp_mail( $primary, $subject, $html_body, $headers );

        remove_action( 'wp_mail_failed', $listener );

        if ( ! $sent ) {
            $msg = $last_error ? $last_error->get_error_message() : 'wp_mail() returned false — no SMTP error captured.';
            error_log( "[Ivory Booking] Admin email FAILED | To: {$primary} | Subject: {$subject} | Error: {$msg}" );
        }

        return $sent;
    }

    /**
     * Send a test email to the admin address. Used by the Settings page test button.
     */
    public static function send_test_email(): bool {
        $to      = get_option( 'ivory_admin_email', get_option( 'admin_email' ) );
        $subject = '✅ Ivory Booking — Test Email';
        $body    = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;padding:30px;">'
            . '<h2 style="color:#0F3714;">Test Email — Ivory Booking</h2>'
            . '<p>If you can read this, WordPress email is working correctly.</p>'
            . '<p style="color:#888;font-size:13px;">Sent: ' . current_time( 'mysql' ) . '</p>'
            . '</body></html>';

        return self::send( $to, $subject, $body );
    }

    // ─── Pending Booking Alert HTML ───────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    private static function pending_booking_alert_html( array $data ): string {
        $admin_url    = admin_url( 'admin.php?page=ivory-bookings' );
        $checkin_fmt  = ! empty( $data['checkin_date'] )
            ? date_i18n( 'l, F j, Y', strtotime( $data['checkin_date'] ) )
            : '—';
        $checkout_fmt = ! empty( $data['checkout_date'] )
            ? date_i18n( 'l, F j, Y', strtotime( $data['checkout_date'] ) )
            : '—';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #222; margin: 0; padding: 0; background: #fffbf0; }
            .wrap { max-width: 520px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; border: 2px solid #D3AD63; }
            .header { background: #0F3714; padding: 28px 32px; text-align: center; }
            .header h1 { margin: 0; color: #D3AD63; font-size: 19px; letter-spacing: 0.04em; }
            .header p  { margin: 6px 0 0; color: rgba(255,255,255,0.7); font-size: 13px; }
            .body { padding: 28px 32px; }
            .notice { background: #fffbf0; border: 1px solid #f0d070; border-radius: 8px; padding: 13px 17px; margin-bottom: 20px; font-size: 14px; color: #7a5a00; }
            table { width: 100%; border-collapse: collapse; margin-top: 4px; }
            td { padding: 10px 12px; border-bottom: 1px solid #ece8df; font-size: 14px; }
            td:first-child { font-weight: 600; color: #856637; width: 140px; }
            .btn { display: inline-block; margin-top: 22px; padding: 12px 26px; background: #0F3714; color: #fff; text-decoration: none; border-radius: 7px; font-size: 14px; font-weight: 600; }
            .footer { background: #0F3714; padding: 16px 32px; text-align: center; font-size: 12px; color: rgba(255,255,255,0.5); }
        </style>
        </head>
        <body>
        <div class="wrap">
            <div class="header">
                <h1>THE IVORY APARTMENT</h1>
                <p>⏳ Booking Attempt — Awaiting Payment</p>
            </div>
            <div class="body">
                <div class="notice">
                    A guest has completed the booking form and opened the payment screen.
                    If no confirmed booking arrives shortly, consider following up directly.
                </div>

                <table>
                    <?php if ( ! empty( $data['guest_name'] ) ) : ?>
                    <tr><td>Name</td><td><?php echo esc_html( $data['guest_name'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( ! empty( $data['guest_email'] ) ) : ?>
                    <tr><td>Email</td><td><?php echo esc_html( $data['guest_email'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( ! empty( $data['guest_phone'] ) ) : ?>
                    <tr><td>Phone</td><td><?php echo esc_html( $data['guest_phone'] ); ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Check-in</td><td><?php echo esc_html( $checkin_fmt ); ?></td></tr>
                    <tr><td>Check-out</td><td><?php echo esc_html( $checkout_fmt ); ?></td></tr>
                    <tr><td>Status</td><td><strong style="color:#b7791f;">Pending — payment not yet received</strong></td></tr>
                </table>

                <a href="<?php echo esc_url( $admin_url ); ?>" class="btn">Open Admin Dashboard →</a>

                <p style="margin-top: 18px; font-size: 13px; color: #888;">
                    This is an automated heads-up. If payment is completed, a separate
                    <strong>booking confirmed</strong> email will follow immediately.
                    No action needed unless payment does not arrive within 90 minutes.
                </p>
            </div>
            <div class="footer">Ivory Booking System — automated alert</div>
        </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

<?php
/**
 * Template: [ivory_confirmation] shortcode — Booking confirmed page.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="ivory-wrap">
  <div class="ivory-confirmation-wrap">

    <div class="ivory-confirmation-card">

      <!-- Success icon -->
      <div class="iv-confirm-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5"/>
        </svg>
      </div>

      <h1><?php esc_html_e( 'Booking Confirmed!', 'ivory-booking' ); ?></h1>
      <p style="color:#666;font-size:15px;margin-top:6px;">
        <?php esc_html_e( 'Your payment was successful. A confirmation email has been sent to you.', 'ivory-booking' ); ?>
      </p>

      <!-- Reference badge (also updated by JS) -->
      <div class="iv-ref-badge" id="iv-conf-ref-badge" aria-label="Booking reference">
        <?php esc_html_e( 'Loading…', 'ivory-booking' ); ?>
      </div>

      <!-- Detail rows (populated by JS) -->
      <div class="iv-confirm-details" aria-live="polite">

        <div class="iv-confirm-row">
          <span class="label"><?php esc_html_e( 'Reference', 'ivory-booking' ); ?></span>
          <span class="value" id="iv-conf-ref">—</span>
        </div>
        <div class="iv-confirm-row">
          <span class="label"><?php esc_html_e( 'Guest', 'ivory-booking' ); ?></span>
          <span class="value" id="iv-conf-name">—</span>
        </div>
        <div class="iv-confirm-row">
          <span class="label"><?php esc_html_e( 'Check-in', 'ivory-booking' ); ?></span>
          <span class="value" id="iv-conf-checkin">—</span>
        </div>
        <div class="iv-confirm-row">
          <span class="label"><?php esc_html_e( 'Check-out', 'ivory-booking' ); ?></span>
          <span class="value" id="iv-conf-checkout">—</span>
        </div>
        <div class="iv-confirm-row">
          <span class="label"><?php esc_html_e( 'Duration', 'ivory-booking' ); ?></span>
          <span class="value" id="iv-conf-nights">—</span>
        </div>
        <div class="iv-confirm-row">
          <span class="label"><?php esc_html_e( 'Guests', 'ivory-booking' ); ?></span>
          <span class="value" id="iv-conf-guests">—</span>
        </div>
        <div class="iv-confirm-row" style="font-weight:700;">
          <span class="label"><?php esc_html_e( 'Total Paid', 'ivory-booking' ); ?></span>
          <span class="value" id="iv-conf-total">—</span>
        </div>

      </div><!-- .iv-confirm-details -->

      <!-- Address -->
      <p style="font-size:13px;color:#888;margin-bottom:20px;">
        📍 <?php esc_html_e( '40, Karimu Street, Ojuelegba, Surulere, Lagos', 'ivory-booking' ); ?>
      </p>

      <!-- Check-in instructions -->
      <div style="background:#f5f4f0;border-radius:10px;padding:16px 20px;text-align:left;margin-bottom:24px;font-size:13px;line-height:1.8;color:#444;">
        <strong style="color:#0F3714;display:block;margin-bottom:6px;">
          <?php esc_html_e( 'Before You Arrive', 'ivory-booking' ); ?>
        </strong>
        <?php
        $checkin_time = esc_html( get_option( 'ivory_checkin_time', '2:00 PM' ) );
        printf(
            /* translators: %s = check-in time */
            esc_html__( 'Check-in is from %s.', 'ivory-booking' ),
            $checkin_time
        );
        ?>
      </div>

      <!-- House Rules reminder -->
      <div class="iv-house-rules iv-house-rules--light">
        <p class="iv-house-rules__title">
          🏡 <?php esc_html_e( 'Ivory Apartment Rules', 'ivory-booking' ); ?>
        </p>
        <ol class="iv-house-rules__list">
          <li><?php esc_html_e( 'No smoking', 'ivory-booking' ); ?></li>
          <li><?php esc_html_e( 'No parties', 'ivory-booking' ); ?></li>
          <li><?php esc_html_e( 'No overcrowding', 'ivory-booking' ); ?></li>
          <li><?php esc_html_e( 'Kindly notify us at least 6 hours before your checkout time if you would like an extension or to check out.', 'ivory-booking' ); ?></li>
          <li><?php esc_html_e( 'A valid ID (Voter\'s Card, NIN slip/card, or International Passport) must be presented before entry.', 'ivory-booking' ); ?></li>
        </ol>
      </div>

      <!-- Actions -->
      <button class="iv-print-btn" id="iv-print-btn" onclick="window.print()">
        🖨️ <?php esc_html_e( 'Print / Save Receipt', 'ivory-booking' ); ?>
      </button>

      <p style="margin-top:14px;">
        <a href="<?php echo esc_url( home_url() ); ?>" style="color:#856637;font-size:13px;">
          ← <?php esc_html_e( 'Back to Home', 'ivory-booking' ); ?>
        </a>
      </p>

    </div><!-- .ivory-confirmation-card -->
  </div>
</div>

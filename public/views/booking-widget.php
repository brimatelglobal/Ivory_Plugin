<?php
/**
 * Template: [ivory_booking] shortcode — Calendar booking widget.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="ivory-wrap">
  <div class="ivory-booking-widget" role="region" aria-label="<?php esc_attr_e( 'Book your stay', 'ivory-booking' ); ?>">

    <!-- Header -->
    <div class="ivory-widget-header">
      <span class="iv-eyebrow"><?php esc_html_e( 'Availability', 'ivory-booking' ); ?></span>
      <h2><?php esc_html_e( 'Select Your Dates', 'ivory-booking' ); ?></h2>
      <p><?php esc_html_e( 'Choose your check-in and check-out dates below. Gold dates are available.', 'ivory-booking' ); ?></p>
    </div>

    <!-- Calendar (JS-rendered) -->
    <div class="ivory-calendar-outer" id="ivory-calendar" aria-live="polite">
      <div style="text-align:center;padding:32px;color:rgba(255,255,255,0.5);font-size:14px;">
        <?php esc_html_e( 'Loading calendar…', 'ivory-booking' ); ?>
      </div>
    </div>

    <!-- Range error -->
    <div id="iv-range-error" style="display:none;background:hsl(0,70%,92%);color:hsl(0,60%,35%);border-radius:6px;padding:10px 14px;font-size:13px;margin-bottom:16px;"></div>

    <!-- Price summary -->
    <div class="ivory-price-summary" id="ivory-price-summary" aria-live="polite">
      <span class="iv-summary-placeholder">
        <?php esc_html_e( 'Select check-in and check-out dates to see your total.', 'ivory-booking' ); ?>
      </span>
    </div>

    <!-- Guest selector -->
    <div class="ivory-guest-row">
      <span class="iv-guest-label"><?php esc_html_e( 'Guests:', 'ivory-booking' ); ?></span>
      <div class="iv-guest-toggle" role="group" aria-label="<?php esc_attr_e( 'Number of guests', 'ivory-booking' ); ?>">
        <button class="iv-guest-btn" data-guests="1" aria-pressed="false">1</button>
        <button class="iv-guest-btn active" data-guests="2" aria-pressed="true">2</button>
      </div>
      <span style="font-size:12px;color:rgba(255,255,255,0.4);">
        <?php esc_html_e( '(max 2 guests)', 'ivory-booking' ); ?>
      </span>
    </div>

    <!-- Book Now CTA -->
    <button class="ivory-book-btn" id="ivory-book-btn" disabled
            data-original-label="<?php esc_attr_e( 'Book Now', 'ivory-booking' ); ?>">
      <?php esc_html_e( 'Book Now', 'ivory-booking' ); ?>
    </button>

    <!-- Nightly rate note -->
    <p style="text-align:center;margin-top:14px;font-size:12px;color:hsl(30,20%,52%);">
      <?php
      $rate = number_format( (float) get_option( 'ivory_nightly_rate', 60000 ), 0, '.', ',' );
      /* translators: %s = formatted price */
      printf( esc_html__( '₦%s per night · All payments are non-refundable', 'ivory-booking' ), esc_html( $rate ) );
      ?>
    </p>

  </div>
</div>

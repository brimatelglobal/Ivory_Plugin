<?php
/**
 * Template: [ivory_checkout] shortcode — Checkout form + summary.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="ivory-wrap">
  <div class="ivory-checkout-wrap">

    <!-- ── Left: Form ──────────────────────────────────────────────────── -->
    <div class="ivory-checkout-form">
      <h2><?php esc_html_e( 'Your Details', 'ivory-booking' ); ?></h2>

      <!-- Name row -->
      <div class="iv-field-group">
        <div class="iv-field">
          <label for="iv-guest-name">
            <?php esc_html_e( 'Full Name', 'ivory-booking' ); ?>
            <span class="required" aria-hidden="true">*</span>
          </label>
          <input type="text" id="iv-guest-name" name="guest_name"
                 placeholder="<?php esc_attr_e( 'e.g. Adaeze Okonkwo', 'ivory-booking' ); ?>"
                 required autocomplete="name">
        </div>

        <div class="iv-field">
          <label for="iv-phone">
            <?php esc_html_e( 'Phone Number', 'ivory-booking' ); ?>
            <span class="required" aria-hidden="true">*</span>
          </label>
          <input type="tel" id="iv-phone" name="phone"
                 placeholder="<?php esc_attr_e( '+234 800 000 0000', 'ivory-booking' ); ?>"
                 required autocomplete="tel">
        </div>
      </div>

      <!-- Email -->
      <div class="iv-field">
        <label for="iv-email">
          <?php esc_html_e( 'Email Address', 'ivory-booking' ); ?>
          <span class="required" aria-hidden="true">*</span>
        </label>
        <input type="email" id="iv-email" name="email"
               placeholder="<?php esc_attr_e( 'you@example.com', 'ivory-booking' ); ?>"
               required autocomplete="email">
      </div>

      <!-- Address -->
      <div class="iv-field">
        <label for="iv-address">
          <?php esc_html_e( 'Home Address', 'ivory-booking' ); ?>
          <span class="required" aria-hidden="true">*</span>
        </label>
        <input type="text" id="iv-address" name="address"
               placeholder="<?php esc_attr_e( 'Street, City, State', 'ivory-booking' ); ?>"
               required autocomplete="street-address">
      </div>

      <!-- Occupation -->
      <div class="iv-field">
        <label for="iv-occupation">
          <?php esc_html_e( 'Occupation', 'ivory-booking' ); ?>
        </label>
        <input type="text" id="iv-occupation" name="occupation"
               placeholder="<?php esc_attr_e( 'e.g. Engineer, Business Owner', 'ivory-booking' ); ?>">
      </div>

      <!-- Next of Kin row -->
      <div class="iv-field-group">
        <div class="iv-field">
          <label for="iv-nok">
            <?php esc_html_e( 'Next of Kin', 'ivory-booking' ); ?>
          </label>
          <input type="text" id="iv-nok" name="next_of_kin"
                 placeholder="<?php esc_attr_e( 'Full name', 'ivory-booking' ); ?>">
        </div>
        <div class="iv-field">
          <label for="iv-nok-phone">
            <?php esc_html_e( 'Next of Kin Phone', 'ivory-booking' ); ?>
          </label>
          <input type="tel" id="iv-nok-phone" name="next_of_kin_phone"
                 placeholder="<?php esc_attr_e( '+234 800 000 0000', 'ivory-booking' ); ?>">
        </div>
      </div>

      <!-- Reason for Booking -->
      <div class="iv-field">
        <label for="iv-reason">
          <?php esc_html_e( 'Reason for Booking', 'ivory-booking' ); ?>
        </label>
        <input type="text" id="iv-reason" name="booking_reason"
               placeholder="<?php esc_attr_e( 'e.g. Leisure, Workation, Family visit, Corporate stay…', 'ivory-booking' ); ?>">
      </div>

      <!-- Government ID upload -->
      <div class="iv-field">
        <label>
          <?php esc_html_e( 'Government-Issued ID', 'ivory-booking' ); ?>
          <span class="required" aria-hidden="true">*</span>
        </label>
        <div class="iv-file-upload">
          <input type="file" name="id_document" id="iv-id-upload"
                 accept="image/*,.pdf" required aria-label="<?php esc_attr_e( 'Upload your Government ID', 'ivory-booking' ); ?>">
          <div class="iv-file-label">
            📎 <?php esc_html_e( 'Click to upload', 'ivory-booking' ); ?>
            <span><?php esc_html_e( '(NIN slip, Passport, Voter\'s card &mdash; JPG, PNG, or PDF)', 'ivory-booking' ); ?></span>
          </div>
          <div class="iv-file-name" aria-live="polite"></div>
        </div>
      </div>

      <!-- Special requests -->
      <div class="iv-field">
        <label for="iv-special"><?php esc_html_e( 'Special Requests', 'ivory-booking' ); ?></label>
        <input type="text" id="iv-special" name="special_req"
               placeholder="<?php esc_attr_e( 'Anything we should know before your arrival?', 'ivory-booking' ); ?>">
      </div>

      <!-- Proceed button -->
      <button class="ivory-proceed-btn" id="ivory-proceed-btn"
              data-original-label="<?php esc_attr_e( 'Proceed to Payment', 'ivory-booking' ); ?>">
        <?php esc_html_e( 'Proceed to Payment', 'ivory-booking' ); ?>
      </button>

      <!-- Error message -->
      <div class="iv-form-error" role="alert" aria-live="assertive"></div>

      <!-- Security note -->
      <p style="margin-top:14px;font-size:12px;color:#aaa;text-align:center;">
        🔒 <?php esc_html_e( 'Payment is processed securely by Paystack. Your card details are never stored on this site.', 'ivory-booking' ); ?>
      </p>
    </div>

    <!-- ── Right: Summary ──────────────────────────────────────────────── -->
    <div class="ivory-checkout-summary">
      <h3><?php esc_html_e( 'Booking Summary', 'ivory-booking' ); ?></h3>

      <div class="iv-sum-row">
        <span><?php esc_html_e( 'Property', 'ivory-booking' ); ?></span>
        <strong><?php esc_html_e( 'The Ivory Apartment', 'ivory-booking' ); ?></strong>
      </div>
      <div class="iv-sum-row iv-sum-dates-row">
        <span><?php esc_html_e( 'Check-in', 'ivory-booking' ); ?></span>
        <label class="iv-date-picker-wrap">
          <strong id="iv-sum-checkin-text">—</strong>
          <input type="date" id="iv-date-checkin" class="iv-date-hidden"
                 min="<?php echo esc_attr( date('Y-m-d') ); ?>" required>
        </label>
      </div>
      <div class="iv-sum-row iv-sum-dates-row">
        <span><?php esc_html_e( 'Check-out', 'ivory-booking' ); ?></span>
        <label class="iv-date-picker-wrap">
          <strong id="iv-sum-checkout-text">—</strong>
          <input type="date" id="iv-date-checkout" class="iv-date-hidden"
                 min="<?php echo esc_attr( date('Y-m-d', strtotime('+1 day')) ); ?>" required>
        </label>
      </div>
      <p class="iv-dates-hint">✏️ <?php esc_html_e( 'Tap a date to change it', 'ivory-booking' ); ?></p>
      <div class="iv-sum-row">
        <span><?php esc_html_e( 'Duration', 'ivory-booking' ); ?></span>
        <strong><span id="iv-sum-nights">—</span> <?php esc_html_e( 'night(s)', 'ivory-booking' ); ?></strong>
      </div>
      <div class="iv-sum-row">
        <span><?php esc_html_e( 'Guests', 'ivory-booking' ); ?></span>
        <strong id="iv-sum-guests">—</strong>
      </div>
      <div class="iv-sum-row">
        <span><?php esc_html_e( 'Rate', 'ivory-booking' ); ?></span>
        <strong id="iv-sum-rate">—</strong>
      </div>
      <div class="iv-sum-row total">
        <span><?php esc_html_e( 'Total', 'ivory-booking' ); ?></span>
        <span id="iv-sum-total">—</span>
      </div>

      <p class="iv-policy-note">
        <?php
        $checkin_time  = esc_html( get_option( 'ivory_checkin_time', '2:00 PM' ) );
        $checkout_time = esc_html( get_option( 'ivory_checkout_time', '12:00 PM' ) );
        /* translators: 1: check-in time, 2: check-out time */
        printf(
            esc_html__( 'Check-in from %1$s · Check-out by %2$s · All prepayments are non-refundable.', 'ivory-booking' ),
            $checkin_time,
            $checkout_time
        );
        ?>
      </p>

      <!-- House Rules -->
      <div class="iv-house-rules">
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
    </div>

  </div><!-- .ivory-checkout-wrap -->
</div>

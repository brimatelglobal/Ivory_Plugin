<?php
/**
 * Admin view: Settings.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Unauthorized', 'ivory-booking' ) );

$ical_export_url = add_query_arg( [
    'ivory_ical' => '1',
    'token'      => get_option( 'ivory_ical_token', '' ),
], home_url( '/' ) );
?>
<div class="wrap ivory-admin-wrap">
  <h1 class="iv-admin-page-title"><?php esc_html_e( 'Ivory Booking Settings', 'ivory-booking' ); ?></h1>

  <form method="post" action="options.php" class="iv-settings-form">
    <?php settings_fields( 'ivory_booking_settings' ); ?>

    <!-- ── Paystack ─────────────────────────────────────────────────────── -->
    <div class="iv-settings-section">
      <div class="iv-settings-section-header">
        <h2><?php esc_html_e( 'Paystack Payment', 'ivory-booking' ); ?></h2>
        <p><?php esc_html_e( 'Enter your Paystack API keys. Get them from your Paystack Dashboard → Settings → API Keys.', 'ivory-booking' ); ?></p>
      </div>

      <div class="iv-settings-body">
        <div class="iv-settings-row">
          <label for="ivory_paystack_public_key">
            <?php esc_html_e( 'Public Key', 'ivory-booking' ); ?>
            <span class="iv-hint"><?php esc_html_e( 'Starts with pk_', 'ivory-booking' ); ?></span>
          </label>
          <input type="text" id="ivory_paystack_public_key" name="ivory_paystack_public_key"
                 value="<?php echo esc_attr( get_option( 'ivory_paystack_public_key', '' ) ); ?>"
                 placeholder="pk_live_xxxxxxxxxxxxxxxx" class="regular-text">
        </div>

        <div class="iv-settings-row">
          <label for="ivory_paystack_secret_key_raw">
            <?php esc_html_e( 'Secret Key', 'ivory-booking' ); ?>
            <span class="iv-hint"><?php esc_html_e( 'Stored encrypted. Starts with sk_', 'ivory-booking' ); ?></span>
          </label>
          <input type="password" id="ivory_paystack_secret_key_raw" name="ivory_paystack_secret_key_raw"
                 value="" placeholder="<?php esc_attr_e( 'Leave blank to keep current key', 'ivory-booking' ); ?>"
                 class="regular-text" autocomplete="new-password">
          <?php if ( get_option( 'ivory_paystack_secret_key_encrypted' ) ) : ?>
            <span class="iv-key-saved">✅ <?php esc_html_e( 'Secret key saved (encrypted)', 'ivory-booking' ); ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Pricing ─────────────────────────────────────────────────────── -->
    <div class="iv-settings-section">
      <div class="iv-settings-section-header">
        <h2><?php esc_html_e( 'Pricing & Policies', 'ivory-booking' ); ?></h2>
      </div>
      <div class="iv-settings-body">
        <div class="iv-settings-row">
          <label for="ivory_nightly_rate"><?php esc_html_e( 'Nightly Rate (₦)', 'ivory-booking' ); ?></label>
          <input type="number" id="ivory_nightly_rate" name="ivory_nightly_rate"
                 value="<?php echo esc_attr( get_option( 'ivory_nightly_rate', '60000' ) ); ?>"
                 min="0" step="500" class="small-text">
        </div>

        <div class="iv-settings-row">
          <label for="ivory_checkin_time"><?php esc_html_e( 'Check-in Time', 'ivory-booking' ); ?></label>
          <input type="text" id="ivory_checkin_time" name="ivory_checkin_time"
                 value="<?php echo esc_attr( get_option( 'ivory_checkin_time', '2:00 PM' ) ); ?>"
                 placeholder="2:00 PM" class="small-text">
        </div>

        <div class="iv-settings-row">
          <label for="ivory_checkout_time"><?php esc_html_e( 'Check-out Time', 'ivory-booking' ); ?></label>
          <input type="text" id="ivory_checkout_time" name="ivory_checkout_time"
                 value="<?php echo esc_attr( get_option( 'ivory_checkout_time', '12:00 PM' ) ); ?>"
                 placeholder="12:00 PM" class="small-text">
        </div>
      </div>
    </div>

    <!-- ── Email ──────────────────────────────────────────────────────── -->
    <div class="iv-settings-section">
      <div class="iv-settings-section-header">
        <h2><?php esc_html_e( 'Email Notifications', 'ivory-booking' ); ?></h2>
      </div>
      <div class="iv-settings-body">
        <div class="iv-settings-row">
          <label for="ivory_admin_email">
            <?php esc_html_e( 'Admin Notification Email', 'ivory-booking' ); ?>
            <span class="iv-hint"><?php esc_html_e( 'Receives an alert on each new confirmed booking', 'ivory-booking' ); ?></span>
          </label>
          <input type="email" id="ivory_admin_email" name="ivory_admin_email"
                 value="<?php echo esc_attr( get_option( 'ivory_admin_email', get_option( 'admin_email' ) ) ); ?>"
                 class="regular-text">
        </div>

        <!-- Test Email -->
        <div class="iv-settings-row" style="align-items:center;gap:12px;flex-wrap:wrap;">
          <label><?php esc_html_e( 'Test Email Delivery', 'ivory-booking' ); ?>
            <span class="iv-hint"><?php esc_html_e( 'Sends a test email to the address above', 'ivory-booking' ); ?></span>
          </label>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <button type="button" id="iv-test-email-btn" class="button button-secondary">
              📧 <?php esc_html_e( 'Send Test Email', 'ivory-booking' ); ?>
            </button>
            <span id="iv-test-email-result" style="font-size:13px;font-weight:600;"></span>
          </div>
        </div>
      </div>
    </div>
    <script>
    document.getElementById('iv-test-email-btn').addEventListener('click', function() {
      const btn    = this;
      const result = document.getElementById('iv-test-email-result');
      btn.disabled = true;
      btn.textContent = '⏳ Sending…';
      result.textContent = '';
      result.style.color = '';

      fetch(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'ivory_test_email',
          nonce:  IvoryAdmin.nonce,
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          result.textContent = '✅ Email sent! Check your inbox.';
          result.style.color = '#1e7e34';
        } else {
          result.textContent = '❌ Failed: ' + (data.data || 'Unknown error. Check server error log.');
          result.style.color = '#c0392b';
        }
      })
      .catch(() => {
        result.textContent = '❌ Request error. Check browser console.';
        result.style.color = '#c0392b';
      })
      .finally(() => {
        btn.disabled = false;
        btn.textContent = '📧 Send Test Email';
      });
    });
    </script>

    <!-- ── iCal Export ───────────────────────────────────────────────── -->
    <div class="iv-settings-section">
      <div class="iv-settings-section-header">
        <h2><?php esc_html_e( 'iCal Export URL', 'ivory-booking' ); ?></h2>
        <p><?php esc_html_e( 'Copy this URL and paste it into Airbnb / Booking.com "Sync Calendar" to block your website bookings on those platforms.', 'ivory-booking' ); ?></p>
      </div>
      <div class="iv-settings-body">
        <div class="iv-settings-row">
          <label><?php esc_html_e( 'Your .ics Export URL', 'ivory-booking' ); ?></label>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="text" value="<?php echo esc_url( $ical_export_url ); ?>"
                   class="regular-text" id="iv-ical-export-url" readonly onclick="this.select()">
            <button type="button" class="button" onclick="
              navigator.clipboard.writeText(document.getElementById('iv-ical-export-url').value);
              this.textContent='Copied!';
              setTimeout(()=>this.textContent='Copy',2000);">
              <?php esc_html_e( 'Copy', 'ivory-booking' ); ?>
            </button>
          </div>
        </div>
      </div>
    </div>

    <?php submit_button( __( 'Save Settings', 'ivory-booking' ) ); ?>
  </form>
</div>

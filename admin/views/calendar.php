<?php
/**
 * Admin view: Calendar — visual monthly calendar + date blocking.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Unauthorized', 'ivory-booking' ) );

$ranges   = Ivory_Database::get_unavailable_ranges();
$blocks   = Ivory_Database::get_blocks();
$bookings = Ivory_Database::get_bookings( 'confirmed' );
?>
<div class="wrap ivory-admin-wrap">
  <h1 class="iv-admin-page-title"><?php esc_html_e( 'Availability Calendar', 'ivory-booking' ); ?></h1>

  <div class="iv-admin-calendar-layout">

    <!-- Calendar -->
    <div class="iv-admin-cal-card">
      <div id="iv-admin-calendar" data-ranges="<?php echo esc_attr( wp_json_encode( $ranges ) ); ?>">
        <!-- Rendered by admin.js -->
      </div>
    </div>

    <!-- Right sidebar -->
    <div class="iv-admin-sidebar">

      <!-- Block Dates form -->
      <div class="iv-admin-card">
        <h3><?php esc_html_e( 'Block Dates', 'ivory-booking' ); ?></h3>
        <p style="font-size:12px;color:#777;margin-bottom:12px;">
          <?php esc_html_e( 'Block a date range for owner use, maintenance, or off-platform reservations.', 'ivory-booking' ); ?>
        </p>
        <div class="iv-field">
          <label><?php esc_html_e( 'Start Date', 'ivory-booking' ); ?></label>
          <input type="date" id="iv-block-start">
        </div>
        <div class="iv-field">
          <label><?php esc_html_e( 'End Date', 'ivory-booking' ); ?></label>
          <input type="date" id="iv-block-end">
        </div>
        <div class="iv-field">
          <label><?php esc_html_e( 'Reason (optional)', 'ivory-booking' ); ?></label>
          <input type="text" id="iv-block-reason"
                 placeholder="<?php esc_attr_e( 'e.g. Owner use, Maintenance', 'ivory-booking' ); ?>">
        </div>
        <button class="button button-primary" id="iv-block-submit">
          <?php esc_html_e( 'Block Dates', 'ivory-booking' ); ?>
        </button>
        <div id="iv-block-result" style="margin-top:10px;font-size:13px;"></div>
      </div>

      <!-- Legend -->
      <div class="iv-admin-card">
        <h3><?php esc_html_e( 'Legend', 'ivory-booking' ); ?></h3>
        <ul class="iv-legend">
          <li><span class="iv-dot iv-dot--booked"></span><?php esc_html_e( 'Website Booking', 'ivory-booking' ); ?></li>
          <li><span class="iv-dot iv-dot--blocked"></span><?php esc_html_e( 'Manually Blocked', 'ivory-booking' ); ?></li>
          <li><span class="iv-dot iv-dot--synced"></span><?php esc_html_e( 'iCal Synced', 'ivory-booking' ); ?></li>
          <li><span class="iv-dot iv-dot--today"></span><?php esc_html_e( 'Today', 'ivory-booking' ); ?></li>
        </ul>
      </div>

      <!-- Active blocks -->
      <?php if ( $blocks ) : ?>
      <div class="iv-admin-card">
        <h3><?php esc_html_e( 'Active Manual Blocks', 'ivory-booking' ); ?></h3>
        <ul class="iv-blocks-list">
        <?php foreach ( $blocks as $bl ) :
          if ( $bl['source'] !== 'manual' ) continue; ?>
          <li class="iv-block-item">
            <div>
              <strong><?php echo esc_html( date_i18n( 'j M', strtotime( $bl['block_start'] ) ) ); ?></strong>
              → <strong><?php echo esc_html( date_i18n( 'j M Y', strtotime( $bl['block_end'] ) ) ); ?></strong>
              <?php if ( $bl['reason'] ) echo '<br><small>' . esc_html( $bl['reason'] ) . '</small>'; ?>
            </div>
            <button class="button button-small iv-unblock-btn"
                    data-id="<?php echo (int) $bl['id']; ?>"
                    title="<?php esc_attr_e( 'Remove block', 'ivory-booking' ); ?>">✕</button>
          </li>
        <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

    </div><!-- .iv-admin-sidebar -->
  </div><!-- .iv-admin-calendar-layout -->
</div>

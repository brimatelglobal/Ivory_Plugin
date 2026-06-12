<?php
/**
 * Admin view: iCal Sync manager.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Unauthorized', 'ivory-booking' ) );

$feeds = get_option( 'ivory_ical_import_feeds', [] );
?>
<div class="wrap ivory-admin-wrap">
  <h1 class="iv-admin-page-title"><?php esc_html_e( 'iCal Sync', 'ivory-booking' ); ?></h1>

  <p style="max-width:640px;color:#555;margin-bottom:24px;">
    <?php esc_html_e( 'Add iCal feed URLs from Airbnb, Booking.com, or any other platform. The plugin will automatically import and block those dates on your website calendar every 2 hours.', 'ivory-booking' ); ?>
  </p>

  <!-- Add new feed -->
  <div class="iv-admin-card" style="max-width:640px;">
    <h3><?php esc_html_e( 'Add iCal Feed', 'ivory-booking' ); ?></h3>
    <div class="iv-field">
      <label for="iv-ical-label"><?php esc_html_e( 'Label (e.g. "Airbnb")', 'ivory-booking' ); ?></label>
      <input type="text" id="iv-ical-label" placeholder="Airbnb" class="regular-text">
    </div>
    <div class="iv-field">
      <label for="iv-ical-url"><?php esc_html_e( 'iCal URL (.ics)', 'ivory-booking' ); ?></label>
      <input type="url" id="iv-ical-url"
             placeholder="https://www.airbnb.com/calendar/ical/xxxxx.ics"
             class="large-text">
    </div>
    <button class="button button-primary" id="iv-ical-add">
      <?php esc_html_e( 'Add & Sync Now', 'ivory-booking' ); ?>
    </button>
    <div id="iv-ical-add-result" style="margin-top:12px;font-size:13px;"></div>
  </div>

  <!-- Existing feeds -->
  <?php if ( $feeds ) : ?>
  <div class="iv-admin-card" style="margin-top:24px;max-width:860px;">
    <h3><?php esc_html_e( 'Active Feeds', 'ivory-booking' ); ?></h3>
    <table class="iv-ical-table widefat">
      <thead>
        <tr>
          <th><?php esc_html_e( 'Label', 'ivory-booking' ); ?></th>
          <th><?php esc_html_e( 'URL', 'ivory-booking' ); ?></th>
          <th><?php esc_html_e( 'Last Synced', 'ivory-booking' ); ?></th>
          <th><?php esc_html_e( 'Ranges', 'ivory-booking' ); ?></th>
          <th><?php esc_html_e( 'Status', 'ivory-booking' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'ivory-booking' ); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ( $feeds as $feed ) :
        $last = $feed['last_synced']
            ? date_i18n( 'j M Y, H:i', strtotime( $feed['last_synced'] ) )
            : __( 'Never', 'ivory-booking' );
      ?>
        <tr data-url="<?php echo esc_attr( $feed['url'] ); ?>">
          <td><?php echo esc_html( $feed['label'] ?: $feed['url'] ); ?></td>
          <td style="word-break:break-all;max-width:280px;font-size:12px;color:#888;">
            <?php echo esc_html( $feed['url'] ); ?>
          </td>
          <td><?php echo esc_html( $last ); ?></td>
          <td><?php echo (int) ( $feed['count'] ?? 0 ); ?></td>
          <td>
            <span class="iv-status-badge iv-status-<?php echo esc_attr( $feed['status'] ?? 'pending' ); ?>">
              <?php echo esc_html( ucfirst( $feed['status'] ?? 'pending' ) ); ?>
            </span>
          </td>
          <td>
            <button class="button button-small iv-ical-sync-btn"
                    data-url="<?php echo esc_attr( $feed['url'] ); ?>">
              ↻ <?php esc_html_e( 'Sync Now', 'ivory-booking' ); ?>
            </button>
            <button class="button button-small iv-ical-remove-btn"
                    data-url="<?php echo esc_attr( $feed['url'] ); ?>">
              ✕ <?php esc_html_e( 'Remove', 'ivory-booking' ); ?>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else : ?>
    <p style="color:#777;margin-top:20px;"><?php esc_html_e( 'No iCal feeds added yet.', 'ivory-booking' ); ?></p>
  <?php endif; ?>

  <!-- Next cron run -->
  <?php
  $next = wp_next_scheduled( 'ivory_ical_sync' );
  if ( $next ) :
    $diff = human_time_diff( time(), $next );
  ?>
    <p style="color:#999;font-size:12px;margin-top:20px;">
      <?php
      printf(
          /* translators: %s = human readable time */
          esc_html__( 'Next automatic sync in approximately %s.', 'ivory-booking' ),
          esc_html( $diff )
      );
      ?>
    </p>
  <?php endif; ?>

</div>

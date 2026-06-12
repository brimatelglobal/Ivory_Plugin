<?php
/**
 * Admin view: All Bookings list.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Unauthorized', 'ivory-booking' ) );

// Handle status filter.
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$search        = sanitize_text_field( $_GET['s']      ?? '' );

// Handle single booking detail view.
$view_ref = sanitize_text_field( $_GET['ref'] ?? '' );
if ( $view_ref ) {
    $booking = Ivory_Database::get_booking_by_reference( $view_ref );
}

$bookings = Ivory_Database::get_bookings( $status_filter );

// Apply search filter in PHP (small dataset).
if ( $search ) {
    $bookings = array_filter( $bookings, function ( array $b ) use ( $search ): bool {
        return stripos( $b['guest_name'], $search ) !== false
            || stripos( $b['guest_email'], $search ) !== false
            || stripos( $b['reference'], $search ) !== false;
    } );
}

$totals = [
    'all'       => Ivory_Database::count_bookings(),
    'confirmed' => Ivory_Database::count_bookings( 'confirmed' ),
    'pending'   => Ivory_Database::count_bookings( 'pending' ),
    'cancelled' => Ivory_Database::count_bookings( 'cancelled' ),
    'completed' => Ivory_Database::count_bookings( 'completed' ),
];

$status_labels = [
    ''          => __( 'All', 'ivory-booking' ),
    'confirmed' => __( 'Confirmed', 'ivory-booking' ),
    'pending'   => __( 'Pending', 'ivory-booking' ),
    'cancelled' => __( 'Cancelled', 'ivory-booking' ),
    'completed' => __( 'Completed', 'ivory-booking' ),
];

$format_money = fn( $v ) => '₦' . number_format( (float) $v, 0 );
$format_date  = fn( $d ) => $d ? date_i18n( 'j M Y', strtotime( $d ) ) : '—';
$base_url     = admin_url( 'admin.php?page=ivory-bookings' );
?>

<div class="wrap ivory-admin-wrap">
  <h1 class="iv-admin-page-title">
    <?php esc_html_e( 'Bookings', 'ivory-booking' ); ?>
    <a href="#" id="iv-add-booking-trigger" class="page-title-action">
      + <?php esc_html_e( 'Add Manual Booking', 'ivory-booking' ); ?>
    </a>
  </h1>

  <!-- Status filter tabs -->
  <ul class="iv-status-tabs">
    <?php foreach ( $status_labels as $key => $label ) :
      $count = $key === '' ? $totals['all'] : ( $totals[ $key ] ?? 0 );
      $active = $status_filter === $key;
    ?>
      <li>
        <a href="<?php echo esc_url( add_query_arg( 'status', $key, $base_url ) ); ?>"
           class="<?php echo $active ? 'active' : ''; ?>">
          <?php echo esc_html( $label ); ?>
          <span class="iv-tab-count"><?php echo (int) $count; ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <!-- Search -->
  <form method="get" class="iv-search-form">
    <input type="hidden" name="page" value="ivory-bookings">
    <input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
           placeholder="<?php esc_attr_e( 'Search by name, email, or reference…', 'ivory-booking' ); ?>">
    <button type="submit" class="button"><?php esc_html_e( 'Search', 'ivory-booking' ); ?></button>
  </form>

  <!-- Table -->
  <table class="iv-bookings-table widefat">
    <thead>
      <tr>
        <th><?php esc_html_e( 'Reference', 'ivory-booking' ); ?></th>
        <th><?php esc_html_e( 'Guest', 'ivory-booking' ); ?></th>
        <th><?php esc_html_e( 'Check-in', 'ivory-booking' ); ?></th>
        <th><?php esc_html_e( 'Check-out', 'ivory-booking' ); ?></th>
        <th><?php esc_html_e( 'Nights', 'ivory-booking' ); ?></th>
        <th><?php esc_html_e( 'Total', 'ivory-booking' ); ?></th>
        <th><?php esc_html_e( 'Source', 'ivory-booking' ); ?></th>
        <th><?php esc_html_e( 'Status', 'ivory-booking' ); ?></th>
        <th><?php esc_html_e( 'Booked On', 'ivory-booking' ); ?></th>
      </tr>
    </thead>
    <tbody>
    <?php if ( empty( $bookings ) ) : ?>
      <tr><td colspan="9" class="iv-empty-state"><?php esc_html_e( 'No bookings found.', 'ivory-booking' ); ?></td></tr>
    <?php else : ?>
      <?php foreach ( $bookings as $b ) :
        $detail_url = add_query_arg( [ 'page' => 'ivory-bookings', 'ref' => $b['reference'] ], admin_url( 'admin.php' ) );
      ?>
        <tr>
          <td><a href="<?php echo esc_url( $detail_url ); ?>" class="iv-ref-link"><?php echo esc_html( $b['reference'] ); ?></a></td>
          <td>
            <strong><?php echo esc_html( $b['guest_name'] ); ?></strong><br>
            <small><?php echo esc_html( $b['guest_email'] ); ?></small>
          </td>
          <td><?php echo esc_html( $format_date( $b['checkin_date'] ) ); ?></td>
          <td><?php echo esc_html( $format_date( $b['checkout_date'] ) ); ?></td>
          <td><?php echo (int) $b['nights']; ?></td>
          <td><?php echo esc_html( $format_money( $b['total_amount'] ) ); ?></td>
          <td><span class="iv-source-badge iv-source-<?php echo esc_attr( $b['source'] ); ?>"><?php echo esc_html( ucfirst( $b['source'] ) ); ?></span></td>
          <td><span class="iv-status-badge iv-status-<?php echo esc_attr( $b['status'] ); ?>"><?php echo esc_html( ucfirst( $b['status'] ) ); ?></span></td>
          <td><?php echo esc_html( $format_date( $b['created_at'] ) ); ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <?php if ( $view_ref && isset( $booking ) && $booking ) : ?>
  <!-- Booking detail panel (shown when ?ref= is in URL) -->
  <div class="iv-detail-panel">
    <h2><?php echo esc_html( $booking['reference'] ); ?></h2>
    <div class="iv-detail-grid">
      <?php
      $fields = [
        __( 'Guest Name',    'ivory-booking' ) => $booking['guest_name'],
        __( 'Email',         'ivory-booking' ) => $booking['guest_email'],
        __( 'Phone',         'ivory-booking' ) => $booking['guest_phone'],
        __( 'Check-in',      'ivory-booking' ) => $format_date( $booking['checkin_date'] ),
        __( 'Check-out',     'ivory-booking' ) => $format_date( $booking['checkout_date'] ),
        __( 'Nights',        'ivory-booking' ) => $booking['nights'],
        __( 'Guests',        'ivory-booking' ) => $booking['guests'],
        __( 'Total Amount',  'ivory-booking' ) => $format_money( $booking['total_amount'] ),
        __( 'Status',        'ivory-booking' ) => ucfirst( $booking['status'] ),
        __( 'Paystack Ref',  'ivory-booking' ) => $booking['payment_ref']   ?: '—',
        __( 'Source',        'ivory-booking' ) => ucfirst( $booking['source'] ),
        __( 'Address',       'ivory-booking' ) => $booking['address']          ?: '—',
        __( 'Occupation',    'ivory-booking' ) => $booking['occupation']       ?: '—',
        __( 'Next of Kin',   'ivory-booking' ) => $booking['next_of_kin']      ?: '—',
        __( 'N.o.K. Phone',  'ivory-booking' ) => $booking['next_of_kin_phone'] ?: '—',
        __( 'Reason',        'ivory-booking' ) => $booking['booking_reason']   ?: '—',
        __( 'Special Req.',  'ivory-booking' ) => $booking['special_req']      ?: '—',
        __( 'Booked On',     'ivory-booking' ) => $format_date( $booking['created_at'] ),
      ];
      foreach ( $fields as $label => $value ) :
      ?>
        <div class="iv-detail-row">
          <span class="iv-detail-label"><?php echo esc_html( $label ); ?></span>
          <span class="iv-detail-value"><?php echo esc_html( (string) $value ); ?></span>
        </div>
      <?php endforeach; ?>

      <?php
      // ── Gov. ID viewer row (separate — uses a link, not plain text) ──────────
      echo '<div class="iv-detail-row">';
      echo '<span class="iv-detail-label">' . esc_html__( 'Gov. ID File', 'ivory-booking' ) . '</span>';
      if ( ! empty( $booking['id_file_path'] ) ) {
          $view_url = add_query_arg( [
              'action'   => 'ivory_view_id',
              'ref'      => $booking['reference'],
              '_wpnonce' => wp_create_nonce( 'ivory_view_id' ),
          ], admin_url( 'admin-post.php' ) );
          printf(
              '<span class="iv-detail-value"><a href="%s" target="_blank" class="iv-view-id-link">📎 %s</a></span>',
              esc_url( $view_url ),
              esc_html( basename( $booking['id_file_path'] ) )
          );
      } else {
          echo '<span class="iv-detail-value">—</span>';
      }
      echo '</div>';
      ?>
    </div>
    <div class="iv-detail-actions">
      <a href="<?php echo esc_url( $base_url ); ?>" class="button">&larr; <?php esc_html_e( 'Back to list', 'ivory-booking' ); ?></a>

      <?php
      $cancellable  = in_array( $booking['status'], [ 'pending', 'confirmed' ], true );
      $completable  = $booking['status'] === 'confirmed';
      $action_ref   = esc_attr( $booking['reference'] );
      ?>

      <?php if ( $cancellable ) : ?>
      <button class="button iv-status-action-btn iv-cancel-btn"
              data-reference="<?php echo $action_ref; ?>"
              data-status="cancelled">
        🚫 <?php esc_html_e( 'Cancel Booking', 'ivory-booking' ); ?>
      </button>
      <?php endif; ?>

      <?php if ( $completable ) : ?>
      <button class="button iv-status-action-btn iv-complete-btn"
              data-reference="<?php echo $action_ref; ?>"
              data-status="completed">
        ✅ <?php esc_html_e( 'Mark as Completed', 'ivory-booking' ); ?>
      </button>
      <?php endif; ?>
    </div>

    <div id="iv-status-feedback" class="iv-status-feedback" style="display:none;"></div>
  </div>
  <?php endif; ?>

</div><!-- .ivory-admin-wrap -->

<!-- Add Manual Booking modal -->
<div id="iv-manual-modal" class="iv-modal" style="display:none;">
  <div class="iv-modal-inner">
    <h2><?php esc_html_e( 'Add Manual Booking', 'ivory-booking' ); ?></h2>
    <div class="iv-field">
      <label><?php esc_html_e( 'Guest Name', 'ivory-booking' ); ?></label>
      <input type="text" id="iv-m-name" placeholder="Full name">
    </div>
    <div class="iv-field">
      <label><?php esc_html_e( 'Email', 'ivory-booking' ); ?></label>
      <input type="email" id="iv-m-email" placeholder="email@example.com">
    </div>
    <div class="iv-field">
      <label><?php esc_html_e( 'Phone', 'ivory-booking' ); ?></label>
      <input type="text" id="iv-m-phone" placeholder="+234...">
    </div>
    <div class="iv-field-row">
      <div class="iv-field">
        <label><?php esc_html_e( 'Check-in', 'ivory-booking' ); ?></label>
        <input type="date" id="iv-m-checkin">
      </div>
      <div class="iv-field">
        <label><?php esc_html_e( 'Check-out', 'ivory-booking' ); ?></label>
        <input type="date" id="iv-m-checkout">
      </div>
    </div>
    <div class="iv-field">
      <label><?php esc_html_e( 'Guests', 'ivory-booking' ); ?></label>
      <select id="iv-m-guests"><option>1</option><option selected>2</option></select>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px;">
      <button class="button button-primary" id="iv-m-submit"><?php esc_html_e( 'Create Booking', 'ivory-booking' ); ?></button>
      <button class="button" id="iv-modal-close"><?php esc_html_e( 'Cancel', 'ivory-booking' ); ?></button>
    </div>
    <div id="iv-m-result" style="margin-top:12px;font-size:13px;"></div>
  </div>
</div>

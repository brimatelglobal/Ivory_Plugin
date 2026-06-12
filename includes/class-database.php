<?php
/**
 * Database class — handles custom table creation, upgrades, and queries.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ivory_Database {

    // ─── Table Names ─────────────────────────────────────────────────────────

    public static function table_bookings(): string {
        global $wpdb;
        return $wpdb->prefix . 'ivory_bookings';
    }

    public static function table_blocks(): string {
        global $wpdb;
        return $wpdb->prefix . 'ivory_blocks';
    }

    public static function table_locks(): string {
        global $wpdb;
        return $wpdb->prefix . 'ivory_locks';
    }

    // ─── Installation ─────────────────────────────────────────────────────────

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // ── ivory_bookings ────────────────────────────────────────────────────
        $sql_bookings = "CREATE TABLE " . self::table_bookings() . " (
            id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            reference      VARCHAR(20)         NOT NULL,
            guest_name     VARCHAR(255)        NOT NULL,
            guest_email    VARCHAR(255)        NOT NULL,
            guest_phone    VARCHAR(50)         NOT NULL,
            checkin_date   DATE                NOT NULL,
            checkout_date  DATE                NOT NULL,
            nights         TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
            guests         TINYINT(1) UNSIGNED NOT NULL DEFAULT 2,
            total_amount   DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
            status         VARCHAR(20)         NOT NULL DEFAULT 'pending',
            payment_ref    VARCHAR(100)        DEFAULT NULL,
            id_file_path   TEXT                DEFAULT NULL,
            special_req    TEXT                DEFAULT NULL,
            arrival_time      VARCHAR(50)         DEFAULT NULL,
            address           TEXT                DEFAULT NULL,
            occupation        VARCHAR(100)        DEFAULT NULL,
            next_of_kin       VARCHAR(255)        DEFAULT NULL,
            next_of_kin_phone VARCHAR(50)         DEFAULT NULL,
            booking_reason    TEXT                DEFAULT NULL,
            source         VARCHAR(50)         NOT NULL DEFAULT 'website',
            created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   reference (reference),
            KEY          checkin_date (checkin_date),
            KEY          status (status)
        ) $charset_collate;";

        // ── ivory_blocks ──────────────────────────────────────────────────────
        $sql_blocks = "CREATE TABLE " . self::table_blocks() . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            block_start DATE               NOT NULL,
            block_end   DATE               NOT NULL,
            reason      VARCHAR(255)       DEFAULT NULL,
            source      VARCHAR(100)       NOT NULL DEFAULT 'manual',
            created_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY         block_start (block_start)
        ) $charset_collate;";

        // ── ivory_locks ───────────────────────────────────────────────────────
        $sql_locks = "CREATE TABLE " . self::table_locks() . " (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            checkin_date  DATE               NOT NULL,
            checkout_date DATE               NOT NULL,
            session_token VARCHAR(64)        NOT NULL,
            expires_at    DATETIME           NOT NULL,
            created_at    DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   session_token (session_token),
            KEY          expires_at (expires_at)
        ) $charset_collate;";

        dbDelta( $sql_bookings );
        dbDelta( $sql_blocks );
        dbDelta( $sql_locks );

        // Store the installed DB version for future upgrade checks.
        update_option( 'ivory_db_version', IVORY_VERSION );
    }

    // ─── Uninstall ────────────────────────────────────────────────────────────

    public static function uninstall(): void {
        global $wpdb;

        $tables = [
            self::table_locks(),
            self::table_blocks(),
            self::table_bookings(),
        ];

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
        }

        // Remove all plugin options.
        $options = [
            'ivory_db_version',
            'ivory_paystack_public_key',
            'ivory_paystack_secret_key_encrypted',
            'ivory_nightly_rate',
            'ivory_checkin_time',
            'ivory_checkout_time',
            'ivory_admin_email',
            'ivory_ical_import_feeds',
            'ivory_ical_token',
            'ivory_booking_page_id',
            'ivory_checkout_page_id',
            'ivory_confirmation_page_id',
        ];

        foreach ( $options as $option ) {
            delete_option( $option );
        }
    }

    // ─── Query Helpers ────────────────────────────────────────────────────────

    /**
     * Returns all booked date ranges (confirmed/pending) and manual blocks
     * as an array of [ checkin_date, checkout_date ] pairs.
     * Used by the REST availability endpoint.
     *
     * @return array<int, array{checkin: string, checkout: string, type: string}>
     */
    public static function get_unavailable_ranges(): array {
        global $wpdb;
        $results = [];

        // ── Active bookings ───────────────────────────────────────────────────
        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT checkin_date, checkout_date
                 FROM   %i
                 WHERE  status IN ('pending', 'confirmed')
                   AND  checkout_date >= CURDATE()",
                self::table_bookings()
            ),
            ARRAY_A
        );

        foreach ( (array) $bookings as $row ) {
            $results[] = [
                'checkin'  => $row['checkin_date'],
                'checkout' => $row['checkout_date'],
                'type'     => 'booked',
            ];
        }

        // ── Manual + iCal blocks ──────────────────────────────────────────────
        $blocks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT block_start, block_end, source
                 FROM   %i
                 WHERE  block_end >= CURDATE()",
                self::table_blocks()
            ),
            ARRAY_A
        );

        foreach ( (array) $blocks as $row ) {
            $results[] = [
                'checkin'  => $row['block_start'],
                'checkout' => $row['block_end'],
                'type'     => $row['source'] === 'manual' ? 'blocked' : 'synced',
            ];
        }

        return $results;
    }

    /**
     * Check whether a given checkin–checkout range overlaps any existing
     * confirmed/pending booking or block. Returns true if the range is free.
     */
    public static function is_range_available( string $checkin, string $checkout ): bool {
        global $wpdb;

        // Check bookings table.
        $booking_conflict = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i
                 WHERE status IN ('pending', 'confirmed')
                   AND checkin_date  < %s
                   AND checkout_date > %s",
                self::table_bookings(),
                $checkout,
                $checkin
            )
        );

        if ( (int) $booking_conflict > 0 ) {
            return false;
        }

        // Check active locks.
        $now_wat       = wp_date( 'Y-m-d H:i:s' ); // WAT — no MySQL timezone dependency
        $lock_conflict = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i
                 WHERE expires_at > %s
                   AND checkin_date  < %s
                   AND checkout_date > %s",
                self::table_locks(),
                $now_wat,
                $checkout,
                $checkin
            )
        );

        if ( (int) $lock_conflict > 0 ) {
            return false;
        }

        // Check blocks table.
        $block_conflict = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i
                 WHERE block_start < %s
                   AND block_end   > %s",
                self::table_blocks(),
                $checkout,
                $checkin
            )
        );

        return (int) $block_conflict === 0;
    }

    /**
     * Get a single booking by its reference code.
     *
     * @return array<string, mixed>|null
     */
    public static function get_booking_by_reference( string $reference ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE reference = %s LIMIT 1",
                self::table_bookings(),
                $reference
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get all bookings — optionally filtered by status.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_bookings( string $status = '', int $limit = 100, int $offset = 0 ): array {
        global $wpdb;

        if ( $status ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE status = %s ORDER BY checkin_date DESC LIMIT %d OFFSET %d",
                    self::table_bookings(),
                    $status,
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i ORDER BY checkin_date DESC LIMIT %d OFFSET %d",
                    self::table_bookings(),
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
        }

        return (array) $rows;
    }

    /**
     * Count all bookings, optionally by status.
     */
    public static function count_bookings( string $status = '' ): int {
        global $wpdb;

        if ( $status ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM %i WHERE status = %s",
                    self::table_bookings(),
                    $status
                )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM %i", self::table_bookings() )
        );
    }

    /**
     * Get all manual blocks.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_blocks(): array {
        global $wpdb;
        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i ORDER BY block_start ASC",
                self::table_blocks()
            ),
            ARRAY_A
        );
    }

    /**
     * Insert a manual date block.
     */
    public static function insert_block( string $start, string $end, string $reason = '', string $source = 'manual' ): bool {
        global $wpdb;
        return (bool) $wpdb->insert(
            self::table_blocks(),
            [
                'block_start' => $start,
                'block_end'   => $end,
                'reason'      => $reason,
                'source'      => $source,
            ],
            [ '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Delete a block by ID.
     */
    public static function delete_block( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            self::table_blocks(),
            [ 'id' => $id ],
            [ '%d' ]
        );
    }

    /**
     * Delete all iCal-sourced blocks for a given source identifier,
     * then re-insert fresh ones. Used during iCal sync.
     */
    public static function replace_ical_blocks( string $source, array $ranges ): void {
        global $wpdb;

        // Remove stale blocks for this source.
        $wpdb->delete(
            self::table_blocks(),
            [ 'source' => $source ],
            [ '%s' ]
        );

        // Insert fresh ranges.
        foreach ( $ranges as $range ) {
            $wpdb->insert(
                self::table_blocks(),
                [
                    'block_start' => $range['start'],
                    'block_end'   => $range['end'],
                    'reason'      => $range['reason'] ?? 'iCal sync',
                    'source'      => $source,
                ],
                [ '%s', '%s', '%s', '%s' ]
            );
        }
    }

    /**
     * Update the status of a booking by its reference.
     * Only allows transitions to safe statuses (cancelled, completed).
     *
     * @return bool  True on update, false if the booking wasn't found or DB error.
     */
    public static function update_booking_status( string $reference, string $new_status ): bool {
        global $wpdb;

        $allowed = [ 'cancelled', 'completed', 'confirmed', 'pending', 'conflict' ];
        if ( ! in_array( $new_status, $allowed, true ) ) {
            return false;
        }

        $result = $wpdb->update(
            self::table_bookings(),
            [ 'status' => $new_status ],
            [ 'reference' => $reference ],
            [ '%s' ],
            [ '%s' ]
        );

        return $result !== false && $result > 0;
    }

    /**
     * Purge all expired date locks. Called by WP-Cron.
     */
    public static function purge_expired_locks(): int {
        global $wpdb;
        $now_wat = wp_date( 'Y-m-d H:i:s' ); // WAT
        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %i WHERE expires_at <= %s",
                self::table_locks(),
                $now_wat
            )
        );
    }
}

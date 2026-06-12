<?php
/**
 * iCal import and export.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ivory_iCal {

    // ─── Export ───────────────────────────────────────────────────────────────

    /**
     * Generate and stream a .ics file of all confirmed bookings + blocks.
     * Called from template_redirect on `?ivory_ical=1&token=...`.
     */
    public static function export_and_send(): void {
        $site_name = get_bloginfo( 'name' );
        $prodid    = '-//Ivory Booking//EN';

        $lines   = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:' . $prodid;
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:' . $site_name . ' Availability';
        $lines[] = 'X-WR-CALDESC:Booked dates for The Ivory Apartment';

        // ── Confirmed bookings ────────────────────────────────────────────────
        $bookings = Ivory_Database::get_bookings( 'confirmed' );
        foreach ( $bookings as $b ) {
            $lines = array_merge( $lines, self::build_vevent(
                $b['checkin_date'],
                $b['checkout_date'],
                'IVORY-' . $b['reference'],
                'Booked',
                $b['guest_name'] . ' — ' . $b['reference']
            ) );
        }

        // ── Manual blocks ─────────────────────────────────────────────────────
        $blocks = Ivory_Database::get_blocks();
        foreach ( $blocks as $bl ) {
            $lines = array_merge( $lines, self::build_vevent(
                $bl['block_start'],
                $bl['block_end'],
                'IVORYBLOCK-' . $bl['id'],
                'Not available',
                $bl['reason'] ?: 'Blocked'
            ) );
        }

        $lines[] = 'END:VCALENDAR';

        $output = implode( "\r\n", $lines ) . "\r\n";

        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="ivory-availability.ics"' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Expires: 0' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $output;
    }

    // ─── Import ───────────────────────────────────────────────────────────────

    /**
     * Fetch and parse an external iCal URL.
     * Stores results in the blocks table under the given source identifier.
     *
     * @return array{success: bool, count: int, message: string}
     */
    public static function import( string $url ): array {
        $response = wp_remote_get( $url, [
            'timeout'    => 30,
            'user-agent' => 'IvoryBooking/1.0 (WordPress; +' . home_url() . ')',
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'count'   => 0,
                'message' => $response->get_error_message(),
            ];
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return [ 'success' => false, 'count' => 0, 'message' => 'Empty response from iCal URL.' ];
        }

        $ranges = self::parse_ical( $body );

        // Use the URL itself (md5-hashed) as the source key.
        $source = 'ical_' . md5( $url );
        Ivory_Database::replace_ical_blocks( $source, $ranges );

        // Update last-synced timestamp for this URL.
        $feeds = get_option( 'ivory_ical_import_feeds', [] );
        foreach ( $feeds as &$feed ) {
            if ( $feed['url'] === $url ) {
                $feed['last_synced'] = current_time( 'mysql' );
                $feed['status']      = 'ok';
                $feed['count']       = count( $ranges );
            }
        }
        unset( $feed );
        update_option( 'ivory_ical_import_feeds', $feeds );

        return [
            'success' => true,
            'count'   => count( $ranges ),
            'message' => 'Synced ' . count( $ranges ) . ' date range(s).',
        ];
    }

    /**
     * Run import for all saved feed URLs. Called by WP-Cron every 2 hours.
     */
    public static function sync_all(): void {
        $feeds = get_option( 'ivory_ical_import_feeds', [] );
        foreach ( $feeds as $feed ) {
            if ( ! empty( $feed['url'] ) ) {
                self::import( $feed['url'] );
            }
        }
    }

    // ─── Parser ───────────────────────────────────────────────────────────────

    /**
     * Parse a raw iCal string and return an array of date ranges.
     *
     * @return array<int, array{start: string, end: string, reason: string}>
     */
    private static function parse_ical( string $ical_content ): array {
        $ranges = [];

        // Unfold long lines (RFC 5545 §3.1).
        $content = preg_replace( '/\r\n[ \t]/', '', $ical_content );
        $content = str_replace( "\r\n", "\n", $content );

        // Match VEVENT blocks.
        preg_match_all( '/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $events );

        foreach ( $events[1] as $event ) {
            $start   = self::extract_ical_prop( $event, 'DTSTART' );
            $end     = self::extract_ical_prop( $event, 'DTEND' );
            $summary = self::extract_ical_prop( $event, 'SUMMARY' );

            if ( ! $start || ! $end ) {
                continue;
            }

            $start_date = self::parse_ical_date( $start );
            $end_date   = self::parse_ical_date( $end );

            if ( ! $start_date || ! $end_date ) {
                continue;
            }

            // Skip past events.
            if ( $end_date < wp_date( 'Y-m-d' ) ) {
                continue;
            }

            $ranges[] = [
                'start'  => $start_date,
                'end'    => $end_date,
                'reason' => $summary ?: 'External booking',
            ];
        }

        return $ranges;
    }

    /**
     * Extract a property value from a VEVENT string.
     */
    private static function extract_ical_prop( string $event, string $prop ): string {
        // Match PROP or PROP;TZID=... or PROP;VALUE=DATE:...
        if ( preg_match( '/^' . preg_quote( $prop, '/' ) . '[;:][^\r\n]*?:([^\r\n]+)/m', $event, $m ) ) {
            return trim( $m[1] );
        }
        return '';
    }

    /**
     * Convert iCal date string (YYYYMMDD or YYYYMMDDTHHMMSSZ) to Y-m-d.
     */
    private static function parse_ical_date( string $date_str ): string {
        // Date-only: 20260615
        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $date_str, $m ) ) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        // DateTime: 20260615T120000Z
        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})T/', $date_str, $m ) ) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        return '';
    }

    // ─── VEVENT Builder ───────────────────────────────────────────────────────

    /**
     * @return string[]
     */
    private static function build_vevent(
        string $checkin,
        string $checkout,
        string $uid,
        string $summary,
        string $description
    ): array {
        // DTSTAMP in WAT (Africa/Lagos, UTC+1), formatted per RFC 5545.
        $now     = wp_date( 'Ymd\THis' ) . '+0100';
        $ci_ical = str_replace( '-', '', $checkin );
        $co_ical  = str_replace( '-', '', $checkout );
        $host     = wp_parse_url( home_url(), PHP_URL_HOST );

        return [
            'BEGIN:VEVENT',
            'DTSTART;VALUE=DATE:' . $ci_ical,
            'DTEND;VALUE=DATE:' . $co_ical,
            'DTSTAMP:' . $now,
            'UID:' . $uid . '@' . $host,
            'SUMMARY:' . self::escape_ical( $summary ),
            'DESCRIPTION:' . self::escape_ical( $description ),
            'END:VEVENT',
        ];
    }

    private static function escape_ical( string $text ): string {
        return str_replace( [ '\\', ';', ',', "\n" ], [ '\\\\', '\\;', '\\,', '\\n' ], $text );
    }
}

<?php
/**
 * Paystack integration — webhook verification and transaction lookup.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ivory_Paystack {

    private const API_BASE = 'https://api.paystack.co';

    // ─── Key Management ───────────────────────────────────────────────────────

    public static function get_public_key(): string {
        return (string) get_option( 'ivory_paystack_public_key', '' );
    }

    public static function get_secret_key(): string {
        $encrypted = (string) get_option( 'ivory_paystack_secret_key_encrypted', '' );
        if ( empty( $encrypted ) ) {
            return '';
        }
        return self::decrypt( $encrypted );
    }

    public static function save_secret_key( string $raw_key ): void {
        update_option( 'ivory_paystack_secret_key_encrypted', self::encrypt( $raw_key ) );
    }

    // ─── Webhook Verification ─────────────────────────────────────────────────

    /**
     * Verify that an incoming webhook request came from Paystack.
     * Paystack signs the payload with HMAC-SHA512 using your secret key.
     *
     * @param string $raw_body   The raw request body (do NOT json_decode first).
     * @param string $signature  The value of the X-Paystack-Signature header.
     */
    public static function verify_webhook( string $raw_body, string $signature ): bool {
        $secret   = self::get_secret_key();
        $expected = hash_hmac( 'sha512', $raw_body, $secret );
        return hash_equals( $expected, $signature );
    }

    /**
     * Parse the webhook body and return the event type and data.
     *
     * @return array{event: string, data: array<string, mixed>}|null
     */
    public static function parse_webhook( string $raw_body ): ?array {
        $payload = json_decode( $raw_body, true );
        if ( ! is_array( $payload ) || empty( $payload['event'] ) ) {
            return null;
        }
        return [
            'event' => $payload['event'],
            'data'  => $payload['data'] ?? [],
        ];
    }

    // ─── Transaction Verification (Fallback) ─────────────────────────────────

    /**
     * Verify a transaction directly with the Paystack API.
     * Use this as a fallback if the webhook is not received.
     *
     * @return array<string, mixed>|null  Returns transaction data or null on failure.
     */
    public static function verify_transaction( string $paystack_reference ): ?array {
        $secret = self::get_secret_key();
        if ( empty( $secret ) ) {
            return null;
        }

        $response = wp_remote_get(
            self::API_BASE . '/transaction/verify/' . rawurlencode( $paystack_reference ),
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret,
                    'Content-Type'  => 'application/json',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['status'] ) || $body['status'] !== true ) {
            return null;
        }

        return $body['data'] ?? null;
    }

    // ─── AES-256 Encryption Helpers ───────────────────────────────────────────

    private static function encryption_key(): string {
        // Derive a 32-byte key from the WP secret key (never stored separately).
        return substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, 32 );
    }

    private static function encrypt( string $plaintext ): string {
        $iv         = random_bytes( 16 );
        $ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', self::encryption_key(), OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $ciphertext );
    }

    private static function decrypt( string $encoded ): string {
        $raw        = base64_decode( $encoded );
        $iv         = substr( $raw, 0, 16 );
        $ciphertext = substr( $raw, 16 );
        return (string) openssl_decrypt( $ciphertext, 'aes-256-cbc', self::encryption_key(), OPENSSL_RAW_DATA, $iv );
    }
}

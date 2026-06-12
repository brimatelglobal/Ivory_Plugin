<?php
/**
 * Public-facing class — shortcodes, REST API endpoints, and asset enqueuing.
 *
 * @package IvoryBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ivory_Public {

    public function __construct() {
        add_shortcode( 'ivory_booking',      [ $this, 'shortcode_booking'      ] );
        add_shortcode( 'ivory_checkout',     [ $this, 'shortcode_checkout'     ] );
        add_shortcode( 'ivory_confirmation', [ $this, 'shortcode_confirmation' ] );

        add_action( 'wp_enqueue_scripts',    [ $this, 'maybe_enqueue_assets'  ] );
        add_action( 'rest_api_init',         [ $this, 'register_rest_routes'  ] );
    }

    // ─── Asset Enqueueing ─────────────────────────────────────────────────────

    public function maybe_enqueue_assets(): void {
        // Only enqueue on pages that contain our shortcodes.
        global $post;
        if ( ! $post instanceof WP_Post ) {
            return;
        }

        $has_shortcode =
            has_shortcode( $post->post_content, 'ivory_booking'      ) ||
            has_shortcode( $post->post_content, 'ivory_checkout'     ) ||
            has_shortcode( $post->post_content, 'ivory_confirmation' );

        if ( ! $has_shortcode ) {
            return;
        }

        wp_enqueue_style(
            'ivory-booking',
            IVORY_PLUGIN_URL . 'public/assets/booking.css',
            [],
            IVORY_VERSION
        );

        // Paystack.js — loaded from Paystack's CDN.
        wp_enqueue_script(
            'paystack-js',
            'https://js.paystack.co/v2/inline.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'ivory-booking',
            IVORY_PLUGIN_URL . 'public/assets/booking.js',
            [ 'paystack-js' ],
            IVORY_VERSION,
            true
        );

        // Pass configuration to JS.
        wp_localize_script( 'ivory-booking', 'IvoryConfig', [
            'restBase'       => esc_url_raw( rest_url( 'ivory/v1/' ) ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'checkoutUrl'    => esc_url( get_permalink( get_option( 'ivory_checkout_page_id' ) ) ),
            'confirmUrl'     => esc_url( get_permalink( get_option( 'ivory_confirmation_page_id' ) ) ),
            'bookingUrl'     => esc_url( get_permalink( get_option( 'ivory_booking_page_id' ) ) ),
            'paystackKey'    => esc_js( Ivory_Paystack::get_public_key() ),
            'nightlyRate'    => (int) get_option( 'ivory_nightly_rate', 60000 ),
            'currency'       => 'NGN',
            'checkinTime'    => esc_js( get_option( 'ivory_checkin_time', '2:00 PM' ) ),
            'checkoutTime'   => esc_js( get_option( 'ivory_checkout_time', '12:00 PM' ) ),
            'i18n'           => [
                'unavailable'   => __( 'Unavailable', 'ivory-booking' ),
                'selectCheckin' => __( 'Select check-in date', 'ivory-booking' ),
                'selectCheckout'=> __( 'Select check-out date', 'ivory-booking' ),
                'nights'        => __( 'night(s)', 'ivory-booking' ),
                'total'         => __( 'Total', 'ivory-booking' ),
                'bookNow'       => __( 'Book Now', 'ivory-booking' ),
                'processing'    => __( 'Processing…', 'ivory-booking' ),
                'errorGeneric'  => __( 'Something went wrong. Please try again.', 'ivory-booking' ),
                'lockExpired'   => __( 'Your hold expired. Please select dates again.', 'ivory-booking' ),
            ],
        ] );
    }

    // ─── Shortcodes ───────────────────────────────────────────────────────────

    public function shortcode_booking(): string {
        ob_start();
        include IVORY_PLUGIN_DIR . 'public/views/booking-widget.php';
        return ob_get_clean();
    }

    public function shortcode_checkout(): string {
        ob_start();
        include IVORY_PLUGIN_DIR . 'public/views/checkout.php';
        return ob_get_clean();
    }

    public function shortcode_confirmation(): string {
        ob_start();
        include IVORY_PLUGIN_DIR . 'public/views/confirmation.php';
        return ob_get_clean();
    }

    // ─── REST API ─────────────────────────────────────────────────────────────

    public function register_rest_routes(): void {
        $namespace = 'ivory/v1';

        // GET /ivory/v1/availability
        register_rest_route( $namespace, '/availability', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_availability' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /ivory/v1/booking/init
        register_rest_route( $namespace, '/booking/init', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_init_booking' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'checkin'  => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'checkout' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // POST /ivory/v1/booking/confirm
        register_rest_route( $namespace, '/booking/confirm', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_confirm_booking' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /ivory/v1/paystack/webhook  (no nonce — verified via HMAC)
        register_rest_route( $namespace, '/paystack/webhook', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_paystack_webhook' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /ivory/v1/booking/{reference}
        register_rest_route( $namespace, '/booking/(?P<reference>[A-Z0-9\-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_get_booking' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'reference' => [ 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    // ─── REST Handlers ────────────────────────────────────────────────────────

    public function rest_availability( WP_REST_Request $request ): WP_REST_Response {
        $ranges = Ivory_Database::get_unavailable_ranges();
        return new WP_REST_Response( [ 'ranges' => $ranges ], 200 );
    }

    public function rest_init_booking( WP_REST_Request $request ): WP_REST_Response {
        if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Invalid nonce.' ], 403 );
        }

        // Accept multipart/form-data
        $body = (array) $request->get_body_params();

        // Handle government ID file upload if present.
        if ( ! empty( $_FILES['id_document'] ) ) {
            $upload = $this->handle_id_upload();
            if ( $upload ) {
                $body['id_file_path'] = $upload;
            }
        }

        $result = Ivory_Booking::init_booking( $body );

        if ( $result['success'] ) {
            Ivory_Email::send_pending_booking_alert( [
                'guest_name'    => sanitize_text_field( $body['guest_name'] ?? '' ),
                'guest_email'   => sanitize_email(      $body['email']      ?? '' ),
                'guest_phone'   => sanitize_text_field( $body['phone']      ?? '' ),
                'checkin_date'  => sanitize_text_field( $body['checkin']    ?? '' ),
                'checkout_date' => sanitize_text_field( $body['checkout']   ?? '' ),
            ] );
        }

        return new WP_REST_Response( $result, $result['success'] ? 200 : 422 );
    }

    public function rest_confirm_booking( WP_REST_Request $request ): WP_REST_Response {
        if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Invalid nonce.' ], 403 );
        }

        $json = (array) $request->get_json_params();
        $reference    = sanitize_text_field( $json['reference'] ?? '' );
        $paystack_ref = sanitize_text_field( $json['paystack_ref'] ?? '' );

        if ( empty( $reference ) || empty( $paystack_ref ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Missing payment data.' ], 400 );
        }

        $confirmed = Ivory_Booking::confirm_booking( $reference, $paystack_ref );

        if ( ! $confirmed ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Could not confirm booking.' ], 500 );
        }

        return new WP_REST_Response( [
            'success' => true,
            'status'  => 'confirmed',
            'message' => 'Booking confirmed successfully.'
        ], 200 );
    }

    public function rest_paystack_webhook( WP_REST_Request $request ): WP_REST_Response {
        $raw_body  = $request->get_body();
        $signature = $request->get_header( 'X-Paystack-Signature' );

        if ( ! Ivory_Paystack::verify_webhook( $raw_body, $signature ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid signature.' ], 401 );
        }

        $parsed = Ivory_Paystack::parse_webhook( $raw_body );
        if ( ! $parsed ) {
            return new WP_REST_Response( [ 'error' => 'Malformed payload.' ], 400 );
        }

        if ( $parsed['event'] === 'charge.success' ) {
            $data      = $parsed['data'];
            $ref       = sanitize_text_field( $data['reference']            ?? '' );
            $meta_ref  = sanitize_text_field( $data['metadata']['booking_reference'] ?? '' );

            if ( $meta_ref ) {
                Ivory_Booking::confirm_booking( $meta_ref, $ref );
            }
        }

        // Always respond 200 to Paystack.
        return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }

    public function rest_get_booking( WP_REST_Request $request ): WP_REST_Response {
        $reference = strtoupper( $request->get_param( 'reference' ) );
        $booking   = Ivory_Database::get_booking_by_reference( $reference );

        if ( ! $booking ) {
            return new WP_REST_Response( [ 'error' => 'Not found.' ], 404 );
        }

        // Strip sensitive data from public response.
        unset( $booking['id_file_path'], $booking['payment_ref'] );

        return new WP_REST_Response( $booking, 200 );
    }

    // ─── ID Upload Handler ────────────────────────────────────────────────────

    private function handle_id_upload(): string {
        // Ensure upload dir is outside web root.
        $upload_dir  = wp_upload_dir();
        $private_dir = $upload_dir['basedir'] . '/ivory-ids/';

        if ( ! file_exists( $private_dir ) ) {
            wp_mkdir_p( $private_dir );
            // Drop an .htaccess to deny direct access.
            file_put_contents( $private_dir . '.htaccess', "deny from all\n" );
        }

        $overrides = [
            'upload_dir'   => static function () use ( $private_dir, $upload_dir ) {
                return [
                    'path'    => $private_dir,
                    'url'     => $upload_dir['baseurl'] . '/ivory-ids/',
                    'subdir'  => '/ivory-ids',
                    'basedir' => $upload_dir['basedir'],
                    'baseurl' => $upload_dir['baseurl'],
                    'error'   => false,
                ];
            },
            'test_form' => false,
        ];

        $uploaded = wp_handle_upload( $_FILES['id_document'], $overrides );

        return $uploaded['file'] ?? '';
    }
}

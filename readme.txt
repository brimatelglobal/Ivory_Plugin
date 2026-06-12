=== Ivory Booking ===
Contributors: ivorybrimatel
Tags: booking, short-let, apartment, paystack, ical, availability calendar
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A bespoke booking system for The Ivory Apartment — a single-unit luxury short-let in Surulere, Lagos. Calendar-first UX, Paystack payments, iCal sync, and a full admin dashboard.

== Description ==

Ivory Booking is a fully custom, standalone WordPress plugin built specifically for single-unit short-stay properties. No page builder required. No premium dependencies.

**Key Features:**

* **Calendar-First Booking Widget** — Beautiful double-month interactive calendar showing real-time availability. Guests click check-in → checkout directly on the calendar.
* **Atomic Date Locking** — When a guest proceeds to payment, their selected dates are locked for 10 minutes using database transactions — preventing double-bookings on a single-unit property.
* **Paystack Integration** — Supports Bank Transfer, Cards (Visa/Mastercard/Verve), and USSD. Webhook verification using HMAC-SHA512.
* **Government ID Upload** — Guests are required to upload a government-issued ID at checkout, stored securely outside the public web root.
* **Admin Dashboard** — View all bookings, block/unblock dates, manage iCal feeds, and configure settings — all inside WordPress Admin.
* **iCal Sync** — Export an `.ics` calendar to paste into Airbnb/Booking.com. Import external iCal feeds, synced automatically every 2 hours via WP-Cron.
* **Email Notifications** — Premium HTML confirmation email to the guest; instant alert to the host on each booking.
* **Auto-Setup** — On activation, the plugin creates all database tables, generates the Booking/Checkout/Confirmation pages with shortcodes pre-inserted, and schedules WP-Cron events automatically.

**Shortcodes:**

* `[ivory_booking]` — Full interactive booking calendar widget
* `[ivory_checkout]` — Checkout form with guest details, ID upload, and Paystack popup
* `[ivory_confirmation]` — Booking confirmation page with receipt

== Installation ==

1. Download `ivory-booking.zip`
2. In WordPress Admin, go to **Plugins → Add New → Upload Plugin**
3. Upload `ivory-booking.zip` and click **Install Now**
4. Click **Activate Plugin**
5. Go to **Ivory Booking → Settings** and enter your Paystack API keys
6. Done — your Booking and Checkout pages have been created automatically

== Frequently Asked Questions ==

= Does this work with Elementor? =
Yes. The shortcodes work on any WordPress page, whether built with Elementor, Gutenberg, or a custom theme. The plugin does not *require* any page builder.

= Where are government ID uploads stored? =
Inside `/wp-content/uploads/ivory-ids/` with an `.htaccess` rule preventing direct browser access. Files are only accessible by the server.

= How does the Paystack secret key work? =
The secret key is encrypted using AES-256-CBC (derived from your WordPress auth salts) before being stored in `wp_options`. The raw key is never saved to the database.

= What happens if a guest closes the browser mid-payment? =
The date lock expires automatically after 10 minutes (via WP-Cron), releasing the dates for other guests to book.

= Can I add bookings manually (e.g. for phone bookings)? =
Yes — go to **Ivory Booking → All Bookings → Add Manual Booking**.

== Screenshots ==

1. Guest-facing booking calendar (dark green & gold theme)
2. Checkout form with Government ID upload
3. Booking confirmation page
4. Admin bookings list with status badges
5. Admin calendar with blocked/booked/synced dates
6. Settings page with Paystack key configuration
7. iCal Sync manager

== Changelog ==

= 1.1.0 =
* Added: Secure Government ID Viewer — admins can now click "View ID" in the booking detail panel to securely stream the uploaded identity document directly in-browser.
* Added: Interactive Admin Calendar Date Blocking — click any two dates on the admin calendar to auto-fill the block-dates form without manual typing.
* Added: Booking Status Management — Cancel and "Mark as Completed" action buttons in the booking detail panel, with automated guest notification emails on each status change.

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial stable release.

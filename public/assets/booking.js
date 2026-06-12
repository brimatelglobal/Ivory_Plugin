/**
 * Ivory Booking — Frontend JS
 * Vanilla ES6+. No jQuery. No frameworks.
 *
 * Responsibilities:
 *   1. Render the interactive double-month availability calendar
 *   2. Handle date range selection + real-time price calculation
 *   3. POST to REST API: /lock, /booking
 *   4. Launch Paystack inline popup
 *   5. Handle Paystack callback → POST booking record → redirect to confirmation
 */

/* global IvoryConfig, PaystackPop */

(function () {
  'use strict';

  const cfg = window.IvoryConfig || {};
  const API = cfg.restBase || '/wp-json/ivory/v1/';
  const NONCE = cfg.nonce || '';

  /* ── Utility ────────────────────────────────────────────────────────────── */

  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

  function formatNGN(amount) {
    return '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 0 });
  }

  function toISODate(d) {
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function parseDate(str) {
    const [y, m, d] = str.split('-').map(Number);
    return new Date(y, m - 1, d);
  }

  function daysBetween(a, b) {
    return Math.round((b - a) / 86400000);
  }

  function addMonths(date, n) {
    const d = new Date(date);
    d.setDate(1);
    d.setMonth(d.getMonth() + n);
    return d;
  }

  /* ── Availability Store ─────────────────────────────────────────────────── */

  const store = {
    unavailableRanges: [], // [{checkin, checkout, type}]
    loading: false,

    async load() {
      this.loading = true;
      try {
        const res = await fetch(API + 'availability', { credentials: 'same-origin' });
        const data = await res.json();
        this.unavailableRanges = data.ranges || [];
      } catch (_) {
        this.unavailableRanges = [];
      }
      this.loading = false;
    },

    isUnavailable(dateStr) {
      return this.unavailableRanges.some(r => dateStr >= r.checkin && dateStr < r.checkout);
    },

    typeForDate(dateStr) {
      const r = this.unavailableRanges.find(r => dateStr >= r.checkin && dateStr < r.checkout);
      return r ? r.type : null;
    },
  };

  /* ═══════════════════════════════════════════════════════════════════════════
     CALENDAR WIDGET
     ═══════════════════════════════════════════════════════════════════════════ */

  class IvoryCalendar {
    constructor(container) {
      this.container   = container;
      this.viewStart   = new Date(); this.viewStart.setDate(1);
      this.checkin     = null;
      this.checkout    = null;
      this.selecting   = false;
      this.onCheckinSelected = null;
      this.onRangeSelected   = null;

      this.render();
      this.attachNav();
    }

    /* ── Build DOM (called only on month change) ─────────────────────────── */
    render() {
      const monthA = this.viewStart;
      const monthB = addMonths(this.viewStart, 1);

      this.container.innerHTML = `
        <div class="iv-cal-nav">
          <button class="iv-cal-arrow" id="iv-prev" aria-label="Previous month">&#8249;</button>
          <div class="iv-cal-labels">
            <span class="iv-cal-month-label" id="iv-label-a"></span>
            <span class="iv-cal-month-label iv-label-secondary" id="iv-label-b"></span>
          </div>
          <button class="iv-cal-arrow" id="iv-next" aria-label="Next month">&#8250;</button>
        </div>
        <div class="iv-cal-months">
          <div class="iv-month" id="iv-month-a"></div>
          <div class="iv-month" id="iv-month-b"></div>
        </div>`;

      this._buildMonth($('#iv-month-a', this.container), monthA, $('#iv-label-a', this.container));
      this._buildMonth($('#iv-month-b', this.container), monthB, $('#iv-label-b', this.container));
    }

    _buildMonth(el, month, labelEl) {
      const MONTHS = ['January','February','March','April','May','June',
                      'July','August','September','October','November','December'];
      const DAYS   = ['Su','Mo','Tu','We','Th','Fr','Sa'];

      labelEl.textContent = `${MONTHS[month.getMonth()]} ${month.getFullYear()}`;

      const today      = new Date(); today.setHours(0,0,0,0);
      const firstDay   = new Date(month.getFullYear(), month.getMonth(), 1).getDay();
      const daysInMonth= new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();

      let html = `<div class="iv-weekdays">${DAYS.map(d => `<span>${d}</span>`).join('')}</div><div class="iv-days">`;
      for (let i = 0; i < firstDay; i++) html += `<div class="iv-day iv-day--empty"></div>`;

      for (let day = 1; day <= daysInMonth; day++) {
        const date     = new Date(month.getFullYear(), month.getMonth(), day);
        const dateStr  = toISODate(date);
        const isPast   = date < today;
        const isBooked = store.isUnavailable(dateStr);
        const type     = store.typeForDate(dateStr);

        let classes = 'iv-day';
        let tooltip = '';

        if (isPast) {
          classes += ' iv-day--past iv-day--disabled';
        } else if (isBooked) {
          classes += ' iv-day--booked'; // no iv-day--disabled — keeps opacity visible
          tooltip = type === 'synced' ? 'Synced booking' : (cfg.i18n?.unavailable || 'Unavailable');
        }

        const attrs = tooltip
          ? `data-date="${dateStr}" data-tooltip="${tooltip}"`
          : `data-date="${dateStr}"`;

        html += `<div class="${classes}" ${attrs}>${day}</div>`;
      }
      html += '</div>';
      el.innerHTML = html;

      // Attach events once — these never get torn down until month navigation
      $$('.iv-day:not(.iv-day--disabled):not(.iv-day--empty):not(.iv-day--booked)', el).forEach(cell => {
        cell.addEventListener('click',      () => this.onDayClick(cell.dataset.date));
        cell.addEventListener('mouseenter', () => this.onDayHover(cell.dataset.date));
        cell.addEventListener('mouseleave', () => this.onDayLeave());
      });
    }

    /* ── Interaction — zero DOM rebuilds ────────────────────────────────── */
    onDayClick(dateStr) {
      if (!this.selecting) {
        // ① First tap → set check-in via class only
        this.checkin   = dateStr;
        this.checkout  = null;
        this.selecting = true;
        this._clearAll();
        this._cell(dateStr)?.classList.add('iv-day--checkin');
        this.onCheckinSelected && this.onCheckinSelected(dateStr);
      } else {
        // ② Second tap → set check-out
        if (dateStr <= this.checkin) {
          // Tapped before/on check-in: restart
          this.checkin = dateStr;
          this.checkout = null;
          this._clearAll();
          this._cell(dateStr)?.classList.add('iv-day--checkin');
          this.onCheckinSelected && this.onCheckinSelected(dateStr);
          return;
        }
        if (this.rangeHasUnavailableDate(this.checkin, dateStr)) {
          this.showRangeError();
          return;
        }
        this.checkout  = dateStr;
        this.selecting = false;
        this._clearHoverPreview();
        this._applyRange();
        this.onRangeSelected && this.onRangeSelected(this.checkin, this.checkout);
      }
    }

    onDayHover(dateStr) {
      if (!this.selecting || dateStr <= this.checkin) return;
      // Update preview with CSS classes only — no DOM rebuild
      this._clearHoverPreview();
      $$('.iv-day[data-date]', this.container).forEach(cell => {
        const d = cell.dataset.date;
        if (d === dateStr)                         cell.classList.add('iv-day--hover-checkout');
        else if (d > this.checkin && d < dateStr)  cell.classList.add('iv-day--hover-in-range');
      });
    }

    onDayLeave() {
      if (this.selecting) this._clearHoverPreview();
    }

    /* ── Helpers ────────────────────────────────────────────────────────── */
    _cell(d) { return $(`.iv-day[data-date="${d}"]`, this.container); }

    _clearHoverPreview() {
      $$('.iv-day--hover-checkout, .iv-day--hover-in-range', this.container)
        .forEach(el => el.classList.remove('iv-day--hover-checkout', 'iv-day--hover-in-range'));
    }

    _clearAll() {
      $$('.iv-day--checkin,.iv-day--checkout,.iv-day--in-range,'
       + '.iv-day--hover-checkout,.iv-day--hover-in-range', this.container)
        .forEach(el => el.classList.remove(
          'iv-day--checkin','iv-day--checkout','iv-day--in-range',
          'iv-day--hover-checkout','iv-day--hover-in-range'));
    }

    _applyRange() {
      this._clearAll();
      if (!this.checkin) return;
      $$('.iv-day[data-date]', this.container).forEach(cell => {
        const d = cell.dataset.date;
        if (d === this.checkin)  cell.classList.add('iv-day--checkin');
        if (this.checkout) {
          if (d === this.checkout)                        cell.classList.add('iv-day--checkout');
          if (d > this.checkin && d < this.checkout)  cell.classList.add('iv-day--in-range');
        }
      });
    }

    rangeHasUnavailableDate(checkin, checkout) {
      return store.unavailableRanges.some(r => r.checkin < checkout && r.checkout > checkin);
    }

    showRangeError() {
      const el = $('#iv-range-error', this.container.closest('.ivory-booking-widget'));
      if (el) {
        el.textContent = 'Selected range includes unavailable dates. Please choose a different period.';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 4000);
      }
    }

    /* ── Navigation ─────────────────────────────────────────────────────── */
    attachNav() {
      this.container.addEventListener('click', e => {
        if (e.target.id === 'iv-prev') {
          const prev  = addMonths(this.viewStart, -1);
          const today = new Date(); today.setDate(1); today.setHours(0,0,0,0);
          if (prev >= today) { this.viewStart = prev; this.render(); this._applyRange(); }
        }
        if (e.target.id === 'iv-next') {
          this.viewStart = addMonths(this.viewStart, 1);
          this.render();
        }
      });
    }
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     BOOKING WIDGET INIT ([ivory_booking] shortcode page)
     ═══════════════════════════════════════════════════════════════════════════ */

  function initBookingWidget() {
    const widget = $('.ivory-booking-widget');
    if (!widget) return;

    const calOuter  = $('.ivory-calendar-outer', widget);
    const summary   = $('.ivory-price-summary',  widget);
    const bookBtn   = $('.ivory-book-btn',        widget);
    const guestBtns = $$('.iv-guest-btn',         widget);

    let selectedGuests = 2;
    let cal = null; // declared here so guest-toggle closure can access it

    // Guest toggle
    guestBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        guestBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedGuests = parseInt(btn.dataset.guests, 10);
        if (cal) updateSummary(cal.checkin, cal.checkout);
      });
    });

    // Load availability then render calendar
    store.load().then(() => {
      cal = new IvoryCalendar(calOuter); // assign to outer variable

      cal.onCheckinSelected = (checkin) => {
        // Prompt guest to now pick their checkout date
        const fmt = dt => parseDate(dt).toLocaleDateString('en-NG', { weekday: 'short', day: 'numeric', month: 'short' });
        summary.innerHTML = `
          <span class="iv-summary-placeholder" style="display:flex;align-items:center;gap:10px;">
            <span style="font-weight:600;color:hsl(30,35%,22%);">✓ Check-in: ${fmt(checkin)}</span>
            <span style="color:hsl(30,20%,52%);">— Now select your check-out date</span>
          </span>`;
        bookBtn && (bookBtn.disabled = true);
      };

      cal.onRangeSelected = (checkin, checkout) => {
        updateSummary(checkin, checkout);
        bookBtn.disabled = false;
      };

      function updateSummary(checkin, checkout) {
        if (!checkin || !checkout) {
          summary.innerHTML = `<span class="iv-summary-placeholder">${cfg.i18n?.selectCheckin || 'Select check-in and check-out dates'}</span>`;
          bookBtn && (bookBtn.disabled = true);
          return;
        }
        const nights = daysBetween(parseDate(checkin), parseDate(checkout));
        const rate   = cfg.nightlyRate || 60000;
        const total  = nights * rate;

        const fmt = dt => parseDate(dt).toLocaleDateString('en-NG', { day:'numeric', month:'short', year:'numeric' });

        summary.innerHTML = `
          <div class="iv-summary-details">
            <span class="iv-summary-item"><strong>${fmt(checkin)}</strong> → <strong>${fmt(checkout)}</strong></span>
            <span class="iv-summary-item">${nights} ${cfg.i18n?.nights || 'night(s)'} × ${formatNGN(rate)}</span>
          </div>
          <span class="iv-summary-total">${formatNGN(total)}</span>`;
      }

      // "Book Now" navigates to checkout page with params
      bookBtn && bookBtn.addEventListener('click', () => {
        if (!cal.checkin || !cal.checkout) return;
        const url = new URL(cfg.checkoutUrl || '/ivory-checkout');
        url.searchParams.set('checkin',  cal.checkin);
        url.searchParams.set('checkout', cal.checkout);
        url.searchParams.set('guests',   selectedGuests);
        window.location.href = url.toString();
      });

      // Initial state
      summary.innerHTML = `<span class="iv-summary-placeholder">${cfg.i18n?.selectCheckin || 'Select your check-in and check-out dates above'}</span>`;
      bookBtn && (bookBtn.disabled = true);
    });
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     CHECKOUT PAGE ([ivory_checkout] shortcode page)
     ═══════════════════════════════════════════════════════════════════════════ */

  function initCheckoutPage() {
    const form = $('.ivory-checkout-form');
    if (!form) return;

    const params    = new URLSearchParams(window.location.search);
    let   checkin   = params.get('checkin')  || '';
    let   checkout  = params.get('checkout') || '';
    const guests    = parseInt(params.get('guests') || '2', 10);

    // Redirect home if no dates provided
    if (!checkin || !checkout) { window.location.href = cfg.bookingUrl || '/'; return; }

    const rate    = cfg.nightlyRate || 60000;
    let   nights  = daysBetween(parseDate(checkin), parseDate(checkout));
    let   total   = nights * rate;

    const fmt = dt => dt ? parseDate(dt).toLocaleDateString('en-NG', { weekday:'short', day:'numeric', month:'long', year:'numeric' }) : '—';

    // ── Wire up editable date inputs ──────────────────────────────────────
    const ciInput = document.getElementById('iv-date-checkin');
    const coInput = document.getElementById('iv-date-checkout');

    if (ciInput) ciInput.value = checkin;
    if (coInput) {
      coInput.value = checkout;
      coInput.min   = checkin; // checkout must be after checkin
    }

    function updateDateTexts() {
      const sumPanel = $('.ivory-checkout-summary');
      if (sumPanel) {
        $('#iv-sum-checkin-text',  sumPanel) && ($('#iv-sum-checkin-text',  sumPanel).textContent = fmt(checkin));
        $('#iv-sum-checkout-text', sumPanel) && ($('#iv-sum-checkout-text', sumPanel).textContent = fmt(checkout));
      }
    }

    function recalcSummary() {
      if (!checkin || !checkout || checkout <= checkin) return;
      nights = daysBetween(parseDate(checkin), parseDate(checkout));
      if (nights < 1) return;
      total  = nights * rate;
      const sumPanel = $('.ivory-checkout-summary');
      if (sumPanel) {
        $('#iv-sum-nights', sumPanel) && ($('#iv-sum-nights', sumPanel).textContent = nights);
        $('#iv-sum-total',  sumPanel) && ($('#iv-sum-total',  sumPanel).textContent = formatNGN(total));
      }
    }

    ciInput && ciInput.addEventListener('change', () => {
      checkin = ciInput.value;
      if (coInput) {
        coInput.min = checkin;
        // Auto-clear checkout if it's now before or equal to new checkin
        if (coInput.value && coInput.value <= checkin) {
          coInput.value = '';
          checkout = '';
        }
      }
      updateDateTexts();
      recalcSummary();
    });

    coInput && coInput.addEventListener('change', () => {
      checkout = coInput.value;
      // Update checkout-min in case checkin changed first
      if (ciInput && checkout <= ciInput.value) {
        coInput.value = '';
        checkout = '';
        updateDateTexts();
        return;
      }
      updateDateTexts();
      recalcSummary();
    });

    // Populate summary panel
    updateDateTexts();
    const sumPanel = $('.ivory-checkout-summary');
    if (sumPanel) {
      $('#iv-sum-nights',   sumPanel) && ($('#iv-sum-nights',   sumPanel).textContent = nights);
      $('#iv-sum-guests',   sumPanel) && ($('#iv-sum-guests',   sumPanel).textContent = guests);
      $('#iv-sum-rate',     sumPanel) && ($('#iv-sum-rate',     sumPanel).textContent = formatNGN(rate));
      $('#iv-sum-total',    sumPanel) && ($('#iv-sum-total',    sumPanel).textContent = formatNGN(total));
    }

    // File upload label
    const fileInput = $('input[name="id_document"]', form);
    const fileName  = $('.iv-file-name', form);
    if (fileInput && fileName) {
      fileInput.addEventListener('change', () => {
        fileName.textContent = fileInput.files[0]?.name || '';
      });
    }

    // Form submission
    const proceedBtn = $('.ivory-proceed-btn', form);
    const errorBox   = $('.iv-form-error', form);

    proceedBtn && proceedBtn.addEventListener('click', async () => {
      if (!validateForm(form)) return;

      setLoading(proceedBtn, true);
      hideError(errorBox);

      try {
        // 1. Lock the dates — also send guest details so admin gets a heads-up email
        const lockRes = await apiFetch('lock', 'POST', {
          checkin,
          checkout,
          name:  $('input[name="guest_name"]', form)?.value.trim(),
          email: $('input[name="email"]',       form)?.value.trim(),
          phone: $('input[name="phone"]',        form)?.value.trim(),
        });
        if (!lockRes.success) {
          showError(errorBox, lockRes.message || cfg.i18n?.errorGeneric);
          setLoading(proceedBtn, false);
          return;
        }

        const sessionToken = lockRes.token;

        // 2. Launch Paystack
        const handler = PaystackPop.setup({
          key:       cfg.paystackKey,
          email:     $('input[name="email"]', form).value.trim(),
          amount:    total * 100, // Paystack expects kobo
          currency:  'NGN',
          ref:       `IVORY-${Date.now()}`,
          metadata: {
            booking_reference_pending: true,
            checkin,
            checkout,
            guests,
            session_token: sessionToken,
          },
          onClose: () => {
            setLoading(proceedBtn, false);
            showError(errorBox, 'Payment was cancelled. Your date hold is still active for a few minutes.');
          },
          callback: async (response) => {
            // 3. Build booking payload and create record
            const rawData = gatherFormData(form, checkin, checkout, guests, sessionToken);
            rawData.paystack_ref = response.reference; // pass Paystack transaction ref

            let bookRes;
            const fileInput = form.querySelector('input[name="id_document"]');
            const hasFile   = fileInput && fileInput.files && fileInput.files.length > 0;

            if (hasFile) {
              // Multipart so the file travels with the rest of the data
              const fd = new FormData();
              Object.entries(rawData).forEach(([k, v]) => fd.append(k, v));
              fd.append('id_document', fileInput.files[0]);
              bookRes = await fetch(API + 'booking', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': NONCE }, // no Content-Type — let browser set boundary
                body: fd,
              }).then(r => r.json());
            } else {
              bookRes = await apiFetch('booking', 'POST', rawData);
            }

            if (bookRes.success) {
              window.location.href = `${cfg.confirmUrl}?ref=${bookRes.reference}&pref=${response.reference}`;
            } else {
              showError(errorBox, bookRes.message || cfg.i18n?.errorGeneric);
              setLoading(proceedBtn, false);
            }
          },
        });

        handler.openIframe();

      } catch (err) {
        showError(errorBox, cfg.i18n?.errorGeneric || 'Something went wrong.');
        setLoading(proceedBtn, false);
      }
    });
  }

  function gatherFormData(form, checkin, checkout, guests, sessionToken) {
    return {
      name:              $('input[name="guest_name"]',       form)?.value.trim(),
      email:             $('input[name="email"]',             form)?.value.trim(),
      phone:             $('input[name="phone"]',             form)?.value.trim(),
      address:           $('input[name="address"]',        form)?.value.trim(),
      occupation:        $('input[name="occupation"]',        form)?.value.trim(),
      next_of_kin:       $('input[name="next_of_kin"]',       form)?.value.trim(),
      next_of_kin_phone: $('input[name="next_of_kin_phone"]', form)?.value.trim(),
      booking_reason:    $('input[name="booking_reason"]', form)?.value.trim(),
      special_req:       $('input[name="special_req"]',    form)?.value.trim(),
      checkin,
      checkout,
      guests,
      session_token: sessionToken,
    };
  }

  function validateForm(form) {
    let valid = true;
    $$('input[required], textarea[required]', form).forEach(field => {
      field.classList.remove('error');
      if (!field.value.trim()) {
        field.classList.add('error');
        valid = false;
      }
    });

    const email = $('input[name="email"]', form);
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
      email.classList.add('error');
      valid = false;
    }

    return valid;
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     CONFIRMATION PAGE ([ivory_confirmation] shortcode page)
     ═══════════════════════════════════════════════════════════════════════════ */

  function initConfirmationPage() {
    const wrap = $('.ivory-confirmation-wrap');
    if (!wrap) return;

    const params    = new URLSearchParams(window.location.search);
    const reference = params.get('ref') || '';

    if (!reference) return;

    // Fetch booking details from REST
    fetch(`${API}booking/${encodeURIComponent(reference)}`, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(booking => {
        if (booking.error) return;

        const fmt = dt => parseDate(dt).toLocaleDateString('en-NG', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
        const set = (id, val) => { const el = $(`#${id}`, wrap); if (el) el.textContent = val; };

        set('iv-conf-ref',      booking.reference);
        set('iv-conf-name',     booking.guest_name);
        set('iv-conf-checkin',  fmt(booking.checkin_date));
        set('iv-conf-checkout', fmt(booking.checkout_date));
        set('iv-conf-nights',   booking.nights + ' night' + (booking.nights > 1 ? 's' : ''));
        set('iv-conf-guests',   booking.guests);
        set('iv-conf-total',    formatNGN(booking.total_amount));

        // Also set the reference badge
        $$('.iv-ref-badge', wrap).forEach(el => el.textContent = booking.reference);
      })
      .catch(() => {});

    // Print button
    const printBtn = $('.iv-print-btn', wrap);
    printBtn && printBtn.addEventListener('click', () => window.print());
  }

  /* ── API helper ─────────────────────────────────────────────────────────── */

  async function apiFetch(endpoint, method = 'GET', body = null) {
    const opts = {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce':   NONCE,
      },
    };
    if (body) opts.body = JSON.stringify(body);

    const res  = await fetch(API + endpoint, opts);
    return res.json();
  }

  /* ── UI helpers ─────────────────────────────────────────────────────────── */

  function setLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;
    btn.innerHTML = loading
      ? `<span class="iv-spinner"></span>${cfg.i18n?.processing || 'Processing…'}`
      : (btn.dataset.originalLabel || btn.textContent);
    if (!loading && btn.dataset.originalLabel) btn.textContent = btn.dataset.originalLabel;
  }

  function showError(el, msg) { if (el) { el.textContent = msg; el.classList.add('visible'); } }
  function hideError(el)      { if (el) el.classList.remove('visible'); }

  /* ── Boot ───────────────────────────────────────────────────────────────── */

  document.addEventListener('DOMContentLoaded', () => {
    initBookingWidget();
    initCheckoutPage();
    initConfirmationPage();
  });

})();

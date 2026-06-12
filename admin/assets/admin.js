/* global IvoryAdmin */
(function () {
  'use strict';

  const cfg   = window.IvoryAdmin || {};
  const ajax  = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';
  const nonce = cfg.nonce   || '';

  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

  /* ── AJAX helper ──────────────────────────────────────────────────────── */
  async function doAjax(action, data = {}) {
    const body = new URLSearchParams({ action, nonce, ...data });
    const res  = await fetch(ajax, { method: 'POST', body });
    return res.json();
  }

  /* ─────────────────────────────────────────────────────────────────────────
     ADMIN CALENDAR
     ─────────────────────────────────────────────────────────────────────── */
  function initAdminCalendar() {
    const calEl = document.getElementById('iv-admin-calendar');
    if (!calEl) return;

    let ranges = [];
    try { ranges = JSON.parse(calEl.dataset.ranges || '[]'); } catch (_) {}

    const MONTHS  = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];
    const DAYS    = ['Su','Mo','Tu','We','Th','Fr','Sa'];

    let viewDate = new Date(); viewDate.setDate(1);

    function addMonths(d, n) {
      const copy = new Date(d); copy.setMonth(copy.getMonth() + n); return copy;
    }

    function isUnavailable(str) {
      return ranges.find(r => str >= r.checkin && str < r.checkout) || null;
    }

    function renderMonth(month) {
      const today      = new Date(); today.setHours(0,0,0,0);
      const firstDay   = new Date(month.getFullYear(), month.getMonth(), 1).getDay();
      const daysInMonth= new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
      const pad        = n => String(n).padStart(2,'0');
      const toStr      = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

      let html = `<div class="iv-weekdays">${DAYS.map(d=>`<span>${d}</span>`).join('')}</div><div class="iv-days">`;
      for (let i = 0; i < firstDay; i++) html += `<div class="iv-day iv-day--empty"></div>`;

      for (let day = 1; day <= daysInMonth; day++) {
        const date    = new Date(month.getFullYear(), month.getMonth(), day);
        const dateStr = toStr(date);
        const isPast  = date < today;
        const isToday = date.toDateString() === today.toDateString();
        const conflict= isUnavailable(dateStr);

        let cls = 'iv-day';
        if (isPast)   cls += ' iv-day--past iv-day--disabled';
        if (isToday)  cls += ' iv-day--today';
        if (conflict) {
          const type = conflict.type;
          cls += type === 'booked' ? ' iv-day--booked' : type === 'synced' ? ' iv-day--synced' : ' iv-day--blocked';
        }
        html += `<div class="${cls}" title="${dateStr}">${day}</div>`;
      }
      html += '</div>';
      return html;
    }

    function render() {
      const monthB = addMonths(viewDate, 1);
      calEl.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
          <button id="iv-adm-prev" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:6px;width:32px;height:32px;cursor:pointer;font-size:18px;">&#8249;</button>
          <div style="display:flex;gap:120px;">
            <span style="color:#fff;font-size:16px;font-weight:600;">${MONTHS[viewDate.getMonth()]} ${viewDate.getFullYear()}</span>
            <span style="color:#fff;font-size:16px;font-weight:600;">${MONTHS[monthB.getMonth()]} ${monthB.getFullYear()}</span>
          </div>
          <button id="iv-adm-next" style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:6px;width:32px;height:32px;cursor:pointer;font-size:18px;">&#8250;</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;">
          <div>${renderMonth(viewDate)}</div>
          <div>${renderMonth(monthB)}</div>
        </div>`;

      // ── Interactive date selection for block form ──────────────────────────
      let selectStart = null;

      $$('.iv-day:not(.iv-day--disabled):not(.iv-day--empty)', calEl).forEach(cell => {
        cell.style.cursor = 'pointer';

        cell.addEventListener('click', () => {
          const dateStr = cell.title;
          if (!selectStart) {
            // First click: set the start date
            selectStart = dateStr;
            $$('.iv-day--select-start, .iv-day--select-preview', calEl)
              .forEach(el => el.classList.remove('iv-day--select-start', 'iv-day--select-preview'));
            cell.classList.add('iv-day--select-start');

            const startInput = document.getElementById('iv-block-start');
            const endInput   = document.getElementById('iv-block-end');
            if (startInput) startInput.value = dateStr;
            if (endInput)   endInput.value   = '';
          } else {
            // Second click: must be after start
            if (dateStr <= selectStart) {
              // Restart selection
              selectStart = dateStr;
              $$('.iv-day--select-start, .iv-day--select-preview', calEl)
                .forEach(el => el.classList.remove('iv-day--select-start', 'iv-day--select-preview'));
              cell.classList.add('iv-day--select-start');
              const startInput = document.getElementById('iv-block-start');
              if (startInput) startInput.value = dateStr;
              return;
            }

            // Apply range preview
            $$('.iv-day[title]', calEl).forEach(c => {
              const d = c.title;
              c.classList.remove('iv-day--select-start', 'iv-day--select-preview');
              if (d === selectStart || d === dateStr) c.classList.add('iv-day--select-start');
              else if (d > selectStart && d < dateStr)  c.classList.add('iv-day--select-preview');
            });

            const startInput = document.getElementById('iv-block-start');
            const endInput   = document.getElementById('iv-block-end');
            if (startInput) startInput.value = selectStart;
            if (endInput)   endInput.value   = dateStr;

            selectStart = null; // reset for next selection
          }
        });

        cell.addEventListener('mouseenter', () => {
          if (!selectStart) return;
          const dateStr = cell.title;
          $$('.iv-day--select-preview', calEl)
            .forEach(el => el.classList.remove('iv-day--select-preview'));
          if (dateStr > selectStart) {
            $$('.iv-day[title]', calEl).forEach(c => {
              const d = c.title;
              if (d > selectStart && d <= dateStr) c.classList.add('iv-day--select-preview');
            });
          }
        });
      });
      // ─────────────────────────────────────────────────────────────────────

      $('#iv-adm-prev').addEventListener('click', () => { viewDate = addMonths(viewDate,-1); render(); });
      $('#iv-adm-next').addEventListener('click', () => { viewDate = addMonths(viewDate, 1); render(); });
    }

    render();
  }

  /* ─────────────────────────────────────────────────────────────────────────
     BLOCK DATES
     ─────────────────────────────────────────────────────────────────────── */
  function initBlockDates() {
    const btn = document.getElementById('iv-block-submit');
    if (!btn) return;

    btn.addEventListener('click', async () => {
      const start  = document.getElementById('iv-block-start')?.value  || '';
      const end    = document.getElementById('iv-block-end')?.value    || '';
      const reason = document.getElementById('iv-block-reason')?.value || '';
      const result = document.getElementById('iv-block-result');

      if (!start || !end) {
        result.textContent = 'Please fill in both dates.';
        result.style.color = '#c62828';
        return;
      }

      btn.disabled = true; btn.textContent = 'Blocking…';
      const res = await doAjax('ivory_block_dates', { start, end, reason });
      btn.disabled = false; btn.textContent = 'Block Dates';

      result.textContent = res.success ? '✅ Dates blocked!' : '❌ ' + (res.data || 'Error.');
      result.style.color = res.success ? '#1e7e34' : '#c62828';

      if (res.success) {
        setTimeout(() => location.reload(), 1200);
      }
    });

    // Unblock buttons
    $$('.iv-unblock-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Remove this block?')) return;
        const id  = btn.dataset.id;
        const res = await doAjax('ivory_unblock_dates', { block_id: id });
        if (res.success) btn.closest('li')?.remove();
      });
    });
  }

  /* ─────────────────────────────────────────────────────────────────────────
     iCal Sync
     ─────────────────────────────────────────────────────────────────────── */
  function initIcal() {
    // Add feed
    const addBtn = document.getElementById('iv-ical-add');
    const addResult = document.getElementById('iv-ical-add-result');

    addBtn && addBtn.addEventListener('click', async () => {
      const url   = document.getElementById('iv-ical-url')?.value.trim() || '';
      const label = document.getElementById('iv-ical-label')?.value.trim() || '';
      if (!url) { addResult.textContent = 'Please enter a URL.'; addResult.style.color = '#c62828'; return; }

      addBtn.disabled = true; addBtn.textContent = 'Adding…';
      const res = await doAjax('ivory_add_ical_feed', { url, label });
      addBtn.disabled = false; addBtn.textContent = 'Add & Sync Now';

      addResult.textContent = res.success ? '✅ Feed added and synced!' : '❌ ' + (res.data || 'Error.');
      addResult.style.color = res.success ? '#1e7e34' : '#c62828';
      if (res.success) setTimeout(() => location.reload(), 1500);
    });

    // Sync individual feed
    $$('.iv-ical-sync-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const url = btn.dataset.url;
        btn.disabled = true; btn.textContent = 'Syncing…';
        const res = await doAjax('ivory_sync_ical_feed', { url });
        btn.disabled = false; btn.textContent = '↻ Sync Now';
        alert(res.success ? `✅ Synced ${res.data?.count || 0} ranges.` : '❌ ' + (res.data || 'Error.'));
        if (res.success) location.reload();
      });
    });

    // Remove feed
    $$('.iv-ical-remove-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Remove this feed? Its synced blocks will also be removed.')) return;
        const url = btn.dataset.url;
        const res = await doAjax('ivory_remove_ical_feed', { url });
        if (res.success) btn.closest('tr')?.remove();
      });
    });
  }

  /* ─────────────────────────────────────────────────────────────────────────
     MANUAL BOOKING MODAL
     ─────────────────────────────────────────────────────────────────────── */
  function initManualBooking() {
    const trigger  = document.getElementById('iv-add-booking-trigger');
    const modal    = document.getElementById('iv-manual-modal');
    const closeBtn = document.getElementById('iv-modal-close');
    const submit   = document.getElementById('iv-m-submit');
    const result   = document.getElementById('iv-m-result');

    if (!trigger || !modal) return;

    trigger.addEventListener('click', e => { e.preventDefault(); modal.style.display = 'flex'; });
    closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
    modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

    submit.addEventListener('click', async () => {
      const data = {
        name:    document.getElementById('iv-m-name')?.value    || '',
        email:   document.getElementById('iv-m-email')?.value   || '',
        phone:   document.getElementById('iv-m-phone')?.value   || '',
        checkin: document.getElementById('iv-m-checkin')?.value || '',
        checkout:document.getElementById('iv-m-checkout')?.value|| '',
        guests:  document.getElementById('iv-m-guests')?.value  || 2,
      };

      if (!data.name || !data.checkin || !data.checkout) {
        result.textContent = 'Please fill in all required fields.';
        result.style.color = '#c62828';
        return;
      }

      submit.disabled = true; submit.textContent = 'Creating…';
      const res = await doAjax('ivory_manual_booking', data);
      submit.disabled = false; submit.textContent = 'Create Booking';

      if (res.success) {
        result.textContent = `✅ Booking created: ${res.data?.reference}`;
        result.style.color = '#1e7e34';
        setTimeout(() => location.reload(), 1500);
      } else {
        result.textContent = '❌ ' + (res.data || 'Could not create booking. Check for date conflicts.');
        result.style.color = '#c62828';
      }
    });
  }

  /* ─────────────────────────────────────────────────────────────────────────
     BOOKING STATUS ACTIONS (Cancel / Complete)
     ─────────────────────────────────────────────────────────────────────── */
  function initBookingStatusActions() {
    $$('.iv-status-action-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const reference = btn.dataset.reference;
        const status    = btn.dataset.status;
        const label     = status === 'cancelled' ? 'cancel' : 'mark as completed';

        if (!confirm(`Are you sure you want to ${label} booking ${reference}? A notification email will be sent to the guest.`)) return;

        btn.disabled = true;
        btn.textContent = 'Updating…';

        const res = await doAjax('ivory_update_booking_status', { reference, status });

        const feedback = document.getElementById('iv-status-feedback');

        if (res.success) {
          // Update the status badge in the detail panel live.
          const badge = document.querySelector('.iv-detail-value .iv-status-badge');
          if (badge) {
            badge.textContent = res.data.label;
            badge.className   = `iv-status-badge iv-status-${res.data.new_status}`;
          }

          // Hide all action buttons (status is now final).
          $$('.iv-status-action-btn').forEach(b => b.remove());

          if (feedback) {
            feedback.textContent = `✅ Booking ${reference} has been ${res.data.new_status}. Guest notified by email.`;
            feedback.style.display = 'block';
            feedback.style.color   = '#1e7e34';
          }
        } else {
          btn.disabled    = false;
          btn.textContent = status === 'cancelled' ? '🚫 Cancel Booking' : '✅ Mark as Completed';

          if (feedback) {
            feedback.textContent = '❌ ' + (res.data || 'Could not update booking status.');
            feedback.style.display = 'block';
            feedback.style.color   = '#c62828';
          }
        }
      });
    });
  }

  /* ── Boot ─────────────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    initAdminCalendar();
    initBlockDates();
    initIcal();
    initManualBooking();
    initBookingStatusActions();
  });
})();

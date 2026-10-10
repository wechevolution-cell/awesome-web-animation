/* Storefront enhancements. Every feature degrades to plain forms/links without JS. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const body = document.body;
  const base = body.dataset.base || '';
  const csrf = body.dataset.csrf;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Dialog helpers (native <dialog>, light-dismiss fallback where closedby is unsupported) */
  const lightDismissFallback = (dlg) => {
    if ('closedBy' in HTMLDialogElement.prototype) return;
    dlg.addEventListener('click', (e) => {
      if (e.target !== dlg) return;
      const r = dlg.getBoundingClientRect();
      const inside = r.top <= e.clientY && e.clientY <= r.bottom && r.left <= e.clientX && e.clientX <= r.right;
      const panel = dlg.firstElementChild?.getBoundingClientRect();
      const inPanel = panel && panel.top <= e.clientY && e.clientY <= panel.bottom && panel.left <= e.clientX && e.clientX <= panel.right;
      if (!inside || !inPanel) dlg.close();
    });
  };
  $$('dialog').forEach((d) => { lightDismissFallback(d); $$('[data-dialog-close]', d).forEach((b) => b.addEventListener('click', () => d.close())); });

  /* Toast */
  const toast = (msg, err = false) => {
    const t = $('#toast'); if (!t) return;
    t.textContent = msg; t.classList.toggle('is-error', err); t.hidden = false;
    clearTimeout(t._h); t._h = setTimeout(() => (t.hidden = true), 2800);
  };

  /* Mobile menu */
  const mnav = $('#mnav');
  $$('[data-menu-toggle]').forEach((b) => b.addEventListener('click', () => {
    if (!mnav) return;
    mnav.open ? mnav.close() : mnav.showModal();
    $$('[data-menu-toggle]').forEach((x) => x.setAttribute('aria-expanded', String(mnav.open)));
  }));
  mnav?.addEventListener('close', () => $$('[data-menu-toggle]').forEach((x) => x.setAttribute('aria-expanded', 'false')));

  /* Fetch helper */
  const post = async (url, data) => {
    const fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) Object.entries(data || {}).forEach(([k, v]) => fd.append(k, v));
    if (!fd.has('_csrf')) fd.append('_csrf', csrf);
    const r = await fetch(url, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
    const j = await r.json().catch(() => ({ ok: false, error: 'Error' }));
    if (!r.ok) j.ok = false;
    return j;
  };

  /* Cart drawer */
  const drawer = $('#drawer');
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const renderCart = (c) => {
    $$('[data-cart-count]').forEach((el) => { el.textContent = c.count; el.hidden = !c.count; });
    if (!drawer) return;
    const bodyEl = $('[data-drawer-body]', drawer);
    $('[data-drawer-subtotal]', drawer).textContent = c.subtotal;
    bodyEl.innerHTML = c.lines.length ? c.lines.map((l) => `
      <div class="dl-line"><img src="${esc(l.image)}" alt="" width="64" height="64" loading="lazy">
        <div><a href="${esc(l.url)}">${esc(l.name)}</a>${l.variant ? `<small>${esc(l.variant)}</small>` : ''}
          <div class="dl-qty"><button type="button" data-q="${esc(l.key)}" data-d="-1" aria-label="−">−</button><span aria-live="polite">${l.qty}</span><button type="button" data-q="${esc(l.key)}" data-d="1" aria-label="+">+</button></div></div>
        <strong>${esc(l.total)}</strong></div>`).join('') : `<p class="muted">${esc(drawer.dataset.empty || bodyEl.textContent.trim() || '')}</p>`;
    drawer.dataset.empty ??= bodyEl.textContent.trim();
    $$('[data-q]', bodyEl).forEach((b) => b.addEventListener('click', async () => {
      const line = c.lines.find((x) => x.key === b.dataset.q);
      const q = Math.max(0, line.qty + Number(b.dataset.d));
      const res = await post(base + '/cart/update/', { [`qty[${b.dataset.q}]`]: q });
      if (res.ok) renderCart(res);
    }));
  };
  const openDrawer = async () => {
    if (!drawer) return false;
    const r = await fetch(base + '/api/cart/', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (r.ok) renderCart(await r.json());
    drawer.showModal();
    return true;
  };
  $$('[data-drawer-open]').forEach((a) => a.addEventListener('click', (e) => { if (drawer && !location.pathname.endsWith('/cart/')) { e.preventDefault(); openDrawer(); } }));

  /* Add to cart (AJAX) */
  $$('form[data-add-to-cart]').forEach((f) => f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = $('button[type=submit]', f); btn && (btn.disabled = true);
    try {
      const res = await post(f.action, new FormData(f));
      if (res.ok) { renderCart(res); toast(res.message || '✓'); if (f.classList.contains('buy-form')) openDrawer(); }
      else toast(res.error || 'Error', true);
    } catch { f.submit(); }
    btn && (btn.disabled = false);
  }));

  /* Wishlist toggle */
  $$('form[data-wishlist]').forEach((f) => f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await post(f.action, new FormData(f));
    if (!res.ok) return toast(res.error || 'Error', true);
    const b = $('button', f); b.classList.toggle('is-on', res.in); b.setAttribute('aria-pressed', String(res.in));
  }));

  /* Newsletter / popup subscribe */
  $$('form[data-ajax-subscribe]').forEach((f) => f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await post(f.action, new FormData(f));
    const m = $('[data-form-msg]', f);
    if (m) m.textContent = res.ok ? res.message : res.error;
    if (res.ok) { f.reset(); try { localStorage.setItem('popup_done', '1'); } catch {} setTimeout(() => $('#popup')?.close(), 1600); }
  }));

  /* Popup (once per session/visitor) */
  const popup = $('#popup');
  if (popup && body.classList.contains('is-home')) {
    let seen = false; try { seen = sessionStorage.getItem('popup_seen') || localStorage.getItem('popup_done'); } catch {}
    if (!seen) setTimeout(() => { if (!document.querySelector('dialog[open]')) { popup.showModal(); try { sessionStorage.setItem('popup_seen', '1'); } catch {} } }, (Number(popup.dataset.delay) || 5) * 1000);
  }

  /* Variant selector: live price/stock */
  $$('[data-variants]').forEach((f) => {
    const sel = $('[data-variant-select]', f); if (!sel) return;
    const data = JSON.parse(f.dataset.variants || '[]');
    sel.addEventListener('change', () => {
      const v = data.find((x) => String(x.id) === sel.value);
      if (v) { const p = $('[data-price]'); if (p) p.textContent = v.price; }
    });
  });

  /* Quantity steppers */
  $$('[data-qty]').forEach((q) => {
    const i = $('input', q);
    $('[data-qty-dec]', q)?.addEventListener('click', () => { i.value = Math.max(1, (+i.value || 1) - 1); });
    $('[data-qty-inc]', q)?.addEventListener('click', () => { i.value = Math.min(99, (+i.value || 1) + 1); });
  });

  /* Gallery: thumbnails, keyboard arrows, zoom dialog */
  $$('[data-gallery]').forEach((g) => {
    const slides = $$('[data-slide]', g), thumbs = $$('[data-thumb]', g);
    const show = (n) => { slides.forEach((s, i) => s.classList.toggle('is-on', i === n)); thumbs.forEach((t, i) => t.classList.toggle('is-on', i === n)); };
    thumbs.forEach((t) => t.addEventListener('click', () => show(+t.dataset.thumb)));
    g.addEventListener('keydown', (e) => {
      const cur = slides.findIndex((s) => s.classList.contains('is-on'));
      if (e.key === 'ArrowRight') show((cur + 1) % slides.length);
      if (e.key === 'ArrowLeft') show((cur - 1 + slides.length) % slides.length);
    });
    $$('[data-zoom]', g).forEach((a) => a.addEventListener('click', (e) => {
      e.preventDefault();
      const d = document.createElement('dialog'); d.className = 'zoom'; d.setAttribute('closedby', 'any'); d.setAttribute('aria-label', 'Zoom');
      d.innerHTML = `<img src="${esc(a.href)}" alt="">`;
      d.addEventListener('click', () => d.close()); d.addEventListener('close', () => d.remove());
      document.body.append(d); d.showModal();
    }));
  });

  /* Sticky buy bar (product page) */
  const sticky = $('[data-sticky-buy]'), buy = $('.buy-form');
  if (sticky && buy && 'IntersectionObserver' in window) {
    new IntersectionObserver(([en]) => { sticky.hidden = en.isIntersecting || en.boundingClientRect.top > 0; }).observe(buy);
    $('[data-scroll-buy]', sticky)?.addEventListener('click', (e) => { e.preventDefault(); buy.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' }); $('button[type=submit]', buy)?.focus({ preventScroll: true }); });
  }

  /* Listing: filter toggle, autosubmit sort, grid/list view */
  $$('[data-toggle]').forEach((b) => b.addEventListener('click', () => { const t = $(b.dataset.toggle); if (!t) return; const open = t.classList.toggle('is-open'); b.setAttribute('aria-expanded', String(open)); }));
  $$('form[data-autosubmit] select').forEach((s) => s.addEventListener('change', () => s.form.submit()));
  const grid = $('[data-grid]');
  const setView = (v) => { if (!grid) return; grid.classList.toggle('is-list', v === 'list'); $$('[data-view]').forEach((b) => { const on = b.dataset.view === v; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', String(on)); }); try { localStorage.setItem('view', v); } catch {} };
  $$('[data-view]').forEach((b) => b.addEventListener('click', () => setView(b.dataset.view)));
  try { if (localStorage.getItem('view') === 'list') setView('list'); } catch {}

  /* Checkout: live totals */
  const totals = $('[data-totals]');
  if (totals) {
    const loc = totals.dataset.locale, sub = +totals.dataset.subtotal;
    const fmt = (c) => loc === 'el' ? (c / 100).toLocaleString('el-GR', { minimumFractionDigits: 2 }) + ' €' : '€' + (c / 100).toLocaleString('en-GB', { minimumFractionDigits: 2 });
    const upd = () => {
      const s = $('input[name=shipping_method]:checked'), p = $('input[name=payment_method]:checked');
      const sc = +(s?.dataset.shipCost || 0), pf = +(p?.dataset.payFee || 0);
      $('[data-ship-out]').textContent = sc ? fmt(sc) : (loc === 'el' ? 'Δωρεάν' : 'Free');
      $('[data-fee-row]').hidden = !pf; if (p) $('[data-fee-label]').textContent = p.closest('label')?.querySelector('span')?.textContent || ''; $('[data-fee-out]').textContent = fmt(pf);
      $('[data-total-out]').textContent = fmt(sub + sc + pf);
      const addr = $('[data-address]'); if (addr) addr.hidden = s?.value === 'pickup';
    };
    $$('input[name=shipping_method],input[name=payment_method]').forEach((i) => i.addEventListener('change', upd)); upd();
  }

  /* Subtle reveal on scroll (purposeful: pacing long homepages) */
  if (!reduce && 'IntersectionObserver' in window && body.classList.contains('is-home')) {
    document.documentElement.classList.add('js-reveal');
    const io = new IntersectionObserver((ents) => ents.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } }), { rootMargin: '0px 0px -8% 0px' });
    $$('.sec .wrap > *').forEach((el) => io.observe(el));
  }

  /* GA4 only after explicit consent */
  const ga = body.dataset.ga;
  const loadGA = () => {
    if (!ga || window.gtag) return;
    const s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga); document.head.append(s);
    window.dataLayer = window.dataLayer || []; window.gtag = function () { window.dataLayer.push(arguments); }; window.gtag('js', new Date()); window.gtag('config', ga, { anonymize_ip: true });
  };
  const cookie = $('#cookie');
  if (cookie) {
    let c = null; try { c = localStorage.getItem('consent'); } catch {}
    if (c === 'yes') loadGA(); else if (c !== 'no') cookie.hidden = false;
    $$('[data-consent]', cookie).forEach((b) => b.addEventListener('click', () => { try { localStorage.setItem('consent', b.dataset.consent); } catch {} cookie.hidden = true; if (b.dataset.consent === 'yes') loadGA(); }));
  }
})();

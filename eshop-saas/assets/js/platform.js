(() => {
  'use strict';
  const nav = document.querySelector('[data-nav]'), burger = document.querySelector('[data-burger]');
  burger?.addEventListener('click', () => { const o = nav.classList.toggle('is-open'); burger.setAttribute('aria-expanded', String(o)); });
  const onScroll = () => nav?.classList.toggle('is-scrolled', scrollY > 8);
  addEventListener('scroll', onScroll, { passive: true }); onScroll();
  const ga = document.body.dataset.ga, cookie = document.getElementById('cookie');
  const load = () => { if (!ga || window.gtag) return; const s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga); document.head.append(s); window.dataLayer = window.dataLayer || []; window.gtag = function () { dataLayer.push(arguments); }; gtag('js', new Date()); gtag('config', ga, { anonymize_ip: true }); };
  if (cookie) {
    let c = null; try { c = localStorage.getItem('consent'); } catch {}
    if (c === 'yes') load(); else if (c !== 'no') cookie.hidden = false;
    cookie.querySelectorAll('[data-consent]').forEach((b) => b.addEventListener('click', () => { try { localStorage.setItem('consent', b.dataset.consent); } catch {} cookie.hidden = true; if (b.dataset.consent === 'yes') load(); }));
  }
})();

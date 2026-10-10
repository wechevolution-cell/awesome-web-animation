/* Back-office enhancements (all forms work without JS). */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = document.body.dataset.csrf;
  document.documentElement.classList.add('js');
  const post = async (url, data) => {
    const fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) Object.entries(data).forEach(([k, v]) => Array.isArray(v) ? v.forEach((x) => fd.append(k, x)) : fd.append(k, v));
    if (!fd.has('_csrf')) fd.append('_csrf', csrf);
    const r = await fetch(url, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
    const j = await r.json().catch(() => ({ ok: false, error: 'Unexpected response' }));
    return { status: r.status, ...j };
  };
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  /* Sidebar + generic toggles */
  $$('[data-toggle]').forEach((b) => b.addEventListener('click', () => $(b.dataset.toggle)?.classList.toggle('is-open')));

  /* Confirmations (two-step for destructive actions) */
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-confirm]');
    if (el && !confirm(el.dataset.confirm)) { e.preventDefault(); e.stopImmediatePropagation(); }
  }, true);

  /* Copy to clipboard */
  $$('[data-copy]').forEach((c) => { c.title = 'Click to copy'; c.style.cursor = 'copy'; c.addEventListener('click', () => navigator.clipboard?.writeText(c.textContent.trim()).then(() => { c.dataset.was = c.textContent; c.textContent = 'Copied ✓'; setTimeout(() => (c.textContent = c.dataset.was), 1000); })); });

  /* Bulk selection */
  $$('form[data-bulk]').forEach((f) => {
    const bar = $('[data-bulk-bar]', f), cnt = $('[data-bulk-count]', f), all = $('[data-check-all]', f);
    const upd = () => { const n = $$('[data-check]:checked', f).length; bar.classList.toggle('is-on', n > 0); cnt.textContent = n + ' selected'; };
    all?.addEventListener('change', () => { $$('[data-check]', f).forEach((c) => (c.checked = all.checked)); upd(); });
    $$('[data-check]', f).forEach((c) => c.addEventListener('change', upd));
    const act = $('[data-bulk-action]', f);
    act?.addEventListener('change', () => $$('[data-bulk-extra]', f).forEach((x) => (x.hidden = x.dataset.bulkExtra !== act.value)));
  });

  /* Product status toggle (AJAX with quota feedback) */
  $$('[data-status-toggle]').forEach((b) => b.addEventListener('click', async (e) => {
    e.preventDefault();
    const id = b.dataset.statusToggle, on = b.classList.contains('is-on');
    b.classList.add('is-busy');
    const r = await post(`/admin/products/${id}/status/`, { to: on ? 'inactive' : 'active' });
    b.classList.remove('is-busy');
    if (r.ok) {
      b.classList.toggle('is-on', !on); b.setAttribute('aria-pressed', String(!on)); $('em', b).textContent = on ? 'Inactive' : 'Active';
      const form = $(`#st-${id} input[name=to]`); if (form) form.value = on ? 'active' : 'inactive';
      if (r.quota) updateQuota(r.quota);
    } else alert(r.error || 'Could not change status.');
  }));
  const updateQuota = (q) => {
    $$('.quota').forEach((el) => {
      $('.quota-top strong', el).textContent = `${q.active} / ${q.limit}`;
      $('.quota-track span', el).style.width = q.pct + '%';
      el.classList.remove('quota-ok', 'quota-warn', 'quota-bad');
      el.classList.add(q.active >= q.limit ? 'quota-bad' : q.pct >= 85 ? 'quota-warn' : 'quota-ok');
    });
    const t = $('.top-quota'); if (t) t.lastChild.textContent = ` ${q.active}/${q.limit}`;
  };

  /* Slug autogen preview (server makes the final slug) */
  const translit = (s) => {
    const m = { α: 'a', ά: 'a', β: 'v', γ: 'g', δ: 'd', ε: 'e', έ: 'e', ζ: 'z', η: 'i', ή: 'i', θ: 'th', ι: 'i', ί: 'i', ϊ: 'i', ΐ: 'i', κ: 'k', λ: 'l', μ: 'm', ν: 'n', ξ: 'x', ο: 'o', ό: 'o', π: 'p', ρ: 'r', σ: 's', ς: 's', τ: 't', υ: 'y', ύ: 'y', ϋ: 'y', ΰ: 'y', φ: 'f', χ: 'ch', ψ: 'ps', ω: 'o', ώ: 'o' };
    return s.toLowerCase().split('').map((c) => m[c] ?? c).join('').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
  };
  $$('[data-slug-source]').forEach((src) => {
    const target = $(`[name="${src.dataset.slugSource}"]`, src.form);
    if (!target) return;
    let manual = target.value !== '';
    target.addEventListener('input', () => (manual = target.value !== ''));
    src.addEventListener('input', () => { if (!manual) target.placeholder = translit(src.value); });
  });

  /* Repeatable rows (variants, specs) */
  $$('[data-repeat]').forEach((r) => {
    r.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-repeat-remove]');
      if (rm) { const rows = $$('[data-repeat-row]', r); const row = rm.closest('[data-repeat-row]'); if (rows.length > 1) row.remove(); else $$('input', row).forEach((i) => (i.value = i.type === 'number' ? 0 : '')); }
    });
    $('[data-repeat-add]', r)?.addEventListener('click', () => {
      const rows = $$('[data-repeat-row]', r), last = rows[rows.length - 1], c = last.cloneNode(true);
      $$('input', c).forEach((i) => (i.value = i.type === 'number' ? 0 : i.type === 'hidden' ? 0 : ''));
      last.after(c); $('input:not([type=hidden])', c)?.focus();
    });
  });

  /* Minimal rich-text editor (contenteditable synced to the textarea; server sanitizes) */
  $$('[data-rte]').forEach((wrap) => {
    const ta = $('[data-rte-source]', wrap);
    const area = document.createElement('div');
    area.className = 'rte-area'; area.contentEditable = 'true'; area.setAttribute('role', 'textbox'); area.setAttribute('aria-multiline', 'true');
    area.setAttribute('aria-label', wrap.closest('.field')?.querySelector('label')?.textContent || 'Content');
    area.innerHTML = ta.value; ta.hidden = true; ta.after(area);
    const sync = () => { if (ta.hidden) ta.value = area.innerHTML; ta.dispatchEvent(new Event('input', { bubbles: true })); };
    area.addEventListener('input', sync);
    $$('[data-cmd]', wrap).forEach((b) => b.addEventListener('click', () => {
      const c = b.dataset.cmd;
      if (c === 'html') { if (ta.hidden) { ta.value = area.innerHTML; ta.hidden = false; area.hidden = true; b.classList.add('is-on'); } else { area.innerHTML = ta.value; ta.hidden = true; area.hidden = false; b.classList.remove('is-on'); } return; }
      area.focus();
      if (c === 'h2' || c === 'h3') document.execCommand('formatBlock', false, c);
      else if (c === 'ul') document.execCommand('insertUnorderedList');
      else if (c === 'ol') document.execCommand('insertOrderedList');
      else if (c === 'link') { const u = prompt('Link URL (https://… or /path/)'); if (u && /^(https?:\/\/|\/|mailto:|tel:)/i.test(u)) document.execCommand('createLink', false, u); }
      else document.execCommand(c);
      sync();
    }));
    ta.form?.addEventListener('submit', () => { if (ta.hidden) ta.value = area.innerHTML; });
  });

  /* Media picker modal */
  const modal = $('#media-modal');
  let pickCb = null, multi = false, selected = new Set();
  if (modal && !('closedBy' in HTMLDialogElement.prototype)) modal.addEventListener('click', (e) => { if (e.target === modal) { const r = modal.getBoundingClientRect(); if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) modal.close(); } });
  const renderGrid = (items) => {
    const g = $('[data-media-grid]', modal);
    g.innerHTML = items.length ? items.map((m) => `<button type="button" class="mm-item" data-id="${m.id}" data-url="${m.url}" aria-pressed="false" title="${m.name.replace(/"/g, '')}"><img src="${m.url}" alt="" loading="lazy"></button>`).join('') : '<p class="muted">No images yet — upload some above.</p>';
    $$('.mm-item', g).forEach((b) => b.addEventListener('click', () => {
      if (!multi) { selected.clear(); $$('.mm-item', g).forEach((x) => { x.classList.remove('is-sel'); x.setAttribute('aria-pressed', 'false'); }); }
      const id = b.dataset.id;
      selected.has(id) ? selected.delete(id) : selected.add(id);
      b.classList.toggle('is-sel', selected.has(id)); b.setAttribute('aria-pressed', String(selected.has(id)));
    }));
  };
  const loadMedia = async () => { const r = await fetch('/admin/api/media/', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); const j = await r.json(); renderGrid(j.items || []); };
  const openPicker = (isMulti, cb) => { multi = isMulti; pickCb = cb; selected = new Set(); modal.showModal(); loadMedia(); };
  $$('[data-modal-close]', modal || document).forEach((b) => b.addEventListener('click', () => modal.close()));
  $('[data-media-confirm]', modal || document)?.addEventListener('click', () => {
    const items = $$('.mm-item', modal).filter((b) => selected.has(b.dataset.id)).map((b) => ({ id: b.dataset.id, url: b.dataset.url }));
    pickCb?.(items); modal.close();
  });
  $('input[type=file]', $('[data-media-upload]') || document.createElement('div'))?.addEventListener('change', async (e) => {
    const f = e.target.form, fd = new FormData(f);
    const r = await post(f.action, fd);
    if (r.errors?.length) alert(r.errors.join('\n'));
    await loadMedia(); (r.ids || []).forEach((id) => { selected.add(String(id)); $(`.mm-item[data-id="${id}"]`, modal)?.classList.add('is-sel'); });
    e.target.value = '';
  });
  $$('[data-media-field]').forEach((fld) => {
    const input = $('[data-media-input]', fld), prev = $('[data-media-prev]', fld);
    $('[data-media-open]', fld)?.addEventListener('click', () => openPicker(false, (items) => { if (!items[0]) return; input.value = items[0].id; prev.innerHTML = `<img src="${items[0].url}" alt="">`; }));
    $('[data-media-clear]', fld)?.addEventListener('click', () => { input.value = 0; prev.innerHTML = '<span class="muted">No image</span>'; });
  });
  $$('[data-media-open][data-media-multi]').forEach((b) => b.addEventListener('click', () => {
    const list = $(b.dataset.mediaTarget);
    openPicker(true, (items) => items.forEach((m) => {
      const d = document.createElement('div'); d.className = 'img-item'; d.draggable = true;
      d.innerHTML = `<img src="${m.url}" alt=""><input type="hidden" name="images[]" value="${m.id}"><button type="button" class="img-x" data-image-remove aria-label="Remove image">×</button>`;
      list.insertBefore(d, list.querySelector('input[type=hidden][value="0"]'));
    }));
  }));
  /* Image list: remove + drag reorder */
  $$('[data-image-list]').forEach((list) => {
    list.addEventListener('click', (e) => { if (e.target.closest('[data-image-remove]')) e.target.closest('.img-item').remove(); });
    let drag = null;
    list.addEventListener('dragstart', (e) => { drag = e.target.closest('.img-item'); e.dataTransfer.effectAllowed = 'move'; });
    list.addEventListener('dragover', (e) => { e.preventDefault(); const over = e.target.closest('.img-item'); if (drag && over && over !== drag) { const r = over.getBoundingClientRect(); over.parentNode.insertBefore(drag, e.clientX < r.left + r.width / 2 ? over : over.nextSibling); } });
  });

  /* Dropzone (media page) */
  $$('[data-dropzone]').forEach((z) => {
    const inp = $('input[type=file]', z);
    inp?.addEventListener('change', () => z.submit());
    ['dragenter', 'dragover'].forEach((ev) => z.addEventListener(ev, (e) => { e.preventDefault(); z.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((ev) => z.addEventListener(ev, () => z.classList.remove('is-over')));
    z.addEventListener('drop', (e) => { e.preventDefault(); if (e.dataTransfer.files.length) { inp.files = e.dataTransfer.files; z.submit(); } });
  });

  /* Live SEO / AEO analyzer */
  $$('form[data-analyze]').forEach((f) => {
    const box = $('[data-analyzer]', f); if (!box) return;
    const run = debounce(async () => {
      const fd = new FormData(f);
      const d = { type: f.dataset.analyze, id: f.dataset.id || 0, title: fd.get('name') || fd.get('title') || '', seo_title: fd.get('seo_title') || '', seo_description: fd.get('seo_description') || '',
        slug: fd.get('slug') || $('[name=slug]', f)?.placeholder || '', focus_keyword: fd.get('focus_keyword') || '', content: fd.get('description') ?? fd.get('content') ?? '', short_description: fd.get('short_description') || '',
        faq: fd.get('faq') || '', robots: fd.get('robots') || '', brand: fd.get('brand') || '', sku: fd.get('sku') || '', images_count: fd.getAll('images[]').filter((x) => x !== '0').length || (fd.get('image_id') > 0 ? 1 : 0),
        attributes_count: fd.getAll('attr_name[]').filter(Boolean).length };
      const r = await post('/admin/api/analyze/', d);
      if (!r.ok) return;
      for (const k of ['seo', 'aeo']) { const s = $(`[data-score=${k}]`, box); $('strong', s).textContent = r[k]; s.className = 'score ' + (r[k] >= 75 ? 'good' : r[k] >= 50 ? 'mid' : 'bad'); }
      let html = '', g = '';
      for (const c of r.checks) { if (c.group !== g) { g = c.group; html += `<li class="grp">${g === 'seo' ? 'SEO' : 'AEO / AI search'}</li>`; } html += `<li class="${c.ok ? 'ok' : ''}"><span>${c.label}${c.tip ? ` <small>${c.tip}</small>` : ''}</span></li>`; }
      $('[data-checks]', box).innerHTML = html;
      $('[data-cannibal]', box).innerHTML = r.cannibal?.length ? `<div class="cannibal"><strong>Keyword cannibalization:</strong> ${r.cannibal.map((c) => `${c.type} “${c.title}” targets “${c.keyword}” (${c.similarity}% overlap)`).join('; ')}</div>` : '';
      const st = $('[data-serp-title]', f); if (st) { st.textContent = d.seo_title || d.title || '—'; $('[data-serp-desc]', f).textContent = d.seo_description || 'Meta description preview…'; }
    }, 450);
    f.addEventListener('input', run); run();
  });

  /* Appearance colours */
  $$('[data-color-sync]').forEach((c) => {
    const txt = c.parentElement.querySelector('[data-color-text]'), prev = $('[data-token-preview]');
    const map = { c_primary: '--p', c_background: '--bg', c_text: '--tx', c_button_text: '--bt', c_accent: '--ac', c_link: '--lk', c_surface: '--sf', c_muted: '--mu' };
    const apply = () => { if (prev && map[c.name]) prev.style.setProperty(map[c.name], c.value); };
    c.addEventListener('input', () => { txt.value = c.value; apply(); });
    txt.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(txt.value)) { c.value = txt.value; apply(); } });
  });

  /* Menus: show the relevant target field */
  $$('[data-menu-form]').forEach((f) => {
    const sel = $('[data-menu-type]', f);
    const upd = () => $$('[data-menu-ref]', f).forEach((x) => (x.hidden = x.dataset.menuRef !== sel.value));
    sel.addEventListener('change', upd); upd();
  });

  /* Role presets for staff permissions */
  $$('[data-role-presets]').forEach((f) => {
    const presets = JSON.parse(f.dataset.rolePresets), sel = $('[data-role-select]', f), box = $('[data-perms]', f);
    const upd = (prefill) => { box.hidden = sel.value === 'owner'; if (prefill && presets[sel.value]) $$('input[name="perms[]"]', box).forEach((c) => { if (!c.disabled) c.checked = presets[sel.value].includes(c.value); }); };
    sel.addEventListener('change', () => upd(true)); upd(false);
  });

  /* Shop creation wizard */
  $$('[data-wizard]').forEach((f) => {
    const steps = $$('[data-step]', f), dots = $$('[data-step-dot]', f), nav = $('.wizard-nav', f);
    let cur = 0;
    const firstErr = steps.findIndex((s) => $('[aria-invalid=true], .f-error', s));
    if (firstErr > 0) cur = firstErr;
    const durSel = $('[data-duration-select]', f);
    const syncDur = () => { const p = $('input[name=plan_id]:checked', f); if (!p || !durSel) return; const allowed = p.dataset.durations.split(','); [...durSel.options].forEach((o) => (o.disabled = !allowed.includes(o.value))); if (durSel.selectedOptions[0]?.disabled) durSel.value = allowed[0]; };
    $$('input[name=plan_id]', f).forEach((r) => r.addEventListener('change', syncDur)); syncDur();
    const review = () => {
      const fd = new FormData(f), p = $('input[name=plan_id]:checked', f), t = $('input[name=template_key]:checked', f);
      const rows = [['Shop', fd.get('name')], ['Contact', `${fd.get('owner_name') || ''} · ${fd.get('email')} · ${fd.get('phone') || ''}`], ['Domain', fd.get('domain') || '(none yet) → /s/' + (fd.get('slug') || $('[name=slug]', f).placeholder) + '/'], ['Package', p ? p.dataset.name : '—'], ['Duration', durSel?.selectedOptions[0]?.textContent], ['Start', fd.get('start_date')], ['Template', t ? t.value : '—'], ['Admin', fd.get('admin_email') + (fd.get('admin_password') ? ' (password set)' : ' (invitation link)')], ['Status', fd.get('status')]];
      $('[data-review]', f).innerHTML = rows.map(([k, v]) => `<dt>${k}</dt><dd>${String(v ?? '').replace(/</g, '&lt;')}</dd>`).join('');
    };
    const show = (n) => {
      cur = Math.max(0, Math.min(steps.length - 1, n));
      steps.forEach((s, i) => s.classList.toggle('is-cur', i === cur));
      dots.forEach((d, i) => { d.classList.toggle('is-cur', i === cur); d.classList.toggle('is-done', i < cur); });
      nav.classList.toggle('is-last', cur === steps.length - 1);
      $('[data-wiz-prev]', f).disabled = cur === 0;
      if (cur === steps.length - 1) review();
      $('legend', steps[cur])?.scrollIntoView({ block: 'nearest' });
    };
    $('[data-wiz-next]', f).addEventListener('click', () => {
      const bad = $$('input,select', steps[cur]).find((i) => !i.checkValidity());
      if (bad) { bad.reportValidity(); return; }
      show(cur + 1);
    });
    $('[data-wiz-prev]', f).addEventListener('click', () => show(cur - 1));
    const name = $('[name=name]', f), slug = $('[name=slug]', f), em = $('[name=email]', f), aem = $('[name=admin_email]', f);
    name?.addEventListener('input', () => { if (!slug.value) slug.placeholder = translit(name.value).slice(0, 40); });
    slug?.form?.addEventListener('submit', () => { if (!slug.value) slug.value = slug.placeholder; });
    em?.addEventListener('change', () => { if (!aem.value) aem.value = em.value; });
    show(cur);
  });

  /* Import progress: drive batches from the browser (cron continues if the tab closes) */
  $$('[data-import-progress]').forEach((box) => {
    const id = box.dataset.importProgress; if (!id) return;
    const tick = async () => {
      const r = await post(`/admin/import/${id}/process/`, {});
      if (!r.ok && !r.status) return setTimeout(tick, 3000);
      const pct = r.pct ?? 0;
      $('[data-ip-bar] span', box).style.width = pct + '%'; $('[data-ip-bar]', box).setAttribute('aria-valuenow', pct);
      $('[data-ip-text]', box).textContent = `${(r.processed || 0).toLocaleString()} / ${(r.total || 0).toLocaleString()} rows (${pct}%)`;
      ['created', 'updated', 'activated', 'skipped', 'errors'].forEach((k) => { const el = $(`[data-ip="${k}"]`, box); if (el) el.textContent = r[k] ?? 0; });
      if (r.quota) updateQuota(r.quota);
      if (['completed', 'failed', 'cancelled'].includes(r.status)) { $('[data-ip-note]', box).textContent = 'Finished: ' + r.status + '. Reloading…'; setTimeout(() => location.reload(), 900); }
      else setTimeout(tick, r.busy ? 2500 : 250);
    };
    tick();
  });
})();
/* Main website: sync template choice with selected shop + preview link */
(() => {
  const f = document.querySelector('[data-main-site]'); if (!f) return;
  const sel = f.querySelector('[name=shop_id]'), prev = f.querySelector('[data-ms-preview]');
  const upd = (syncTpl) => {
    const o = sel.selectedOptions[0];
    if (syncTpl) { const r = f.querySelector(`input[name=template_key][value="${o.dataset.tpl}"]`); if (r) r.checked = true; }
    const t = f.querySelector('input[name=template_key]:checked')?.value || o.dataset.tpl;
    prev.href = `/s/${o.dataset.slug}/?preview_template=${t}`;
  };
  sel.addEventListener('change', () => upd(true));
  f.querySelectorAll('input[name=template_key]').forEach((r) => r.addEventListener('change', () => upd(false)));
  upd(false);
})();

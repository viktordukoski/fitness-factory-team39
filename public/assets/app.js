'use strict';
(() => {
  const navButton = document.querySelector('.nav-toggle');
  const nav = document.querySelector('#primary-navigation');
  navButton?.addEventListener('click', () => {
    const open = navButton.getAttribute('aria-expanded') !== 'true';
    navButton.setAttribute('aria-expanded', String(open));
    nav?.classList.toggle('is-open', open);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && navButton?.getAttribute('aria-expanded') === 'true') {
      navButton.setAttribute('aria-expanded', 'false');
      nav?.classList.remove('is-open');
      navButton.focus();
    }
  });
  document.querySelectorAll('details.language-menu, details.nav-more').forEach(details => {
    document.addEventListener('click', event => { if (!details.contains(event.target)) details.open = false; });
    details.addEventListener('keydown', event => { if (event.key === 'Escape') { details.open = false; details.querySelector('summary')?.focus(); } });
  });
  let previousFocus = null;
  const openDialog = dialog => { if (!dialog) return; previousFocus = document.activeElement; dialog.showModal(); document.body.classList.add('dialog-open'); dialog.querySelector('button, input, a')?.focus(); };
  document.querySelectorAll('dialog').forEach(dialog => {
    dialog.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => { document.body.classList.remove('dialog-open'); previousFocus?.focus(); });
    dialog.addEventListener('click', event => { if (event.target !== dialog) return; const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close(); });
  });
  const consentName = 'ff_consent';
  const readConsent = () => { try { const cookie = document.cookie.split('; ').find(row => row.startsWith(consentName + '=')); return cookie ? JSON.parse(decodeURIComponent(cookie.split('=').slice(1).join('='))) : null; } catch { return null; } };
  const banner = document.querySelector('.cookie-banner');
  const cookieDialog = document.querySelector('#cookie-dialog');
  const preferencesInput = document.querySelector('#cookie-preferences-form [name=preferences]');
  const secure = location.protocol === 'https:' ? '; Secure' : '';
  const saveConsent = preferences => {
    const consent = { necessary: true, preferences: Boolean(preferences), analytics: false, version: 1 };
    document.cookie = `${consentName}=${encodeURIComponent(JSON.stringify(consent))}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
    if (preferences) document.cookie = `ff_language=${encodeURIComponent(document.documentElement.lang)}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
    else document.cookie = `ff_language=; Path=/; Max-Age=0; SameSite=Lax${secure}`;
    if (banner) banner.hidden = true;
    if (cookieDialog?.open) cookieDialog.close();
  };
  const savedConsent = readConsent();
  if (savedConsent?.preferences) document.cookie = `ff_language=${encodeURIComponent(document.documentElement.lang)}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
  if (banner) banner.hidden = Boolean(savedConsent);
  document.querySelectorAll('[data-cookie-accept]').forEach(button => button.addEventListener('click', () => saveConsent(true)));
  document.querySelectorAll('[data-cookie-reject]').forEach(button => button.addEventListener('click', () => saveConsent(false)));
  document.querySelectorAll('[data-cookie-open]').forEach(button => button.addEventListener('click', () => { if (preferencesInput) preferencesInput.checked = Boolean(readConsent()?.preferences); openDialog(cookieDialog); }));
  document.querySelector('#cookie-preferences-form')?.addEventListener('submit', event => { event.preventDefault(); saveConsent(preferencesInput?.checked); });
  document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
  document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => { const input = document.getElementById(button.dataset.passwordToggle); if (!input) return; const show = input.type === 'password'; input.type = show ? 'text' : 'password'; button.setAttribute('aria-pressed', String(show)); }));
  document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); }));
  document.querySelectorAll('form[data-processing-form]').forEach(form => form.addEventListener('submit', () => { const button = form.querySelector('[data-processing-text]'); if (!button) return; button.disabled = true; button.textContent = button.dataset.processingText; form.setAttribute('aria-busy', 'true'); }));
  const scheduleList = document.querySelector('[data-schedule-list]');
  const scheduleCalendar = document.querySelector('[data-schedule-calendar]');
  document.querySelectorAll('[data-schedule-view]').forEach(button => button.addEventListener('click', () => { const list = button.dataset.scheduleView === 'list'; if (scheduleList) scheduleList.hidden = !list; if (scheduleCalendar) scheduleCalendar.hidden = list; document.querySelectorAll('[data-schedule-view]').forEach(item => { const selected = item === button; item.setAttribute('aria-pressed', String(selected)); item.classList.toggle('selected', selected); }); }));
  if (new URLSearchParams(location.search).get('view') === 'calendar') document.querySelector('[data-schedule-view="calendar"]')?.click();
  const galleryItems = [...document.querySelectorAll('[data-gallery-src]')];
  const galleryDialog = document.querySelector('#gallery-dialog');
  let galleryIndex = 0;
  const showGalleryItem = index => { if (!galleryItems.length) return; galleryIndex = (index + galleryItems.length) % galleryItems.length; const item = galleryItems[galleryIndex]; const image = document.querySelector('[data-gallery-image]'); if (image) { image.src = item.dataset.gallerySrc; image.alt = item.dataset.galleryAlt || ''; } const caption = document.querySelector('[data-gallery-text]'); if (caption) caption.textContent = item.dataset.galleryCaption || ''; };
  galleryItems.forEach((item, index) => item.addEventListener('click', () => { showGalleryItem(index); openDialog(galleryDialog); }));
  document.querySelector('[data-gallery-prev]')?.addEventListener('click', () => showGalleryItem(galleryIndex - 1));
  document.querySelector('[data-gallery-next]')?.addEventListener('click', () => showGalleryItem(galleryIndex + 1));
  galleryDialog?.addEventListener('keydown', event => { if (event.key === 'ArrowLeft') { event.preventDefault(); showGalleryItem(galleryIndex - 1); } if (event.key === 'ArrowRight') { event.preventDefault(); showGalleryItem(galleryIndex + 1); } });
  document.querySelector('[data-share]')?.addEventListener('click', async () => { try { if (navigator.share) await navigator.share({ title: document.title, url: location.href }); else if (navigator.clipboard) { await navigator.clipboard.writeText(location.href); const status = document.querySelector('[data-share-status]'); if (status) status.textContent = location.href; } else window.prompt(document.title, location.href); } catch { /* Cancelling a share is harmless. */ } });
})();

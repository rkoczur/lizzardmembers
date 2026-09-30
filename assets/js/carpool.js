// Telekocsi-szervező: szerepválasztó, helyszám-léptető, űrlap-ellenőrzés, link másolása
(function () {
  // Szerepválasztó fülek
  const tabs = document.querySelectorAll('[data-cp-tab]');
  tabs.forEach(tab => tab.addEventListener('click', () => {
    const name = tab.dataset.cpTab;
    tabs.forEach(t => t.classList.toggle('is-active', t === tab));
    document.querySelectorAll('[data-cp-panel]').forEach(p =>
      p.classList.toggle('is-open', p.dataset.cpPanel === name));
  }));

  // Szabad helyek léptető
  const seats = document.querySelector('[data-cp-seats]');
  document.querySelectorAll('[data-cp-step]').forEach(btn => btn.addEventListener('click', () => {
    const next = Number(seats.value || 0) + Number(btn.dataset.cpStep);
    seats.value = Math.min(Number(seats.max), Math.max(Number(seats.min), next));
  }));

  // Sofőr űrlap: legalább egy elérhetőség kötelező
  const form = document.querySelector('.cp-driver-form');
  form?.addEventListener('submit', ev => {
    const filled = [...form.querySelectorAll('[data-cp-contact]')].some(i => i.value.trim() !== '');
    form.querySelector('[data-cp-contact-hint]').hidden = filled;
    if (!filled) ev.preventDefault();
  });

  // Megerősítés kérése
  document.querySelectorAll('[data-cp-confirm]').forEach(f => f.addEventListener('submit', ev => {
    if (!confirm(f.dataset.cpConfirm)) ev.preventDefault();
  }));

  // Admin: link másolása
  document.querySelector('[data-cp-copy]')?.addEventListener('click', ev => {
    const url = document.querySelector('[data-cp-url]').value;
    navigator.clipboard.writeText(url).then(() => {
      ev.target.textContent = 'Másolva!';
      setTimeout(() => { ev.target.textContent = 'Link másolása'; }, 1800);
    });
  });
})();

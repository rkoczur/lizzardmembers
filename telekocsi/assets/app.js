// Önálló telekocsi-szervező: kinyíló űrlap, link másolása
(function () {
  // Létrehozó gomb → űrlap kinyitása
  document.querySelectorAll('[data-tk-reveal]').forEach(box => {
    box.querySelector('[data-tk-reveal-btn]')?.addEventListener('click', () => {
      box.classList.add('is-open');
      const body = box.querySelector('[data-tk-reveal-body]');
      body.hidden = false;
      body.querySelector('input:not([type=hidden])')?.focus();
    });
  });

  // Szerepválasztó fülek
  const tabs = document.querySelectorAll('[data-tk-tab]');
  tabs.forEach(tab => tab.addEventListener('click', () => {
    tabs.forEach(t => t.classList.toggle('is-active', t === tab));
    document.querySelectorAll('[data-tk-panel]').forEach(p =>
      p.classList.toggle('is-open', p.dataset.tkPanel === tab.dataset.tkTab));
  }));

  // Szabad helyek léptető
  const seats = document.querySelector('[data-tk-seats]');
  document.querySelectorAll('[data-tk-step]').forEach(btn => btn.addEventListener('click', () => {
    const next = Number(seats.value || 0) + Number(btn.dataset.tkStep);
    seats.value = Math.min(Number(seats.max), Math.max(Number(seats.min), next));
  }));

  // Sofőr űrlap: legalább egy elérhetőség kötelező
  const driverForm = document.querySelector('.tk-driver-form');
  driverForm?.addEventListener('submit', ev => {
    const filled = [...driverForm.querySelectorAll('[data-tk-contact]')].some(i => i.value.trim() !== '');
    driverForm.querySelector('[data-tk-contact-hint]').hidden = filled;
    if (!filled) ev.preventDefault();
  });

  // Megerősítés kérése
  document.querySelectorAll('[data-tk-confirm]').forEach(f => f.addEventListener('submit', ev => {
    if (!confirm(f.dataset.tkConfirm)) ev.preventDefault();
  }));

  // Link másolása
  document.querySelector('[data-tk-copy]')?.addEventListener('click', ev => {
    const btn = ev.currentTarget;
    const url = document.querySelector('[data-tk-url]');
    const done = () => {
      btn.textContent = 'Másolva!';
      setTimeout(() => { btn.textContent = 'Link másolása'; }, 1800);
    };
    if (navigator.clipboard) {
      navigator.clipboard.writeText(url.value).then(done);
    } else {
      url.select();
      document.execCommand('copy');
      done();
    }
  });
})();

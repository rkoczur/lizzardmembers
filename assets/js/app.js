/* ===== Member list search + column filters ===== */
const _memberFilters = {};

function filterMemberRows() {
  const q = (document.getElementById('member-search')?.value || '').toLowerCase();
  document.querySelectorAll('#member-table tbody tr').forEach(row => {
    if (!row.dataset.role) return;
    let show = !q || row.textContent.toLowerCase().includes(q);
    if (show) {
      for (const [key, val] of Object.entries(_memberFilters)) {
        if (val && row.dataset[key] !== val) { show = false; break; }
      }
    }
    row.style.display = show ? '' : 'none';
  });
}

function initMemberSearch() {
  const input = document.getElementById('member-search');
  if (!input) return;
  input.addEventListener('input', filterMemberRows);
}

function initMemberFilters() {
  if (!document.querySelector('#member-table')) return;

  function closeAll() {
    document.querySelectorAll('.col-filter-menu.open').forEach(m => m.classList.remove('open'));
  }

  document.addEventListener('click', closeAll);
  window.addEventListener('scroll', closeAll, true);

  document.querySelectorAll('.col-filter-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const menu = btn.nextElementSibling;
      const isOpen = menu.classList.contains('open');
      closeAll();
      if (!isOpen) {
        const rect = btn.getBoundingClientRect();
        menu.style.top  = (rect.bottom + 4) + 'px';
        menu.style.left = (rect.left + rect.width / 2) + 'px';
        menu.classList.add('open');
      }
    });
  });

  document.querySelectorAll('.col-filter-menu li button').forEach(item => {
    item.addEventListener('click', e => {
      e.stopPropagation();
      const menu = item.closest('.col-filter-menu');
      const btn  = menu.previousElementSibling;
      const filter = btn.dataset.filter;
      const value  = item.dataset.value;
      _memberFilters[filter] = value;
      menu.querySelectorAll('li').forEach(li => li.classList.remove('selected'));
      item.closest('li').classList.add('selected');
      btn.classList.toggle('active', !!value);
      closeAll();
      filterMemberRows();
    });
  });
}

/* ===== Avatar upload preview ===== */
function initAvatarUpload() {
  const fileInput = document.getElementById('avatar-file-input');
  const previewImg = document.getElementById('avatar-preview');
  const uploadOverlay = document.getElementById('avatar-upload-overlay');

  if (!fileInput || !previewImg) return;

  if (uploadOverlay) {
    uploadOverlay.addEventListener('click', () => fileInput.click());
  }

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      showAlert('Kérjük, válasszon egy képfájlt.', 'error');
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      showAlert('A képnek 2 MB-nál kisebbnek kell lennie.', 'error');
      return;
    }

    const reader = new FileReader();
    reader.onload = e => { previewImg.src = e.target.result; };
    reader.readAsDataURL(file);
  });
}

/* ===== Flash alerts ===== */
function showAlert(message, type = 'info') {
  const container = document.getElementById('alert-container');
  if (!container) return;
  const div = document.createElement('div');
  div.className = `alert alert-${type}`;
  div.textContent = message;
  container.prepend(div);
  setTimeout(() => div.remove(), 5000);
}

/* ===== Auto-dismiss alerts ===== */
function initAutoDismissAlerts() {
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 4000);
  });
}

/* ===== Confirm delete ===== */
function confirmDelete(message) {
  return confirm(message || 'Biztosan folytatja?');
}

/* ===== Modals ===== */
function initModals() {
  document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = document.getElementById(btn.dataset.modalOpen);
      if (modal) modal.classList.add('open');
    });
  });
  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-backdrop');
      if (modal) modal.classList.remove('open');
    });
  });
  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', e => {
      if (e.target === backdrop) backdrop.classList.remove('open');
    });
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop.open').forEach(m => m.classList.remove('open'));
    }
  });
}

/* ===== Member picker ===== */
function initMemberPicker() {
  const select = document.getElementById('member-picker-select');
  const addBtn = document.getElementById('member-picker-add');
  const list   = document.getElementById('member-picker-list');
  const empty  = document.getElementById('member-picker-empty');
  if (!select || !addBtn || !list) return;

  function updateEmpty() {
    if (empty) empty.style.display = list.children.length === 0 ? '' : 'none';
  }

  function wireRemove(item) {
    item.querySelector('button').addEventListener('click', () => {
      item.remove();
      updateEmpty();
    });
  }

  // Wire existing pre-populated items
  list.querySelectorAll('.member-picker-item').forEach(wireRemove);

  addBtn.addEventListener('click', () => {
    const val  = select.value;
    const text = select.options[select.selectedIndex]?.text;
    if (!val) return;
    if (list.querySelector(`[data-member-id="${val}"]`)) return;

    const item = document.createElement('div');
    item.className = 'member-picker-item';
    item.dataset.memberId = val;
    item.innerHTML = `<span>${text}</span><input type="hidden" name="member_ids[]" value="${val}"><button type="button" class="btn btn-danger btn-sm">Eltávolít</button>`;
    wireRemove(item);
    list.appendChild(item);
    select.value = '';
    updateEmpty();
  });
}

/* ===== Tour list search + mine filter ===== */
let _tourMineOnly = false;

function filterTourRows() {
  const q = (document.getElementById('tour-search')?.value || '').toLowerCase();
  document.querySelectorAll('#tour-table tbody tr').forEach(row => {
    if (!row.dataset.mine) return;
    let show = !q || row.textContent.toLowerCase().includes(q);
    if (show && _tourMineOnly && row.dataset.mine !== '1') show = false;
    row.style.display = show ? '' : 'none';
  });
}

function initTourSearch() {
  const input = document.getElementById('tour-search');
  if (!input) return;
  input.addEventListener('input', filterTourRows);
}

function initTourMineFilter() {
  const btn = document.getElementById('tour-mine-filter');
  if (!btn) return;
  btn.addEventListener('click', () => {
    _tourMineOnly = !_tourMineOnly;
    btn.classList.toggle('btn-primary', _tourMineOnly);
    btn.classList.toggle('btn-ghost', !_tourMineOnly);
    filterTourRows();
  });
}

/* ===== Mobile hamburger menu ===== */
function initMobileMenu() {
  const btn     = document.getElementById('hamburger-btn');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!btn || !sidebar) return;

  function openSidebar() {
    sidebar.classList.add('sidebar-open');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('sidebar-open');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  btn.addEventListener('click', () => {
    sidebar.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
  });

  if (overlay) overlay.addEventListener('click', closeSidebar);

  sidebar.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', closeSidebar);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initMemberSearch();
  initMemberFilters();
  initAvatarUpload();
  initAutoDismissAlerts();
  initModals();
  initTourSearch();
  initTourMineFilter();
  initMemberPicker();
  initMobileMenu();
});

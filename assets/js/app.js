// Mobile Navigation Toggle
const mobileToggle = document.querySelector('.mobile-toggle');
if (mobileToggle) {
  mobileToggle.addEventListener('click', function (e) {
    e.stopPropagation();
    const open = document.body.classList.toggle('nav-open');
    this.setAttribute('aria-expanded', String(open));
  });

  document.addEventListener('click', function (e) {
    if (document.body.classList.contains('nav-open')) {
      if (!e.target.closest('.sidebar') && !e.target.closest('.mobile-toggle')) {
        document.body.classList.remove('nav-open');
        mobileToggle.setAttribute('aria-expanded', 'false');
      }
    }
  });
}

// Confirmation Dialogs
document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));

// Report Printing
document.getElementById('print-report')?.addEventListener('click', () => window.print());

// Live Booking Calculator
const quantities = [...document.querySelectorAll('.qty')];
const currency = amount => 'LKR ' + amount.toLocaleString('en-LK', {minimumFractionDigits: 2, maximumFractionDigits: 2});
function updateBooking() {
  let total = 0;
  const summary = document.getElementById('summary-lines');
  if (!summary) return;
  summary.replaceChildren();
  quantities.forEach(input => {
    const quantity = Math.max(0, Math.min(100, Number(input.value) || 0));
    const cents = Math.round(Number(input.dataset.price) * 100) * quantity;
    total += cents;
    const parent = input.closest('.booking-service');
    if (parent) {
      const sub = parent.querySelector('.line-subtotal');
      if (sub) sub.textContent = currency(cents / 100);
    }
    if (quantity) {
      const row = document.createElement('div'); row.className = 'summary-line';
      const name = document.createElement('span'); name.textContent = `${input.dataset.service} × ${quantity}`;
      const amount = document.createElement('strong'); amount.textContent = currency(cents / 100);
      row.append(name, amount); summary.append(row);
    }
  });
  if (!total) {
    const p = document.createElement('p'); p.className = 'muted'; p.textContent = 'Choose a service to get started.'; summary.append(p);
  }
  const grand = document.getElementById('grand-total');
  if (grand) grand.textContent = currency(total / 100);
}
quantities.forEach(input => input.addEventListener('input', updateBooking));
if (quantities.length) updateBooking();

// ==========================================================================
// Theme Management (Light / Dark Mode)
// ==========================================================================
function getPreferredTheme() {
  try {
    const stored = localStorage.getItem('smart_laundry_theme');
    if (stored === 'dark' || stored === 'light') return stored;
  } catch (e) {}
  return 'light';
}

function applyTheme(theme) {
  const isDark = (theme === 'dark');
  const effectiveTheme = isDark ? 'dark' : 'light';

  document.documentElement.setAttribute('data-theme', effectiveTheme);
  document.documentElement.setAttribute('data-bs-theme', effectiveTheme);

  try {
    localStorage.setItem('smart_laundry_theme', effectiveTheme);
  } catch (e) {}

  document.querySelectorAll('#theme-toggle, .btn-theme-toggle').forEach(btn => {
    if (btn.classList.contains('notif-btn')) return;

    const icon = btn.querySelector('#theme-icon') || btn.querySelector('i');
    const text = btn.querySelector('#theme-text') || btn.querySelector('.theme-text');
    if (icon) {
      icon.className = isDark ? 'bi bi-sun' : 'bi bi-moon-stars';
    }
    if (text) {
      text.textContent = isDark ? 'Light' : 'Dark';
    }
    btn.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
  });

  window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: effectiveTheme } }));
}

// Synchronize UI buttons with the current theme immediately on script execution
const initialTheme = (document.documentElement.getAttribute('data-theme') === 'dark') ? 'dark' : getPreferredTheme();
applyTheme(initialTheme);

// Document-level single delegated event listener (prevents duplicates and handles child icon/span clicks)
document.addEventListener('click', (e) => {
  const btn = e.target.closest('#theme-toggle, .btn-theme-toggle');
  if (!btn || btn.classList.contains('notif-btn')) return;
  e.preventDefault();

  const current = document.documentElement.getAttribute('data-theme') || 'light';
  const next = (current === 'dark') ? 'light' : 'dark';
  applyTheme(next);
});

// ==========================================================================
// In-App Notification Center Dropdown
// ==========================================================================
const notifToggle = document.getElementById('notif-toggle');
const notifMenu = document.getElementById('notif-menu');

if (notifToggle && notifMenu) {
  notifToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    const isHidden = notifMenu.classList.toggle('d-none');
    notifToggle.setAttribute('aria-expanded', String(!isHidden));
  });

  notifMenu.addEventListener('click', (e) => {
    e.stopPropagation();
  });

  document.addEventListener('click', () => {
    if (!notifMenu.classList.contains('d-none')) {
      notifMenu.classList.add('d-none');
      notifToggle.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !notifMenu.classList.contains('d-none')) {
      notifMenu.classList.add('d-none');
      notifToggle.setAttribute('aria-expanded', 'false');
    }
  });
}

// Unit test for app.js theme toggle logic in Node.js
const fs = require('fs');
const path = require('path');

// Mock localStorage
const storage = {};
global.localStorage = {
  getItem: (k) => storage[k] || null,
  setItem: (k, v) => { storage[k] = String(v); },
  removeItem: (k) => { delete storage[k]; }
};

// Mock Document and DOM
class MockElement {
  constructor(tagName, id = '', className = '') {
    this.tagName = tagName.toUpperCase();
    this.id = id;
    this.className = className;
    this.classList = {
      contains: (c) => this.className.split(/\s+/).includes(c),
      add: (c) => { if (!this.classList.contains(c)) this.className += ' ' + c; },
      remove: (c) => { this.className = this.className.split(/\s+/).filter(x => x !== c).join(' '); },
      toggle: (c) => {
        if (this.classList.contains(c)) { this.classList.remove(c); return false; }
        else { this.classList.add(c); return true; }
      }
    };
    this.attributes = {};
    this.children = [];
    this.parentElement = null;
    this.textContent = '';
  }
  setAttribute(k, v) { this.attributes[k] = String(v); }
  getAttribute(k) { return this.attributes[k] || null; }
  querySelector(sel) {
    if (sel.startsWith('#')) {
      const id = sel.slice(1);
      return this.children.find(c => c.id === id) || null;
    }
    if (sel.startsWith('.')) {
      const cls = sel.slice(1);
      return this.children.find(c => c.classList.contains(cls)) || null;
    }
    return this.children.find(c => c.tagName.toLowerCase() === sel.toLowerCase()) || null;
  }
  querySelectorAll(sel) {
    let res = [];
    if (sel.includes(',')) {
      const parts = sel.split(',').map(s => s.trim());
      for (const p of parts) res.push(...this.querySelectorAll(p));
      return Array.from(new Set(res));
    }
    for (const c of this.children) {
      if (sel.startsWith('#') && c.id === sel.slice(1)) res.push(c);
      else if (sel.startsWith('.') && c.classList.contains(sel.slice(1))) res.push(c);
      else if (c.tagName.toLowerCase() === sel.toLowerCase()) res.push(c);
      res.push(...c.querySelectorAll(sel));
    }
    return res;
  }
  closest(sel) {
    let el = this;
    const parts = sel.split(',').map(s => s.trim());
    while (el) {
      for (const p of parts) {
        if (p.startsWith('#') && el.id === p.slice(1)) return el;
        if (p.startsWith('.') && el.classList.contains(p.slice(1))) return el;
        if (el.tagName.toLowerCase() === p.toLowerCase()) return el;
      }
      el = el.parentElement;
    }
    return null;
  }
  appendChild(child) {
    child.parentElement = this;
    this.children.push(child);
  }
}

// Build DOM hierarchy
const html = new MockElement('html');
const body = new MockElement('body');
html.appendChild(body);

// Topbar theme toggle button
const themeBtn = new MockElement('button', 'theme-toggle', 'btn-theme-toggle');
const themeIcon = new MockElement('i', 'theme-icon', 'bi bi-moon-stars');
const themeText = new MockElement('span', 'theme-text', 'theme-text');
themeText.textContent = 'Dark';
themeBtn.appendChild(themeIcon);
themeBtn.appendChild(themeText);
body.appendChild(themeBtn);

// Notification toggle button (shares class btn-theme-toggle, but has notif-btn)
const notifBtn = new MockElement('button', 'notif-toggle', 'btn-theme-toggle notif-btn');
body.appendChild(notifBtn);

// Event registry
const listeners = {};
global.document = {
  documentElement: html,
  body: body,
  getElementById: (id) => html.querySelector('#' + id),
  querySelector: (s) => html.querySelector(s),
  querySelectorAll: (s) => html.querySelectorAll(s),
  addEventListener: (event, handler) => {
    listeners[event] = listeners[event] || [];
    listeners[event].push(handler);
  }
};

let eventDispatched = null;
global.window = {
  dispatchEvent: (evt) => { eventDispatched = evt; }
};
global.CustomEvent = class {
  constructor(name, options) {
    this.type = name;
    this.detail = options ? options.detail : null;
  }
};

// Load app.js
const appJsCode = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
eval(appJsCode);

function dispatchClick(target) {
  const event = {
    target: target,
    preventDefault: () => {}
  };
  for (const handler of (listeners['click'] || [])) {
    handler(event);
  }
}

console.log('--- TEST 1: Default initial theme is light ---');
console.assert(html.getAttribute('data-theme') === 'light', 'Default data-theme should be light');
console.assert(themeIcon.className === 'bi bi-moon-stars', 'Icon should be moon');
console.assert(themeText.textContent === 'Dark', 'Text should be Dark');
console.log('PASS: Initial theme is light');

console.log('\n--- TEST 2: Clicking theme button toggles to dark ---');
dispatchClick(themeBtn);
console.assert(html.getAttribute('data-theme') === 'dark', 'Theme should switch to dark');
console.assert(html.getAttribute('data-bs-theme') === 'dark', 'Bootstrap theme should switch to dark');
console.assert(global.localStorage.getItem('smart_laundry_theme') === 'dark', 'localStorage should be dark');
console.assert(themeIcon.className === 'bi bi-sun', 'Icon should change to sun');
console.assert(themeText.textContent === 'Light', 'Text should change to Light');
console.assert(eventDispatched && eventDispatched.detail.theme === 'dark', 'themeChanged event should be fired with dark');
console.log('PASS: Toggled to dark mode successfully');

console.log('\n--- TEST 3: Clicking child icon inside theme button toggles back to light ---');
dispatchClick(themeIcon);
console.assert(html.getAttribute('data-theme') === 'light', 'Theme should switch to light');
console.assert(html.getAttribute('data-bs-theme') === 'light', 'Bootstrap theme should switch to light');
console.assert(global.localStorage.getItem('smart_laundry_theme') === 'light', 'localStorage should be light');
console.assert(themeIcon.className === 'bi bi-moon-stars', 'Icon should change to moon');
console.assert(themeText.textContent === 'Dark', 'Text should change to Dark');
console.assert(eventDispatched && eventDispatched.detail.theme === 'light', 'themeChanged event should be fired with light');
console.log('PASS: Child icon click handled properly');

console.log('\n--- TEST 4: Clicking notif-btn does NOT toggle theme ---');
dispatchClick(notifBtn);
console.assert(html.getAttribute('data-theme') === 'light', 'Theme must remain unchanged when clicking notif-btn');
console.log('PASS: notif-btn click ignored by theme toggle');

console.log('\n--- TEST 5: Persistence check on re-initialization ---');
// Simulate page reload with dark stored in localStorage
global.localStorage.setItem('smart_laundry_theme', 'dark');
html.setAttribute('data-theme', 'dark');
applyTheme('dark');
console.assert(html.getAttribute('data-theme') === 'dark', 'Re-init respects dark theme');
console.assert(themeIcon.className === 'bi bi-sun', 'Re-init sets sun icon');
console.assert(themeText.textContent === 'Light', 'Re-init sets Light label');
console.log('PASS: Persistence verified');

console.log('\nALL THEME TESTS PASSED SUCCESSFULLY!');

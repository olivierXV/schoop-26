// Shared behaviours. Delegated listeners only: the page's CSP forbids inline handlers.
document.addEventListener('click', (ev) => {
  const t = ev.target.closest('[data-toggle-pw],[data-print],[data-copy]');
  if (!t) return;
  if (t.dataset.togglePw) {
    const input = document.getElementById(t.dataset.togglePw);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    t.textContent = show ? 'Hide' : 'Show';
    t.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  } else if (t.hasAttribute('data-print')) {
    window.print();
  } else if (t.dataset.copy) {
    const text = document.getElementById(t.dataset.copy).textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(
      () => { t.textContent = 'Copied'; }, () => { t.textContent = 'Select & copy manually'; });
  }
});
document.addEventListener('submit', (ev) => {
  const msg = ev.target.dataset && ev.target.dataset.confirm;
  if (msg && !window.confirm(msg)) ev.preventDefault();
});

// Month calendar. Event text is inserted with textContent only (never innerHTML).
(() => {
  const root = document.getElementById('calendar');
  if (!root) return;
  let events = [];
  try { events = JSON.parse(root.dataset.events || '[]'); } catch (e) { events = []; }
  const pad = (n) => String(n).padStart(2, '0');
  const ymd = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;
  let view = new Date(); view = new Date(view.getFullYear(), view.getMonth(), 1);
  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; };

  function draw() {
    root.replaceChildren();
    const y = view.getFullYear(), m = view.getMonth();
    const head = el('div', 'cal-head');
    const prev = el('button', 'btn ghost small', '‹'); prev.type = 'button'; prev.setAttribute('aria-label', 'Previous month');
    const next = el('button', 'btn ghost small', '›'); next.type = 'button'; next.setAttribute('aria-label', 'Next month');
    prev.onclick = () => { view = new Date(y, m - 1, 1); draw(); };
    next.onclick = () => { view = new Date(y, m + 1, 1); draw(); };
    const title = el('h2', '', view.toLocaleString('en-PH', { month: 'long', year: 'numeric' })); title.setAttribute('aria-live', 'polite');
    head.append(prev, title, next);
    const grid = el('div', 'cal-grid'); grid.setAttribute('role', 'grid');
    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach((d) => grid.append(el('div', 'cal-dow', d)));
    const first = new Date(y, m, 1).getDay(), days = new Date(y, m + 1, 0).getDate();
    for (let i = 0; i < first; i++) grid.append(el('div', 'cal-day empty'));
    const today = new Date(); const todayStr = ymd(today.getFullYear(), today.getMonth(), today.getDate());
    const monthEvents = new Map();
    for (let d = 1; d <= days; d++) {
      const s = ymd(y, m, d);
      const hits = events.filter((ev) => ev.s <= s && s <= ev.e);
      let cls = 'cal-day'; if (s === todayStr) cls += ' today';
      if (hits.some((h) => h.k === 'holiday')) cls += ' holiday'; else if (hits.length) cls += ' has-event';
      const cell = el('div', cls, String(d));
      if (hits.length) { cell.title = hits.map((h) => h.t).join('\n'); hits.forEach((h) => monthEvents.set(h.s + h.t, h)); }
      grid.append(cell);
    }
    root.append(head, grid);
    const list = el('ul', 'events compact');
    [...monthEvents.values()].sort((a, b) => a.s.localeCompare(b.s)).forEach((h) => {
      const li = el('li', h.k);
      li.append(el('span', 'when', h.s.slice(5).replace('-', '/') + (h.e !== h.s ? '–' + h.e.slice(5).replace('-', '/') : '')), el('span', '', h.t));
      list.append(li);
    });
    root.append(list.children.length ? list : el('p', 'muted small', 'Nothing scheduled this month.'));
  }
  draw();
})();

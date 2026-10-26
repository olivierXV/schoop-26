// Grade entry grid. Tracks edited cells, saves them in one request, and shows the server-computed averages.
(() => {
  const ed = document.getElementById('editor');
  if (!ed) return;
  const saveBtn = document.getElementById('save'), status = document.getElementById('status');
  const dirty = new Map();   // key "lrn|subject|field" -> change
  const band = (g) => g === null ? 'none' : g >= 90 ? 'outstanding' : g >= 85 ? 'very-satisfactory' : g >= 80 ? 'satisfactory' : g >= 75 ? 'fairly-satisfactory' : 'did-not-meet';
  const fmt = (v) => v === null || v === undefined ? '—' : String(Number(v));

  function refresh() {
    saveBtn.disabled = dirty.size === 0;
    status.textContent = dirty.size ? `${dirty.size} unsaved change${dirty.size > 1 ? 's' : ''}.` : 'No changes yet.';
  }
  ed.addEventListener('input', (ev) => {
    const inp = ev.target.closest('input.score');
    if (!inp) return;
    const tr = inp.closest('tr'), key = `${tr.dataset.lrn}|${tr.dataset.subject}|${inp.dataset.field}`;
    const v = inp.value.trim();
    const ok = v === '' || (/^\d{1,3}$/.test(v) && +v <= 100);
    inp.classList.toggle('invalid', !ok);
    if (!ok) { dirty.delete(key); inp.setCustomValidity('0–100'); refresh(); status.textContent = 'Scores must be whole numbers from 0 to 100.'; return; }
    inp.setCustomValidity('');
    if (v === inp.dataset.orig) { dirty.delete(key); inp.classList.remove('dirty'); }
    else { dirty.set(key, { lrn: tr.dataset.lrn, subjectID: +tr.dataset.subject, grade: tr.dataset.grade, field: inp.dataset.field, value: v === '' ? null : +v }); inp.classList.add('dirty'); }
    refresh();
  });
  saveBtn.addEventListener('click', async () => {
    if (!dirty.size) return;
    saveBtn.disabled = true; status.textContent = 'Saving…';
    try {
      const res = await fetch(ed.dataset.endpoint, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': ed.dataset.csrf },
        body: JSON.stringify({ sy: ed.dataset.sy, quarter: ed.dataset.quarter, changes: [...dirty.values()] }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || 'Could not save. Please try again.');
      data.rows.forEach((r) => {
        const tr = ed.querySelector(`tr[data-lrn="${r.lrn}"][data-subject="${r.subjectID}"]`);
        if (!tr) return;
        tr.querySelectorAll('input.score.dirty').forEach((i) => { i.dataset.orig = i.value.trim(); i.classList.remove('dirty'); });
        tr.querySelector('[data-out="ww_qz_average"]').textContent = fmt(r.ww_qz_average);
        tr.querySelector('[data-out="pt_average"]').textContent = fmt(r.pt_average);
        const q = tr.querySelector('[data-out="quarter_grade"]'); q.replaceChildren();
        const chip = document.createElement('span'); chip.className = 'grade g-' + band(r.quarter_grade); chip.textContent = fmt(r.quarter_grade); q.append(chip);
      });
      dirty.clear(); refresh(); status.textContent = `Saved ${data.saved} record${data.saved > 1 ? 's' : ''}.`;
    } catch (err) { status.textContent = err.message; saveBtn.disabled = false; }
  });
  window.addEventListener('beforeunload', (ev) => { if (dirty.size) { ev.preventDefault(); ev.returnValue = ''; } });
})();

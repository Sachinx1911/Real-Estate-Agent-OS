/* RE360 — front-end interactions (vanilla JS, no framework) */
const RE360 = {
  openSearch() {
    const m = document.getElementById('searchModal');
    if (!m) return;
    m.classList.add('open');
    setTimeout(() => document.getElementById('searchInput')?.focus(), 50);
  },
  closeSearch() {
    document.getElementById('searchModal')?.classList.remove('open');
  },
  toggleTheme() {
    // The app is dark-first. Light mode kept minimal for now.
    document.documentElement.classList.toggle('light');
  },
  money(r) {
    r = +r || 0;
    if (r >= 1e7) return '₹' + (r / 1e7).toFixed(2).replace(/\.?0+$/, '') + ' Cr';
    if (r >= 1e5) return '₹' + (r / 1e5).toFixed(2).replace(/\.?0+$/, '') + ' L';
    return '₹' + r.toLocaleString('en-IN');
  },
  async searchNow(q) {
    const box = document.getElementById('searchResults');
    if (!box) return;
    if (!q || q.length < 2) { box.innerHTML = '<div class="muted small" style="padding:14px">Type at least 2 characters…</div>'; return; }
    box.innerHTML = '<div class="muted small" style="padding:14px">Searching…</div>';
    try {
      const res = await fetch('api/search.php?q=' + encodeURIComponent(q));
      const data = await res.json();
      if (!data.length) { box.innerHTML = '<div class="muted small" style="padding:14px">No results.</div>'; return; }
      box.innerHTML = data.map(r => `
        <a href="${r.url}" class="feed-item" style="text-decoration:none">
          <div class="feed-ic ic-box ${r.color || 'purple'}"></div>
          <div style="flex:1">
            <div class="strong" style="color:var(--text);font-weight:600">${r.title}</div>
            <div class="muted small">${r.subtitle || ''}</div>
          </div>
          <div class="badge grey">${r.type}</div>
        </a>`).join('');
    } catch (e) {
      box.innerHTML = '<div class="muted small" style="padding:14px">Search unavailable.</div>';
    }
  }
};

document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); RE360.openSearch(); }
  if (e.key === 'Escape') RE360.closeSearch();
});

document.addEventListener('DOMContentLoaded', () => {
  const si = document.getElementById('searchInput');
  if (si) {
    let t;
    si.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => RE360.searchNow(si.value.trim()), 220); });
  }
  // close modal on overlay click
  document.getElementById('searchModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'searchModal') RE360.closeSearch();
  });
});

/* Chart.js global defaults for dark theme */
if (window.Chart) {
  Chart.defaults.color = '#7c88a3';
  Chart.defaults.borderColor = '#1e2740';
  Chart.defaults.font.family = "'Inter', sans-serif";
}

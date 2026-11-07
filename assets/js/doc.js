(() => {
  const B = document.body, base = B.dataset.base, csrf = B.dataset.csrf;
  const nd = document.getElementById('noidung'); if (!nd) return;
  const chuong = nd.dataset.chuong, login = nd.dataset.login === '1';
  const audio = document.getElementById('audio');

  // FR4.4 – tùy chỉnh giao diện đọc, lưu localStorage (không lưu CSDL)
  const K = 'waka_doc_pref';
  const pref = (() => { try { return Object.assign({ size: 18, font: 'serif', nen: 'toi' }, JSON.parse(localStorage.getItem(K) || '{}')); } catch { return { size: 18, font: 'serif', nen: 'toi' }; } })();
  const ap = () => {
    nd.style.fontSize = pref.size + 'px';
    nd.classList.remove('sans', 'mono'); if (pref.font !== 'serif') nd.classList.add(pref.font);
    B.classList.remove('doc-nen-sang', 'doc-nen-vang', 'doc-nen-toi'); B.classList.add('doc-nen-' + pref.nen);
    try { localStorage.setItem(K, JSON.stringify(pref)); } catch {}
  };
  const f = document.getElementById('phong'), n = document.getElementById('nen');
  if (f) { f.value = pref.font; f.onchange = () => { pref.font = f.value; ap(); }; }
  if (n) { n.value = pref.nen; n.onchange = () => { pref.nen = n.value; ap(); }; }
  document.querySelectorAll('[data-size]').forEach(b => b.onclick = () => { pref.size = Math.max(13, Math.min(32, pref.size + 2 * +b.dataset.size)); ap(); });
  ap();

  // Vị trí hiện tại (%): cuộn trang, hoặc thời gian phát với sách nói
  const vitri = () => {
    if (audio && audio.duration) return +(audio.currentTime / audio.duration * 100).toFixed(2);
    const h = document.documentElement.scrollHeight - innerHeight;
    return h > 0 ? +Math.min(100, Math.max(0, scrollY / h * 100)).toFixed(2) : 0;
  };
  const bar = document.getElementById('tiendo-bar');
  const batDau = +nd.dataset.vt || 0;
  const khoiPhuc = () => {
    if (audio) { const t = () => { if (audio.duration) audio.currentTime = audio.duration * batDau / 100; }; audio.readyState >= 1 ? t() : audio.addEventListener('loadedmetadata', t, { once: true }); }
    else scrollTo(0, (document.documentElement.scrollHeight - innerHeight) * batDau / 100);
  };
  if (batDau > 0) { khoiPhuc(); addEventListener('load', khoiPhuc); }

  // FR4.3 – lưu tiến độ mỗi 4 giây khi thay đổi và khi rời trang
  let cuoi = batDau;
  const gui = (dung) => {
    const v = vitri(); if (bar) bar.style.width = v + '%';
    if (!login || (dung !== true && Math.abs(v - cuoi) < 0.5)) return;
    cuoi = v;
    const d = new URLSearchParams({ csrf, action: 'tien_do', c: chuong, vt: v });
    if (dung === true && navigator.sendBeacon) navigator.sendBeacon(base + '/api_doc.php', d);
    else fetch(base + '/api_doc.php', { method: 'POST', body: d, keepalive: true }).catch(() => {});
  };
  setInterval(() => gui(), 4000);
  addEventListener('scroll', () => { if (bar) bar.style.width = vitri() + '%'; }, { passive: true });
  addEventListener('pagehide', () => gui(true));
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') gui(true); });

  // FR4.5 – dấu trang
  const them = document.getElementById('them-dt'), ds = document.getElementById('ds-dt'), trong = document.getElementById('dt-trong');
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  if (them) them.onclick = async () => {
    const ghi = prompt('Ghi chú cho dấu trang (có thể bỏ trống):', '');
    if (ghi === null) return;
    const r = await fetch(base + '/api_doc.php', { method: 'POST', body: new URLSearchParams({ csrf, action: 'them_dau_trang', c: chuong, vt: vitri(), ghi_chu: ghi }) }).then(r => r.json());
    if (!r.ok) { alert(r.loi || 'Không thêm được dấu trang'); return; }
    const li = document.createElement('li'); li.dataset.id = r.id;
    li.innerHTML = `<span><a href="?c=${chuong}&vt=${r.vt}">${esc(r.tieu_de)} · ${r.vt}%</a> <small>${esc(r.ghi_chu)}</small></span><button class="btn nho xam" data-xoa="${r.id}">Xóa</button>`;
    ds.prepend(li); if (trong) trong.hidden = true;
  };
  if (ds) ds.addEventListener('click', async e => {
    const id = e.target.dataset.xoa; if (!id || !confirm('Xóa dấu trang này?')) return;
    const r = await fetch(base + '/api_doc.php', { method: 'POST', body: new URLSearchParams({ csrf, action: 'xoa_dau_trang', id }) }).then(r => r.json());
    if (r.ok) { e.target.closest('li').remove(); if (trong && !ds.children.length) trong.hidden = false; }
  });
})();

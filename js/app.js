/* Hành vi nhỏ: menu bên (điện thoại), sao chép, xác nhận, đóng menu khi bấm ra ngoài, kiểu quy tắc. */
(function () {
  var body = document.body;
  document.querySelectorAll('[data-nav-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var open = body.classList.toggle('nav-open');
      var t = document.querySelector('.topbar [data-nav-toggle]');
      if (t) t.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.menu[open], details.rowmenu[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
    var c = e.target.closest('[data-copy]');
    if (c && navigator.clipboard) {
      navigator.clipboard.writeText(c.getAttribute('data-copy')).then(function () {
        var old = c.getAttribute('aria-label'); c.setAttribute('aria-label', 'Đã sao chép'); c.classList.add('copied');
        setTimeout(function () { c.setAttribute('aria-label', old); c.classList.remove('copied'); }, 1400);
      });
    }
  });
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]');
    if (o) { var d = document.getElementById(o.getAttribute('data-open')); if (d && d.showModal) d.showModal(); }
    if (e.target.closest('[data-close]')) { var p = e.target.closest('dialog'); if (p) p.close(); }
  });
  var dlg = document.getElementById('confirm-dialog');
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm]');
    if (!b || !dlg || !dlg.showModal) return;
    if (!b.hasAttribute('formnovalidate') && b.form && !b.form.checkValidity()) return;      // để trình duyệt báo ô bắt buộc trước
    e.preventDefault();
    document.getElementById('confirm-text').textContent = b.getAttribute('data-confirm');
    dlg.returnValue = '';
    dlg.onclose = function () { if (dlg.returnValue === 'yes') b.form.requestSubmit(b.name ? b : undefined); };
    dlg.showModal();
  });
  document.querySelectorAll('.rule-fields select[name=rule_type]').forEach(function (s) {
    var box = s.closest('.rule-fields');
    var sync = function () { box.setAttribute('data-type', s.value); };
    s.addEventListener('change', sync); sync();
  });

  /* Menu thả xuống: đặt position:fixed theo vị trí nút để không bị cắt bởi vùng cuộn của bảng/thanh bên. */
  function place(d) {
    var p = d.querySelector('.menu-panel'), t = d.querySelector('summary');
    if (!p || !t) return;
    var r = t.getBoundingClientRect(), row = d.classList.contains('rowmenu');
    p.style.position = 'fixed'; p.style.right = 'auto'; p.style.bottom = 'auto';
    var w = row ? Math.min(340, innerWidth - 24) : r.width;
    p.style.width = w + 'px';
    p.style.left = Math.max(12, Math.min(row ? r.right - w : r.left, innerWidth - w - 12)) + 'px';
    var h = p.offsetHeight, below = r.bottom + 4;
    p.style.top = (below + h > innerHeight - 12 ? Math.max(12, r.top - h - 4) : below) + 'px';
  }
  document.querySelectorAll('details.menu, details.rowmenu').forEach(function (d) {
    d.addEventListener('toggle', function () { if (d.open) place(d); });
  });
  var closeMenus = function () { document.querySelectorAll('details.menu[open], details.rowmenu[open]').forEach(function (d) { d.removeAttribute('open'); }); };
  window.addEventListener('resize', closeMenus);
  window.addEventListener('scroll', closeMenus, true);

  /* Nút đang gửi: chặn bấm hai lần, báo bận. Đặt sau khi dữ liệu form đã được lấy. */
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!(f instanceof HTMLFormElement) || f.method === 'dialog') return;
    var b = e.submitter || f.querySelector('button:not([type=button])'); if (!b) return;
    setTimeout(function () { b.setAttribute('aria-busy', 'true'); b.disabled = true; }, 0);
  });
  window.addEventListener('pageshow', function () { document.querySelectorAll('[aria-busy]').forEach(function (b) { b.removeAttribute('aria-busy'); b.disabled = false; }); });

  /* Mở ngăn kéo nội dòng (thay cho hộp thoại khi việc không cần ngắt luồng). */
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open-drawer]'); if (!o) return;
    var d = document.getElementById(o.getAttribute('data-open-drawer')); if (!d) return;
    e.preventDefault(); d.open = true; d.scrollIntoView({ block: 'center', behavior: 'smooth' });
    var i = d.querySelector('input:not([type=hidden]),textarea,select'); if (i) i.focus({ preventScroll: true });
  });
})();

/* Hộp chọn nhiều người dùng chung: tìm, lọc theo nhóm, chọn tất cả đang hiển thị, Shift+bấm để chọn cả dải.
   Dùng cho thành viên nhóm (project.php) và cho quy tắc "Danh sách người dùng" (version.php). */
function ffmPicker(dlg, D) {
  var $ = function (s) { return dlg.querySelector(s); };
  var list = $('[data-picker-list]'), search = $('[data-picker-search]'), filter = $('[data-picker-filter]');
  var selected = new Set(), visible = [], lastIdx = -1, exclude = null, MAX_ROWS = 1200;

  function userGroups(u) { return D.userGroups[u] || []; }
  function render() {
    var q = search.value.trim().toLowerCase(), f = filter.value;
    visible = D.users.filter(function (u) {
      if (exclude && exclude(u)) return false;
      if (q && u.toLowerCase().indexOf(q) === -1) return false;
      var gs = userGroups(u);
      if (f === '_none') return gs.length === 0;
      return !f || gs.indexOf(f) !== -1;
    });
    var frag = document.createDocumentFragment();
    visible.slice(0, MAX_ROWS).forEach(function (u, i) {
      var l = document.createElement('label'); l.className = 'picker-row';
      var c = document.createElement('input'); c.type = 'checkbox'; c.value = u; c.checked = selected.has(u); c.setAttribute('data-i', i);
      var n = document.createElement('code'); n.textContent = u;
      var g = document.createElement('small'); g.textContent = userGroups(u).join(' · ');
      l.appendChild(c); l.appendChild(n); l.appendChild(g); frag.appendChild(l);
    });
    list.replaceChildren(frag);
    if (!visible.length) { var e = document.createElement('p'); e.className = 'picker-empty'; e.textContent = 'Không có người dùng nào khớp.'; list.appendChild(e); }
    count();
  }
  function count() {
    $('[data-picker-count]').textContent = 'Đã chọn ' + selected.size.toLocaleString('vi-VN') + ' · đang hiển thị ' + visible.length.toLocaleString('vi-VN');
  }

  list.addEventListener('click', function (e) {
    var c = e.target.closest('input[type=checkbox]'); if (!c) return;
    var i = +c.getAttribute('data-i');
    if (e.shiftKey && lastIdx >= 0) {                                    // chọn/bỏ cả dải giữa lần bấm trước và lần này
      var a = Math.min(lastIdx, i), b = Math.max(lastIdx, i);
      list.querySelectorAll('input[type=checkbox]').forEach(function (x) {
        var k = +x.getAttribute('data-i');
        if (k >= a && k <= b) { x.checked = c.checked; c.checked ? selected.add(x.value) : selected.delete(x.value); }
      });
    } else { c.checked ? selected.add(c.value) : selected.delete(c.value); }
    lastIdx = i; count();
  });
  $('[data-picker-all]').addEventListener('click', function () { visible.forEach(function (u) { selected.add(u); }); render(); });
  $('[data-picker-none]').addEventListener('click', function () { visible.forEach(function (u) { selected.delete(u); }); render(); });
  search.addEventListener('input', function () { lastIdx = -1; render(); });
  filter.addEventListener('change', function () { lastIdx = -1; render(); });
  search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

  return {
    /* o.selected: mã đang được chọn; o.exclude(u): true thì ẩn người đó khỏi danh sách. */
    open: function (o) {
      selected = new Set(o.selected || []); exclude = o.exclude || null; lastIdx = -1;
      search.value = ''; filter.value = ''; render(); dlg.showModal(); search.focus();
    },
    /* Mã đã chọn: người có trong danh sách theo thứ tự danh sách, sau đó là mã gõ tay chưa có trong danh sách (được giữ nguyên). */
    selected: function () {
      var known = new Set(D.users), inList = D.users.filter(function (u) { return selected.has(u); });
      var extra = Array.from(selected).filter(function (u) { return !known.has(u); }).sort();
      return inList.concat(extra);
    }
  };
}

/* Thành viên nhóm. */
(function () {
  var dlg = document.getElementById('picker'), dataEl = document.getElementById('picker-data');
  if (!dlg || !dataEl) return;
  var D = JSON.parse(dataEl.textContent), form = dlg.querySelector('[data-picker-form]');
  var $ = function (s) { return dlg.querySelector(s); };
  var P = ffmPicker(dlg, D);

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-picker]'); if (!b) return;
    var mode = b.getAttribute('data-picker'), group = D.groups[b.getAttribute('data-group')]; if (!group) return;
    form.elements.group_id.value = b.getAttribute('data-group');
    form.elements.action.value = mode === 'set' ? 'set_group_members' : 'add_group_members';
    form.elements.new_ids.value = '';
    $('[data-picker-new]').hidden = mode !== 'add';
    $('[data-picker-title]').textContent = (mode === 'set' ? 'Sửa thành viên nhóm ' : 'Thêm thành viên vào nhóm ') + group.name;
    $('[data-picker-note]').textContent = mode === 'set'
      ? 'Tick những người thuộc nhóm. Lưu sẽ thay toàn bộ danh sách bằng những người đang được tick.'
      : 'Chỉ hiện người chưa ở trong nhóm. Người đã có trong nhóm được giữ nguyên.';
    $('[data-picker-submit]').textContent = mode === 'set' ? 'Lưu danh sách' : 'Thêm vào nhóm';
    P.open({
      selected: mode === 'set' ? group.members : [],
      exclude: mode === 'add' ? function (u) { return group.members.indexOf(u) !== -1; } : null
    });
  });
  form.addEventListener('submit', function () { form.elements.members.value = P.selected().join('\n'); });
})();

/* Quy tắc "Danh sách người dùng": chọn nhiều người rồi ghi vào ô mã người dùng (vẫn gõ hoặc dán tay được). */
(function () {
  var dlg = document.getElementById('rule-picker'), dataEl = document.getElementById('rule-picker-data');
  if (!dlg || !dataEl) return;
  var ta = document.querySelector('textarea[name=users]'); if (!ta) return;
  var P = ffmPicker(dlg, JSON.parse(dataEl.textContent));
  var out = document.querySelector('[data-users-count]');
  function parse() { return ta.value.split(/[\s,;]+/).filter(Boolean); }
  function show() {
    var n = new Set(parse()).size;
    if (out) out.textContent = n ? 'Đã chọn ' + n.toLocaleString('vi-VN') + ' người' : 'Chưa chọn ai';
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-rule-picker]')) P.open({ selected: parse() });
    if (e.target.closest('[data-rule-picker-apply]')) { ta.value = P.selected().join(' '); show(); dlg.close(); ta.focus(); }
  });
  ta.addEventListener('input', show); show();
})();

/* Form lọc đánh dấu data-autosubmit tự gửi khi đổi ô chọn (ô tìm kiếm gửi bằng Enter). */
document.addEventListener('change', function (e) {
  var f = e.target.closest && e.target.closest('form[data-autosubmit]');
  if (f && e.target.tagName === 'SELECT') f.requestSubmit ? f.requestSubmit() : f.submit();
});

/* Ô chọn tệp tiếng Việt: hiện tên tệp đã chọn bên cạnh nút. */
document.addEventListener('change', function (e) {
  var inp = e.target;
  if (!inp.matches || !inp.matches('input[type=file].sr-only')) return;
  var name = inp.closest('.filepick').querySelector('[data-file-name]');
  if (name) name.textContent = inp.files && inp.files[0] ? inp.files[0].name : 'Chưa chọn tệp';
});

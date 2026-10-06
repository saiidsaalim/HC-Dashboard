(function () {
  var modal = document.getElementById('login-modal');

  function openLogin() {
    if (!modal) return;
    modal.classList.add('on');
    var input = modal.querySelector('input');
    if (input) setTimeout(function () { input.focus(); }, 50);
  }
  function closeLogin() { if (modal) modal.classList.remove('on'); }

  // Semua elemen [data-open-login] membuka popup (href tetap ke halaman login sebagai cadangan tanpa JS).
  document.querySelectorAll('[data-open-login]').forEach(function (el) {
    el.addEventListener('click', function (e) { e.preventDefault(); openLogin(); });
  });
  if (modal) {
    modal.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', closeLogin); });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeLogin(); });
    if (modal.hasAttribute('data-auto-open')) openLogin(); // buka lagi jika login gagal
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLogin(); });

  // Pilih unit -> tampilkan panel workspace unit tersebut.
  var empty = document.getElementById('ws-empty');
  document.querySelectorAll('.p-ucard[data-unit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-unit');
      document.querySelectorAll('.p-ucard[data-unit]').forEach(function (b) {
        b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
      });
      if (empty) empty.hidden = true;
      document.querySelectorAll('.p-ws').forEach(function (p) { p.hidden = (p.id !== 'ws-' + key); });
      var ws = document.getElementById('workspace');
      if (ws) ws.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
})();

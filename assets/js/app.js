// RRHH — utilidades generales
(function () {
  var b = document.getElementById('burger'), s = document.getElementById('sidebar');
  if (b && s) b.addEventListener('click', function () { s.classList.toggle('open'); });

  // Confirmaciones: <button data-confirm="¿Seguro?">
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-confirm]');
    if (t && !confirm(t.getAttribute('data-confirm'))) { ev.preventDefault(); ev.stopPropagation(); }
  }, true);

  // Filtro de tablas: <input data-filter="#tabla">
  document.querySelectorAll('[data-filter]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var q = inp.value.toLowerCase();
      document.querySelectorAll(inp.getAttribute('data-filter') + ' tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
      });
    });
  });

  // Marcar todos: <input type=checkbox data-checkall=".chk-row">
  document.querySelectorAll('[data-checkall]').forEach(function (c) {
    c.addEventListener('change', function () {
      document.querySelectorAll(c.getAttribute('data-checkall')).forEach(function (x) {
        if (x.closest('tr') && x.closest('tr').style.display === 'none') return;
        x.checked = c.checked;
      });
    });
  });

  // Vista previa de imágenes: <input type=file data-preview="#img">
  document.querySelectorAll('[data-preview]').forEach(function (f) {
    f.addEventListener('change', function () {
      var img = document.querySelector(f.getAttribute('data-preview'));
      if (img && f.files[0]) { img.src = URL.createObjectURL(f.files[0]); img.style.display = 'block'; }
    });
  });
})();

window.csrf = function () { var m = document.querySelector('meta[name=csrf]'); return m ? m.content : ''; };
window.fmt = function (n) { return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

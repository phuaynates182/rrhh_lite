// Cálculo de boletas por fila / selección / lote (AJAX)
(function () {
  var tab = document.getElementById('tabPla');
  if (!tab || tab.getAttribute('data-edit') !== '1') return;
  var periodo = tab.getAttribute('data-periodo');
  var prog = document.getElementById('prog');

  function rowData(tr) {
    var fd = new FormData();
    fd.append('periodo', periodo);
    fd.append('emp', tr.getAttribute('data-emp'));
    tr.querySelectorAll('input[name]').forEach(function (i) { if (i.type !== 'checkbox') fd.append(i.name, i.value); });
    return fd;
  }

  function calc(tr) {
    var btn = tr.querySelector('.row-save');
    if (btn) { btn.disabled = true; btn.textContent = '…'; }
    return fetch('planilla_api.php', {
      method: 'POST', body: rowData(tr),
      headers: { 'X-CSRF-Token': window.csrf(), 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (btn) { btn.disabled = false; btn.textContent = '✏️ Recalcular'; }
      if (!j.ok) { tr.style.outline = '2px solid #fb7185'; tr.title = j.error; alert(j.error); return false; }
      tr.style.outline = ''; tr.title = ''; tr.classList.remove('sin');
      tr.querySelector('.c-bruto').textContent = fmt(j.bruto);
      tr.querySelector('.c-desc').textContent = fmt(j.descuentos);
      tr.querySelector('.c-neto').innerHTML = '<b>S/ ' + fmt(j.neto) + '</b>';
      tr.querySelector('.c-apor').textContent = fmt(j.aportes);
      var d = tr.querySelector('input[name=dias]'); if (d) d.value = j.dias;
      var act = tr.querySelector('.actions');
      if (!act.querySelector('a')) {
        var a = document.createElement('a'); a.className = 'btn sm ghost'; a.target = '_blank'; a.href = 'boleta.php?b=' + j.boleta; a.textContent = 'Boleta';
        act.insertBefore(a, act.firstChild);
      }
      return true;
    }).catch(function () { if (btn) { btn.disabled = false; btn.textContent = 'Reintentar'; } alert('Error de red.'); return false; });
  }

  function totales() {
    var b = 0, d = 0, n = 0, a = 0;
    tab.querySelectorAll('tbody tr[data-emp]').forEach(function (tr) {
      var g = function (c) { var t = tr.querySelector(c).textContent.replace(/[^0-9.\-]/g, ''); return t ? parseFloat(t) : 0; };
      b += g('.c-bruto'); d += g('.c-desc'); n += g('.c-neto'); a += g('.c-apor');
    });
    document.getElementById('t-bruto').textContent = fmt(b);
    document.getElementById('t-desc').textContent = fmt(d);
    document.getElementById('t-neto').textContent = 'S/ ' + fmt(n);
    document.getElementById('t-apor').textContent = fmt(a);
  }

  tab.addEventListener('click', function (ev) {
    var b = ev.target.closest('.row-save');
    if (!b) return;
    ev.preventDefault();
    calc(b.closest('tr')).then(totales);
  });

  function lote(rows) {
    if (!rows.length) { alert('No hay colaboradores para calcular.'); return; }
    prog.style.display = 'block';
    var bar = prog.querySelector('i'), i = 0, fallos = 0;
    (function next() {
      if (i >= rows.length) {
        bar.style.width = '100%'; totales();
        setTimeout(function () { prog.style.display = 'none'; bar.style.width = '0'; if (!fallos) location.reload(); }, 500);
        return;
      }
      calc(rows[i]).then(function (ok) { if (!ok) fallos++; i++; bar.style.width = Math.round(i * 100 / rows.length) + '%'; next(); });
    })();
  }
  var visibles = function () { return Array.prototype.filter.call(tab.querySelectorAll('tbody tr[data-emp]'), function (tr) { return tr.style.display !== 'none'; }); };
  document.getElementById('btnSel').addEventListener('click', function () {
    lote(visibles().filter(function (tr) { return tr.querySelector('.chk-row').checked; }));
  });
  document.getElementById('btnAll').addEventListener('click', function () {
    if (confirm('¿Calcular las boletas de todos los colaboradores de la lista?')) lote(visibles());
  });
})();

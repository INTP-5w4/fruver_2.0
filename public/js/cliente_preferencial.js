// Modal para cambiar (o revertir) el estado de cliente preferencial.
(function () {
    'use strict';

    var modal   = document.getElementById('modalPreferencial');
    if (!modal) { return; }

    var form    = document.getElementById('form-pref');
    var inId    = document.getElementById('pref_id');
    var motivo  = document.getElementById('pref_motivo');
    var bloque  = document.getElementById('pref_motivo_bloque');
    var cont    = document.getElementById('pref_contador');
    var cliente = document.getElementById('pref_cliente');
    var actual  = document.getElementById('pref_actual');
    var radios  = form.querySelectorAll('input[name="modo"]');

    function modoElegido() {
        var r = form.querySelector('input[name="modo"]:checked');
        return r ? r.value : 'automatico';
    }

    // El motivo solo se pide cuando el cambio es manual (si / no)
    function actualizar() {
        var manual = modoElegido() !== 'automatico';
        bloque.style.display = manual ? 'block' : 'none';
        motivo.required = manual;
        cont.textContent = motivo.value.length + ' / ' + motivo.maxLength;
    }

    function textoActual(d) {
        var base = d.modo === 'si'  ? 'Ahora: preferencial siempre (manual).'
                 : d.modo === 'no'  ? 'Ahora: no preferencial (manual).'
                 : 'Ahora: automático, con ' + d.compras + ' compra(s).';
        if (d.modo !== 'automatico' && d.por) {
            base += ' Cambiado por ' + d.por + (d.fecha ? ' el ' + d.fecha : '') + '.';
        }
        return base;
    }

    function abrir(boton) {
        var d = boton.dataset;
        inId.value = d.id;
        cliente.textContent = d.nombre;               // textContent: nunca se interpreta como HTML
        actual.textContent  = textoActual(d);
        radios.forEach(function (r) { r.checked = (r.value === d.modo); });
        motivo.value = d.modo === 'automatico' ? '' : (d.motivo || '');
        actualizar();
        modal.style.display = 'block';
    }

    function cerrar() { modal.style.display = 'none'; }

    document.querySelectorAll('.btn-pref').forEach(function (b) {
        b.addEventListener('click', function () { if (!b.disabled) { abrir(b); } });
    });
    radios.forEach(function (r) { r.addEventListener('change', actualizar); });
    motivo.addEventListener('input', actualizar);
    document.getElementById('pref_cancelar').addEventListener('click', cerrar);
    modal.addEventListener('click', function (e) { if (e.target === modal) { cerrar(); } });
    form.addEventListener('submit', function () {
        // Al volver a automático el motivo no se guarda
        if (modoElegido() === 'automatico') { motivo.value = ''; }
    });
})();

// Entrada rápida: marca las filas con cantidad, cuenta cuántas se van a registrar
// y activa el botón solo cuando hay al menos una.
(function () {
    'use strict';

    var form = document.getElementById('form-rapida');
    if (!form) { return; }

    var botones    = form.querySelectorAll('.er-guardar');
    var contadores = form.querySelectorAll('.er-contador');
    var cants      = form.querySelectorAll('.er-cant');

    function actualizar() {
        var n = 0;
        cants.forEach(function (input) {
            var valor = parseFloat(input.value);
            var con   = isFinite(valor) && valor > 0;
            input.closest('tr').classList.toggle('er-con-cantidad', con);
            if (con) { n++; }
        });
        botones.forEach(function (b) { b.disabled = n === 0; });
        contadores.forEach(function (c) { c.textContent = '(' + n + ')'; });
    }

    cants.forEach(function (input) { input.addEventListener('input', actualizar); });

    // Enter en una cantidad pasa a la cantidad de abajo (captura rápida), sin enviar el formulario
    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.classList.contains('er-cant')) { return; }
        e.preventDefault();
        var lista = Array.prototype.slice.call(cants);
        var sig = lista[lista.indexOf(e.target) + 1];
        if (sig) { sig.focus(); sig.select(); }
    });

    // Evita doble envío
    form.addEventListener('submit', function () {
        botones.forEach(function (b) { b.disabled = true; });
    });

    // Precios precargados con la última entrada: al enfocar un campo que aún no se ha
    // tocado se vacía (para teclear el nuevo sin tener que borrar); al escribir queda
    // "tocado" y ya no se vacía; si se deja en blanco al salir, vuelve el valor
    // original (el backend también copia el último si el campo queda vacío).
    form.querySelectorAll('.er-precio').forEach(function (input) {
        var original = input.dataset.original || input.value || '';
        input.addEventListener('focus', function () {
            if (input.dataset.tocado !== '1') { input.value = ''; }
            else { input.select(); }
        });
        input.addEventListener('input', function () { input.dataset.tocado = '1'; });
        input.addEventListener('blur', function () {
            if (input.value.trim() === '') { input.value = original; input.dataset.tocado = ''; }
        });
    });

    actualizar();
})();

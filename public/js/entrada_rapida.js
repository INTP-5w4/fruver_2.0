// Entrada rápida: marca las filas con cantidad, cuenta cuántas se van a registrar
// y activa el botón solo cuando hay al menos una.
(function () {
    'use strict';

    var form = document.getElementById('form-rapida');
    if (!form) { return; }

    var boton    = document.getElementById('er-guardar');
    var contador = document.getElementById('er-contador');
    var cants    = form.querySelectorAll('.er-cant');

    function actualizar() {
        var n = 0;
        cants.forEach(function (input) {
            var valor = parseFloat(input.value);
            var con   = isFinite(valor) && valor > 0;
            input.closest('tr').classList.toggle('er-con-cantidad', con);
            if (con) { n++; }
        });
        if (boton)    { boton.disabled = n === 0; }
        if (contador) { contador.textContent = '(' + n + ')'; }
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
        if (boton) { boton.disabled = true; }
    });

    actualizar();
})();

/* privacidad_form.js — casillas "Seleccionar todas/todos" del formato de consentimiento.
   Cada casilla maestra (data-pv-todos="grupo") marca o desmarca las del mismo grupo
   (data-pv-item="grupo") y refleja su estado: marcada, parcial (indeterminada) o vacía.
   La maestra no tiene "name", así que no se envía con el formulario. */
(function () {
    'use strict';

    function inicializar(maestra) {
        var grupo = maestra.getAttribute('data-pv-todos');
        var contenedor = maestra.closest('[data-pv-grupo]') || document;
        var items = Array.prototype.slice.call(
            contenedor.querySelectorAll('input[data-pv-item="' + grupo + '"]')
        );
        if (!items.length) { return; }

        function sincronizar() {
            var marcadas = items.filter(function (i) { return i.checked; }).length;
            maestra.checked = marcadas === items.length;
            maestra.indeterminate = marcadas > 0 && marcadas < items.length;
        }

        maestra.addEventListener('change', function () {
            var estado = maestra.checked; // se guarda antes: sincronizar() puede cambiarla a mitad del bucle
            items.forEach(function (i) { i.checked = estado; });
            maestra.checked = estado;
            maestra.indeterminate = false;
        });
        items.forEach(function (i) { i.addEventListener('change', sincronizar); });
        sincronizar();
    }

    // Registro compacto: una casilla ("Acepto todo") marca la declaración, las finalidades y los canales.
    // Las opciones siguen siendo editables dentro de <details> ("Ver y editar mis opciones").
    function inicializarTotal(total) {
        var cont = total.closest('[data-pv-compacto]');
        if (!cont) { return; }
        function casillas() {
            return Array.prototype.slice.call(cont.querySelectorAll(
                'details input[type="checkbox"]:not([data-pv-todos])'
            ));
        }
        var tel = cont.querySelector('input[name="tel_contacto"]');
        var llamada = cont.querySelector('input[name="canal_telefono"]');
        var mensajeria = cont.querySelector('input[name="canal_mensajeria"]');

        function sincronizar() {
            var c = casillas();
            var n = c.filter(function (i) { return i.checked; }).length;
            total.checked = n === c.length;
            total.indeterminate = n > 0 && n < c.length;
            // El teléfono es obligatorio solo si se eligió llamada o WhatsApp.
            if (tel) { tel.required = !!((llamada && llamada.checked) || (mensajeria && mensajeria.checked)); }
        }

        total.addEventListener('change', function () {
            var estado = total.checked;
            casillas().forEach(function (i) {
                if (i.checked !== estado) {
                    i.checked = estado;
                    i.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            total.checked = estado;
            total.indeterminate = false;
            sincronizar();
            total.checked = estado;
            // Si quedó elegida una vía de contacto y falta el teléfono, se muestra el campo.
            if (estado && tel && tel.required && !tel.value.trim()) {
                var d = cont.querySelector('details');
                if (d) { d.open = true; }
                tel.focus();
            }
        });
        cont.addEventListener('change', function (ev) { if (ev.target !== total) { sincronizar(); } });
        sincronizar();
    }

    // Un campo obligatorio dentro del bloque cerrado no se puede enfocar: se abre para mostrar el aviso.
    document.addEventListener('invalid', function (ev) {
        var d = ev.target.closest ? ev.target.closest('details.pv-personalizar') : null;
        if (d && !d.open) {
            d.open = true;
            setTimeout(function () { if (ev.target.reportValidity) { ev.target.reportValidity(); } }, 0);
        }
    }, true);

    function arrancar() {
        document.querySelectorAll('input[data-pv-todos]').forEach(inicializar);
        document.querySelectorAll('input[data-pv-total]').forEach(inicializarTotal);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();

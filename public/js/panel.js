/**
 * panel.js — Comportamiento común del panel de Fruver (se carga desde partials/estilos_panel.php).
 *  - Los avisos flotantes (.flash-container) se ocultan solos a los 4 segundos.
 *  - El botón ☰ abre y cierra el menú lateral en celular.
 *  - La tecla Escape cierra el menú y cualquier ventana modal de W3 abierta.
 */
(function () {
    'use strict';

    function ocultarAvisos() {
        document.querySelectorAll('.flash-container').forEach(function (caja) {
            if (!caja.querySelector('.w3-panel')) { return; }
            setTimeout(function () {
                caja.classList.add('oculto');
                setTimeout(function () { caja.hidden = true; }, 450);
            }, 4000);
        });
    }

    function menu() {
        var boton = document.querySelector('[data-abrir-menu]');
        if (!boton) { return; }
        if (!document.querySelector('.sidebar')) { boton.hidden = true; return; }

        function fijar(abierto) {
            document.body.classList.toggle('menu-abierto', abierto);
            boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            boton.setAttribute('aria-label', abierto ? 'Cerrar el menú' : 'Abrir el menú');
        }

        boton.addEventListener('click', function () {
            fijar(!document.body.classList.contains('menu-abierto'));
        });
        document.querySelectorAll('[data-cerrar-menu]').forEach(function (el) {
            el.addEventListener('click', function () { fijar(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { fijar(false); }
        });
    }

    function modales() {
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') { return; }
            document.querySelectorAll('.w3-modal').forEach(function (m) {
                if (m.style.display === 'block') { m.style.display = 'none'; }
            });
        });
    }

    function iniciar() { ocultarAvisos(); menu(); modales(); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();

/*
 * Búsqueda mientras se escribe en las listas del panel (app/Views/partials/buscador_lista.php).
 *
 * 180 ms después de la última tecla pide la misma lista al servidor con ?buscar=... y
 * reemplaza solo la tabla (el elemento con data-busqueda-resultados). Así la búsqueda
 * sigue siendo del servidor: encuentra en todas las páginas, no solo en la visible.
 *
 * Cuidados de rendimiento:
 *  - una sola petición en vuelo: la anterior se cancela (AbortController);
 *  - no se repite la búsqueda si el texto no cambió;
 *  - se reemplaza solo la tabla, no la página.
 * Sin JavaScript, el formulario funciona igual con el botón "Buscar".
 */
(function () {
    'use strict';

    var ESPERA_MS = 180;

    function preparar(form) {
        var input = form.querySelector('input[name="buscar"]');
        var zona = document.querySelector('[data-busqueda-resultados]');
        if (!input || !zona || !window.fetch || !window.DOMParser) { return; }

        var limpiar = form.querySelector('[data-busqueda-limpiar]');
        var estado = form.querySelector('[data-busqueda-estado]');
        var temporizador = null;
        var control = null;
        var ultimo = input.value.trim();

        function buscar() {
            var texto = input.value.trim();
            if (texto === ultimo) { return; }
            ultimo = texto;

            var url = new URL(form.action, location.href);
            if (texto !== '') { url.searchParams.set('buscar', texto); }

            if (control) { control.abort(); }
            control = 'AbortController' in window ? new AbortController() : null;
            zona.setAttribute('aria-busy', 'true');
            zona.classList.add('buscando');

            fetch(url.href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: control ? control.signal : undefined
            }).then(function (r) {
                if (!r.ok || r.redirected) { throw new Error('respuesta ' + r.status); }
                return r.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var nueva = doc.querySelector('[data-busqueda-resultados]');
                if (!nueva) { throw new Error('sin resultados en la respuesta'); }
                zona.innerHTML = nueva.innerHTML;

                if (limpiar) { limpiar.hidden = texto === ''; }
                if (estado) {
                    var filas = zona.querySelectorAll('tbody tr').length;
                    estado.textContent = texto === '' ? '' : (filas === 0 ? 'Sin resultados' : filas + (filas === 1 ? ' resultado' : ' resultados') + ' en esta página');
                }
                history.replaceState(null, '', url.href);
            }).catch(function (e) {
                if (e && e.name === 'AbortError') { return; }
                // Si algo falla, se busca de la forma normal (recargando la página).
                form.submit();
            }).then(function () {
                zona.removeAttribute('aria-busy');
                zona.classList.remove('buscando');
            });
        }

        input.addEventListener('input', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(buscar, ESPERA_MS);
        });

        // Enter o el botón "Buscar": sin recargar, de inmediato.
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            clearTimeout(temporizador);
            ultimo = null;
            buscar();
        });

        if (limpiar) {
            limpiar.addEventListener('click', function (ev) {
                ev.preventDefault();
                input.value = '';
                clearTimeout(temporizador);
                buscar();
                input.focus();
            });
        }
    }

    function iniciar() {
        document.querySelectorAll('form[data-busqueda-viva]').forEach(preparar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();

/*
 * Comunicaciones con clientes (app/Views/comunicaciones.php).
 *
 *  - El mensaje de cada persona se rearma mientras se escribe (180 ms después de la última
 *    tecla): mismas etiquetas {nombre}, {productos}, {pedido}, {tienda} y mismo pie que
 *    Comunicaciones::personalizar() en el servidor. También se actualizan los enlaces de
 *    WhatsApp y el mensaje que se manda por correo. Sin JavaScript queda el botón
 *    "Actualizar mensajes", que hace lo mismo en el servidor.
 *  - Al pulsar "Enviar" (WhatsApp) la fila queda marcada "Abierto" en este navegador,
 *    durante el día, para saber a quién ya se le escribió.
 */
(function () {
    'use strict';

    var LIMITE = 500;

    function iniciar() {
        var form = document.getElementById('com-form-mensaje');
        var area = document.getElementById('mensaje');
        if (!form || !area) { return; }

        var pie = form.getAttribute('data-pie') || '';
        var finalidad = (form.querySelector('[name="finalidad"]') || {}).value || '';
        var correo = document.querySelector('[data-com-mensaje-correo]');
        var filas = Array.prototype.slice.call(document.querySelectorAll('[data-com-fila]'));
        var temporizador = null;

        // Con JavaScript no hace falta el botón: avisamos que es en vivo.
        var boton = form.querySelector('[data-com-actualizar]');
        var vivo = form.querySelector('[data-com-vivo]');
        if (boton) { boton.hidden = true; boton.style.display = 'none'; }
        if (vivo) { vivo.hidden = false; }

        // Misma limpieza que Comunicaciones::texto(): sin caracteres de control ni marcas de dirección.
        function limpiar(t) {
            return t.replace(/\r\n/g, '\n')
                .replace(/(?!\n)[\u0000-\u001F\u007F-\u009F]|[‪-‮⁦-⁩]/g, '')
                .trim()
                .slice(0, LIMITE);
        }

        // Una sola pasada, igual que strtr() en el servidor: lo que trae un valor (por ejemplo un
        // nombre que contenga "{tienda}") se deja tal cual y no se vuelve a sustituir.
        function personalizar(plantilla, reemplazos) {
            return plantilla.replace(/\{[a-z]+\}/g, function (etiqueta) {
                return Object.prototype.hasOwnProperty.call(reemplazos, etiqueta) ? String(reemplazos[etiqueta]) : etiqueta;
            }) + pie;
        }

        function pintarTexto(celda, texto) {
            celda.textContent = '';
            texto.split('\n').forEach(function (linea, i) {
                if (i > 0) { celda.appendChild(document.createElement('br')); }
                celda.appendChild(document.createTextNode(linea));
            });
        }

        function actualizar() {
            var plantilla = limpiar(area.value);
            if (correo) { correo.value = plantilla; }

            filas.forEach(function (fila) {
                var reemplazos = {};
                try { reemplazos = JSON.parse(fila.getAttribute('data-reemplazos') || '{}'); } catch (e) { reemplazos = {}; }
                var texto = personalizar(plantilla, reemplazos);
                var celda = fila.querySelector('[data-com-texto]');
                if (celda) { pintarTexto(celda, texto); }
                var wa = fila.querySelector('[data-com-wa]');
                if (wa) { wa.href = 'https://wa.me/' + (fila.getAttribute('data-tel') || '') + '?text=' + encodeURIComponent(texto); }
            });

            // La dirección de la página guarda el mensaje (al recargar o cambiar de pestaña no se pierde).
            try {
                var url = new URL(location.href);
                url.searchParams.set('mensaje', plantilla);
                history.replaceState(null, '', url);
            } catch (e) { /* sin historial: no pasa nada */ }
        }

        area.addEventListener('input', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(actualizar, 180);
        });

        // ── "Abierto": a quién ya se le abrió WhatsApp hoy ─────────────────
        var clave = 'fruver-com-abiertos-' + finalidad + '-' + new Date().toISOString().slice(0, 10);
        var abiertos = [];
        try { abiertos = JSON.parse(localStorage.getItem(clave) || '[]'); } catch (e) { abiertos = []; }

        function marcar(fila) {
            var etiqueta = fila.querySelector('[data-com-abierto]');
            if (etiqueta) { etiqueta.hidden = false; }
            fila.classList.add('com-fila-abierta');
        }

        filas.forEach(function (fila) {
            var tel = fila.getAttribute('data-tel');
            if (tel && abiertos.indexOf(tel) !== -1) { marcar(fila); }
            var wa = fila.querySelector('[data-com-wa]');
            if (!wa) { return; }
            wa.addEventListener('click', function () {
                marcar(fila);
                if (tel && abiertos.indexOf(tel) === -1) {
                    abiertos.push(tel);
                    try { localStorage.setItem(clave, JSON.stringify(abiertos)); } catch (e) { /* modo privado */ }
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();

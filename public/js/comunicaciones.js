/*
 * Comunicaciones con clientes (app/Views/comunicaciones.php).
 *
 *  - El mensaje de cada persona se rearma mientras se escribe (180 ms después de la última
 *    tecla): mismas etiquetas {nombre}, {productos}, {pedido}, {tienda} y mismo pie que
 *    Comunicaciones::personalizar() en el servidor. También se actualizan los enlaces de
 *    WhatsApp y el mensaje que se manda por correo. Sin JavaScript queda el botón
 *    "Actualizar mensajes", que hace lo mismo en el servidor.
 *  - Groserías: cada cambio se revisa en el servidor (GET comunicaciones/revisar; la lista no
 *    llega al navegador). Si hay alguna, se avisa y se desactivan los botones de envío.
 *  - "Ver mensaje" abre el mensaje completo de la persona en una ventana (modal).
 *  - Al abrir WhatsApp la fila queda marcada "Abierto" en este navegador, durante el día.
 *  - "Enviar a todos por WhatsApp" abre los chats uno tras otro, un clic por persona.
 */
(function () {
    'use strict';

    var LIMITE = 500;

    function iniciar() {
        var form = document.getElementById('com-form-mensaje');
        var area = document.getElementById('mensaje');
        if (!form || !area) { return; }

        var pie = form.getAttribute('data-pie') || '';
        var urlRevisar = form.getAttribute('data-revisar') || '';
        var finalidad = (form.querySelector('[name="finalidad"]') || {}).value || '';
        var correo = document.querySelector('[data-com-mensaje-correo]');
        var avisoGroseria = form.querySelector('[data-com-groseria]');
        var filas = Array.prototype.slice.call(document.querySelectorAll('[data-com-fila]'));
        var temporizador = null;
        var consulta = null;
        var bloqueado = !!(avisoGroseria && !avisoGroseria.hidden);

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

        function reemplazosDe(fila) {
            try { return JSON.parse(fila.getAttribute('data-reemplazos') || '{}'); } catch (e) { return {}; }
        }

        /** Mensaje actual (ya personalizado) de una persona. */
        function textoDe(fila) {
            return personalizar(limpiar(area.value), reemplazosDe(fila));
        }

        function enlaceWa(fila, texto) {
            return 'https://wa.me/' + (fila.getAttribute('data-tel') || '') + '?text=' + encodeURIComponent(texto);
        }

        function pintarTexto(celda, texto) {
            celda.textContent = '';
            texto.split('\n').forEach(function (linea, i) {
                if (i > 0) { celda.appendChild(document.createElement('br')); }
                celda.appendChild(document.createTextNode(linea));
            });
        }

        // ── Groserías: botones de envío activos o no ────────────────────────
        function aplicarBloqueo(valor) {
            bloqueado = valor;
            if (avisoGroseria) { avisoGroseria.hidden = !valor; }
            document.querySelectorAll('[data-com-wa], [data-com-d-wa]').forEach(function (a) {
                if (valor) { a.setAttribute('aria-disabled', 'true'); } else { a.removeAttribute('aria-disabled'); }
            });
            var envioCorreo = document.querySelector('.com-form-correo [type="submit"]');
            if (envioCorreo) { envioCorreo.disabled = valor; }
            pintarCola();
        }

        function revisar(plantilla) {
            if (!urlRevisar || !window.fetch) { return; }
            if (consulta) { consulta.abort(); }
            consulta = window.AbortController ? new AbortController() : null;
            fetch(urlRevisar + '?texto=' + encodeURIComponent(plantilla), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: consulta ? consulta.signal : undefined
            }).then(function (r) { return r.json(); })
              .then(function (j) { aplicarBloqueo(!j.ok); })
              .catch(function () { /* sin conexión o cancelada: el servidor vuelve a revisar al enviar el correo */ });
        }

        function actualizar() {
            var plantilla = limpiar(area.value);
            if (correo) { correo.value = plantilla; }

            filas.forEach(function (fila) {
                var texto = personalizar(plantilla, reemplazosDe(fila));
                var celda = fila.querySelector('[data-com-texto]');
                if (celda) { pintarTexto(celda, texto); }
                var wa = fila.querySelector('[data-com-wa]');
                if (wa) { wa.href = enlaceWa(fila, texto); }
            });
            revisar(plantilla);

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

        function guardar() {
            try { localStorage.setItem(clave, JSON.stringify(abiertos)); } catch (e) { /* modo privado */ }
        }

        function abrir(fila) {
            var tel = fila.getAttribute('data-tel');
            marcar(fila);
            if (tel && abiertos.indexOf(tel) === -1) { abiertos.push(tel); guardar(); }
            pintarCola();
        }

        filas.forEach(function (fila) {
            var tel = fila.getAttribute('data-tel');
            if (tel && abiertos.indexOf(tel) !== -1) { marcar(fila); }
            var wa = fila.querySelector('[data-com-wa]');
            if (!wa) { return; }
            wa.addEventListener('click', function (ev) {
                if (bloqueado) { ev.preventDefault(); return; }
                wa.href = enlaceWa(fila, textoDe(fila));
                abrir(fila);
            });
        });

        // ── Ver el mensaje completo en una ventana ──────────────────────────
        var dialogo = document.getElementById('com-dialogo');
        var filaDialogo = null;

        function abrirDialogo(fila) {
            if (!dialogo) { return; }
            filaDialogo = fila;
            var texto = textoDe(fila);
            dialogo.querySelector('[data-com-d-nombre]').textContent = fila.getAttribute('data-nombre') || '';
            pintarTexto(dialogo.querySelector('[data-com-d-texto]'), texto);

            var waFila = fila.querySelector('[data-com-wa]');
            var wa = dialogo.querySelector('[data-com-d-wa]');
            var sinWa = dialogo.querySelector('[data-com-d-sinwa]');
            wa.hidden = !waFila;
            if (waFila) { wa.href = enlaceWa(fila, texto); }
            if (bloqueado) { wa.setAttribute('aria-disabled', 'true'); } else { wa.removeAttribute('aria-disabled'); }
            var motivo = fila.querySelector('[data-com-sinwa]');
            sinWa.hidden = !!waFila || !motivo;
            sinWa.textContent = motivo ? motivo.textContent.trim() + '.' : '';

            var copiar = dialogo.querySelector('[data-com-d-copiar] span');
            if (copiar) { copiar.textContent = 'Copiar texto'; }

            if (typeof dialogo.showModal === 'function') { dialogo.showModal(); } else { dialogo.setAttribute('open', ''); }
        }

        function cerrarDialogo() {
            if (!dialogo) { return; }
            if (typeof dialogo.close === 'function') { dialogo.close(); } else { dialogo.removeAttribute('open'); }
        }

        if (dialogo) {
            filas.forEach(function (fila) {
                var ver = fila.querySelector('[data-com-ver]');
                if (!ver) { return; }
                ver.hidden = false;
                ver.addEventListener('click', function () { abrirDialogo(fila); });
                // La vista previa también abre el mensaje.
                var previa = fila.querySelector('[data-com-texto]');
                if (previa) { previa.addEventListener('click', function () { abrirDialogo(fila); }); }
            });
            dialogo.querySelectorAll('[data-com-d-cerrar]').forEach(function (b) { b.addEventListener('click', cerrarDialogo); });
            // Clic fuera de la ventana (en el fondo) la cierra.
            dialogo.addEventListener('click', function (ev) { if (ev.target === dialogo) { cerrarDialogo(); } });
            dialogo.querySelector('[data-com-d-wa]').addEventListener('click', function (ev) {
                if (bloqueado || !filaDialogo) { ev.preventDefault(); return; }
                abrir(filaDialogo);
            });
            dialogo.querySelector('[data-com-d-copiar]').addEventListener('click', function () {
                if (!filaDialogo) { return; }
                var etiqueta = this.querySelector('span');
                var texto = textoDe(filaDialogo);
                var listo = function () { if (etiqueta) { etiqueta.textContent = 'Copiado'; } };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(texto).then(listo, function () { /* sin permiso: no pasa nada */ });
                }
            });
        }

        // ── "Enviar a todos por WhatsApp": un chat tras otro ────────────────
        // WhatsApp no deja mandar en masa desde un enlace y el navegador bloquea abrir varias
        // pestañas de golpe; por eso cada clic abre el chat de la siguiente persona pendiente.
        var cola = document.querySelector('[data-com-cola]');
        var conWa = filas.filter(function (f) { return f.getAttribute('data-tel') && f.querySelector('[data-com-wa]'); });

        function pendientes() {
            return conWa.filter(function (f) { return abiertos.indexOf(f.getAttribute('data-tel')) === -1; });
        }

        function pintarCola() {
            if (!cola || !conWa.length) { return; }
            var faltan = pendientes();
            var hechos = conWa.length - faltan.length;
            var botonCola = cola.querySelector('[data-com-cola-boton]');
            var texto = cola.querySelector('[data-com-cola-texto]');
            var estado = cola.querySelector('[data-com-cola-estado]');
            var barra = cola.querySelector('[data-com-cola-barra]');
            var reiniciar = cola.querySelector('[data-com-cola-reiniciar]');

            barra.style.width = Math.round(hechos * 100 / conWa.length) + '%';
            reiniciar.hidden = hechos === 0;

            if (bloqueado) {
                botonCola.disabled = true;
                texto.textContent = 'Enviar a todos por WhatsApp (' + conWa.length + ')';
                estado.textContent = 'Corrige el mensaje: tiene palabras que no están permitidas.';
                return;
            }
            if (faltan.length === 0) {
                botonCola.disabled = true;
                texto.textContent = 'Listo: se abrieron los ' + conWa.length + ' chats';
                estado.textContent = 'Revisa en WhatsApp que hayas pulsado Enviar en cada uno.';
                return;
            }
            botonCola.disabled = false;
            var siguiente = faltan[0].getAttribute('data-nombre') || 'siguiente persona';
            texto.textContent = hechos === 0
                ? 'Enviar a todos por WhatsApp (' + conWa.length + ')'
                : 'Siguiente: ' + siguiente + ' (' + (hechos + 1) + ' de ' + conWa.length + ')';
            estado.textContent = hechos === 0
                ? 'Primero: ' + siguiente + '.'
                : hechos + ' de ' + conWa.length + ' abiertos hoy.';
        }

        if (cola && conWa.length) {
            cola.hidden = false;
            cola.querySelector('[data-com-cola-boton]').addEventListener('click', function () {
                var fila = pendientes()[0];
                if (!fila || bloqueado) { return; }
                window.open(enlaceWa(fila, textoDe(fila)), '_blank', 'noopener');
                abrir(fila);
            });
            cola.querySelector('[data-com-cola-reiniciar]').addEventListener('click', function () {
                if (!window.confirm('¿Volver a empezar? Se quitan las marcas "Abierto" de hoy en esta pestaña.')) { return; }
                abiertos = [];
                guardar();
                filas.forEach(function (f) {
                    f.classList.remove('com-fila-abierta');
                    var etiqueta = f.querySelector('[data-com-abierto]');
                    if (etiqueta) { etiqueta.hidden = true; }
                });
                pintarCola();
            });
        }

        aplicarBloqueo(bloqueado);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();

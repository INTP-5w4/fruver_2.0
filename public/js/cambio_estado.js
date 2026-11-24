/*
 * Cambio rápido de estatus en Productos por pedido (columna "Estatus").
 *
 * Cada pedido tiene un selector "Cambiar a…" con SOLO los pasos permitidos desde su estado
 * actual (Modelo_pedido::TRANSICIONES). Al elegir uno se guarda al momento (fetch a
 * POST pedido/{id}/estado) y la insignia y el selector se actualizan sin recargar.
 * Cancelar pide confirmación. Sin JavaScript, el formulario se envía con su botón.
 */
(function () {
    'use strict';

    var TEXTO_CONFIRMAR = {
        pedido_cancelado: '¿Cancelar este pedido? Ya no se podrá cambiar de estatus.',
        pedido_pagado: '¿Marcar este pedido como pagado? Es el último paso del pedido.'
    };

    function aviso(texto, error) {
        var caja = document.querySelector('.flash-container');
        if (!caja) { return; }
        caja.classList.remove('oculto');
        var panel = document.createElement('div');
        panel.className = 'w3-panel ' + (error ? 'w3-red' : 'w3-green');
        panel.setAttribute('role', error ? 'alert' : 'status');
        var p = document.createElement('p');
        p.textContent = texto;
        panel.appendChild(p);
        caja.appendChild(panel);
        setTimeout(function () { panel.remove(); }, error ? 7000 : 4000);
    }

    function opciones(select, siguientes) {
        select.length = 1; // deja "Cambiar a…"
        select.selectedIndex = 0;
        siguientes.forEach(function (s) { select.add(new Option(s.texto, s.valor)); });
    }

    document.addEventListener('change', function (ev) {
        var select = ev.target;
        if (!select.matches || !select.matches('form[data-cambio-estado] select[name="estado"]')) { return; }

        var form = select.form;
        var valor = select.value;
        if (!valor) { return; }

        var pregunta = TEXTO_CONFIRMAR[valor];
        if (pregunta && !window.confirm(pregunta)) {
            select.selectedIndex = 0;
            return;
        }

        var caja = form.closest('[data-estado-rapido]');
        select.disabled = true;
        caja.classList.add('guardando');

        var datos = new FormData(form);
        datos.set('estado', valor); // el select está deshabilitado y FormData lo omitiría

        fetch(form.action, {
            method: 'POST',
            body: datos,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, mensaje: 'No se pudo cambiar el estatus.' }; });
        }).then(function (j) {
            if (!j.ok) {
                aviso(j.mensaje || 'No se pudo cambiar el estatus.', true);
                select.selectedIndex = 0;
                return;
            }

            // Insignia nueva (HTML de estado_pedido(), generado en el servidor).
            var vieja = caja.querySelector('.est');
            if (vieja) {
                var tmp = document.createElement('div');
                tmp.innerHTML = j.insignia;
                if (tmp.firstElementChild) { vieja.replaceWith(tmp.firstElementChild); }
            }

            if (j.siguientes && j.siguientes.length) {
                opciones(select, j.siguientes);
            } else {
                form.remove(); // estado final: ya no hay a dónde pasar
            }

            // Si la lista está filtrada por otro estado, el pedido ya no pertenece a esta pestaña.
            var filtro = new URLSearchParams(location.search).get('estado');
            var fila = caja.closest('tr');
            if (filtro && filtro !== j.estado && fila) {
                fila.classList.add('fila-movida');
                fila.title = 'Este pedido pasó a otra pestaña';
            }

            aviso(j.mensaje, false);
        }).catch(function () {
            aviso('Sin conexión: no se pudo cambiar el estatus.', true);
            select.selectedIndex = 0;
        }).then(function () {
            if (document.body.contains(select)) { select.disabled = false; }
            caja.classList.remove('guardando');
        });
    });
})();

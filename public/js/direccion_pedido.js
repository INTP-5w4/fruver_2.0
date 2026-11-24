/*
 * Selector de dirección de entrega que depende del cliente elegido.
 *
 *   <select name="id_direccion" form="formCarrito"
 *           data-direcciones-de="ped_id_cliente"
 *           data-api="https://…/api_direcciones_cliente/"></select>
 *
 * Al cambiar el cliente se piden sus direcciones. La opción vacía significa
 * "la principal del cliente" (el servidor la resuelve y valida).
 */
(function () {
    'use strict';

    function cargar(select, idCliente, seleccionada) {
        if (typeof select === 'string') { select = document.getElementById(select); }
        if (!select) { return Promise.resolve(); }

        select.innerHTML = '';
        select.appendChild(new Option('Dirección principal del cliente', ''));
        if (!idCliente) { return Promise.resolve(); }

        select.disabled = true;

        return fetch(select.getAttribute('data-api') + encodeURIComponent(idCliente), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { direcciones: [] }; })
            .then(function (j) {
                (j.direcciones || []).forEach(function (d) {
                    var t = (d.principal ? '★ ' : '') + d.texto + (d.ubicada ? '' : ' (sin ubicar en mapa)');
                    var o = new Option(t, String(d.id));
                    select.appendChild(o);
                });
                if (!(j.direcciones || []).length) {
                    select.options[0].text = 'El cliente no tiene direcciones registradas';
                }
                select.value = seleccionada ? String(seleccionada) : '';
            })
            .catch(function () { /* sin red: se usará la principal */ })
            .then(function () { select.disabled = false; });
    }

    function preparar() {
        document.querySelectorAll('select[data-direcciones-de]').forEach(function (select) {
            var cliente = document.getElementById(select.getAttribute('data-direcciones-de'));
            if (!cliente) { return; }
            cliente.addEventListener('change', function () { cargar(select, cliente.value, null); });
            cargar(select, cliente.value, null);
        });
    }

    window.FruverDireccionesPedido = { cargar: cargar };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', preparar);
    } else {
        preparar();
    }
})();

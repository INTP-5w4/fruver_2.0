/**
 * panel.js — Comportamiento común del panel de Fruver (se carga desde partials/estilos_panel.php).
 *  - Los avisos flotantes (.flash-container) se ocultan solos a los 4 segundos.
 *  - Menú lateral (escritorio): grupos plegables; se recuerda cuáles dejó abiertos la persona.
 *  - Hoja «Más» (celular): se abre desde la barra inferior; Escape, el fondo o ✕ la cierran,
 *    y el foco no se sale de ella mientras está abierta.
 *  - Tablas de las listas: cada celda recibe el nombre de su columna (data-etiqueta) para
 *    mostrarse como tarjeta en celular sin perder qué es cada dato.
 * Las ventanas modales (Escape, foco, clic en el fondo) las maneja js/modales.js.
 */
(function () {
    'use strict';

    var LLAVE_GRUPOS = 'fruver.menu.grupos';

    function leer(llave) {
        try { return JSON.parse(window.localStorage.getItem(llave) || '{}') || {}; } catch (e) { return {}; }
    }
    function guardar(llave, valor) {
        try { window.localStorage.setItem(llave, JSON.stringify(valor)); } catch (e) { /* sin almacenamiento: no se recuerda */ }
    }

    function ocultarAvisos() {
        document.querySelectorAll('.flash-container').forEach(function (caja) {
            if (!caja.querySelector('.w3-panel')) { return; }
            setTimeout(function () {
                caja.classList.add('oculto');
                setTimeout(function () { caja.hidden = true; }, 450);
            }, 4000);
        });
    }

    // ── Menú lateral: grupos plegables ─────────────────────────────────
    function grupos() {
        var estado = leer(LLAVE_GRUPOS);
        document.querySelectorAll('.sidebar details.nav-grupo').forEach(function (g) {
            var clave = g.getAttribute('data-grupo');
            // El grupo de la página actual siempre abre; los demás, como los dejó la persona.
            if (!g.hasAttribute('data-actual') && Object.prototype.hasOwnProperty.call(estado, clave)) {
                g.open = !!estado[clave];
            }
            g.addEventListener('toggle', function () {
                var e = leer(LLAVE_GRUPOS);
                e[clave] = g.open;
                guardar(LLAVE_GRUPOS, e);
            });
        });
    }

    // ── Hoja «Más» ─────────────────────────────────────────────────────
    function hoja() {
        var boton = document.querySelector('[data-abrir-hoja]');
        if (!boton) { return; }
        var panel = document.getElementById(boton.getAttribute('data-abrir-hoja'));
        var fondo = document.querySelector('.hoja-fondo');
        if (!panel) { return; }

        function enfocables() {
            return Array.prototype.filter.call(panel.querySelectorAll('a[href], button'), function (el) { return el.offsetParent !== null; });
        }
        function abrir() {
            panel.hidden = false;
            if (fondo) { fondo.hidden = false; }
            document.body.classList.add('hoja-abierta');
            boton.setAttribute('aria-expanded', 'true');
            // El enlace de la página actual (si está en la hoja) recibe el foco; si no, el primero.
            var actual = panel.querySelector('[aria-current="page"]') || enfocables()[1] || enfocables()[0];
            if (actual) { actual.focus(); }
        }
        function cerrar(devolverFoco) {
            if (panel.hidden) { return; }
            panel.hidden = true;
            if (fondo) { fondo.hidden = true; }
            document.body.classList.remove('hoja-abierta');
            boton.setAttribute('aria-expanded', 'false');
            if (devolverFoco) { boton.focus(); }
        }

        boton.addEventListener('click', function () { if (panel.hidden) { abrir(); } else { cerrar(true); } });
        document.querySelectorAll('[data-cerrar-hoja]').forEach(function (el) {
            el.addEventListener('click', function () { cerrar(true); });
        });
        document.addEventListener('keydown', function (e) {
            if (panel.hidden) { return; }
            if (e.key === 'Escape') { e.preventDefault(); cerrar(true); return; }
            if (e.key !== 'Tab') { return; }
            var lista = enfocables();
            if (!lista.length) { return; }
            var primero = lista[0], ultimo = lista[lista.length - 1];
            if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
        });
        // Al pasar a escritorio (girar la tableta, agrandar la ventana) la hoja no tiene sentido.
        window.matchMedia('(min-width: 768px)').addEventListener('change', function (m) { if (m.matches) { cerrar(false); } });
    }

    // ── Tablas como tarjetas en celular ────────────────────────────────
    var SELECTOR_TABLAS = '.lista-wrapper .tabla-wrapper > table:not(.w3-table):not(.mini), .lista-wrapper > table:not(.w3-table):not(.mini)';

    function etiquetar(tabla) {
        var filaCabeza = tabla.tHead && tabla.tHead.rows[tabla.tHead.rows.length - 1];
        if (!filaCabeza) { return; }
        var nombres = [];
        Array.prototype.forEach.call(filaCabeza.cells, function (th) {
            var texto = th.textContent.replace(/\s+/g, ' ').trim();
            for (var i = 0; i < (th.colSpan || 1); i++) { nombres.push(texto); }
        });
        tabla.classList.add('tabla-tarjetas');
        Array.prototype.forEach.call(tabla.tBodies, function (cuerpo) {
            Array.prototype.forEach.call(cuerpo.rows, function (fila) {
                if (fila.classList.contains('fila-detalle')) { return; }
                var col = 0;
                Array.prototype.forEach.call(fila.cells, function (td) {
                    var nombre = nombres[col] || '';
                    col += td.colSpan || 1;
                    if (td.colSpan > 1) { td.classList.add('celda-completa'); return; }
                    // Celda solo con botones o enlaces de acción (editar, borrar...): va al pie de la tarjeta.
                    var soloAcciones = td.querySelector('button, a, form') && td.textContent.trim() === '';
                    td.classList.toggle('celda-accion', !!soloAcciones);
                    if (nombre && !td.hasAttribute('data-etiqueta')) { td.setAttribute('data-etiqueta', nombre); }
                });
                var acciones = fila.querySelectorAll(':scope > td.celda-accion');
                Array.prototype.forEach.call(acciones, function (td, i) { td.classList.toggle('celda-accion-primera', i === 0); });
            });
        });
    }

    function tablas() {
        document.querySelectorAll(SELECTOR_TABLAS).forEach(etiquetar);
        // La búsqueda mientras se escribe (busqueda_viva.js) reemplaza la tabla: se vuelve a etiquetar.
        document.querySelectorAll('[data-busqueda-resultados]').forEach(function (zona) {
            new MutationObserver(function () { zona.querySelectorAll('table').forEach(function (t) { if (t.matches(SELECTOR_TABLAS)) { etiquetar(t); } }); })
                .observe(zona, { childList: true, subtree: true });
        });
    }

    // Fila de pantallas del área (celular): la pantalla actual siempre a la vista («estás aquí»).
    function subnav() {
        var actual = document.querySelector('.subnav [aria-current="page"]');
        if (!actual) { return; }
        var fila = actual.parentElement;
        var izquierda = actual.offsetLeft - (fila.clientWidth - actual.offsetWidth) / 2;
        fila.scrollLeft = Math.max(0, izquierda);
    }

    function iniciar() { ocultarAvisos(); grupos(); hoja(); tablas(); subnav(); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();

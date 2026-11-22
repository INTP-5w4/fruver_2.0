/*
 * modales.js — Ventanas modales de W3.CSS (.w3-modal) accesibles y sin pérdida de datos.
 *
 * Las vistas siguen abriendo y cerrando sus modales con style.display = 'block' / 'none';
 * este script lo detecta y agrega lo que les faltaba:
 *
 *  - Un clic en el fondo NO cierra una ventana con formulario. Se cierra con «Cancelar»,
 *    con la ✕ o con Escape. (Antes, un clic que empezaba en «Guardar» y terminaba en el
 *    fondo cerraba la ventana y se perdía lo capturado.) Las ventanas solo informativas,
 *    sin campos, sí se cierran con un clic en el fondo, siempre que el clic empiece y
 *    termine en él.
 *  - Escape cierra la ventana de encima; si ya se capturó algo, pregunta antes.
 *  - Al abrir, el foco va al primer campo; Tab no se sale de la ventana; al cerrar, el foco
 *    regresa al botón que la abrió (WCAG 2.1: 2.1.2, 2.4.3).
 *  - role="dialog" y aria-modal="true", con el título de la ventana como nombre.
 *
 * Atributos opcionales en el .w3-modal:
 *   data-al-cerrar="nombreFuncion"  función global que hace el cierre (limpia el estado).
 *   data-sucio="1"                   la página marca que hay datos sin guardar fuera de
 *                                    los campos (p. ej. el carrito de un pedido).
 *   data-titulo="…"                  nombre de la ventana si no tiene encabezado.
 */
(function () {
    'use strict';

    var ENFOCABLES = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), '
        + 'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    var CAMPOS = 'input:not([type="hidden"]):not([type="button"]):not([type="submit"]), select, textarea';

    var estado = new WeakMap(); // modal → { abierta, origen, inicial }
    var pila = [];              // modales abiertas, la última encima
    var inicioClic = null;

    function abierta(m) { return m.style.display === 'block' || getComputedStyle(m).display === 'block'; }
    function visible(el) { return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length); }
    function tieneCampos(m) { return !!m.querySelector(CAMPOS); }

    function valorDe(el) {
        if (el.type === 'checkbox' || el.type === 'radio') { return el.checked ? '1' : '0'; }
        if (el.tagName === 'SELECT' && el.multiple) {
            return Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; }).join('\u0001');
        }
        return el.value;
    }

    function foto(m) {
        return Array.prototype.map.call(m.querySelectorAll(CAMPOS), valorDe).join('\u0000');
    }

    function sucia(m) {
        var e = estado.get(m);
        return m.dataset.sucio === '1' || (!!e && e.inicial !== foto(m));
    }

    function preparar(m) {
        if (m.dataset.modalListo) { return; }
        m.dataset.modalListo = '1';
        m.setAttribute('role', 'dialog');
        m.setAttribute('aria-modal', 'true');
        var contenido = m.querySelector('.w3-modal-content') || m;
        if (!contenido.hasAttribute('tabindex')) { contenido.setAttribute('tabindex', '-1'); }
        if (!m.hasAttribute('aria-labelledby') && !m.hasAttribute('aria-label')) {
            var titulo = m.querySelector('h1, h2, h3, h4, header [class*="titulo"]');
            if (titulo) {
                if (!titulo.id) { titulo.id = (m.id || 'modal') + '-titulo'; }
                m.setAttribute('aria-labelledby', titulo.id);
            } else if (m.dataset.titulo) {
                m.setAttribute('aria-label', m.dataset.titulo);
            }
        }
        estado.set(m, { abierta: abierta(m), origen: null, inicial: '' });
        new MutationObserver(function () { revisar(m); }).observe(m, { attributes: true, attributeFilter: ['style', 'class'] });
    }

    function revisar(m) {
        var e = estado.get(m), ahora = abierta(m);
        if (ahora === e.abierta) { return; }
        e.abierta = ahora;
        if (ahora) { alAbrir(m, e); } else { alCerrar(m, e); }
    }

    function alAbrir(m, e) {
        e.origen = document.activeElement && document.activeElement !== document.body ? document.activeElement : null;
        pila = pila.filter(function (x) { return x !== m; });
        pila.push(m);
        // La vista llena los campos antes de mostrar la ventana: la foto se toma ya mostrada.
        setTimeout(function () {
            e.inicial = foto(m);
            if (m.contains(document.activeElement) && document.activeElement !== m) { return; }
            var primero = Array.prototype.find.call(m.querySelectorAll(m.dataset.enfocar || ENFOCABLES), function (el) {
                return visible(el) && !el.closest('footer') && !el.readOnly;
            });
            (primero || m.querySelector('.w3-modal-content') || m).focus({ preventScroll: false });
        }, 30);
    }

    function alCerrar(m, e) {
        pila = pila.filter(function (x) { return x !== m; });
        delete m.dataset.sucio;
        var volver = e.origen;
        e.origen = null;
        if (volver && document.contains(volver) && visible(volver)) { volver.focus({ preventScroll: true }); }
    }

    /** Cierra la ventana como lo haría su botón «Cancelar». */
    function cerrar(m) {
        var fn = m.dataset.alCerrar;
        if (fn && typeof window[fn] === 'function') { window[fn](); } else { m.style.display = 'none'; }
    }

    function intentarCerrar(m) {
        if (sucia(m) && !window.confirm('¿Cerrar sin guardar? Se perderá lo que capturaste en esta ventana.')) { return; }
        cerrar(m);
    }

    function encima() {
        for (var i = pila.length - 1; i >= 0; i--) { if (abierta(pila[i])) { return pila[i]; } }
        return null;
    }

    // ── Clic en el fondo ──────────────────────────────────────────────────
    // En fase de captura de window: corre antes que los window.onclick de las vistas.
    window.addEventListener('mousedown', function (ev) { inicioClic = ev.target; }, true);
    window.addEventListener('click', function (ev) {
        var m = ev.target;
        if (!m.classList || !m.classList.contains('w3-modal')) { return; }
        ev.stopPropagation();
        var empezoEnFondo = inicioClic === m;
        inicioClic = null;
        if (empezoEnFondo && !tieneCampos(m)) { cerrar(m); }
    }, true);

    // ── Teclado: Escape y Tab ─────────────────────────────────────────────
    document.addEventListener('keydown', function (ev) {
        var m = encima();
        if (!m) { return; }
        if (ev.key === 'Escape') {
            // Una lista de sugerencias abierta dentro de la ventana se cierra primero.
            if (ev.defaultPrevented) { return; }
            ev.preventDefault();
            intentarCerrar(m);
            return;
        }
        if (ev.key !== 'Tab') { return; }
        var lista = Array.prototype.filter.call(m.querySelectorAll(ENFOCABLES), visible);
        if (!lista.length) { return; }
        var primero = lista[0], ultimo = lista[lista.length - 1];
        if (ev.shiftKey && (document.activeElement === primero || !m.contains(document.activeElement))) {
            ev.preventDefault(); ultimo.focus();
        } else if (!ev.shiftKey && (document.activeElement === ultimo || !m.contains(document.activeElement))) {
            ev.preventDefault(); primero.focus();
        }
    });

    function iniciar() {
        document.querySelectorAll('.w3-modal').forEach(function (m) {
            preparar(m);
            if (abierta(m)) { alAbrir(m, estado.get(m)); }
        });
        // Modales que se agregan después (contenido cargado con fetch).
        new MutationObserver(function (cambios) {
            cambios.forEach(function (c) {
                c.addedNodes.forEach(function (n) {
                    if (n.nodeType !== 1) { return; }
                    if (n.classList.contains('w3-modal')) { preparar(n); }
                    n.querySelectorAll && n.querySelectorAll('.w3-modal').forEach(preparar);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    window.FruverModal = { cerrar: intentarCerrar, sucia: sucia };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();

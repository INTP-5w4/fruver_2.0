/*
 * Aviso de privacidad en ventana modal.
 *
 * Cualquier enlace a /aviso-de-privacidad o /aviso-de-privacidad/consentimiento abre el
 * documento en una ventana sobre la página actual (un <dialog> con la página del aviso en
 * versión "modal", sin encabezado ni pie: ?modal=1). Así quien está llenando un formulario
 * (registro, preferencias, alta de cliente) lo lee sin perder lo que lleva escrito.
 *
 *  - Ctrl/Cmd/Shift + clic o clic central: se abre en otra pestaña, como un enlace normal.
 *  - data-sin-modal en el enlace: navega normal.
 *  - Sin <dialog> (navegadores muy viejos): navega normal.
 *
 * Dentro de la ventana, los enlaces entre los dos avisos se quedan en la ventana y los
 * demás se abren en otra pestaña. Carga su hoja de estilos (estilos/aviso_modal.css).
 */
(function () {
    'use strict';

    var RUTA = /\/aviso-de-privacidad(\/consentimiento)?\/?$/;
    var enMarco = window.self !== window.top && /(^|[?&])modal=1(&|$)/.test(location.search);

    function urlAviso(a) {
        if (!a || !a.href || a.hasAttribute('data-sin-modal') || a.hasAttribute('download')) { return null; }
        try {
            var u = new URL(a.href, location.href);
            return u.origin === location.origin && RUTA.test(u.pathname) ? u : null;
        } catch (e) {
            return null;
        }
    }

    function conModal(u) {
        var c = new URL(u.href);
        c.searchParams.set('modal', '1');
        return c.href;
    }

    function esClicNormal(ev) {
        return !ev.defaultPrevented && ev.button === 0 && !ev.ctrlKey && !ev.metaKey && !ev.shiftKey && !ev.altKey;
    }

    // ── Dentro de la ventana (página del aviso en modo modal) ──────────────
    if (enMarco) {
        document.addEventListener('click', function (ev) {
            var a = ev.target.closest ? ev.target.closest('a[href]') : null;
            if (!a || !esClicNormal(ev)) { return; }
            var u = urlAviso(a);
            if (u) {
                if (u.hash && u.pathname === location.pathname) { return; } // índice interno
                ev.preventDefault();
                location.href = conModal(u);
            } else if (!/^(#|mailto:|tel:)/i.test(a.getAttribute('href'))) {
                a.target = '_blank';
                a.rel = 'noopener';
            }
        });
        return;
    }

    // ── Página que abre la ventana ─────────────────────────────────────────
    if (!window.HTMLDialogElement) { return; }

    var dialogo = null, marco = null, titulo = null, nuevaPestana = null, origen = null;

    function crear() {
        dialogo = document.createElement('dialog');
        dialogo.className = 'pv-dialogo';
        dialogo.setAttribute('aria-labelledby', 'pv-dialogo-titulo');

        var cabeza = document.createElement('div');
        cabeza.className = 'pv-dialogo-cabeza';

        titulo = document.createElement('h2');
        titulo.id = 'pv-dialogo-titulo';

        nuevaPestana = document.createElement('a');
        nuevaPestana.className = 'pv-dialogo-pestana';
        nuevaPestana.target = '_blank';
        nuevaPestana.rel = 'noopener';
        nuevaPestana.setAttribute('data-sin-modal', '');
        nuevaPestana.textContent = 'Abrir en otra pestaña';

        var cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'pv-dialogo-cerrar';
        cerrar.setAttribute('aria-label', 'Cerrar el aviso');
        cerrar.textContent = '×';
        cerrar.autofocus = true;
        cerrar.addEventListener('click', function () { dialogo.close(); });

        cabeza.appendChild(titulo);
        cabeza.appendChild(nuevaPestana);
        cabeza.appendChild(cerrar);

        marco = document.createElement('iframe');
        marco.className = 'pv-dialogo-marco';
        marco.title = 'Texto del aviso de privacidad';
        // Si dentro de la ventana se pasa al otro aviso, el título y "otra pestaña" lo siguen.
        marco.addEventListener('load', function () {
            try {
                var u = new URL(marco.contentWindow.location.href);
                u.searchParams.delete('modal');
                nuevaPestana.href = u.href;
                titulo.textContent = /consentimiento\/?$/.test(u.pathname) ? 'Aviso y formato de consentimiento' : 'Aviso de privacidad integral';
            } catch (e) { /* otra página: se deja como estaba */ }
        });

        var pie = document.createElement('div');
        pie.className = 'pv-dialogo-pie';
        var listo = document.createElement('button');
        listo.type = 'button';
        listo.className = 'pv-dialogo-listo';
        listo.textContent = 'Entendido, cerrar';
        listo.addEventListener('click', function () { dialogo.close(); });
        pie.appendChild(listo);

        dialogo.appendChild(cabeza);
        dialogo.appendChild(marco);
        dialogo.appendChild(pie);

        // Clic en el fondo oscuro: cierra.
        dialogo.addEventListener('click', function (ev) { if (ev.target === dialogo) { dialogo.close(); } });
        dialogo.addEventListener('close', function () {
            document.documentElement.classList.remove('pv-dialogo-abierto');
            if (origen && origen.focus) { origen.focus(); }
        });

        document.body.appendChild(dialogo);
    }

    function abrir(u, enlace) {
        if (!dialogo) { crear(); }
        origen = enlace;
        var esConsentimiento = /consentimiento\/?$/.test(u.pathname);
        titulo.textContent = esConsentimiento ? 'Aviso y formato de consentimiento' : 'Aviso de privacidad integral';
        nuevaPestana.href = u.href;
        var destino = conModal(u);
        if (marco.getAttribute('src') !== destino) { marco.setAttribute('src', destino); }
        document.documentElement.classList.add('pv-dialogo-abierto');
        dialogo.showModal();
    }

    document.addEventListener('click', function (ev) {
        var a = ev.target.closest ? ev.target.closest('a[href]') : null;
        if (!a || !esClicNormal(ev)) { return; }
        var u = urlAviso(a);
        if (!u) { return; }
        ev.preventDefault();
        abrir(u, a);
    });

    // Hoja de estilos propia, junto a este script.
    var actual = document.currentScript;
    if (actual && !document.querySelector('link[data-aviso-modal]')) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = actual.src.replace(/js\/aviso_modal\.js(\?.*)?$/, 'estilos/aviso_modal.css');
        link.setAttribute('data-aviso-modal', '');
        document.head.appendChild(link);
    }
})();

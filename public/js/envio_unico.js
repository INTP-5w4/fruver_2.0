/*
 * envio_unico.js — Un formulario POST se envía una sola vez.
 *
 * Al enviar, el botón que se pulsó muestra «Enviando…» y los envíos repetidos del mismo
 * formulario (doble clic, Enter otra vez) se ignoran mientras la página no cambie.
 * Es la primera barrera contra duplicados; la segunda, que también cubre un reenvío
 * después de un corte de red, está en el servidor (campo token_envio, tabla envio_unico).
 *
 * No toca los formularios que otro script ya maneja con fetch (cancelan el envío normal
 * con preventDefault) ni los GET (búsquedas). Excluir uno: data-envio-libre.
 */
(function () {
    'use strict';

    var ultimoBoton = new WeakMap();

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest ? ev.target.closest('button[type="submit"], button:not([type]), input[type="submit"]') : null;
        if (b && b.form) { ultimoBoton.set(b.form, b); }
    }, true);

    function bloquear(form) {
        form.dataset.enviando = '1';
        form.setAttribute('aria-busy', 'true');
        var b = ultimoBoton.get(form) || form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
        if (!b) { return; }
        b.classList.add('enviando');
        b.setAttribute('aria-disabled', 'true');
        // No se usa disabled: el navegador dejaría de mandar el name/value del botón.
        if (b.tagName === 'BUTTON' && !b.querySelector('i, svg, img')) {
            b.dataset.textoOriginal = b.textContent;
            b.textContent = 'Enviando…';
        }
    }

    function liberar(form) {
        delete form.dataset.enviando;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('.enviando').forEach(function (b) {
            b.classList.remove('enviando');
            b.removeAttribute('aria-disabled');
            if (b.dataset.textoOriginal !== undefined) { b.textContent = b.dataset.textoOriginal; delete b.dataset.textoOriginal; }
        });
    }

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!(form instanceof HTMLFormElement) || (form.method || '').toLowerCase() !== 'post' || form.hasAttribute('data-envio-libre')) { return; }
        if (form.dataset.enviando === '1') { ev.preventDefault(); return; }
        // Se decide al terminar el evento: si otro script lo canceló (fetch, confirm), no se bloquea.
        setTimeout(function () { if (!ev.defaultPrevented) { bloquear(form); } }, 0);
    });

    // Al volver con «Atrás» la página puede venir de la caché del navegador ya bloqueada.
    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted) { document.querySelectorAll('form[data-enviando]').forEach(liberar); }
    });

    window.FruverEnvio = { bloquear: bloquear, liberar: liberar };
})();

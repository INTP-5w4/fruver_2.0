/**
 * Impresión de tickets desde el navegador (botón de la lista de pedidos y
 * página de diagnóstico de la impresora).
 *
 * Requiere en la página:
 *   <meta name="csrf-token" content="...">           (token CSRF de CodeIgniter)
 * y en cada botón:
 *   data-url="<URL del endpoint POST>"
 */
(function () {
    'use strict';

    // ── Utilidades ──────────────────────────────────────────────

    function leerMeta(nombre) {
        var m = document.querySelector('meta[name="' + nombre + '"]');
        return m ? m.getAttribute('content') : '';
    }

    function guardarToken(token) {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m && token) { m.setAttribute('content', token); }
    }

    function escapar(texto) {
        var d = document.createElement('div');
        d.textContent = texto == null ? '' : String(texto);
        return d.innerHTML;
    }

    // ── Avisos (toasts) ─────────────────────────────────────────

    function contenedorToasts() {
        var c = document.getElementById('ticket-toasts');
        if (c) { return c; }

        var estilo = document.createElement('style');
        estilo.textContent =
            '#ticket-toasts{position:fixed;top:70px;right:16px;z-index:10000;display:flex;flex-direction:column;gap:10px;' +
            'width:min(380px,calc(100vw - 32px));font-family:system-ui,sans-serif}' +
            '.ticket-toast{color:#fff;border-radius:10px;padding:12px 14px;box-shadow:0 4px 16px rgba(0,0,0,.25);font-size:14px;line-height:1.35}' +
            '.ticket-toast.ok{background:#2e7d32}.ticket-toast.error{background:#c62828}.ticket-toast.aviso{background:#ef6c00}' +
            '.ticket-toast b{display:block;margin-bottom:2px}' +
            '.ticket-toast .sug{opacity:.92;margin-top:2px}' +
            '.ticket-toast .acciones{margin-top:8px;display:flex;gap:8px;flex-wrap:wrap}' +
            '.ticket-toast button,.ticket-toast a.btn{background:#fff;color:#222;border:0;border-radius:6px;padding:5px 10px;' +
            'font-size:13px;cursor:pointer;text-decoration:none}' +
            '.ticket-toast .cerrar{float:right;background:none;color:#fff;font-size:18px;line-height:1;padding:0 0 0 8px}';
        document.head.appendChild(estilo);

        c = document.createElement('div');
        c.id = 'ticket-toasts';
        c.setAttribute('role', 'status');
        c.setAttribute('aria-live', 'polite');
        document.body.appendChild(c);

        return c;
    }

    /**
     * @param {'ok'|'error'|'aviso'} tipo
     * @param {{titulo:string, detalle?:string, enlace?:{texto:string,url:string}, ms?:number}} o
     */
    function toast(tipo, o) {
        var t = document.createElement('div');
        t.className = 'ticket-toast ' + tipo;
        t.innerHTML =
            '<button type="button" class="cerrar" aria-label="Cerrar">&times;</button>' +
            '<b>' + escapar(o.titulo) + '</b>' +
            (o.detalle ? '<div class="sug">' + escapar(o.detalle) + '</div>' : '') +
            (o.enlace
                ? '<div class="acciones"><a class="btn" target="_blank" rel="noopener" href="' +
                  escapar(o.enlace.url) + '">' + escapar(o.enlace.texto) + '</a></div>'
                : '');

        function quitar() { if (t.parentNode) { t.parentNode.removeChild(t); } }
        t.querySelector('.cerrar').addEventListener('click', quitar);
        contenedorToasts().appendChild(t);
        setTimeout(quitar, o.ms || (tipo === 'error' ? 15000 : 5000));
    }

    // ── Petición ────────────────────────────────────────────────

    /**
     * POST con CSRF. Siempre resuelve con un objeto {ok, mensaje, ...};
     * los fallos de red o de sesión se convierten en un mensaje entendible.
     */
    function enviarPost(url) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': leerMeta('csrf-token'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(function (res) {
            var tipo = res.headers.get('content-type') || '';

            if (tipo.indexOf('application/json') === -1) {
                // HTML: sesión vencida (login) o token CSRF caducado (403).
                return {
                    ok: false,
                    mensaje: 'La página está desactualizada o tu sesión expiró.',
                    sugerencia: 'Recarga la página (F5), inicia sesión si hace falta e inténtalo de nuevo.'
                };
            }

            return res.json();
        }).then(function (datos) {
            guardarToken(datos.csrf);
            return datos;
        }).catch(function () {
            return {
                ok: false,
                mensaje: 'No se pudo comunicar con el servidor.',
                sugerencia: 'Revisa tu conexión (o que XAMPP/Apache siga encendido) e inténtalo de nuevo.'
            };
        });
    }

    function marcarOcupado(boton, ocupado) {
        if (!boton) { return; }
        boton.disabled = ocupado;

        var icono = boton.querySelector('i');
        if (!icono) { return; }

        if (ocupado) {
            boton.dataset.iconoOriginal = icono.className;
            icono.className = 'fa-solid fa-spinner fa-spin';
        } else if (boton.dataset.iconoOriginal) {
            icono.className = boton.dataset.iconoOriginal;
        }
    }

    function mostrarResultado(r) {
        if (r.ok && r.modo === 'navegador') {
            var ventana = window.open(r.url, '_blank');
            if (!ventana) {
                toast('aviso', {
                    titulo: 'El navegador bloqueó la ventana del ticket.',
                    detalle: 'Permite las ventanas emergentes para este sitio o ábrelo manualmente.',
                    enlace: { texto: 'Abrir ticket', url: r.url }
                });
            }
            return;
        }

        if (r.ok) {
            toast('ok', { titulo: r.mensaje });
            (r.advertencias || []).forEach(function (a) { toast('aviso', { titulo: a }); });
            return;
        }

        toast('error', {
            titulo: r.mensaje || 'No se pudo imprimir el ticket.',
            detalle: r.sugerencia || '',
            enlace: r.alternativa_url
                ? { texto: 'Imprimir con el navegador', url: r.alternativa_url }
                : null,
            ms: 20000
        });
    }

    // ── API pública ─────────────────────────────────────────────

    /** Botón de acción genérico (probar conexión / hoja de prueba). */
    window.ticketAccion = function (boton) {
        if (boton.disabled) { return; }
        marcarOcupado(boton, true);
        enviarPost(boton.dataset.url).then(mostrarResultado).then(function () {
            marcarOcupado(boton, false);
        });
    };

    /** Botón "Imprimir ticket" de cada pedido. */
    window.imprimirTicket = function (boton, evento) {
        if (evento) { evento.stopPropagation(); }
        window.ticketAccion(boton);
    };
})();

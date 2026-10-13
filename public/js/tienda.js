/* FRUVER · Landing de la tienda (main_page_4)
 *
 * - Sin innerHTML: todo se arma con createElement/textContent (anti XSS).
 * - Las peticiones POST llevan el token CSRF en la cabecera que indica el servidor.
 * - El carrito vive en el servidor; aquí solo se pinta lo que el servidor responde.
 */
(function () {
    'use strict';

    var configEl = document.getElementById('tienda-config');
    if (!configEl) { return; }

    var cfg;
    try { cfg = JSON.parse(configEl.textContent); } catch (e) { return; }

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = meta ? meta.getAttribute('content') : '';
    var moneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

    var avisos = document.querySelector('[data-avisos]');
    var panel = document.getElementById('panel-carrito');

    // ---------- Avisos ----------
    var temporizador = null;
    function aviso(texto, tipo) {
        if (!avisos || !texto) { return; }
        var viejo = avisos.querySelector('.aviso-temporal');
        if (viejo) { viejo.remove(); }

        var p = document.createElement('p');
        p.className = 'aviso aviso-temporal aviso-' + (tipo || 'ok');
        p.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
        p.textContent = texto;
        avisos.insertBefore(p, avisos.firstChild);

        clearTimeout(temporizador);
        temporizador = setTimeout(function () { p.remove(); }, 4500);
    }

    // ---------- Peticiones ----------
    function enviar(url, datos) {
        var cuerpo = new URLSearchParams(datos || {});
        var cabeceras = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        };
        cabeceras[cfg.csrfHeader] = csrf;

        return fetch(url, {
            method: 'POST',
            headers: cabeceras,
            body: cuerpo,
            credentials: 'same-origin'
        }).then(function (res) {
            // Sesión vencida: el servidor redirige al login.
            if (res.redirected || res.status === 401) {
                window.location.href = cfg.urlLogin;
                return null;
            }
            return res.json().catch(function () {
                return { ok: false, mensaje: 'No se pudo completar la acción. Recarga la página e intenta de nuevo.' };
            });
        }).then(function (json) {
            if (json && json.csrf) {
                csrf = json.csrf;
                if (meta) { meta.setAttribute('content', csrf); }
                document.querySelectorAll('input[name="csrf_test_name"]').forEach(function (i) { i.value = csrf; });
            }
            return json;
        }).catch(function () {
            return { ok: false, mensaje: 'Sin conexión con el servidor. Intenta de nuevo.' };
        });
    }

    function aplicar(json, mensajeOk) {
        if (!json) { return; }
        if (json.carrito) { pintarCarrito(json.carrito); }
        if (json.ok) {
            if (mensajeOk !== false && json.mensaje) { aviso(json.mensaje, 'ok'); }
        } else {
            aviso(json.mensaje, 'error');
        }
    }

    // ---------- Carrito ----------
    function el(tag, clase, texto) {
        var e = document.createElement(tag);
        if (clase) { e.className = clase; }
        if (texto !== undefined) { e.textContent = texto; }
        return e;
    }

    function pintarCarrito(c) {
        var cuenta = document.querySelector('[data-cuenta]');
        if (cuenta) { cuenta.textContent = String(c.cuenta); }

        var lista = document.querySelector('[data-lineas]');
        var vacio = document.querySelector('[data-vacio]');
        var total = document.querySelector('[data-total]');
        var confirmar = document.querySelector('[data-confirmar]');
        var vaciar = document.querySelector('[data-vaciar]');
        var errorPanel = document.querySelector('[data-panel-error]');
        if (!lista) { return; }

        lista.textContent = '';

        c.lineas.forEach(function (l) {
            var li = el('li', 'linea');

            if (l.img) {
                var img = el('img', 'linea-foto');
                img.src = l.img;
                img.alt = '';
                img.width = 56;
                img.height = 56;
                li.appendChild(img);
            } else {
                li.appendChild(el('span', 'linea-foto', (l.nombre || '?').charAt(0).toUpperCase()));
            }

            var info = el('div');
            info.appendChild(el('p', 'linea-nombre', l.nombre));
            info.appendChild(el('p', 'linea-precio', l.precio_texto + ' por ' + l.unidad));
            li.appendChild(info);

            li.appendChild(el('span', 'linea-subtotal', l.subtotal_texto));

            var controles = el('div', 'linea-controles');
            var grupo = el('div', 'cantidad');
            grupo.setAttribute('role', 'group');
            grupo.setAttribute('aria-label', 'Cantidad de ' + l.nombre);

            var menos = el('button', 'paso', '−');
            menos.type = 'button';
            menos.setAttribute('aria-label', 'Una menos');
            menos.dataset.accion = 'cambiar';
            menos.dataset.id = String(l.id);
            menos.dataset.cantidad = String(l.cantidad - 1);

            var campo = el('input');
            campo.type = 'number';
            campo.min = '1';
            campo.max = String(Math.max(l.stock, 1));
            campo.value = String(l.cantidad);
            campo.setAttribute('inputmode', 'numeric');
            campo.setAttribute('aria-label', 'Cantidad');
            campo.dataset.accion = 'fijar';
            campo.dataset.id = String(l.id);

            var mas = el('button', 'paso', '+');
            mas.type = 'button';
            mas.setAttribute('aria-label', 'Una más');
            mas.dataset.accion = 'cambiar';
            mas.dataset.id = String(l.id);
            mas.dataset.cantidad = String(l.cantidad + 1);

            grupo.appendChild(menos);
            grupo.appendChild(campo);
            grupo.appendChild(mas);
            controles.appendChild(grupo);

            var quitar = el('button', 'linea-quitar', 'Quitar');
            quitar.type = 'button';
            quitar.dataset.accion = 'quitar';
            quitar.dataset.id = String(l.id);
            controles.appendChild(quitar);

            li.appendChild(controles);

            if (l.excede) {
                li.appendChild(el('p', 'linea-exceso',
                    l.stock > 0 ? 'Solo hay ' + l.stock + ' disponibles. Ajusta la cantidad.' : 'Este producto se agotó. Quítalo para continuar.'));
            }

            lista.appendChild(li);
        });

        var hayLineas = c.lineas.length > 0;
        if (vacio) { vacio.hidden = hayLineas; }
        if (total) { total.textContent = c.total_texto || moneda.format(c.total); }
        if (confirmar) { confirmar.disabled = !hayLineas || c.hay_excedidos; }
        if (vaciar) { vaciar.hidden = !hayLineas; }

        if (errorPanel) {
            var textoAvisos = (c.avisos || []).join(' ');
            errorPanel.textContent = textoAvisos;
            errorPanel.hidden = textoAvisos === '';
        }
    }

    // ---------- Abrir / cerrar el panel ----------
    function abrirPanel() {
        if (panel && !panel.open) { panel.showModal(); }
    }
    function cerrarPanel() {
        if (panel && panel.open) { panel.close(); }
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!(t instanceof Element)) { return; }

        if (t.closest('[data-abrir-carrito]')) { abrirPanel(); return; }
        if (t.closest('[data-cerrar-carrito]')) { cerrarPanel(); return; }

        // Clic en el fondo oscuro (fuera del panel) cierra.
        if (panel && t === panel) { cerrarPanel(); return; }

        // Flechas de cada fila.
        var flecha = t.closest('[data-flecha]');
        if (flecha) {
            var seccion = flecha.closest('.fila');
            var pista = seccion && seccion.querySelector('.pista');
            if (pista) {
                var dir = parseInt(flecha.getAttribute('data-flecha'), 10);
                pista.scrollBy({ left: dir * pista.clientWidth * 0.85, behavior: 'smooth' });
            }
            return;
        }

        // Botones − / + de las tarjetas.
        var paso = t.closest('.agregar .paso');
        if (paso) {
            var campoTarjeta = paso.parentElement.querySelector('input[type="number"]');
            var max = parseInt(campoTarjeta.max, 10) || 1;
            var nuevo = (parseInt(campoTarjeta.value, 10) || 1) + parseInt(paso.getAttribute('data-paso'), 10);
            campoTarjeta.value = String(Math.min(Math.max(nuevo, 1), max));
            return;
        }

        // Acciones sobre las líneas del carrito.
        var accion = t.closest('[data-accion]');
        if (accion && panel && panel.contains(accion)) {
            var id = accion.dataset.id;
            if (accion.dataset.accion === 'cambiar') {
                var cantidad = parseInt(accion.dataset.cantidad, 10);
                var peticion = cantidad < 1
                    ? enviar(cfg.urls.quitar, { id_producto: id })
                    : enviar(cfg.urls.cambiar, { id_producto: id, cantidad: cantidad });
                peticion.then(function (j) { aplicar(j, false); });
            } else if (accion.dataset.accion === 'quitar') {
                enviar(cfg.urls.quitar, { id_producto: id }).then(function (j) { aplicar(j, false); });
            }
            return;
        }

        if (t.closest('[data-vaciar]')) {
            enviar(cfg.urls.vaciar, {}).then(function (j) { aplicar(j, false); });
        }
    });

    // Cambio escrito a mano en la cantidad de una línea.
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!(t instanceof HTMLInputElement) || t.dataset.accion !== 'fijar') { return; }

        var cantidad = parseInt(t.value, 10);
        if (isNaN(cantidad)) { cantidad = 1; }

        var peticion = cantidad < 1
            ? enviar(cfg.urls.quitar, { id_producto: t.dataset.id })
            : enviar(cfg.urls.cambiar, { id_producto: t.dataset.id, cantidad: cantidad });
        peticion.then(function (j) { aplicar(j, false); });
    });

    // Agregar desde la tarjeta (sin recargar la página).
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!(form instanceof HTMLFormElement)) { return; }

        if (form.classList.contains('agregar')) {
            ev.preventDefault();
            var boton = form.querySelector('button[type="submit"]');
            if (boton) { boton.disabled = true; }

            enviar(cfg.urls.agregar, {
                id_producto: form.elements['id_producto'].value,
                cantidad: form.elements['cantidad'].value
            }).then(function (j) {
                aplicar(j);
                if (boton) { boton.disabled = false; }
            });
            return;
        }

        // Confirmar pedido: se envía normal (con recarga); solo se evita el doble clic.
        if (form.hasAttribute('data-pedido')) {
            var enviarBtn = form.querySelector('[data-confirmar]');
            if (enviarBtn) {
                // Se deshabilita después de que el navegador arme el envío.
                setTimeout(function () { enviarBtn.disabled = true; enviarBtn.textContent = 'Enviando pedido…'; }, 0);
            }
        }
    });

    // ---------- Inicio ----------
    if (cfg.sesion && cfg.carrito) {
        pintarCarrito(cfg.carrito);
        if (cfg.abrir) { abrirPanel(); }
    }
})();

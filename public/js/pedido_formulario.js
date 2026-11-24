/*
 * pedido_formulario.js — Ventanas «Nuevo pedido» y «Editar pedido» de Productos por pedido
 * (app/Views/partials/modal_pedido.php).
 *
 * Una sola pantalla: datos del pedido, productos y guardar. El selector de producto es un
 * combobox ARIA 1.2 que se usa por completo con el teclado:
 *   escribir filtra (sin mínimo de letras, sin acentos) · ↓/↑ recorre · Enter elige ·
 *   Escape cierra la lista · al elegir, el foco pasa a Cantidad · Enter en Cantidad o en
 *   Precio agrega la línea y regresa al buscador para el siguiente producto.
 *
 * Datos del servidor: <script type="application/json" id="datos-pedido">.
 * El servidor vuelve a validar todo (cliente, stock, precios) al guardar.
 */
(function () {
    'use strict';

    var nodoDatos = document.getElementById('datos-pedido');
    if (!nodoDatos) { return; }
    var DATOS = JSON.parse(nodoDatos.textContent);
    var CATALOGO = DATOS.catalogo;              // [{id, nombre, categoria, u_venta, precio}]
    var STOCK = DATOS.stock || {};              // id_producto → disponible
    var PREFERENCIALES = DATOS.preferenciales;  // [id_cliente]
    var DESCUENTO = DATOS.descuento;            // % del precio preferencial

    var dinero = function (v) { return Number(v).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' }); };
    var redondea2 = function (v) { return Math.round((Number(v) + Number.EPSILON) * 100) / 100; };
    var sinAcentos = function (t) { return String(t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
    var porId = {};
    CATALOGO.forEach(function (p) { porId[String(p.id)] = p; });
    var instancias = []; // formularios vivos (crear/editar), para propagar un cliente nuevo a ambos

    function ahoraLocal(conHora) {
        var d = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString();
        return conHora ? d.slice(0, 16) : d.slice(0, 10);
    }

    // ════════════════════════════════════════════════════════════════════
    //  COMBOBOX sobre un <select>  (buscar escribiendo, sin perder el <select>)
    //  El <select> sigue siendo el campo real que se envía; este combo solo lo maneja.
    // ════════════════════════════════════════════════════════════════════
    function ComboSelect(select, opciones) {
        opciones = opciones || {};
        this.select = select;
        this.alElegir = opciones.alElegir || function () {};
        this.placeholder = opciones.placeholder || 'Buscar…';
        this.items = [];
        this.opciones = [];
        this.activa = -1;
        this.construir();
        this.refrescar();
    }
    ComboSelect.prototype.construir = function () {
        var c = this, sel = c.select;
        sel.removeAttribute('required');           // se valida a mano (el <select> queda oculto)
        sel.classList.add('combo-select-oculto');
        sel.setAttribute('tabindex', '-1');
        sel.setAttribute('aria-hidden', 'true');

        c.id = sel.id + '-combo';
        c.wrap = document.createElement('div');
        c.wrap.className = 'combo';
        var ico = document.createElement('i');
        ico.className = 'fa-solid fa-magnifying-glass combo-icono';
        ico.setAttribute('aria-hidden', 'true');
        c.input = document.createElement('input');
        c.input.type = 'text';
        c.input.id = c.id;
        c.input.className = 'w3-input w3-border combo-entrada';
        c.input.setAttribute('role', 'combobox');
        c.input.setAttribute('aria-autocomplete', 'list');
        c.input.setAttribute('aria-expanded', 'false');
        c.input.autocomplete = 'off';
        c.input.spellcheck = false;
        c.input.placeholder = c.placeholder;
        c.input.setAttribute('data-sin-reglas', '');
        c.lista = document.createElement('ul');
        c.lista.className = 'combo-lista';
        c.lista.id = c.id + '-lista';
        c.lista.setAttribute('role', 'listbox');
        c.lista.hidden = true;
        c.input.setAttribute('aria-controls', c.lista.id);

        c.wrap.append(ico, c.input, c.lista);
        sel.parentNode.insertBefore(c.wrap, sel);

        var lab = document.querySelector('label[for="' + sel.id + '"]');
        if (lab) { lab.setAttribute('for', c.id); }

        c.input.addEventListener('input', function () { c.filtrar(c.input.value); });
        c.input.addEventListener('focus', function () { c.filtrar(c.input.value); });
        c.input.addEventListener('keydown', function (ev) { c.tecla(ev); });
        c.input.addEventListener('blur', function () { setTimeout(function () { c.cerrar(); }, 120); });
        c.lista.addEventListener('mousedown', function (ev) {
            var li = ev.target.closest('[role="option"]');
            if (!li) { return; }
            ev.preventDefault();
            c.elegir(Number(li.dataset.indice));
        });
    };
    // Lee las opciones del <select> (sin el placeholder value="") y refleja el valor actual.
    ComboSelect.prototype.refrescar = function () {
        this.items = Array.prototype.filter.call(this.select.options, function (o) { return o.value !== ''; })
            .map(function (o) { return { value: o.value, texto: o.textContent.trim() }; });
        this.sincronizar();
    };
    ComboSelect.prototype.sincronizar = function () {
        var c = this, v = c.select.value, txt = '';
        for (var i = 0; i < c.items.length; i++) { if (c.items[i].value === v) { txt = c.items[i].texto; break; } }
        c.input.value = txt;
    };
    ComboSelect.prototype.filtrar = function (texto) {
        var c = this, q = sinAcentos(texto.trim());
        c.opciones = c.items.filter(function (it) { return q === '' || sinAcentos(it.texto).indexOf(q) !== -1; })
            .sort(function (a, b) {
                var ia = sinAcentos(a.texto).indexOf(q) === 0 ? 0 : 1, ib = sinAcentos(b.texto).indexOf(q) === 0 ? 0 : 1;
                return ia - ib || a.texto.localeCompare(b.texto, 'es');
            }).slice(0, 50);
        c.activa = q !== '' && c.opciones.length ? 0 : -1;
        c.pintar(q);
    };
    ComboSelect.prototype.pintar = function (q) {
        var c = this;
        c.lista.replaceChildren();
        if (!c.opciones.length) {
            var vac = document.createElement('li');
            vac.className = 'combo-vacio';
            vac.setAttribute('role', 'presentation');
            vac.textContent = 'Nadie coincide con «' + c.input.value.trim() + '».';
            c.lista.appendChild(vac);
        }
        c.opciones.forEach(function (it, i) {
            var li = document.createElement('li');
            li.id = c.id + '-op-' + i;
            li.setAttribute('role', 'option');
            li.dataset.indice = i;
            li.setAttribute('aria-selected', i === c.activa ? 'true' : 'false');
            var pos = q ? sinAcentos(it.texto).indexOf(q) : -1;
            if (pos >= 0) {
                li.append(it.texto.slice(0, pos));
                var m = document.createElement('mark');
                m.textContent = it.texto.slice(pos, pos + q.length);
                li.append(m, it.texto.slice(pos + q.length));
            } else { li.textContent = it.texto; }
            c.lista.appendChild(li);
        });
        c.lista.hidden = false;
        c.input.setAttribute('aria-expanded', 'true');
        c.marcar();
    };
    ComboSelect.prototype.marcar = function () {
        var c = this;
        Array.prototype.forEach.call(c.lista.querySelectorAll('[role="option"]'), function (li, i) {
            li.setAttribute('aria-selected', i === c.activa ? 'true' : 'false');
            if (i === c.activa) { li.scrollIntoView({ block: 'nearest' }); }
        });
        var a = c.activa >= 0 ? c.lista.querySelector('[data-indice="' + c.activa + '"]') : null;
        if (a) { c.input.setAttribute('aria-activedescendant', a.id); } else { c.input.removeAttribute('aria-activedescendant'); }
    };
    ComboSelect.prototype.cerrar = function () {
        this.lista.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
        // Si se tecleó algo sin elegir, el texto vuelve al valor realmente seleccionado
        // (el <select> es la fuente de verdad), para que no diverja de lo que se envía.
        this.sincronizar();
    };
    ComboSelect.prototype.tecla = function (ev) {
        var c = this, abierta = !c.lista.hidden;
        switch (ev.key) {
            case 'ArrowDown':
                ev.preventDefault();
                if (!abierta) { c.filtrar(c.input.value); }
                if (c.opciones.length) { c.activa = (c.activa + 1) % c.opciones.length; c.marcar(); }
                break;
            case 'ArrowUp':
                ev.preventDefault();
                if (!abierta) { c.filtrar(c.input.value); }
                if (c.opciones.length) { c.activa = c.activa <= 0 ? c.opciones.length - 1 : c.activa - 1; c.marcar(); }
                break;
            case 'Enter':
                ev.preventDefault();
                if (abierta && c.activa >= 0) { c.elegir(c.activa); }
                break;
            case 'Escape':
                if (abierta) { ev.preventDefault(); c.cerrar(); }
                break;
            case 'Tab':
                c.cerrar();
                break;
        }
    };
    ComboSelect.prototype.elegir = function (i) {
        var c = this, it = c.opciones[i];
        if (!it) { return; }
        c.input.value = it.texto;
        c.select.value = it.value;
        c.cerrar();
        c.alElegir(it.value);
    };
    // Agrega una opción nueva al <select> y al combo (sin cambiar la selección actual).
    ComboSelect.prototype.agregarOpcion = function (value, texto) {
        var existe = Array.prototype.some.call(this.select.options, function (o) { return o.value === String(value); });
        if (!existe) { this.select.appendChild(new Option(texto, String(value))); }
        this.refrescar();
    };
    // Agrega la opción y la deja elegida.
    ComboSelect.prototype.agregarYElegir = function (value, texto) {
        this.agregarOpcion(value, texto);
        this.select.value = String(value);
        this.sincronizar();
        this.alElegir(String(value));
    };

    // ════════════════════════════════════════════════════════════════════
    //  FORMULARIO (uno por modo: 'c' crear, 'e' editar)
    // ════════════════════════════════════════════════════════════════════
    function Formulario(p) {
        var $ = function (s) { return document.getElementById(p + '-' + s); };
        var f = this;
        f.p = p;
        f.form = $('form');
        f.modal = f.form.closest('.w3-modal');
        f.cliente = $('cliente');
        f.fecha = $('fecha');
        f.repartidor = $('repartidor');
        f.direccion = $('direccion');
        f.ventana = $('ventana');
        f.entrada = $('producto');
        f.lista = $('producto-lista');
        f.cant = $('cant');
        f.unidad = $('unidad');
        f.precio = $('precio');
        f.info = $('info');
        f.aviso = $('aviso');
        f.cuerpoCarrito = $('carrito');
        f.total = $('total');
        f.items = $('items');
        f.barra = $('barra-pref');
        f.lineas = [];
        f.elegido = null;    // producto elegido en el buscador
        f.opciones = [];     // productos que muestra la lista
        f.activa = -1;       // opción resaltada

        f.barra.querySelector('.pref-pct').textContent = DESCUENTO;
        f.conectar();
        instancias.push(f);
    }

    Formulario.prototype.conectar = function () {
        var f = this;

        // ── Combobox ──
        f.entrada.addEventListener('input', function () {
            f.elegido = null;
            f.info.textContent = '';
            f.info.classList.remove('sin-stock');
            f.filtrar(f.entrada.value);
        });
        f.entrada.addEventListener('focus', function () { if (!f.elegido) { f.filtrar(f.entrada.value); } });
        f.entrada.addEventListener('keydown', function (ev) { f.teclaBuscador(ev); });
        f.entrada.addEventListener('blur', function () { setTimeout(function () { f.cerrarLista(); }, 120); });
        // mousedown (no click): se elige antes de que el campo pierda el foco.
        f.lista.addEventListener('mousedown', function (ev) {
            var li = ev.target.closest('[role="option"]');
            if (!li) { return; }
            ev.preventDefault();
            f.elegir(Number(li.dataset.indice));
        });

        // ── Enter agrega la línea (no envía el formulario) ──
        [f.cant, f.precio, f.unidad].forEach(function (el) {
            el.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); f.agregar(); }
            });
        });
        document.getElementById(f.p + '-agregar').addEventListener('click', function () { f.agregar(); });

        // ── Carrito: quitar y precio por línea ──
        f.cuerpoCarrito.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-quitar]');
            if (!b) { return; }
            f.lineas.splice(Number(b.dataset.quitar), 1);
            f.pintarCarrito();
            f.entrada.focus();
        });
        f.cuerpoCarrito.addEventListener('change', function (ev) {
            if (ev.target.matches('[data-precio-linea]')) { f.cambiarPrecioLinea(Number(ev.target.dataset.precioLinea), ev.target); }
        });

        // ── Cliente: buscador (combobox) sobre el <select> + alta en línea ──
        f.clienteCombo = new ComboSelect(f.cliente, {
            placeholder: 'Buscar cliente…',
            alElegir: function () { f.cliente.dispatchEvent(new Event('change', { bubbles: true })); }
        });
        f.conectarNuevoCliente();

        // ── Preferencial ──
        f.cliente.addEventListener('change', function () { f.actualizarBarraPref(); });
        f.barra.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-pref]');
            if (!b) { return; }
            f.cambiarPrecios(b.dataset.pref === 'aplicar' ? f.precioPreferencial : function (x) { return redondea2(x); });
        });

        // ── Cerrar y guardar ──
        f.form.querySelectorAll('[data-cerrar-pedido]').forEach(function (b) {
            b.addEventListener('click', function () { window.FruverModal ? window.FruverModal.cerrar(f.modal) : f.cerrar(); });
        });
        f.form.addEventListener('submit', function (ev) { f.alEnviar(ev); });
    };

    // ── Lista de productos ──────────────────────────────────────────────
    Formulario.prototype.filtrar = function (texto) {
        var q = sinAcentos(texto.trim());
        var f = this;
        f.opciones = CATALOGO.filter(function (p) { return q === '' || sinAcentos(p.nombre).indexOf(q) !== -1; })
            .sort(function (a, b) {
                // Primero los que empiezan con lo escrito, luego los que tienen existencias.
                var ia = sinAcentos(a.nombre).indexOf(q) === 0 ? 0 : 1, ib = sinAcentos(b.nombre).indexOf(q) === 0 ? 0 : 1;
                if (ia !== ib) { return ia - ib; }
                var sa = (STOCK[a.id] || 0) > 0 ? 0 : 1, sb = (STOCK[b.id] || 0) > 0 ? 0 : 1;
                return sa - sb || a.nombre.localeCompare(b.nombre, 'es');
            })
            .slice(0, 50);
        f.activa = q !== '' && f.opciones.length ? 0 : -1;
        f.pintarLista(q);
    };

    Formulario.prototype.pintarLista = function (q) {
        var f = this;
        f.lista.replaceChildren();
        if (!f.opciones.length) {
            var vacio = document.createElement('li');
            vacio.className = 'combo-vacio';
            vacio.setAttribute('role', 'presentation');
            vacio.textContent = 'Ningún producto coincide con «' + f.entrada.value.trim() + '».';
            f.lista.appendChild(vacio);
        }
        f.opciones.forEach(function (prod, i) {
            var li = document.createElement('li');
            var stock = Number(STOCK[prod.id] || 0);
            li.id = f.p + '-opcion-' + prod.id;
            li.setAttribute('role', 'option');
            li.dataset.indice = i;
            li.setAttribute('aria-selected', i === f.activa ? 'true' : 'false');
            var nombre = document.createElement('span');
            var pos = q ? sinAcentos(prod.nombre).indexOf(q) : -1;
            if (pos >= 0) {
                nombre.append(prod.nombre.slice(0, pos));
                var m = document.createElement('mark');
                m.textContent = prod.nombre.slice(pos, pos + q.length);
                nombre.append(m, prod.nombre.slice(pos + q.length));
            } else {
                nombre.textContent = prod.nombre;
            }
            var pill = document.createElement('span');
            pill.className = 'stock-pill ' + (stock > 0 ? 'con-stock' : 'sin-stock');
            pill.textContent = stock > 0 ? 'Disponible: ' + stock : 'Sin existencias';
            li.append(nombre, pill);
            f.lista.appendChild(li);
        });
        f.lista.hidden = false;
        f.entrada.setAttribute('aria-expanded', 'true');
        f.marcarActiva();
    };

    Formulario.prototype.marcarActiva = function () {
        var f = this;
        Array.prototype.forEach.call(f.lista.querySelectorAll('[role="option"]'), function (li, i) {
            li.setAttribute('aria-selected', i === f.activa ? 'true' : 'false');
            if (i === f.activa) { li.scrollIntoView({ block: 'nearest' }); }
        });
        var activa = f.activa >= 0 ? f.lista.querySelector('[data-indice="' + f.activa + '"]') : null;
        if (activa) { f.entrada.setAttribute('aria-activedescendant', activa.id); } else { f.entrada.removeAttribute('aria-activedescendant'); }
    };

    Formulario.prototype.cerrarLista = function () {
        this.lista.hidden = true;
        this.entrada.setAttribute('aria-expanded', 'false');
        this.entrada.removeAttribute('aria-activedescendant');
    };

    Formulario.prototype.teclaBuscador = function (ev) {
        var f = this, abierta = !f.lista.hidden;
        switch (ev.key) {
            case 'ArrowDown':
                ev.preventDefault();
                if (!abierta) { f.filtrar(f.entrada.value); }
                if (f.opciones.length) { f.activa = (f.activa + 1) % f.opciones.length; f.marcarActiva(); }
                break;
            case 'ArrowUp':
                ev.preventDefault();
                if (!abierta) { f.filtrar(f.entrada.value); }
                if (f.opciones.length) { f.activa = f.activa <= 0 ? f.opciones.length - 1 : f.activa - 1; f.marcarActiva(); }
                break;
            case 'Enter':
                ev.preventDefault(); // nunca envía el formulario desde el buscador
                if (abierta && f.activa >= 0) { f.elegir(f.activa); } else if (f.elegido) { f.cant.focus(); }
                break;
            case 'Escape':
                if (abierta) { ev.preventDefault(); f.cerrarLista(); } // la ventana no se cierra
                break;
            case 'Tab':
                f.cerrarLista();
                break;
        }
    };

    Formulario.prototype.elegir = function (i) {
        var f = this, prod = f.opciones[i];
        if (!prod) { return; }
        f.elegido = prod;
        f.entrada.value = prod.nombre;
        f.cerrarLista();
        if (prod.u_venta) { f.elegirUnidad(prod.u_venta); }
        if (prod.precio !== '' && prod.precio !== null && Number(prod.precio) > 0) { f.rellenarPrecio(Number(prod.precio)); } else { f.precio.value = ''; }
        if (!f.cant.value) { f.cant.value = '1'; }

        var stock = Number(STOCK[prod.id] || 0) - f.enCarrito(prod.id);
        f.info.classList.toggle('sin-stock', stock <= 0);
        f.info.textContent = stock > 0
            ? prod.nombre + ': disponible ' + stock + '.' + f.textoPref()
            : prod.nombre + ': sin existencias disponibles.';
        f.aviso.textContent = '';
        f.cant.focus();
        f.cant.select();
    };

    Formulario.prototype.elegirUnidad = function (sugerida) {
        var s = sinAcentos(sugerida);
        Array.prototype.some.call(this.unidad.options, function (o) {
            var v = sinAcentos(o.value);
            if (v === s || v.indexOf(s) === 0 || s.indexOf(v) === 0) { o.selected = true; return true; }
            return false;
        });
    };

    Formulario.prototype.limpiarLinea = function () {
        this.elegido = null;
        this.entrada.value = '';
        this.cant.value = '';
        this.precio.value = '';
        this.info.textContent = '';
        this.info.classList.remove('sin-stock');
    };

    // ── Precio preferencial ─────────────────────────────────────────────
    Formulario.prototype.esPreferencial = function () {
        return PREFERENCIALES.indexOf(parseInt(this.cliente.value, 10)) !== -1;
    };
    Formulario.prototype.precioPreferencial = function (precio) {
        return redondea2(Number(precio) * (100 - DESCUENTO) / 100);
    };
    Formulario.prototype.textoPref = function () {
        return this.esPreferencial() && this.elegido && Number(this.elegido.precio) > 0
            ? ' Precio preferencial (−' + DESCUENTO + ' %) sobre ' + dinero(this.elegido.precio) + '.'
            : '';
    };
    Formulario.prototype.rellenarPrecio = function (precio) {
        this.precio.value = (this.esPreferencial() ? this.precioPreferencial(precio) : precio).toFixed(2);
    };
    Formulario.prototype.actualizarBarraPref = function () {
        this.barra.hidden = !(this.esPreferencial() && this.lineas.length);
    };

    // ── Alta de cliente en línea (sin salir del pedido) ──────────────────
    Formulario.prototype.conectarNuevoCliente = function () {
        var f = this;
        var caja = document.getElementById(f.p + '-nc');
        var abre = document.getElementById(f.p + '-nc-abre');
        if (!caja || !abre) { return; }
        f.nc = {
            caja:  caja,
            aviso: document.getElementById(f.p + '-nc-aviso'),
            nom:   document.getElementById(f.p + '-nc-nom'),
            pat:   document.getElementById(f.p + '-nc-pat'),
            mat:   document.getElementById(f.p + '-nc-mat'),
            tel:   document.getElementById(f.p + '-nc-tel')
        };
        abre.addEventListener('click', function () { f.mostrarNuevoCliente(true); });
        document.getElementById(f.p + '-nc-cancela').addEventListener('click', function () { f.mostrarNuevoCliente(false); });
        document.getElementById(f.p + '-nc-crear').addEventListener('click', function () { f.crearCliente(); });
        [f.nc.nom, f.nc.pat, f.nc.mat, f.nc.tel].forEach(function (el) {
            el.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); f.crearCliente(); } });
        });
    };

    Formulario.prototype.mostrarNuevoCliente = function (ver) {
        if (!this.nc) { return; }
        this.nc.caja.hidden = !ver;
        if (ver) { this.nc.aviso.textContent = ''; this.nc.nom.focus(); } else { this.limpiarNuevoCliente(); }
    };

    Formulario.prototype.limpiarNuevoCliente = function () {
        if (!this.nc) { return; }
        this.nc.nom.value = this.nc.pat.value = this.nc.mat.value = this.nc.tel.value = '';
        this.nc.aviso.textContent = '';
    };

    Formulario.prototype.crearCliente = function () {
        var f = this;
        if (!f.nc || f.nc.enviando) { return; } // evita doble envío (botón o Enter repetido)
        var nom = f.nc.nom.value.trim(), pat = f.nc.pat.value.trim(), tel = f.nc.tel.value.trim();
        if (!nom || !pat || !tel) {
            f.nc.aviso.textContent = 'Nombre, apellido paterno y teléfono son obligatorios.';
            (!nom ? f.nc.nom : (!pat ? f.nc.pat : f.nc.tel)).focus();
            return;
        }
        var token = f.form.querySelector('input[name="csrf_test_name"]');
        var datos = new FormData();
        datos.append('nom', nom);
        datos.append('ape_pat', pat);
        datos.append('ape_mat', f.nc.mat.value.trim());
        datos.append('tel', tel);
        if (token) { datos.append(token.name, token.value); }

        var btn = document.getElementById(f.p + '-nc-crear');
        f.nc.enviando = true;
        btn.disabled = true;
        f.nc.aviso.textContent = 'Guardando…';
        fetch(DATOS.urls.crearCliente, {
            method: 'POST',
            body: datos,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok) { f.nc.aviso.textContent = res.j.error || 'No se pudo crear el cliente.'; return; }
                // El cliente nuevo se registra en TODOS los formularios (crear y editar) para que
                // también exista al editar un pedido sin recargar la página; aquí queda elegido.
                instancias.forEach(function (inst) {
                    if (!inst.clienteCombo) { return; }
                    if (inst === f) { inst.clienteCombo.agregarYElegir(res.j.id, res.j.nombre); }
                    else { inst.clienteCombo.agregarOpcion(res.j.id, res.j.nombre); }
                });
                f.mostrarNuevoCliente(false);
                f.aviso.textContent = 'Cliente «' + res.j.nombre + '» creado y elegido.';
                f.entrada.focus();
            })
            .catch(function () { f.nc.aviso.textContent = 'No se pudo crear el cliente. Revisa tu conexión.'; })
            .then(function () { btn.disabled = false; f.nc.enviando = false; });
    };
    Formulario.prototype.cambiarPrecios = function (calcula) {
        var f = this, sinPrecio = 0;
        f.lineas.forEach(function (it) {
            var p = porId[String(it.id_producto)], base = p && Number(p.precio) > 0 ? Number(p.precio) : null;
            if (base === null) { sinPrecio++; return; }
            it.p_venta = calcula.call(f, base);
            it.total = (it.cant * it.p_venta).toFixed(2);
        });
        f.pintarCarrito();
        f.aviso.textContent = sinPrecio ? sinPrecio + ' producto(s) no tienen precio de catálogo y conservan su precio.' : '';
    };

    // ── Carrito ─────────────────────────────────────────────────────────
    Formulario.prototype.enCarrito = function (idProducto) {
        return this.lineas.filter(function (i) { return String(i.id_producto) === String(idProducto); })
            .reduce(function (s, i) { return s + Number(i.cant); }, 0);
    };

    Formulario.prototype.agregar = function () {
        var f = this;
        if (!f.elegido) {
            f.aviso.textContent = 'Elige un producto de la lista.';
            f.entrada.focus();
            return;
        }
        var cant = Number(f.cant.value), precio = Number(f.precio.value);
        if (!(cant > 0)) { f.aviso.textContent = 'Escribe una cantidad mayor que 0.'; f.cant.focus(); return; }
        if (!(precio > 0) || precio > 99999999) { f.aviso.textContent = 'Escribe un precio mayor que 0.'; f.precio.focus(); return; }

        var libre = Number(STOCK[f.elegido.id] || 0) - f.enCarrito(f.elegido.id);
        if (cant > libre) {
            f.aviso.textContent = libre <= 0
                ? '«' + f.elegido.nombre + '» ya no tiene existencias disponibles.'
                : 'Existencias insuficientes: puedes agregar máximo ' + libre + ' de «' + f.elegido.nombre + '».';
            f.cant.focus();
            f.cant.select();
            return;
        }

        f.lineas.push({
            id: null,
            id_producto: String(f.elegido.id),
            u_venta: f.unidad.value,
            cant: cant,
            p_venta: redondea2(precio),
            total: (cant * redondea2(precio)).toFixed(2)
        });
        f.aviso.textContent = '';
        f.limpiarLinea();
        f.pintarCarrito();
        f.entrada.focus();
    };

    Formulario.prototype.cambiarPrecioLinea = function (i, input) {
        var p = Number(input.value), it = this.lineas[i];
        if (!(p > 0) || p > 99999999) {
            this.aviso.textContent = 'Escribe un precio mayor que 0.';
            input.value = Number(it.p_venta).toFixed(2);
            return;
        }
        it.p_venta = redondea2(p);
        it.total = (it.cant * it.p_venta).toFixed(2);
        this.aviso.textContent = '';
        document.getElementById(this.p + '-tot-' + i).textContent = dinero(it.total);
        this.pintarTotal();
    };

    Formulario.prototype.pintarTotal = function () {
        var t = this.lineas.reduce(function (s, it) { return s + Number(it.total); }, 0);
        this.total.textContent = dinero(t);
    };

    Formulario.prototype.pintarCarrito = function () {
        var f = this, tb = f.cuerpoCarrito;
        tb.replaceChildren();
        if (!f.lineas.length) {
            var tr0 = tb.insertRow();
            tr0.className = 'pedido-vacio';
            var td0 = tr0.insertCell();
            td0.colSpan = 6;
            td0.textContent = 'Aún no hay productos en el pedido.';
        }
        f.lineas.forEach(function (it, i) {
            var prod = porId[String(it.id_producto)], nombre = prod ? prod.nombre : 'Producto #' + it.id_producto;
            var tr = tb.insertRow();
            tr.insertCell().textContent = nombre;
            tr.insertCell().textContent = it.u_venta === 'Ramos' ? 'Ramo' : it.u_venta;
            tr.insertCell().textContent = it.cant;

            var celdaPrecio = tr.insertCell(), input = document.createElement('input');
            input.type = 'number';
            input.className = 'w3-input w3-border precio-linea';
            input.min = '0.01';
            input.step = '0.01';
            input.value = Number(it.p_venta).toFixed(2);
            input.dataset.precioLinea = i;
            input.setAttribute('aria-label', 'Precio unitario de ' + nombre);
            input.setAttribute('data-sin-reglas', '');
            celdaPrecio.appendChild(input);

            var celdaTotal = tr.insertCell();
            celdaTotal.id = f.p + '-tot-' + i;
            celdaTotal.textContent = dinero(it.total);

            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn-icono peligro';
            b.dataset.quitar = i;
            b.title = 'Quitar';
            b.setAttribute('aria-label', 'Quitar ' + nombre + ' del pedido');
            b.innerHTML = '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>';
            tr.insertCell().appendChild(b);
        });
        f.pintarTotal();
        f.actualizarBarraPref();
        // Un carrito con productos cuenta como «datos sin guardar» para la ventana modal.
        if (f.lineas.length) { f.modal.dataset.sucio = '1'; } else { delete f.modal.dataset.sucio; }
    };

    // ── Guardar ─────────────────────────────────────────────────────────
    Formulario.prototype.alEnviar = function (ev) {
        var f = this;
        if (f.form.dataset.enviando === '1') { ev.preventDefault(); return; }

        // Cliente: el <select> quedó oculto tras el combo, así que se valida aquí.
        if (!f.cliente.value) {
            ev.preventDefault();
            f.aviso.textContent = 'Falta: Cliente.';
            if (f.clienteCombo) { f.clienteCombo.input.focus(); }
            return;
        }

        // El formulario usa novalidate para mostrar los avisos aquí, en español y en orden.
        var invalido = Array.prototype.find.call(f.form.querySelectorAll('[required]'), function (el) { return !el.checkValidity(); });
        if (invalido) {
            ev.preventDefault();
            var etiqueta = f.form.querySelector('label[for="' + invalido.id + '"]');
            f.aviso.textContent = 'Falta: ' + (etiqueta ? etiqueta.textContent.replace('*', '').trim() : 'un dato obligatorio') + '.';
            invalido.focus();
            return;
        }
        if (!f.lineas.length) {
            ev.preventDefault();
            f.aviso.textContent = 'Agrega al menos un producto al pedido.';
            f.entrada.focus();
            return;
        }
        if (f.elegido && f.cant.value) {
            // Quedó un producto elegido sin agregar: se avisa en vez de perderlo en silencio.
            ev.preventDefault();
            f.aviso.textContent = '«' + f.elegido.nombre + '» está elegido pero no agregado: pulsa «Agregar» o borra el buscador.';
            document.getElementById(f.p + '-agregar').focus();
            return;
        }
        f.items.value = JSON.stringify(f.lineas);
        if (f.p === 'c') { f.recordarRepartidor(); }
        f.aviso.textContent = '';
        delete f.modal.dataset.sucio;
    };

    // ── Abrir / cerrar ──────────────────────────────────────────────────
    Formulario.prototype.reiniciar = function () {
        this.form.reset();
        this.lineas = [];
        this.limpiarLinea();
        this.cerrarLista();
        this.aviso.textContent = '';
        this.pintarCarrito();
        if (this.clienteCombo) { this.clienteCombo.sincronizar(); }
        this.mostrarNuevoCliente(false);
        if (this.fecha && this.p === 'c') { this.fecha.value = ahoraLocal(false); }
        if (this.p === 'c') { this.proponerRepartidor(); }
        if (window.FruverEnvio) { window.FruverEnvio.liberar(this.form); }
    };

    // El repartidor del pedido anterior viene elegido (se puede cambiar): un paso menos en la tarea
    // más frecuente. Se guarda solo el número del repartidor en este navegador.
    var LLAVE_REPARTIDOR = 'fruver.pedido.repartidor';
    Formulario.prototype.proponerRepartidor = function () {
        var sel = this.repartidor, guardado = null;
        if (!sel || sel.value) { return; }
        try { guardado = window.localStorage.getItem(LLAVE_REPARTIDOR); } catch (e) { guardado = null; }
        if (guardado && Array.prototype.some.call(sel.options, function (o) { return o.value === guardado; })) {
            sel.value = guardado;
        }
    };
    Formulario.prototype.recordarRepartidor = function () {
        try { if (this.repartidor && this.repartidor.value) { window.localStorage.setItem(LLAVE_REPARTIDOR, this.repartidor.value); } } catch (e) { /* sin almacenamiento */ }
    };

    Formulario.prototype.abrir = function () {
        this.modal.style.display = 'block';
    };

    Formulario.prototype.cerrar = function () {
        this.modal.style.display = 'none';
        this.reiniciar();
    };

    // ════════════════════════════════════════════════════════════════════
    //  INSTANCIAS Y FUNCIONES QUE USA LA VISTA
    // ════════════════════════════════════════════════════════════════════
    var crear = document.getElementById('c-form') ? new Formulario('c') : null;
    var editar = document.getElementById('e-form') ? new Formulario('e') : null;

    function cargarDirecciones(f, idCliente, idDireccion) {
        if (f.direccion && window.FruverDireccionesPedido) {
            window.FruverDireccionesPedido.cargar(f.direccion, idCliente, idDireccion);
        }
    }

    window.abrirNuevoPedido = function () {
        if (!crear) { return; }
        if (crear.modal.style.display !== 'block') { crear.reiniciar(); }
        crear.abrir();
    };
    window.cerrarModal = function () { if (crear) { crear.cerrar(); } };
    window.eCerrarModal = function () { if (editar) { editar.cerrar(); } };

    /** «Volver a pedir lo mismo»: abre «Nuevo pedido» con los productos de un pedido anterior. */
    window.recargarPedido = function (idPedido) {
        if (!crear) { return; }
        fetch(DATOS.urls.recargar + encodeURIComponent(idPedido), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok) { window.alert(res.j.error || 'No se pudo recargar el pedido.'); return; }
                var d = res.j;
                crear.reiniciar();
                crear.cliente.value = String(d.pedido.id_cliente);
                if (crear.clienteCombo) { crear.clienteCombo.sincronizar(); }
                if (d.pedido.id_repartidor) { crear.repartidor.value = String(d.pedido.id_repartidor); }
                cargarDirecciones(crear, d.pedido.id_cliente, null);
                crear.lineas = d.items.map(function (i) {
                    return {
                        id: null, id_producto: String(i.id_producto), u_venta: i.u_venta,
                        cant: Number(i.cant), p_venta: Number(i.p_venta), total: Number(i.total).toFixed(2)
                    };
                });
                crear.pintarCarrito();
                crear.abrir();
                if (d.avisos && d.avisos.length) { crear.aviso.textContent = 'Revisa antes de guardar: ' + d.avisos.join(' · '); }
            })
            .catch(function () { window.alert('No se pudo recargar el pedido. Revisa tu conexión.'); });
    };

    /** «Editar»: carga el pedido y muestra solo los estatus a los que puede pasar. */
    window.abrirEditarPedido = function (idPedido) {
        if (!editar) { return; }
        fetch(DATOS.urls.pedido + encodeURIComponent(idPedido), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) { throw new Error('http ' + r.status); } return r.json(); })
            .then(function (d) {
                if (!d.transiciones_validas.length) {
                    window.alert('Este pedido está en «' + d.estado_actual.replace(/_/g, ' ') + '» y ya no admite cambios.');
                    return;
                }
                editar.reiniciar();
                document.getElementById('e-id-pedido').value = idPedido;
                document.getElementById('e-numero').textContent = '#' + idPedido;
                editar.fecha.value = d.pedido.fecha;
                editar.cliente.value = String(d.pedido.id_cliente);
                if (editar.clienteCombo) { editar.clienteCombo.sincronizar(); }
                editar.repartidor.value = d.pedido.id_repartidor ? String(d.pedido.id_repartidor) : '';
                cargarDirecciones(editar, d.pedido.id_cliente, d.pedido.id_direccion);
                if (editar.ventana) { editar.ventana.value = (d.pedido.ventana_fin || '').substring(0, 5); }

                var estado = document.getElementById('e-estado');
                estado.replaceChildren();
                d.transiciones_validas.forEach(function (e) {
                    estado.appendChild(new Option((DATOS.estados[e] || e.replace(/_/g, ' ')), e));
                });
                document.getElementById('e-fecha-estatus').value = ahoraLocal(true);

                editar.lineas = d.items.map(function (it) {
                    return {
                        id: it.id, id_producto: String(it.id_producto), u_venta: it.unidad_venta,
                        cant: Number(it.cant), p_venta: Number(it.precio_venta), total: Number(it.total).toFixed(2)
                    };
                });
                editar.pintarCarrito();
                delete editar.modal.dataset.sucio; // recién cargado: nada que perder todavía
                editar.abrir();
            })
            .catch(function () { window.alert('No se pudieron cargar los datos del pedido.'); });
    };

    /** Fila de la tabla: muestra u oculta los productos del pedido. */
    window.toggleDetalle = function (idPedido) {
        var fila = document.getElementById('detalle-' + idPedido);
        var btn = document.getElementById('btn-' + idPedido);
        var abierto = fila.classList.toggle('abierto');
        btn.classList.toggle('abierto', abierto);
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    };
})();

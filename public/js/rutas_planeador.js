/*
 * Planeador de rutas (panel del administrador).
 *
 * 1. Se eligen pedidos (checkbox). 2. "Calcular" pide al servidor las opciones
 * (openrouteservice, Google o aproximada). 3. Se comparan en tarjetas y en el mapa. 4. Se puede
 * arrastrar la lista para fijar un orden propio y recalcular. 5. "Asignar" guarda.
 *
 * El servidor recalcula todo con los datos de la base: este archivo solo envía
 * ids de pedido y el orden deseado.
 */
(function () {
    'use strict';

    var cfgEl = document.getElementById('rutas-config');
    if (!cfgEl) { return; }
    var cfg = JSON.parse(cfgEl.textContent);

    var lista      = document.getElementById('listaCandidatos');
    var btnCalc    = document.getElementById('btnCalcular');
    var btnManual  = document.getElementById('btnManual');
    var estado     = document.getElementById('estadoCalculo');
    var bloqueOps  = document.getElementById('bloqueOpciones');
    var cajaOps    = document.getElementById('opciones');
    var contador   = document.querySelector('[data-contador]');
    var mapaEl     = document.getElementById('mapaRutas');

    var mapa = null, capas = [], ultimo = null;

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function seleccionados() {
        if (!lista) { return []; }
        return Array.prototype.slice.call(lista.querySelectorAll('li'))
            .filter(function (li) { var c = li.querySelector('input[type=checkbox]'); return c && c.checked; })
            .map(function (li) { return li.getAttribute('data-id'); });
    }

    function actualizarContador() {
        var n = seleccionados().length;
        if (contador) {
            contador.textContent = '(' + n + ' / ' + cfg.max + ')';
            contador.style.color = n > cfg.max ? '#c62828' : '';
        }
        if (btnCalc) { btnCalc.disabled = n === 0 || n > cfg.max || !Object.keys(cfg.almacenes).length; }
        if (!ultimo) { dibujarPrevia(); }
    }

    function invalidar() {
        ultimo = null;
        if (bloqueOps) { bloqueOps.hidden = true; }
        if (lista) {
            lista.querySelectorAll('[data-num]').forEach(function (n) { n.hidden = true; });
            lista.querySelectorAll('[data-eta]').forEach(function (n) { n.textContent = ''; });
        }
    }

    // ── Mapa ───────────────────────────────────────────────

    function limpiarMapa() {
        capas.forEach(function (c) { if (c && c.quitar) { c.quitar(); } });
        capas = [];
    }

    function almacenActual() {
        var sel = document.getElementById('r_almacen');
        return sel ? cfg.almacenes[sel.value] : null;
    }

    function dibujarPrevia() {
        if (!mapa) { return; }
        limpiarMapa();
        var puntos = [];
        var a = almacenActual();
        if (a) {
            capas.push(FruverMapas.marcador(mapa, { lat: a.lat, lng: a.lng }, { texto: 'A', color: '#5d4037', borde: '#3e2723', titulo: a.nombre }));
            puntos.push({ lat: a.lat, lng: a.lng });
        }
        seleccionados().forEach(function (id) {
            var p = cfg.candidatos[id];
            if (!p) { return; }
            capas.push(FruverMapas.marcador(mapa, { lat: p.lat, lng: p.lng }, { color: '#9e9e9e', borde: '#616161', titulo: '#' + id + ' ' + p.cliente }));
            puntos.push({ lat: p.lat, lng: p.lng });
        });
        FruverMapas.encuadrar(mapa, puntos);
    }

    function dibujarOpcion(op, datos) {
        if (!mapa) { return; }
        limpiarMapa();
        var a = datos.almacen, puntos = [{ lat: a.lat, lng: a.lng }];
        capas.push(FruverMapas.marcador(mapa, puntos[0], { texto: 'A', color: '#5d4037', borde: '#3e2723', titulo: 'Almacén' }));

        op.orden.forEach(function (id, i) {
            var p = datos.puntos[id];
            var pos = { lat: p.lat, lng: p.lng };
            puntos.push(pos);
            var m = FruverMapas.marcador(mapa, pos, { texto: i + 1, titulo: (i + 1) + '. #' + id + ' ' + p.cliente + ' — ' + p.dir });
            capas.push(m);
        });

        var linea = puntos.slice();
        if (datos.regresa) { linea.push(puntos[0]); }
        capas.push(FruverMapas.dibujarRuta(mapa, op.polyline, linea, op.proveedor !== 'local' ? '#1565c0' : '#ef6c00'));
        FruverMapas.encuadrar(mapa, puntos);
    }

    // ── Opciones ───────────────────────────────────────────

    function pintarOpciones(datos) {
        cajaOps.innerHTML = '';
        var claves = Object.keys(datos.opciones);

        claves.forEach(function (clave, i) {
            var op  = datos.opciones[clave];
            var div = document.createElement('div');
            div.className = 'ruta-opcion';
            div.setAttribute('role', 'button');
            div.setAttribute('tabindex', '0');

            var h = document.createElement('h4');
            h.textContent = cfg.nombres[clave] || clave;
            if (i === 0 && claves.length > 1) {
                var b = document.createElement('span');
                b.className = 'ruta-badge';
                b.textContent = 'recomendada';
                h.appendChild(document.createTextNode(' '));
                h.appendChild(b);
            }
            if (op.proveedor === 'local') {
                var g = document.createElement('span');
                g.className = 'ruta-badge ruta-badge--gris';
                g.textContent = 'aproximada';
                h.appendChild(document.createTextNode(' '));
                h.appendChild(g);
            }
            div.appendChild(h);

            var cif = document.createElement('div');
            cif.className = 'cifras';
            [['Distancia', op.km], ['Tiempo manejando', op.tiempo], ['Termina aprox.', op.fin]].forEach(function (par) {
                var s = document.createElement('span');
                s.appendChild(document.createTextNode(par[0] + ': '));
                var bb = document.createElement('b');
                bb.textContent = par[1];
                s.appendChild(bb);
                cif.appendChild(s);
            });
            div.appendChild(cif);

            if (op.aviso) {
                var av = document.createElement('p');
                av.className = 'aviso';
                av.textContent = op.aviso;
                div.appendChild(av);
            }

            function elegir() {
                cajaOps.querySelectorAll('.ruta-opcion').forEach(function (x) { x.classList.remove('activa'); });
                div.classList.add('activa');
                document.getElementById('g_token').value = datos.token;
                document.getElementById('g_estrategia').value = clave;
                aplicarOrden(op);
                dibujarOpcion(op, datos);
            }
            div.addEventListener('click', elegir);
            div.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); elegir(); } });
            cajaOps.appendChild(div);

            if (i === 0) { elegir(); }
        });

        bloqueOps.hidden = false;
        if (btnManual) { btnManual.hidden = false; }
    }

    /** Ordena la lista según la opción elegida y muestra número y hora estimada. */
    function aplicarOrden(op) {
        if (!lista) { return; }
        var items = {};
        lista.querySelectorAll('li').forEach(function (li) { items[li.getAttribute('data-id')] = li; });

        lista.querySelectorAll('[data-num]').forEach(function (n) { n.hidden = true; });
        lista.querySelectorAll('[data-eta]').forEach(function (n) { n.textContent = ''; });

        var ancla = lista.firstChild;
        op.orden.forEach(function (id, i) {
            var li = items[String(id)];
            if (!li) { return; }
            lista.insertBefore(li, ancla);
            ancla = li.nextSibling;
            var n = li.querySelector('[data-num]');
            n.textContent = String(i + 1);
            n.hidden = false;
            var eta = li.querySelector('[data-eta]');
            if (eta && op.etas[String(id)]) { eta.textContent = ' · llega ≈ ' + op.etas[String(id)]; }
        });
    }

    // ── Servidor ───────────────────────────────────────────

    function calcular(estrategia) {
        var ids = seleccionados();
        if (!ids.length) { return; }

        var fd = new FormData();
        fd.append(cfg.csrfNombre, csrf());
        fd.append('id_repartidor', document.getElementById('r_repartidor').value);
        fd.append('id_almacen', document.getElementById('r_almacen').value);
        fd.append('fecha', document.getElementById('r_fecha').value);
        fd.append('hora', document.getElementById('r_hora').value);
        fd.append('regresa', document.getElementById('r_regresa').checked ? '1' : '0');
        if (estrategia) { fd.append('estrategia', estrategia); }
        ids.forEach(function (id) { fd.append('pedidos[]', id); });

        btnCalc.disabled = true;
        if (btnManual) { btnManual.disabled = true; }
        estado.textContent = 'Calculando la ruta…';
        estado.style.color = '';

        fetch(cfg.urlOpciones, {
            method: 'POST',
            body: fd,
            headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, mensaje: 'Respuesta inesperada del servidor (HTTP ' + r.status + ').' }; });
        }).then(function (j) {
            if (j.csrf) { var m = document.querySelector('meta[name="csrf-token"]'); if (m) { m.setAttribute('content', j.csrf); } }
            if (!j.ok) {
                estado.textContent = j.mensaje || 'No se pudo calcular.';
                estado.style.color = '#c62828';
                (j.sin_ubicar || []).forEach(function (id) {
                    var li = lista.querySelector('li[data-id="' + id + '"]');
                    if (li) { li.classList.add('sin-ubicar'); }
                });
                invalidar();
                return;
            }
            ultimo = j;
            estado.textContent = Object.keys(j.opciones).length > 1
                ? 'Compara las opciones y elige una. También puedes arrastrar los pedidos para fijar tu propio orden.'
                : 'Revisa la ruta. Puedes arrastrar los pedidos para cambiar el orden y recalcular.';
            pintarOpciones(j);
            habilitarArrastre(true);
        }).catch(function () {
            estado.textContent = 'Sin conexión con el servidor.';
            estado.style.color = '#c62828';
        }).then(function () {
            actualizarContador();
            if (btnManual) { btnManual.disabled = false; }
        });
    }

    // ── Arrastrar para ordenar a mano ──────────────────────

    var arrastrado = null;

    function habilitarArrastre(si) {
        if (!lista) { return; }
        lista.querySelectorAll('li').forEach(function (li) {
            var c = li.querySelector('input[type=checkbox]');
            li.draggable = si && c && c.checked;
        });
    }

    if (lista) {
        lista.addEventListener('dragstart', function (e) {
            arrastrado = e.target.closest('li');
            if (arrastrado) { arrastrado.classList.add('arrastrando'); e.dataTransfer.effectAllowed = 'move'; }
        });
        lista.addEventListener('dragend', function () {
            if (arrastrado) { arrastrado.classList.remove('arrastrando'); }
            arrastrado = null;
        });
        lista.addEventListener('dragover', function (e) {
            if (!arrastrado) { return; }
            e.preventDefault();
            var sobre = e.target.closest('li');
            if (!sobre || sobre === arrastrado) { return; }
            var r = sobre.getBoundingClientRect();
            lista.insertBefore(arrastrado, (e.clientY - r.top) > r.height / 2 ? sobre.nextSibling : sobre);
        });
        lista.addEventListener('drop', function (e) {
            e.preventDefault();
            if (estado && ultimo) { estado.textContent = 'Orden cambiado: pulsa «Recalcular con mi orden».'; }
        });
        lista.addEventListener('change', function (e) {
            if (e.target.matches('input[type=checkbox]')) { invalidar(); habilitarArrastre(false); actualizarContador(); }
        });
    }

    // ── Botones ────────────────────────────────────────────

    document.querySelectorAll('[data-sel]').forEach(function (b) {
        b.addEventListener('click', function () {
            var modo = b.getAttribute('data-sel');
            var rep = document.getElementById('r_repartidor').value;
            lista.querySelectorAll('li').forEach(function (li) {
                var c = li.querySelector('input[type=checkbox]');
                if (!c || c.disabled) { return; }
                c.checked = modo === 'todos' ? true : modo === 'ninguno' ? false : li.getAttribute('data-repartidor') === rep;
            });
            invalidar();
            habilitarArrastre(false);
            actualizarContador();
        });
    });

    if (btnCalc) { btnCalc.addEventListener('click', function () { calcular(null); }); }
    if (btnManual) { btnManual.addEventListener('click', function () { calcular('manual'); }); }

    ['r_repartidor', 'r_almacen', 'r_hora', 'r_regresa'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.addEventListener('change', function () { invalidar(); actualizarContador(); }); }
    });
    var fecha = document.getElementById('r_fecha');
    if (fecha) {
        fecha.addEventListener('change', function () { window.location = cfg.urlFecha + '?fecha=' + encodeURIComponent(fecha.value); });
    }

    var formGuardar = document.getElementById('formGuardar');
    if (formGuardar) {
        formGuardar.addEventListener('submit', function (e) {
            if (!document.getElementById('g_token').value) { e.preventDefault(); return; }
            var b = formGuardar.querySelector('button');
            setTimeout(function () { b.disabled = true; b.textContent = 'Guardando…'; }, 0);
        });
    }

    // ── Inicio ─────────────────────────────────────────────

    if (window.FruverMapas && FruverMapas.disponible && mapaEl) {
        FruverMapas.cargar().then(function () {
            mapa = FruverMapas.crearMapa(mapaEl);
            if (ultimo) { var act = cajaOps.querySelector('.activa'); if (act) { act.click(); } } else { dibujarPrevia(); }
        }).catch(function (err) {
            mapaEl.hidden = true;
            var e = document.querySelector('[data-mapa-estado]');
            if (e) { e.textContent = err.message; e.hidden = false; }
        });
    } else if (mapaEl) {
        mapaEl.hidden = true;
        var e = document.querySelector('[data-mapa-estado]');
        if (e) { e.textContent = 'Mapa desactivado. Puedes planear igual: se muestran distancias y tiempos.'; e.hidden = false; }
    }

    actualizarContador();
})();

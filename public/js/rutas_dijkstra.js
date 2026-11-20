/*
 * Rutas → Dijkstra: camino de menor costo entre dos puntos.
 *
 * Solo envía las claves del origen y del destino ("a3" almacén, "d15" dirección) y el
 * criterio; el servidor toma las coordenadas de la base de datos, descarga las calles,
 * corre Dijkstra y devuelve el camino. Aquí solo se dibuja.
 */
(function () {
    'use strict';

    var cfgEl = document.getElementById('dijkstra-config');
    var boton = document.getElementById('btnDijkstra');
    if (!cfgEl || !boton) { return; }
    var cfg = JSON.parse(cfgEl.textContent);

    var estado  = document.getElementById('estadoDijkstra');
    var cifras  = document.getElementById('cifrasDijkstra');
    var leyenda = document.getElementById('leyendaDijkstra');
    var mapaEl  = document.getElementById('mapaDijkstra');
    var mapa = null, capas = [];

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function cifra(titulo, valor, nota) {
        var d = document.createElement('div');
        d.className = 'dij-cifra';
        d.appendChild(document.createTextNode(titulo));
        var b = document.createElement('b');
        b.textContent = valor;
        d.appendChild(b);
        if (nota) {
            var s = document.createElement('small');
            s.className = 'w3-text-grey';
            s.textContent = nota;
            d.appendChild(s);
        }
        cifras.appendChild(d);
    }

    function dibujar(j) {
        if (!mapa) { return; }
        capas.forEach(function (c) { c.quitar(); });
        capas = [];
        if (j.servicio && j.servicio.polyline) {
            capas.push(FruverMapas.dibujarRuta(mapa, j.servicio.polyline, [], '#1565c0'));
        }
        capas.push(FruverMapas.dibujarRuta(mapa, null, j.camino, '#c62828', true));
        capas.push(FruverMapas.marcador(mapa, j.origen, { texto: 'O', color: '#5d4037', borde: '#3e2723', titulo: 'Origen: ' + j.origen.nombre }));
        capas.push(FruverMapas.marcador(mapa, j.destino, { texto: 'D', color: '#2e7d32', borde: '#1b5e20', titulo: 'Destino: ' + j.destino.nombre }));
        FruverMapas.encuadrar(mapa, j.camino.concat([j.origen, j.destino]));
    }

    function mostrar(j) {
        cifras.innerHTML = '';
        var g = j.grafo;
        cifra('Distancia (Dijkstra)', j.km);
        cifra('Tiempo estimado (Dijkstra)', j.tiempo, 'Peso usado: ' + (j.criterio === 'tiempo' ? 'tiempo' : 'distancia'));
        if (j.servicio) {
            cifra(j.servicio.nombre, j.servicio.km + ' · ' + j.servicio.tiempo, 'Misma consulta con el servicio de rutas');
        }
        cifra('Grafo de calles', g.nodos.toLocaleString('es-MX') + ' nodos · ' + g.aristas.toLocaleString('es-MX') + ' aristas');
        cifra('Nodos revisados por Dijkstra', g.visitados.toLocaleString('es-MX'), 'El camino pasa por ' + g.nodosCamino + ' nodos');
        cifra('Tiempo de cálculo', g.msDijkstra + ' ms', 'Descarga de calles: ' + g.msDescarga + ' ms');
        leyenda.hidden = false;
        document.getElementById('leyendaServicio').textContent = j.servicio ? j.servicio.nombre : 'Servicio de rutas (no disponible)';
        if (g.acercamiento > 150) {
            estado.textContent = 'Nota: un punto quedó a ' + g.acercamiento + ' m de la calle más cercana del mapa.';
        }
        dibujar(j);
    }

    boton.addEventListener('click', function () {
        var fd = new FormData();
        fd.append(cfg.csrfNombre, csrf());
        fd.append('origen', document.getElementById('d_origen').value);
        fd.append('destino', document.getElementById('d_destino').value);
        fd.append('criterio', document.getElementById('d_criterio').value);

        boton.disabled = true;
        estado.style.color = '';
        estado.textContent = 'Descargando calles de OpenStreetMap y calculando… La primera vez para un par de puntos puede tardar hasta un minuto; después queda guardado 24 h.';

        fetch(cfg.url, {
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
                return;
            }
            estado.textContent = '';
            mostrar(j);
        }).catch(function () {
            estado.textContent = 'Sin conexión con el servidor.';
            estado.style.color = '#c62828';
        }).then(function () { boton.disabled = false; });
    });

    if (window.FruverMapas && FruverMapas.disponible && mapaEl) {
        FruverMapas.cargar().then(function () {
            mapa = FruverMapas.crearMapa(mapaEl);
        }).catch(function (err) {
            mapaEl.hidden = true;
            var e = document.querySelector('[data-mapa-estado]');
            if (e) { e.textContent = err.message; e.hidden = false; }
        });
    } else if (mapaEl) {
        mapaEl.hidden = true;
    }
})();

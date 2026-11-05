/*
 * Mapa de una ruta guardada (detalle del admin y "Mi ruta" del repartidor) y
 * captura del GPS del teléfono para los formularios marcados con data-gps.
 *
 * Datos en <script type="application/json" id="ruta-datos">:
 *   { almacen:{lat,lng}, regresa:bool, polyline:"…"|null, proveedor:"google"|"local",
 *     paradas:[{n, lat, lng, titulo, estado}] }
 */
(function () {
    'use strict';

    var COLORES = { asignada: '#1565c0', en_ruta: '#ef6c00', entregada: '#2e7d32', fallida: '#c62828', cancelada: '#9e9e9e' };

    function mapaDeRuta() {
        var el = document.getElementById('mapaRuta');
        var datosEl = document.getElementById('ruta-datos');
        if (!el || !datosEl) { return; }

        var d = JSON.parse(datosEl.textContent);
        var aviso = document.querySelector('[data-mapa-estado]');

        if (!window.FruverMapas || !FruverMapas.disponible) {
            el.hidden = true;
            if (aviso) { aviso.textContent = 'Mapa desactivado (falta rutas.llaveNavegador). Usa los botones «Navegar» de cada parada.'; aviso.hidden = false; }
            return;
        }

        FruverMapas.cargar().then(function () {
            var mapa = FruverMapas.crearMapa(el);
            var puntos = [];
            if (d.almacen && d.almacen.lat) {
                var a = { lat: parseFloat(d.almacen.lat), lng: parseFloat(d.almacen.lng) };
                FruverMapas.marcador(mapa, a, { texto: 'A', color: '#5d4037', borde: '#3e2723', titulo: 'Almacén' });
                puntos.push(a);
            }
            d.paradas.forEach(function (p) {
                if (p.lat === null || p.lng === null) { return; }
                var pos = { lat: parseFloat(p.lat), lng: parseFloat(p.lng) };
                var c = COLORES[p.estado] || '#1565c0';
                var m = FruverMapas.marcador(mapa, pos, { texto: p.n, color: c, borde: c, titulo: p.n + '. ' + p.titulo });
                m.alClic(function () {
                    var tarjeta = document.getElementById('parada-' + p.id);
                    if (tarjeta) { tarjeta.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                });
                puntos.push(pos);
            });
            var linea = puntos.slice();
            if (d.regresa && puntos.length) { linea.push(puntos[0]); }
            FruverMapas.dibujarRuta(mapa, d.polyline, linea, d.proveedor === 'google' ? '#1565c0' : '#ef6c00');
            FruverMapas.encuadrar(mapa, puntos);

            // Punto azul con la ubicación del repartidor (solo si ya dio permiso).
            if (navigator.geolocation && navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'geolocation' }).then(function (st) {
                    if (st.state !== 'granted') { return; }
                    var yo = null;
                    navigator.geolocation.watchPosition(function (pos) {
                        var p = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                        if (!yo) { yo = new google.maps.Circle({ map: mapa, center: p, radius: 12, fillColor: '#1a73e8', fillOpacity: 0.9, strokeColor: '#fff', strokeWeight: 2 }); }
                        else { yo.setCenter(p); }
                    }, function () {}, { enableHighAccuracy: true, maximumAge: 15000 });
                }).catch(function () {});
            }
        }).catch(function (err) {
            el.hidden = true;
            if (aviso) { aviso.textContent = err.message; aviso.hidden = false; }
        });
    }

    /**
     * Formularios con data-gps: antes de enviarse leen la ubicación del teléfono y la
     * ponen en gps_lat / gps_lng. data-gps="requerido" no envía sin ubicación.
     */
    function capturaGps() {
        document.querySelectorAll('form[data-gps]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.__gpsListo || e.defaultPrevented) { return; }
                e.preventDefault();

                var requerido = form.getAttribute('data-gps') === 'requerido';
                var boton = form.querySelector('button[type="submit"]');
                var texto = boton ? boton.innerHTML : '';

                function enviar() {
                    form.__gpsListo = true;
                    if (boton) { boton.disabled = true; }
                    form.submit();
                }

                if (!navigator.geolocation) {
                    if (requerido) { alert('Este teléfono no permite leer la ubicación.'); return; }
                    enviar();
                    return;
                }

                if (boton) { boton.disabled = true; boton.textContent = 'Obteniendo ubicación…'; }

                navigator.geolocation.getCurrentPosition(function (pos) {
                    var lat = form.querySelector('input[name="gps_lat"]');
                    var lng = form.querySelector('input[name="gps_lng"]');
                    if (lat && lng) {
                        lat.value = pos.coords.latitude.toFixed(7);
                        lng.value = pos.coords.longitude.toFixed(7);
                    }
                    enviar();
                }, function () {
                    if (boton) { boton.disabled = false; boton.innerHTML = texto; }
                    if (requerido) {
                        alert('No se pudo leer tu ubicación. Activa el GPS y permite el acceso a la ubicación para este sitio.');
                        return;
                    }
                    enviar(); // la entrega se registra igual, sin GPS
                }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
            });
        });
    }

    function iniciar() { mapaDeRuta(); capturaGps(); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();

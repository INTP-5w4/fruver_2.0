# Rutas de reparto — guía técnica de instalación y uso

Este módulo permite al personal planear la ruta óptima de cada repartidor y al
repartidor seguirla desde su teléfono: ver el orden de las paradas, navegar con
Google Maps (enlace gratuito), registrar entregas con GPS y foto, y reoptimizar desde
donde esté.

**Proveedor recomendado: openrouteservice + OpenStreetMap.** Es gratuito, no pide
tarjeta ni RFC y no hay que instalar nada: Fruver consulta el servicio por internet.
Google Maps sigue disponible como opción (`rutas.proveedor = google`).

> Diseño y decisiones: `docs/PLAN_RUTAS_REPARTO.md` y `docs/CONTRASTE_PLAN_RUTAS.md`.

---

## 1. Base de datos

```bash
php spark migrate --all
```

Aplica `2026-11-05-100000_AddRutasReparto` y `2026-11-05-120000_AddOsmOrigenDireccion`
(ambas idempotentes). Con phpMyAdmin: ejecuta **una vez** `database_cambios/rutas_reparto_cambios.sql`;
si ya lo habías ejecutado antes de este cambio, ejecuta además `database_cambios/rutas_osm_cambios.sql`.

| Tabla | Cambio |
|-------|--------|
| `almacen` | Nueva: punto de salida (y regreso) de las rutas. |
| `ruta` | Nueva: agrupa las entregas de un repartidor en un día, con distancia, tiempo y trazo. |
| `entrega` | `id_ruta`, `orden_ruta`, `eta`, tramo y GPS real de la entrega. Cada entrega es una parada. |
| `direccion` | `lat`, `lng`, `place_id`, `codigo_postal`, `referencias`, `es_principal`, origen (`osm`, `google`, `manual`, `gps_entrega`) y precisión. |
| `pedido` | `id_direccion` (a dónde va) y `ventana_fin` (entregar antes de). |
| `repartidor` | `vehiculo` (moto, auto, camioneta, bicicleta) y `capacidad_kg`. |

Mientras no se corra la migración, el sistema funciona exactamente como antes.

## 2. Proveedor de mapas

| `rutas.proveedor` | Qué usa | Qué necesitas |
|-------------------|---------|---------------|
| `ors` (recomendado) | openrouteservice (rutas, optimización, búsqueda de direcciones) + mapas de OpenStreetMap con Leaflet | Cuenta gratuita en <https://account.heigit.org> y su llave |
| `google` | Google Routes, Geocoding, Maps JavaScript y Places | Cuenta de facturación de Google Cloud y dos llaves |
| `local` | Mapa de OpenStreetMap para ubicar con pin; rutas aproximadas (línea recta, sin tráfico) | Nada |
| *(vacío)* | Automático: `ors` si hay `rutas.llaveOrs`, `google` si hay `rutas.llaveServidor`, si no `local` | — |

### openrouteservice (recomendado)

1. Crea una cuenta en <https://account.heigit.org> y confirma tu correo.
2. En el panel, crea una llave (token) del plan gratuito *Standard*.
3. En `.env`:
   ```ini
   rutas.proveedor = ors
   rutas.llaveOrs  = "tu-llave"
   ```

La llave solo se usa en el servidor (PHP). El navegador nunca la ve: el buscador de
direcciones pregunta a `/direcciones/buscar` y el servidor consulta a openrouteservice.

Límites diarios del plan gratuito (según openrouteservice.org/plans): 2,000 rutas,
500 optimizaciones, 1,000 búsquedas de dirección y 1,000 autocompletados. Cada
«Calcular rutas» usa 1 optimización y 2–3 rutas; cada «Reoptimizar», 1 y 1.

Limitaciones: no considera tráfico en tiempo real, y en México muchos números
exteriores no están en OpenStreetMap, así que varias direcciones se ubicarán de forma
aproximada (calle o colonia). Por eso conviene acomodar el pin, y el GPS de cada
entrega corrige la ubicación para la siguiente vez.

### Google (opcional)

Requiere cuenta de facturación (en México pide RFC). Habilita Routes API, Geocoding API,
Maps JavaScript API y Places API (New); crea una llave de servidor (restringida a Routes
y Geocoding) y una de navegador (restringida por dominio a Maps JavaScript y Places).

```ini
rutas.proveedor      = google
rutas.llaveServidor  = "AIza..."
rutas.llaveNavegador = "AIza..."
rutas.mapId          = ""    # opcional
```

Los Términos de Google solo permiten guardar sus coordenadas 30 días (el comando de
geocodificación las renueva) y exigen mostrarlas sobre un mapa de Google.

### Otras opciones de `.env`

```ini
rutas.horaSalida       = "09:00"   # hora de salida por omisión
rutas.minutosPorParada = 5         # tiempo estimado en cada entrega
rutas.maxParadas       = 25        # paradas por ruta
# rutas.latCentro = 19.0433        # centro del mapa si aún no hay almacén
# rutas.lngCentro = -98.1981
# rutas.mapaTiles = "https://tile.openstreetmap.org/{z}/{x}/{y}.png"   # servidor de capas
```

Todas están documentadas en `app/Config/Rutas.php`.

Sobre las capas de OpenStreetMap: sus servidores los sostienen donaciones y piden uso
moderado y mostrar el crédito (Fruver lo muestra). Con pocos usuarios no hay problema;
si el uso crece, cambia `rutas.mapaTiles` por un proveedor de capas comercial.

## 3. Primeros pasos

1. **Rutas de reparto → Almacén**: registra la bodega y ubica el pin en la puerta de salida.
2. **Repartidores**: indica el vehículo de cada uno (con openrouteservice, moto y auto usan el mismo perfil).
   Para que un repartidor entre al sistema: `php spark repartidor:vincular correo@ejemplo.com <id>`.
3. **Direcciones**: la lista muestra cuáles están «Sin ubicar» o «Aproximada». Ubícalas con
   el botón ⌖ de cada fila o todas de una vez:
   ```bash
   php spark direcciones:geocodificar --prueba   # solo muestra
   php spark direcciones:geocodificar            # pendientes (máx. 200 por ejecución)
   ```
   Con openrouteservice no hace falta repetirlo: las coordenadas no vencen.
   Con Google conviene programarlo semanalmente.

## 4. Uso diario

**Personal — planear (menú «Rutas de reparto»)**
1. Elige fecha, hora de salida, repartidor y almacén.
2. Marca los pedidos (confirmados). Los que no tienen dirección ubicada aparecen deshabilitados.
3. **Calcular rutas óptimas** muestra las opciones para comparar:
   - *Más rápida*: el servicio reordena las paradas.
   - *Por horario y preferentes*: primero quien tiene hora límite y los clientes preferenciales.
   - *Sin casetas ni autopistas*.
4. Puedes **arrastrar** los pedidos de la lista para fijar tu propio orden y pulsar
   **Recalcular con mi orden**.
5. **Asignar esta ruta**: se crean las entregas y el repartidor la ve en su teléfono.
6. En el detalle de la ruta: hora estimada de cada parada, botón de **WhatsApp** para
   avisar la hora al cliente y fotos de evidencia.

**Repartidor — en el teléfono («Mi ruta»)**
1. **Salir a ruta**: todas las paradas pasan a «en ruta» (y los pedidos a *en tránsito*).
2. **Navegar** abre Google Maps hacia la siguiente parada (enlace público, sin llave ni costo).
3. **Entregado**: registra cobro, foto opcional y la ubicación GPS del teléfono
   (que además corrige la ubicación del cliente para la próxima vez).
4. **No se pudo**: registra el motivo; el personal podrá reprogramar.
5. **Reoptimizar desde aquí**: reordena las paradas pendientes desde su ubicación actual.
6. Al cerrar la última parada, la ruta se marca como terminada.

Si el servicio de rutas no responde o se acaba la cuota del día, la ruta se calcula en
modo aproximado y la pantalla lo indica: el reparto no se detiene.

### Preferentes y pedidos "de paso"

La opción **Preferentes primero** pone antes a los pedidos con hora límite y a los clientes
preferenciales, pero un pedido normal que queda **en el camino** hacia un preferente se entrega
al pasar (ej.: Grajales → Puebla pasando por Acajete). Cuenta como "de paso" si el desvío es
como máximo el menor de `desvioMaxKm` (5 km) y `desvioMaxPorcentaje` (15 % del tramo), en
`app/Config/Rutas.php` o en `.env`:

```ini
# rutas.desvioMaxKm = 5
# rutas.desvioMaxPorcentaje = 15
```

Nunca se intercala nada antes de un pedido con hora límite. La opción avisa qué pedidos
se dejan de paso. Los normales que no quedan en el camino van al final, por cercanía.

### Combustible y costo de traslado

Cada opción de ruta muestra los litros estimados y su costo (km ÷ rendimiento × precio por
litro), y el costo por entrega. Al asignar la ruta se guardan en `ruta.litros`,
`ruta.costo_combustible` y `ruta.precio_litro` (migración `AddCombustible`).

- **Rendimiento (km/L) y combustible** (verde = Magna, roja = Premium, diésel): en la ficha
  del repartidor. Sin dato se usa uno típico (moto 30, auto 12, camioneta 8 km/L).
- **Precios por litro**: Rutas → **Combustible**. Los de fábrica están en
  `Config\Rutas::$preciosCombustible`; los que captura el personal se guardan en la tabla `settings`.

## 5. Problemas comunes

| Síntoma | Causa probable |
|---------|----------------|
| Aviso «No hay servicio de rutas configurado» | Falta `rutas.llaveOrs` en `.env` (o el archivo quedó como `.env.txt`). |
| Rutas siempre «aproximadas» | Llave de openrouteservice inválida, cuota diaria agotada o sin internet en el servidor (ver `writable/logs`). |
| El buscador de direcciones no muestra nada | Escribe al menos 4 letras; si sigue, revisa la llave y el log. Siempre puedes tocar el mapa. |
| El mapa sale en blanco | Sin internet en la computadora, o la consola (F12) muestra un bloqueo de *Content Security Policy*: agrega el dominio en `app/Libraries/Rutas/CspMapas.php`. |
| «Reoptimizar» dice que no lee la ubicación | El sitio debe abrirse con **HTTPS** y el repartidor debe permitir la ubicación. |
| Un pedido no aparece en el planeador | Debe estar *confirmado* y sin una entrega abierta. |
| Con Google: «rechazó la llave del navegador» | La llave del navegador no permite tu dominio o no tiene Maps JavaScript API. |

## 6. Archivos del módulo

- Configuración: `app/Config/Rutas.php`
- Motor: `app/Libraries/Rutas/` (`PlaneadorRutas`, `ServiciosMapa`, `OptimizadorOrs`, `OptimizadorGoogle`, `OptimizadorLocal`, `GeocodificadorOrs`, `Geocodificador`, `UbicadorDirecciones`, `EnlaceNavegacion`, `CspMapas`)
- Modelos: `Modelo_ruta`, `Modelo_almacen` (+ cambios en `Modelo_direccion`, `Modelo_pedido`, `Modelo_entrega`, `Modelo_repartidor`)
- Controladores: `Rutas` (planeador), `Entregas` (mi ruta, reoptimizar, evidencia), `Direcciones` (incluye el buscador), `Tienda`
- Vistas: `rutas_planeador`, `rutas_detalle`, `rutas_almacen`, `rutas_combustible`, `mi_ruta`, `partials/*`
- Librerías: `app/Libraries/Rutas/*` (incluye `Combustible.php`)
- JavaScript: `public/js/mapas.js` (adaptador Leaflet/Google), `geo_direccion.js`, `direccion_pedido.js`, `rutas_planeador.js`, `ruta_mapa.js`
- Comando: `php spark direcciones:geocodificar`
- Pruebas: `tests/unit/RutasTest.php`, `tests/unit/RutasOrsTest.php`, `tests/unit/VistasRutasTest.php`

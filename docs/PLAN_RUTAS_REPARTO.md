# Plan de implementación — Rutas de reparto (Fruver 2.0)

> Rama: `Rutas-reparto` · Fecha: noviembre 2026 · Estado: propuesta para revisión
>
> ✅ **Implementado (5 nov 2026)** sobre `feature/impresion-termica`. Instalación y uso: **`docs/RUTAS_REPARTO.md`**.
>
> ⚠️ **Actualización 5 nov 2026:** este plan se escribió sobre `main`. La rama más avanzada,
> `feature/impresion-termica`, ya resuelve varias fases (repartidor ↔ usuario, rol, seguridad,
> tabla `entrega`) y trae CSP activado. Lee **`docs/CONTRASTE_PLAN_RUTAS.md`**: sustituye la §4
> (modelo de datos) y reajusta las §5 y §8 de este documento.
>
> Objetivo: que el repartidor vea sus pedidos del día ordenados en la ruta más eficiente,
> pueda **comparar y escoger** entre opciones de ruta, navegar con Google Maps y marcar
> cada entrega desde su teléfono. Los datos de calles, tráfico y tiempos vienen de
> Google Maps Platform.

---

## 1. Diagnóstico del repositorio (lo que afecta a esta funcionalidad)

Revisé el código actual (CodeIgniter 4.7 + Shield 1.3, MariaDB, vistas PHP con W3.CSS).
Antes de construir rutas hay que resolver estas brechas:

| # | Hallazgo | Dónde | Por qué importa para rutas |
|---|----------|-------|----------------------------|
| 1 | **El pedido no sabe a qué dirección va.** `pedido` solo tiene `id_cliente`, y un cliente puede tener varias direcciones (el cliente 7 tiene 2, el 9 tiene 2). | `fruver.sql` (tabla `pedido`), `Modelo_pedido` | Sin `id_direccion` en el pedido no hay forma de saber a dónde llevarlo. Es el cambio más importante. |
| 2 | **Las direcciones no tienen coordenadas.** Solo texto (`colonia`, `calle`, `numero`, `municipio`, `estado`). No hay código postal ni referencias. | tabla `direccion`, `crea_direccion.php` | Google necesita lat/lng o un `place_id` para calcular rutas fiables. Los datos de prueba incluyen direcciones ficticias ("Oreja de van Goh"). |
| 3 | **El repartidor no es un usuario del sistema.** `repartidor` no está ligado a la tabla `users` de Shield, y no existe un rol "repartidor". | `Modelo_repartidor`, `Config/AuthGroups.php` | El repartidor no puede iniciar sesión y ver *su* ruta. |
| 4 | **No hay punto de partida.** No existe tabla de bodega/sucursal. La columna `repartidor.direccion` es texto libre (todos tienen "CERRADA 1 SN, 401"). | — | Toda ruta necesita origen (y normalmente regreso). |
| 5 | **El flujo de estatus está duplicado e inconsistente.** El mapa de transiciones está copiado 4 veces: `P_pedidos::modifica_pedido_completo`, `P_pedidos::api_pedido`, `Estatus::guarda_estatus` y `Estatus::modifica`, y las copias no coinciden entre sí. `Estatus` usa `pedido_credito`, que **no existe** en el ENUM (`pedido_a_credito`). Además unas consultas ordenan el último estatus por `fecha` y otras por `id`. | `app/Controllers/P_pedidos.php`, `app/Controllers/Estatus.php` | Las rutas van a mover pedidos a `pedido_en_transito` y `pedido_entregado`; necesitan una sola fuente de verdad. |
| 6 | **No hay control de acceso por rol en las rutas HTTP.** Solo `main_page` redirige al grupo `user`; cualquier usuario autenticado puede abrir `lista_pedido`, `borra_id_pedido/…`, etc. CSRF está desactivado globalmente (solo el buzón de sugerencias lo usa). | `Config/Filters.php`, `Config/Routes.php` | Un repartidor con sesión no debe poder borrar pedidos ni ver rutas de otros. |
| 7 | **Archivos de sesión versionados en una carpeta pública.** `public/null/ci_session*` está en git y es accesible por URL. | `public/null/` | Expone tokens CSRF/sesión. Conviene limpiarlo en esta misma rama (ver Fase 0). |
| 8 | **No se usan migraciones.** El esquema vive en dumps (`fruver.sql`, `entrada.sql`, `sugerencias.sql`). `app/Database/Migrations` está vacío. | raíz del repo | Los cambios de esta funcionalidad tocan 4 tablas; con migraciones cada integrante del equipo aplica lo mismo con `php spark migrate`. |
| 9 | Menores: `Modelo_pedido::$allowedFields` incluye `id_producto_pedido` (no existe); los controladores extienden `Controller` en vez de `BaseController`; `main_page_4.php` (vista del rol `user`) está vacía. | varios | `main_page_4` es un buen lugar para la pantalla "Mi ruta de hoy". |

Lo que **ya está bien** y se aprovecha: zona horaria `America/Mexico_City`, estatus con historial
por pedido (tabla `estatus`), la API JSON `api_pedido/(:num)`, el patrón de modales W3.CSS,
el throttler y saneo de entradas del buzón de sugerencias (buen modelo a copiar).

---

## 2. Qué servicios de Google usar y por qué

Google Maps Platform cobra por SKU y desde marzo 2025 da llamadas gratis **por SKU, por mes**:
10,000 (Essentials), 5,000 (Pro) y 1,000 (Enterprise). Al volumen de Fruver
(pocos repartidores, decenas de pedidos al día) **todo debería quedar dentro del nivel gratuito**,
pero se necesita cuenta de facturación activa.

| Necesidad | Servicio | Categoría / costo | Uso en Fruver |
|-----------|----------|-------------------|---------------|
| Capturar direcciones correctas | **Places API (New) — Place Autocomplete** (widget web) | Sesiones de autocompletado | En `crea_direccion` / `modifica_direccion` / modal de `main_page3`. Reduce errores de captura. |
| Convertir texto → coordenadas | **Geocoding API** | Essentials, 10k gratis/mes | Respaldo para direcciones viejas (comando de migración) y cuando no se usó el autocompletado. |
| Mostrar mapa con paradas numeradas | **Maps JavaScript API** (+ librerías `places`, `geometry`, `marker`) | Dynamic Maps, Essentials | Pantalla de planeación (admin) y "Mi ruta" (repartidor). |
| **Ordenar paradas de forma óptima** | **Routes API — `computeRoutes` con `optimizeWaypointOrder: true`** | **Compute Routes Pro** (la optimización se cobra en Pro: 5k gratis/mes, luego ~USD 10 / 1,000) | Motor principal. Hasta 25 paradas intermedias por solicitud, con tráfico (`TRAFFIC_AWARE`). |
| Abrir navegación paso a paso | **Maps URLs** (`https://www.google.com/maps/dir/?api=1&…`) | **Gratis, sin API key** | Botón "Navegar" en el teléfono del repartidor. |
| (Futuro) Asignar pedidos entre varios repartidores con horarios y capacidad | **Route Optimization API** (`optimizeTours`) | Por envío: Single Vehicle = Pro, Fleet (2+ vehículos) = Enterprise | Fase 6, opcional. Requiere cuenta de servicio (OAuth), no basta una API key. |

**Por qué Routes API y no la Directions API ni el `DirectionsService` de JavaScript:** Directions /
Distance Matrix son productos *legacy*; Routes API es su reemplazo, se llama desde el servidor
(la llave no queda expuesta), permite pedir solo los campos necesarios (field mask = menos costo)
y devuelve el orden optimizado en `optimizedIntermediateWaypointIndex`.

**Restricciones técnicas a respetar** (documentación de Google):
- Máximo 25 paradas intermedias por solicitud; más de 10 ya se factura en Pro.
- Con optimización, ninguna parada puede ser `via: true` y no se puede usar `TRAFFIC_AWARE_OPTIMAL` → usar `TRAFFIC_AWARE`.
- Hay que incluir `routes.optimizedIntermediateWaypointIndex` en el field mask.
- Las Maps URLs aceptan **hasta 9 waypoints (solo 3 en navegadores móviles)** y respetan el orden dado: por eso la navegación se abre **parada por parada** o en tramos.
- Términos de servicio: los lat/lng obtenidos de Geocoding/Places solo pueden guardarse **30 días**; el `place_id` sí puede guardarse indefinidamente. Ver §6.

---

## 3. Cómo "escoge" el repartidor su ruta óptima

Routes API no devuelve rutas alternativas cuando hay paradas intermedias, así que las
"opciones" se generan con estrategias distintas y se presentan comparadas:

| Opción | Cómo se calcula | Cuándo conviene |
|--------|-----------------|-----------------|
| **A. Más rápida (recomendada)** | `optimizeWaypointOrder: true`, `TRAFFIC_AWARE`, `departureTime` = hora de salida | Caso general |
| **B. Por prioridad / horario del cliente** | Orden por `ventana_fin` y `prioridad`, Google solo calcula tiempos (sin optimizar) | Clientes con hora límite (restaurantes, fondas) |
| **C. Sin cuotas / sin autopista** | Igual que A con `routeModifiers.avoidTolls` / `avoidHighways` | Moto o ahorro |
| **D. Manual** | El repartidor arrastra paradas (drag & drop) y pulsa "Recalcular" | Conoce la zona mejor que el algoritmo |

La pantalla muestra para cada opción: distancia total, duración, hora estimada de regreso y
ETA por parada. El repartidor (o el admin) elige una y queda guardada en `ruta`. Durante el
recorrido puede pulsar **"Reoptimizar desde aquí"**: se toma su GPS como origen y se
reordenan solo las paradas pendientes.

**Respaldo sin Google:** si la API falla o se agota la cuota, el servicio cae a una heurística
local (vecino más cercano + mejora 2-opt con distancia Haversine). La ruta se marca como
"aproximada" para que el usuario lo sepa.

---

## 4. Modelo de datos

Se propone crearlo con **migraciones de CI4** (`app/Database/Migrations/2026-11-…_Rutas*.php`)
y además dejar `rutas_reparto.sql` para quien importe desde phpMyAdmin, igual que `sugerencias.sql`.

```sql
-- 4.1 Punto de partida (bodega / sucursal)
CREATE TABLE almacen (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(100) NOT NULL,
  direccion    VARCHAR(300) NOT NULL,
  place_id     VARCHAR(255) NULL,
  lat          DECIMAL(10,7) NOT NULL,   -- capturada por el negocio (pin manual) → dato propio
  lng          DECIMAL(10,7) NOT NULL,
  activo       TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.2 Direcciones geolocalizadas
ALTER TABLE direccion
  ADD codigo_postal        VARCHAR(10)  NULL AFTER numero,
  ADD referencias          VARCHAR(300) NULL,            -- "entre calles", "portón verde"
  ADD direccion_formateada VARCHAR(400) NULL,
  ADD place_id             VARCHAR(255) NULL,
  ADD lat                  DECIMAL(10,7) NULL,
  ADD lng                  DECIMAL(10,7) NULL,
  ADD origen_coord         ENUM('google','gps_entrega','manual') NULL,
  ADD precision_geo        VARCHAR(30)  NULL,            -- ROOFTOP, RANGE_INTERPOLATED, APPROXIMATE…
  ADD coord_actualizada_en DATETIME     NULL,
  ADD INDEX idx_direccion_place (place_id);

-- 4.3 El pedido apunta a una dirección concreta y puede tener horario
ALTER TABLE pedido
  ADD id_direccion   INT NULL AFTER id_cliente,
  ADD ventana_inicio TIME NULL,
  ADD ventana_fin    TIME NULL,
  ADD prioridad      TINYINT NOT NULL DEFAULT 0,
  ADD CONSTRAINT fk_pedido_direccion FOREIGN KEY (id_direccion) REFERENCES direccion(id);
-- Backfill: para clientes con una sola dirección se asigna automáticamente;
-- los que tienen varias quedan NULL y se piden al editar el pedido.

-- 4.4 Repartidor ligado a un usuario de Shield
ALTER TABLE repartidor
  ADD id_usuario  INT UNSIGNED NULL,
  ADD vehiculo    ENUM('moto','auto','camioneta','bicicleta') NOT NULL DEFAULT 'moto',
  ADD capacidad_kg DECIMAL(8,2) NULL,                    -- para Fase 6
  ADD UNIQUE KEY uq_repartidor_usuario (id_usuario);

-- 4.5 Rutas y paradas
CREATE TABLE ruta (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  fecha          DATE NOT NULL,
  id_repartidor  INT NOT NULL,
  id_almacen     INT NOT NULL,
  estado         ENUM('borrador','asignada','en_curso','finalizada','cancelada') NOT NULL DEFAULT 'borrador',
  estrategia     ENUM('rapida','prioridad','sin_cuotas','manual','aproximada') NOT NULL DEFAULT 'rapida',
  regresa_almacen TINYINT(1) NOT NULL DEFAULT 1,
  distancia_m    INT NULL,
  duracion_s     INT NULL,
  polyline       MEDIUMTEXT NULL,          -- polyline codificada (se refresca al reabrir la ruta)
  hash_paradas   CHAR(40) NULL,            -- sha1 de paradas+estrategia → evita recalcular/cobrar de más
  creada_por     INT UNSIGNED NULL,        -- users.id
  iniciada_en    DATETIME NULL,
  finalizada_en  DATETIME NULL,
  created_at     DATETIME NULL,
  updated_at     DATETIME NULL,
  CONSTRAINT fk_ruta_repartidor FOREIGN KEY (id_repartidor) REFERENCES repartidor(id),
  CONSTRAINT fk_ruta_almacen    FOREIGN KEY (id_almacen)    REFERENCES almacen(id),
  INDEX idx_ruta_fecha_rep (fecha, id_repartidor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ruta_parada (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  id_ruta       INT NOT NULL,
  id_pedido     INT NOT NULL,
  orden         SMALLINT NOT NULL,
  eta           DATETIME NULL,
  distancia_tramo_m INT NULL,
  duracion_tramo_s  INT NULL,
  estado        ENUM('pendiente','en_camino','entregada','fallida','omitida') NOT NULL DEFAULT 'pendiente',
  motivo_fallo  VARCHAR(200) NULL,          -- "cliente ausente", "dirección incorrecta"…
  llegada_real  DATETIME NULL,
  lat_entrega   DECIMAL(10,7) NULL,         -- GPS del teléfono al marcar entregado
  lng_entrega   DECIMAL(10,7) NULL,
  evidencia_img VARCHAR(255) NULL,          -- foto opcional (reusa public/uploads)
  CONSTRAINT fk_parada_ruta   FOREIGN KEY (id_ruta)   REFERENCES ruta(id) ON DELETE CASCADE,
  CONSTRAINT fk_parada_pedido FOREIGN KEY (id_pedido) REFERENCES pedido(id),
  UNIQUE KEY uq_parada_pedido_ruta (id_ruta, id_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Notas:
- `id_usuario` es `INT UNSIGNED` para coincidir con `users.id` de Shield (mismo criterio que `sugerencia.id_usuario`).
- Un pedido solo puede estar en **una ruta activa** a la vez: validarlo en el modelo (no en SQL, porque una ruta cancelada sí puede liberar al pedido).

---

## 5. Componentes a construir

### 5.1 Configuración (`.env`, nunca en git)
```ini
google.mapsServerKey = "…"   # Routes + Geocoding. Restringida por IP del servidor y por API.
google.mapsBrowserKey = "…"  # Maps JS + Places. Restringida por HTTP referrer (dominio) y por API.
google.mapId = "…"           # Map ID para Advanced Markers (pines numerados)
rutas.maxParadas = 25
rutas.salidaDefault = "09:00"
```
Nuevo `app/Config/Google.php` que lee esos valores. **Dos llaves separadas**: la del navegador
es visible en el HTML, por eso solo debe permitir Maps JS/Places y solo desde el dominio de Fruver.

### 5.2 Librerías (`app/Libraries/`)
| Archivo | Responsabilidad |
|---------|-----------------|
| `FlujoPedido.php` | **Una sola** definición de transiciones de estatus (reemplaza las 4 copias actuales). Métodos `estadoActual($idPedido)`, `puedeCambiar($de, $a)`, `cambiar($idPedido, $a)`. Corrige `pedido_credito` → `pedido_a_credito`. |
| `GoogleRoutesClient.php` | Envuelve `service('curlrequest')` contra `https://routes.googleapis.com/directions/v2:computeRoutes`. Encabezados `X-Goog-Api-Key`, `X-Goog-FieldMask`. Tiempo de espera ≥ 10 s. Registra errores con `log_message`. |
| `Geocodificador.php` | Geocoding API (`region=mx`, `language=es`, `components=country:MX`). Devuelve lat/lng, `place_id`, `location_type`. |
| `Rutas/OptimizadorInterface.php` | Contrato `optimizar(Origen, Paradas[], Opciones): ResultadoRuta`. |
| `Rutas/OptimizadorGoogle.php` | Estrategias A, B, C, D con Routes API. |
| `Rutas/OptimizadorLocal.php` | Respaldo vecino-más-cercano + 2-opt (sin costo). |
| `Rutas/ResultadoRuta.php` | DTO: orden, tramos, distancia, duración, polyline, ETA por parada. |

Ejemplo de solicitud que generaría `OptimizadorGoogle` (estrategia A):
```json
POST https://routes.googleapis.com/directions/v2:computeRoutes
X-Goog-FieldMask: routes.optimizedIntermediateWaypointIndex,routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline,routes.legs.distanceMeters,routes.legs.duration
{
  "origin":       { "location": { "latLng": { "latitude": 19.04, "longitude": -98.20 } } },
  "destination":  { "location": { "latLng": { "latitude": 19.04, "longitude": -98.20 } } },
  "intermediates": [
    { "placeId": "ChIJ…" },
    { "location": { "latLng": { "latitude": 19.05, "longitude": -98.21 } } }
  ],
  "travelMode": "TWO_WHEELER",
  "routingPreference": "TRAFFIC_AWARE",
  "departureTime": "2026-11-06T15:00:00Z",
  "optimizeWaypointOrder": true,
  "languageCode": "es-MX",
  "regionCode": "MX"
}
```
`travelMode` sale de `repartidor.vehiculo` (`TWO_WHEELER` para moto, `DRIVE` para auto/camioneta).

### 5.3 Modelos (`app/Models/`)
`Modelo_almacen`, `Modelo_ruta`, `Modelo_ruta_parada` (con `useTimestamps`), y ajustes a
`Modelo_direccion` (nuevos campos + `faltanCoordenadas()`), `Modelo_pedido` (`id_direccion`,
quitar `id_producto_pedido`, `pendientesDeRuta($fecha)`), `Modelo_repartidor` (`porUsuario($idUsuario)`).

### 5.4 Controladores y rutas HTTP
```php
// Admin / despacho
$routes->group('', ['filter' => ['group:admin,superadmin,developer', 'csrf']], static function ($routes) {
    $routes->get ('rutas',                       'Rutas::planeador');          // mapa + pedidos del día
    $routes->post('rutas/opciones',              'Rutas::opciones');           // JSON: A/B/C calculadas
    $routes->post('rutas/guardar',               'Rutas::guardar');            // guarda la opción elegida
    $routes->get ('rutas/(:num)',                'Rutas::detalle/$1');
    $routes->post('rutas/(:num)/cancelar',       'Rutas::cancelar/$1');
    $routes->get ('almacen',                     'Almacenes::lista');
});
// Repartidor
$routes->group('mi-ruta', ['filter' => ['group:repartidor', 'csrf']], static function ($routes) {
    $routes->get ('/',                           'MiRuta::hoy');
    $routes->post('(:num)/iniciar',              'MiRuta::iniciar/$1');        // pedidos → pedido_en_transito
    $routes->post('(:num)/reoptimizar',          'MiRuta::reoptimizar/$1');    // desde GPS actual
    $routes->post('parada/(:num)/entregada',     'MiRuta::entregada/$1');      // → pedido_entregado
    $routes->post('parada/(:num)/fallida',       'MiRuta::fallida/$1');
});
// Utilidades
$routes->post('direcciones/geocodificar/(:num)', 'Direcciones::geocodificar/$1', ['filter' => 'csrf']);
```
Cada acción de `MiRuta` verifica que la ruta pertenezca al repartidor de la sesión
(`Modelo_repartidor::porUsuario(auth()->id())`), no solo que tenga el rol.

### 5.5 Rol y permisos (Shield)
En `Config/AuthGroups.php`: grupo `repartidor` y permisos `rutas.planear`, `rutas.ver_propias`,
`rutas.actualizar_entrega`. En el formulario de repartidor, opción "Crear acceso" que genera el
usuario Shield y llena `repartidor.id_usuario`. `Productos::main_page` redirige al grupo
`repartidor` a `mi-ruta`.

### 5.6 Comando Spark
`php spark direcciones:geocodificar [--todas] [--dry-run]` — geocodifica direcciones sin
coordenadas o con más de 30 días, respeta un límite por ejecución y reporta las de baja precisión
(`APPROXIMATE`) para revisión manual.

### 5.7 Vistas
| Vista | Contenido |
|-------|-----------|
| `crea_direccion.php` / `modifica_direccion.php` / modal en `main_page3.php` | Campo de búsqueda con **Place Autocomplete** que llena calle, número, colonia, municipio, estado y CP; mini-mapa con **pin arrastrable** para afinar la ubicación; campo "Referencias". |
| `rutas_planeador.php` (nuevo) | Izquierda: pedidos `pedido_confirmado` de la fecha sin ruta, agrupados por repartidor (arrastrar para reasignar). Derecha: mapa con almacén y pines. Botón "Calcular opciones" → tarjetas A/B/C con km, minutos, hora de regreso → "Asignar esta ruta". Aviso si algún pedido no tiene coordenadas o dirección. |
| `mi_ruta.php` (nuevo; reemplaza el `main_page_4.php` vacío) | Pensada para celular: lista de paradas con número, cliente, teléfono (link `tel:`), referencias, productos y total a cobrar; mapa plegable; botones **Navegar** (Maps URL a la siguiente parada), **Entregado**, **No entregado** (motivo), **Reoptimizar desde aquí**. |
| `sidebar.php` | Entrada "Rutas de reparto". |
| `rutas_detalle.php` | Seguimiento: paradas entregadas/pendientes, ETA vs llegada real. |

Ejemplo de Maps URL para navegar a la siguiente parada (sin costo, abre la app en Android/iOS):
```
https://www.google.com/maps/dir/?api=1&destination=19.0512,-98.2113&destination_place_id=ChIJ…&travelmode=two-wheeler
```

---

## 6. Términos de servicio de Google y privacidad

- **Coordenadas en caché:** lat/lng que vienen de Geocoding/Places → máximo 30 días. Por eso
  `direccion` guarda `place_id` (permitido indefinidamente) y `coord_actualizada_en`; el comando
  Spark refresca las vencidas (costo despreciable dentro de las 10k gratuitas).
- **Coordenadas propias:** cuando el repartidor marca "Entregado", el GPS del teléfono se guarda
  en `ruta_parada.lat_entrega` y puede promoverse a `direccion.lat/lng` con `origen_coord='gps_entrega'`.
  Ese dato es del negocio, no contenido de Google, y además es **más preciso** que la geocodificación
  (muy útil en colonias con numeración irregular). Igual con el pin que el usuario acomoda a mano.
- Mostrar resultados de Google sobre un mapa de Google (no mezclar con Leaflet/OSM).
- Geolocalización del navegador solo funciona en **HTTPS** (o localhost). En producción activar
  HTTPS antes de la Fase 4.
- Avisar a los repartidores que su ubicación se registra **solo al marcar una entrega** (no rastreo continuo en esta versión).

---

## 7. Integración con el flujo de estatus

```
pedido_confirmado ──(admin asigna a ruta)──► [en ruta, estado sin cambio]
        │
        └─(repartidor pulsa "Iniciar ruta")──► pedido_en_transito  (todas las paradas)
                 │
                 ├─(Entregado)────────────► pedido_entregado ─► pedido_pagado | pedido_a_credito
                 └─(No entregado)─────────► parada 'fallida'; el pedido vuelve a pedido_confirmado*
```
\* Requiere agregar la transición `pedido_en_transito → pedido_confirmado` (reprogramar) en `FlujoPedido`.
Todas las transiciones pasan por `FlujoPedido::cambiar()`, que inserta en `estatus` como hoy.
Opcional: al marcar entregado, preguntar "¿Pagó?" para pasar directo a `pedido_pagado` / `pedido_a_credito`.

---

## 8. Fases de implementación

Cada fase se puede mezclar a `main` por separado (PR propio) y deja el sistema funcionando.

### Fase 0 — Preparación y limpieza (tamaño S)
- [ ] Crear proyecto en Google Cloud, activar facturación, habilitar Maps JavaScript API, Places API (New), Geocoding API y Routes API.
- [ ] Crear las 2 llaves restringidas; configurar **alertas de presupuesto** (p. ej. USD 5) y **cuotas diarias** por API.
- [ ] `app/Config/Google.php` + variables en `.env`; documentar en README.
- [ ] Eliminar `public/null/` del repo y agregarlo a `.gitignore`; revisar por qué las sesiones se guardaban ahí (`session.savePath` en `.env`).
- [ ] Borrar archivos sueltos sin uso (`tatus`, `uyiyryr.jpeg`) si el equipo está de acuerdo.

### Fase 1 — Unificar el flujo de estatus (tamaño S)
- [ ] Crear `App\Libraries\FlujoPedido` y usarlo en `P_pedidos` y `Estatus`.
- [ ] Corregir `pedido_credito` → `pedido_a_credito` y unificar "último estatus" (ordenar por `fecha DESC, id DESC`).
- [ ] Prueba unitaria de transiciones.

### Fase 2 — Direcciones geolocalizadas (tamaño M)
- [ ] Migración de `direccion` y `almacen`; pantalla para dar de alta el almacén con pin.
- [ ] Place Autocomplete + pin arrastrable en formularios de dirección.
- [ ] `Geocodificador` + endpoint `direcciones/geocodificar/(:num)` + comando Spark.
- [ ] Indicador en `lista_direccion` (✓ ubicada / ⚠ aproximada / ✗ sin ubicar).

### Fase 3 — Pedido ↔ dirección y repartidor ↔ usuario (tamaño M)
- [ ] Migración de `pedido` (`id_direccion`, ventana, prioridad) con backfill automático.
- [ ] Selector de dirección dependiente del cliente en los modales de `lista_p_pedido.php` y `main_page3.php` (usar fetch a un endpoint `api_direcciones_cliente/(:num)`).
- [ ] Migración de `repartidor`, grupo `repartidor` en Shield, botón "Crear acceso".
- [ ] Filtros `group:` en `Routes.php` para separar admin/repartidor (al menos en módulos nuevos; idealmente en todos).

### Fase 4 — Motor de rutas y planeador (tamaño L)
- [ ] `GoogleRoutesClient`, `OptimizadorGoogle` (A, B, C), `OptimizadorLocal`.
- [ ] Migraciones `ruta` y `ruta_parada`; modelos.
- [ ] `Rutas::planeador`, `opciones`, `guardar`; vista con mapa y tarjetas comparativas.
- [ ] Partir automáticamente en varias rutas/viajes si hay más de 25 paradas.
- [ ] Caché por `hash_paradas` para no recalcular (y no pagar) la misma ruta dos veces.

### Fase 5 — App del repartidor "Mi ruta" (tamaño M)
- [ ] Vista móvil, botones Navegar / Entregado / No entregado, captura de GPS y foto opcional.
- [ ] "Reoptimizar desde aquí" (estrategia A con origen = GPS, solo paradas pendientes).
- [ ] Reordenar manual (estrategia D) con drag & drop y recálculo.
- [ ] Tolerancia a mala señal: guardar la ruta en `localStorage` y reintentar envíos pendientes.

### Fase 6 — Mejoras posteriores (opcional)
- [ ] Panel en `Dashboard`: km/día, minutos promedio por entrega, % entregas fallidas, ETA vs real por repartidor.
- [ ] **Route Optimization API** para repartir automáticamente los pedidos entre varios repartidores respetando ventanas horarias y `capacidad_kg` (calcular peso desde `producto_pedido`).
- [ ] Mensaje de WhatsApp al cliente con la hora estimada (enlace `https://wa.me/52…?text=…`, sin costo) y aviso "voy en camino".
- [ ] Zonas de reparto y costo de envío por distancia (Compute Route Matrix).

---

## 9. Costos estimados

Supuesto: 3 repartidores, 1 ruta diaria cada uno, ~3 recálculos por ruta, 26 días hábiles,
~20 direcciones nuevas por semana.

| SKU | Uso mensual aprox. | Gratis/mes | Costo esperado |
|-----|-------------------|-----------|----------------|
| Compute Routes Pro (optimización) | 3 × 4 × 26 ≈ 310 | 5,000 | USD 0 |
| Geocoding | ~100 + refrescos | 10,000 | USD 0 |
| Autocomplete / Place Details | ~100 sesiones | según SKU | USD 0 |
| Dynamic Maps (cargas de mapa) | ~1,500 | 10,000 | USD 0 |

Aun creciendo 10× seguiría cerca del nivel gratuito. Las alertas de presupuesto y las cuotas
de la Fase 0 evitan sorpresas (por ejemplo, un bucle de JavaScript que recargue mapas).

---

## 10. Pruebas

- **Unitarias** (`tests/unit/`): `FlujoPedido`, `OptimizadorLocal` (casos conocidos), armado del JSON de `OptimizadorGoogle`, partición >25 paradas, cálculo de `hash_paradas`.
- **Con HTTP simulado:** inyectar un `CURLRequest` falso que devuelva respuestas de Routes API guardadas en `tests/_support/fixtures/`; probar error 4xx/5xx → respaldo local.
- **Feature tests** (`FeatureTestTrait`): un repartidor no puede ver/editar la ruta de otro; un `user` no puede entrar a `rutas`; CSRF activo en los POST nuevos.
- **Prueba de campo:** un día real con un repartidor comparando el orden sugerido contra su orden habitual (km y tiempo).

---

## 11. Aportaciones adicionales para mejorar el uso del software

1. **Validar la dirección en la captura, no en la calle.** El autocompletado + pin + campo de
   referencias elimina la mayoría de entregas fallidas. Mostrar ⚠ cuando Google devuelva
   precisión `APPROXIMATE` y pedir que se acomode el pin.
2. **Aprender de cada entrega.** Guardar el GPS real de la entrega mejora la siguiente ruta a ese
   cliente sin costo extra.
3. **Hora comprometida al cliente.** Con la ETA por parada, el cliente puede recibir "llego entre
   11:30 y 11:50" por WhatsApp.
4. **Cobro en ruta.** Mostrar en cada parada el total del pedido (ya existe en `producto_pedido.total`) y
   registrar si se pagó o quedó a crédito al entregar.
5. **Hoja de carga.** Antes de salir, listar los productos de la ruta en **orden inverso** de entrega
   (lo último que se entrega se sube primero al vehículo).
6. **Seguridad general.** Aprovechar la Fase 3 para poner filtros por rol a *todas* las rutas
   CRUD y activar CSRF globalmente; hoy cualquier usuario autenticado puede borrar registros por URL.
7. **Migraciones en lugar de dumps.** Empezar con las de este módulo; evita que cada integrante
   tenga una base de datos distinta.

---

## 12. Riesgos y decisiones pendientes

| Tema | Pregunta / riesgo | Propuesta |
|------|-------------------|-----------|
| Vehículo | ¿Reparten en moto, auto o ambos? | Campo `vehiculo` por repartidor; afecta `travelMode`. |
| Regreso | ¿La ruta termina en el almacén o en la última entrega? | Configurable por ruta (`regresa_almacen`). |
| Quién elige | ¿El admin asigna y el repartidor solo ajusta, o el repartidor arma su ruta? | Admin asigna pedidos; repartidor escoge entre A/B/C/D y puede reoptimizar. |
| Varias sucursales | ¿Hay más de un punto de salida? | Tabla `almacen` ya lo soporta. |
| Calidad de datos | Direcciones de prueba ficticias | Limpiar antes de la prueba de campo. |
| Facturación Google | Requiere tarjeta en Google Cloud | Alertas + cuotas desde el día 1. |

---

## Referencias
- Routes API — Optimizar orden de paradas: https://developers.google.com/maps/documentation/routes/opt-way
- Routes API — Uso y facturación: https://developers.google.com/maps/documentation/routes/usage-and-billing
- Route Optimization API — Uso y facturación: https://developers.google.com/maps/documentation/route-optimization/usage-and-billing
- Maps URLs: https://developers.google.com/maps/documentation/urls/guide
- Preguntas frecuentes de precios (niveles Essentials/Pro/Enterprise): https://developers.google.com/maps/billing-and-pricing/faq
- Lista de precios: https://developers.google.com/maps/billing-and-pricing/pricing
- Términos específicos de servicio: https://cloud.google.com/maps-platform/terms/maps-service-terms

# Contraste: plan de rutas vs. rama `feature/impresion-termica`

> Fecha: 5 nov 2026 · Compara `docs/PLAN_RUTAS_REPARTO.md` (escrito sobre `main`) con la rama
> más avanzada del repositorio.

## Por qué esta rama

| Rama | Commits por delante de `main` | Último commit |
|------|------------------------------|---------------|
| **`feature/impresion-termica`** | **18** | 1 nov 2026 |
| `codigo-unico-producto` / `landing-pdo-seguridad` (mismo commit) | 17 | 25 oct 2026 |
| `feature/buzon-sugerencias`, `Leo`, `P1`, `Ruben` | 0 (ya integradas o atrasadas) | — |

`feature/impresion-termica` **contiene** a `landing-pdo-seguridad` y a `codigo-unico-producto`
y agrega la impresión ESC/POS. Cambia 129 archivos respecto a `main` (+16,300 / −6,082 líneas).

**Conclusión principal:** el plan original se escribió sobre `main`, y varias de sus fases ya
están resueltas en esta rama, a veces con un diseño distinto. El módulo de rutas **debe
construirse sobre `feature/impresion-termica`**, no sobre `main`, y apoyarse en la tabla
`entrega` que ya existe en lugar de crear `ruta_parada` desde cero.

---

## 1. Hallazgos del plan: ¿siguen vigentes?

| # | Hallazgo del plan (sobre `main`) | En `feature/impresion-termica` | Estado |
|---|-----------------------------------|--------------------------------|--------|
| 1 | El pedido no sabe a qué dirección va | Sigue igual: `pedido` no tiene `id_direccion`. Además, los pedidos de la tienda (`Modelo_pedido::crearPedidoDeCliente`) se crean sin pedir dirección | 🔴 **Pendiente, y ahora más urgente** |
| 2 | Direcciones sin coordenadas | Sigue igual (`Modelo_direccion` solo tiene colonia/calle/número/municipio/estado) | 🔴 Pendiente |
| 3 | Repartidor no ligado a usuario, sin rol | ✅ Resuelto: migración `AddIdUserToRepartidor` (`repartidor.id_user` → `users.id`, UNIQUE, `ON DELETE SET NULL`), grupo `repartidor`, permisos `entregas.asignar` / `entregas.propias`, comando `php spark repartidor:vincular <correo> <id>` | 🟢 Hecho |
| 4 | No hay punto de partida (almacén) | Sigue igual | 🔴 Pendiente |
| 5 | Flujo de estatus duplicado e inconsistente | Mejoró a medias: `Modelo_pedido::TRANSICIONES` centraliza el flujo del panel, pero `Estatus::FLUJO` sigue siendo otra copia con `pedido_credito` (no existe en el ENUM). Además `Modelo_entrega` lee el último estatus con `ultimoPorId` y `Modelo_pedido::estadoActual` con `ultimoPorFecha` | 🟡 Parcial |
| 6 | Sin control de acceso por rol; CSRF apagado | ✅ Resuelto: grupos `group:superadmin,admin,developer`, `group:repartidor`, `session` en `Routes.php`; borrados pasaron a POST; CSRF, `invalidchars` y `secureheaders` globales; filtro nuevo `SesionGuard` (huella del navegador) | 🟢 Hecho |
| 7 | Sesiones versionadas en `public/null/` | **Sigue igual**: los 7 archivos `ci_session*` siguen en git | 🔴 Pendiente (rápido) |
| 8 | No se usan migraciones | Mejoró: hay 5 migraciones en `app/Database/Migrations`, aunque conviven con scripts manuales en `database_cambios/` (p. ej. `tienda_cambios.sql`, que vuelve `pedido.id_repartidor` NULL y agrega `cliente.id_user`) | 🟡 Parcial |
| 9 | `main_page_4` vacía | Ahora es la **landing de la tienda** (`Tienda::index`). La pantalla del repartidor existe aparte: `repartidor/entregas` → `mis_entregas.php` | 🟢 Ya no aplica |

---

## 2. Lo que ya existe y el plan debe reutilizar

### 2.1 Tabla `entrega` (migración `2026-10-16-100000_CreateEntregaTable`)
Una fila **por intento** de entrega de un pedido: `id_pedido`, `id_repartidor`, `intento`,
`estado` (`asignada → en_ruta → entregada | fallida | cancelada`), `fecha_asignacion`,
`fecha_salida`, `fecha_entrega`, `monto_cobrado`, `metodo_cobro`, `evidencia`, `incidencia`, `notas`.

Esto cubre gran parte de lo que el plan ponía en `ruta_parada` (estado de la parada, llegada,
motivo de fallo, evidencia y cobro). **Cambio al plan:** no crear `ruta_parada`; agregar a
`entrega` las columnas de secuencia y una llave a la nueva tabla `ruta`.

### 2.2 `Modelo_entrega` y `Entregas` (controlador)
- `asignar()` valida que el pedido esté `pedido_confirmado` / `pedido_en_transito`, bloquea filas con `FOR UPDATE` y calcula el número de intento.
- `iniciarRuta()` pasa el pedido a `pedido_en_transito`; `marcarEntregada()` a `pedido_entregado`; `marcarFallida()` deja el pedido en tránsito para reintentar.
- Todas las acciones del repartidor cruzan `repartidor.id_user = auth()->id()` **dentro del SQL**, así nadie modifica entregas ajenas.

**Cambio al plan:** la transición "reprogramar" (`pedido_en_transito → pedido_confirmado`) que
proponía la §7 ya no hace falta: el diseño de intentos resuelve el reintento. Los botones
"Entregado / No entregado" de "Mi ruta" deben llamar a `marcarEntregada()` / `marcarFallida()`,
no a lógica nueva.

### 2.3 Vista `mis_entregas.php` (repartidor)
Tarjetas por entrega con cliente, teléfono (`tel:`), botones Salir / Entregar (monto y método) /
No se pudo entregar. **Lo que le falta para rutas:**
- ❗ **No muestra la dirección** (la consulta `listarDeRepartidor()` no une `direccion`). Hoy el repartidor no sabe a dónde ir desde el sistema.
- No hay orden de visita, mapa, ETA ni botón de navegación.
- `evidencia` existe en la tabla pero `Entregas::entregar()` siempre manda `null` (no hay carga de foto).

### 2.4 Asignación del administrador (`lista_entrega.php`)
Se asigna **un pedido a la vez** con dos selects (pedido y repartidor). El planeador de rutas
del plan reemplaza esto por asignación en bloque, pero debe seguir usando `Modelo_entrega::asignar()`.

### 2.5 Otras piezas aprovechables
| Pieza | Uso en rutas |
|-------|--------------|
| `PdoModelo` (base PDO propia: `fila`, `filas`, `afectar`, `transaccion`, `paginarSql`, `mensajeSeguro`) | **Todos los modelos nuevos deben extender `PdoModelo`**, no `CodeIgniter\Model` como suponía el plan |
| `whatsapp_helper.php` (`whatsapp_telefono`, `whatsapp_url`, `whatsapp_mensaje_pedido`) | Mensaje "tu pedido llega ~11:40" (mejora §11.3 del plan): solo hay que agregar un `whatsapp_mensaje_eta()` |
| `Libraries/Termica/ServicioTicket` (ESC/POS) | Imprimir la **hoja de carga de la ruta** (§11.5 del plan) en la misma impresora térmica |
| Tienda (`Tienda::confirmar`, carrito) | Punto donde el cliente debe **elegir o capturar su dirección** al pedir |
| `Clientes` preferenciales | Puede alimentar la `prioridad` de la parada |

---

## 3. Choque nuevo que el plan no contemplaba: CSP activado

En esta rama `App::$CSPEnabled = true` y `Config/ContentSecurityPolicy.php` solo permite scripts de
`self` y `cdn.jsdelivr.net`, imágenes de `self` y `data:` y conexiones a `self`. La landing
(`Tienda::cspEstricta()`) es todavía más estricta. **Con esa política, Google Maps no carga.**

Google ofrece dos formas de configurarlo:
- **Estricta con nonce** (recomendada por Google): Maps JS toma el nonce del primer `<script>`/`<style>` con nonce y lo aplica a lo que inserta. CI4 ya genera `{csp-script-nonce}` / `{csp-style-nonce}`.
- **Lista de dominios**, aplicada solo en las vistas con mapa (planeador, "Mi ruta", formularios de dirección):

```php
// En el controlador de las vistas con mapa (no globalmente):
$csp   = $this->response->getCSP();
$maps  = ['https://*.googleapis.com', 'https://*.gstatic.com', '*.google.com'];
$csp->addScriptSrc([...$maps, 'blob:']);
$csp->addScriptSrcElem($maps);   // ¡importante! el config define script-src-elem, que manda sobre script-src para <script>
$csp->addImageSrc([...$maps, '*.googleusercontent.com']);
$csp->addConnectSrc($maps);
$csp->addFrameSrc('*.google.com');
$csp->addFontSrc('https://fonts.gstatic.com');
// worker-src blob: → en Config\ContentSecurityPolicy::$workerSrc
```
> Los nombres de métodos corresponden a CI 4.5+; verificar contra la versión instalada.
> Probar en el navegador hasta que la consola no muestre violaciones de CSP; Google
> recomienda ajustar con base en su lista de dominios y sus notas de versión.

Las llamadas a Routes API y Geocoding se hacen **desde el servidor** (PHP), así que no las afecta el CSP.

---

## 4. Modelo de datos revisado (sustituye la §4 del plan)

```sql
-- 4.1 Igual que el plan
CREATE TABLE almacen (... id, nombre, direccion, place_id, lat, lng, activo ...);

ALTER TABLE direccion
  ADD codigo_postal VARCHAR(10) NULL, ADD referencias VARCHAR(300) NULL,
  ADD direccion_formateada VARCHAR(400) NULL, ADD place_id VARCHAR(255) NULL,
  ADD lat DECIMAL(10,7) NULL, ADD lng DECIMAL(10,7) NULL,
  ADD origen_coord ENUM('google','gps_entrega','manual') NULL,
  ADD precision_geo VARCHAR(30) NULL, ADD coord_actualizada_en DATETIME NULL,
  ADD es_principal TINYINT(1) NOT NULL DEFAULT 0;      -- NUEVO: para la tienda

ALTER TABLE pedido
  ADD id_direccion INT NULL AFTER id_cliente,
  ADD ventana_inicio TIME NULL, ADD ventana_fin TIME NULL,
  ADD CONSTRAINT fk_pedido_direccion FOREIGN KEY (id_direccion) REFERENCES direccion(id);

-- 4.2 CAMBIA: repartidor ya tiene id_user; solo faltan datos del vehículo
ALTER TABLE repartidor
  ADD vehiculo ENUM('moto','auto','camioneta','bicicleta') NOT NULL DEFAULT 'moto',
  ADD capacidad_kg DECIMAL(8,2) NULL;

-- 4.3 Ruta = agrupa entregas de un repartidor en un día
CREATE TABLE ruta (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  id_repartidor INT NOT NULL,
  id_almacen INT NOT NULL,
  estado ENUM('borrador','asignada','en_curso','finalizada','cancelada') NOT NULL DEFAULT 'borrador',
  estrategia ENUM('rapida','prioridad','sin_cuotas','manual','aproximada') NOT NULL DEFAULT 'rapida',
  regresa_almacen TINYINT(1) NOT NULL DEFAULT 1,
  distancia_m INT NULL, duracion_s INT NULL, polyline MEDIUMTEXT NULL,
  hash_paradas CHAR(40) NULL, creada_por INT UNSIGNED NULL,
  iniciada_en DATETIME NULL, finalizada_en DATETIME NULL,
  created_at DATETIME NULL, updated_at DATETIME NULL,
  CONSTRAINT fk_ruta_repartidor FOREIGN KEY (id_repartidor) REFERENCES repartidor(id),
  CONSTRAINT fk_ruta_almacen FOREIGN KEY (id_almacen) REFERENCES almacen(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4.4 CAMBIA: en lugar de ruta_parada, se extiende `entrega`
ALTER TABLE entrega
  ADD id_ruta INT NULL AFTER id_repartidor,
  ADD orden_ruta SMALLINT NULL,
  ADD eta DATETIME NULL,
  ADD distancia_tramo_m INT NULL,
  ADD duracion_tramo_s INT NULL,
  ADD lat_entrega DECIMAL(10,7) NULL,
  ADD lng_entrega DECIMAL(10,7) NULL,
  ADD CONSTRAINT fk_entrega_ruta FOREIGN KEY (id_ruta) REFERENCES ruta(id) ON DELETE SET NULL,
  ADD INDEX idx_entrega_ruta_orden (id_ruta, orden_ruta);
```

Por qué así: cada intento ya es una "parada". Si una entrega falla y se reasigna, el nuevo
intento entra a la ruta del día siguiente sin perder el historial. `entrega.id_repartidor` y
`ruta.id_repartidor` deben coincidir; validarlo en `Modelo_ruta`.

Todo como **migraciones** (siguiendo el estilo de `CreateEntregaTable`, con los tipos `INT(11)`
con signo que exigen las llaves foráneas existentes), no como scripts en `database_cambios/`.

---

## 5. Fases del plan, reajustadas

| Fase del plan | Qué cambia | Tamaño nuevo |
|---------------|-----------|--------------|
| **0. Preparación y limpieza** | Google Cloud y llaves: igual. Quitar `public/null/` de git: sigue pendiente. **Agregar:** permisos CSP por vista (§3). | S |
| **1. Unificar estatus** | Ya casi está: solo eliminar `Estatus::FLUJO` y usar `Modelo_pedido::TRANSICIONES`, corregir `pedido_credito`, y que `Modelo_entrega` y `Modelo_pedido` lean el último estatus del mismo modo. | **XS** (antes S) |
| **2. Direcciones geolocalizadas** | Igual, pero modelos con `PdoModelo`. **Agregar:** selector/captura de dirección en el checkout de la tienda y bandera `es_principal`. | M |
| **3. Pedido ↔ dirección, repartidor ↔ usuario** | Repartidor ↔ usuario **ya hecho**. Queda `pedido.id_direccion` y un botón en el panel que haga lo mismo que `repartidor:vincular` (hoy solo existe por CLI). | **S** (antes M) |
| **4. Motor de rutas y planeador** | Igual en lo técnico (Routes API, estrategias A–D, respaldo local). El planeador **usa `Modelo_entrega::asignar()`** para cada pedido y luego rellena `id_ruta`, `orden_ruta`, `eta`. Reemplaza el formulario uno-a-uno de `lista_entrega.php` (conservarlo para casos sueltos). | L |
| **5. "Mi ruta" del repartidor** | **Extender `mis_entregas.php`** en vez de crear `mi_ruta.php`: ordenar por `orden_ruta`, mostrar dirección y referencias (urgente aunque no haya rutas), mapa, botón Navegar (Maps URL), "Reoptimizar desde aquí", guardar GPS en `lat_entrega/lng_entrega`, y **conectar la carga de evidencia** que hoy queda en `null`. Botón "Salir a entregar" por ruta (llama `iniciarRuta()` para cada entrega). | M (antes M, pero con menos código nuevo) |
| **6. Mejoras** | WhatsApp con ETA reutiliza `whatsapp_helper`; hoja de carga reutiliza `ServicioTicket`. | M |

**Las rutas HTTP** nuevas deben ir dentro de los grupos que ya existen en `Routes.php`
(`group:superadmin,admin,developer` para el planeador y `group:repartidor` para "Mi ruta",
p. ej. `repartidor/ruta`, `repartidor/ruta/(:num)/reoptimizar`). CSRF ya es global: no hace falta
agregarlo por ruta como decía la §5.4 del plan.

---

## 6. Primeros pasos recomendados (en orden)

1. **Rebasar `Rutas-reparto` sobre `feature/impresion-termica`** (o esperar a que esa rama se integre a `main`). Construir sobre `main` obligaría a rehacer la migración a PDO, la seguridad y las entregas.
2. **Mostrar la dirección en `mis_entregas.php`**: aun sin `pedido.id_direccion`, se puede unir la dirección del cliente (la única o la más reciente) como paso inmediato. Es el mayor problema práctico hoy.
3. Fase 1 (XS) y limpieza de `public/null/`.
4. Migración de `direccion` + `pedido.id_direccion` + selector en tienda y panel.
5. Seguir con las fases 4 y 5 ajustadas.

---

## 7. Referencias
- Plan original: `docs/PLAN_RUTAS_REPARTO.md`
- Código revisado: rama `feature/impresion-termica`, commit `0bcab22`
- Guía de CSP para Maps JavaScript API: https://developers.google.com/maps/documentation/javascript/content-security-policy

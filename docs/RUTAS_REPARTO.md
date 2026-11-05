# Rutas de reparto con Google Maps — guía de instalación y uso

Este módulo permite al personal planear la ruta óptima de cada repartidor y al
repartidor seguirla desde su teléfono: ver el orden de las paradas, navegar con
Google Maps, registrar entregas con GPS y foto, y reoptimizar desde donde esté.

> Diseño y decisiones: `docs/PLAN_RUTAS_REPARTO.md` y `docs/CONTRASTE_PLAN_RUTAS.md`.

---

## 1. Base de datos

```bash
php spark migrate
```

Aplica `app/Database/Migrations/2026-11-05-100000_AddRutasReparto.php` (idempotente).
Si prefieres phpMyAdmin, ejecuta **una vez** `database_cambios/rutas_reparto_cambios.sql`.

Qué agrega:

| Tabla | Cambio |
|-------|--------|
| `almacen` | Nueva: punto de salida (y regreso) de las rutas. |
| `ruta` | Nueva: agrupa las entregas de un repartidor en un día, con distancia, tiempo y trazo. |
| `entrega` | `id_ruta`, `orden_ruta`, `eta`, tramo y GPS real de la entrega. Cada entrega es una parada. |
| `direccion` | `lat`, `lng`, `place_id`, `codigo_postal`, `referencias`, `es_principal`, origen y precisión de la ubicación. |
| `pedido` | `id_direccion` (a dónde va) y `ventana_fin` (entregar antes de). |
| `repartidor` | `vehiculo` (moto, auto, camioneta, bicicleta) y `capacidad_kg`. |

Datos iniciales: la dirección más reciente de cada cliente queda como principal y los
pedidos de clientes con una sola dirección se ligan a ella. Mientras no se corra la
migración, el sistema funciona exactamente como antes (los campos nuevos no aparecen).

---

## 2. Google Cloud (una sola vez)

1. Entra a <https://console.cloud.google.com/>, crea un proyecto y **activa la facturación**
   (Google da llamadas gratis cada mes por producto; ver §6).
2. En **APIs y servicios → Biblioteca**, habilita:
   - **Routes API** (cálculo y optimización de rutas — servidor)
   - **Geocoding API** (texto → coordenadas — servidor)
   - **Maps JavaScript API** (mapas en pantalla — navegador)
   - **Places API (New)** (autocompletado de direcciones — navegador)
3. En **Credenciales**, crea **dos** llaves:

   | Llave | Restricción de aplicación | Restricción de API |
   |-------|---------------------------|--------------------|
   | Servidor | Direcciones IP (la IP pública del servidor) | Routes API, Geocoding API |
   | Navegador | Sitios web (HTTP referrer): `https://tu-dominio/*` (y `http://localhost/*` para pruebas) | Maps JavaScript API, Places API (New) |

   La llave del navegador queda visible en el HTML; por eso se restringe al dominio.
4. (Opcional) En **Google Maps Platform → Administración de mapas**, crea un **Map ID**
   de tipo JavaScript para tener pines con número.
5. En **Facturación → Presupuestos y alertas**, crea una alerta (p. ej. USD 5) y en
   **Google Maps Platform → Cuotas** pon límites diarios por API.

## 3. Archivo `.env`

```ini
rutas.llaveServidor  = "AIza...servidor"
rutas.llaveNavegador = "AIza...navegador"
rutas.mapId          = ""            # opcional
rutas.horaSalida     = "09:00"       # hora de salida por omisión
rutas.minutosPorParada = 5           # tiempo estimado en cada entrega
# rutas.latCentro = 19.0433          # centro del mapa si aún no hay almacén
# rutas.lngCentro = -98.1981
```

Todas las opciones están documentadas en `app/Config/Rutas.php`.

**Sin llaves** el módulo también funciona: las rutas se calculan con un optimizador
local (vecino más cercano + 2-opt, en línea recta y sin tráfico) y se marcan como
“aproximadas”; los formularios no muestran mapa y la navegación usa el texto de la dirección.

## 4. Primeros pasos

1. **Rutas de reparto → Almacén**: registra la bodega y ubica el pin en la puerta de salida.
2. **Repartidores**: indica el vehículo de cada uno (cambia el modo de viaje en Google).
   Para que un repartidor entre al sistema: `php spark repartidor:vincular correo@ejemplo.com <id>`.
3. **Direcciones**: la lista muestra cuáles están “Sin ubicar”. Ubícalas con el botón
   ⌖ de cada fila o todas de una vez:
   ```bash
   php spark direcciones:geocodificar            # pendientes (máx. 200)
   php spark direcciones:geocodificar --prueba   # solo muestra
   ```
   Conviene programarlo **una vez por semana**: los Términos de Google solo permiten
   conservar coordenadas obtenidas de Geocoding/Places 30 días, y el comando las renueva.
   Las coordenadas del pin manual o del GPS de entrega son datos propios y no vencen.

## 5. Uso diario

**Personal — planear (menú «Rutas de reparto»)**
1. Elige fecha, hora de salida, repartidor y almacén.
2. Marca los pedidos (confirmados). Los que no tienen dirección ubicada aparecen deshabilitados.
3. **Calcular rutas óptimas** muestra hasta tres opciones para comparar:
   - *Más rápida*: Google reordena las paradas considerando el tráfico.
   - *Por horario y preferentes*: primero quien tiene hora límite y los clientes preferenciales.
   - *Sin casetas ni autopistas*.
4. Puedes **arrastrar** los pedidos de la lista para fijar tu propio orden y pulsar
   **Recalcular con mi orden**.
5. **Asignar esta ruta**: se crean las entregas y el repartidor la ve en su teléfono.
6. En el detalle de la ruta: hora estimada de cada parada, botón de **WhatsApp** para
   avisar la hora al cliente y fotos de evidencia.

**Repartidor — en el teléfono («Mi ruta»)**
1. **Salir a ruta**: todas las paradas pasan a “en ruta” (y los pedidos a *en tránsito*).
2. **Navegar** abre Google Maps hacia la siguiente parada (sin costo).
3. **Entregado**: registra cobro, foto opcional y la ubicación GPS del teléfono
   (que además corrige la ubicación del cliente para la próxima vez).
4. **No se pudo**: registra el motivo; el personal podrá reprogramar.
5. **Reoptimizar desde aquí**: reordena las paradas pendientes desde su ubicación actual.
6. Al cerrar la última parada, la ruta se marca como terminada.

Los pedidos de la tienda en línea ahora piden la dirección de entrega (o dejan elegir
una de las ya registradas). En el panel, los modales de pedido tienen selector de
dirección y “entregar antes de”.

## 6. Costos

Desde marzo 2025 Google da llamadas gratis **por SKU, por mes**: 10,000 (Essentials),
5,000 (Pro) y 1,000 (Enterprise). La optimización de paradas se cobra en
**Compute Routes Pro**. Uso típico de Fruver (3 repartidores, ~3 cálculos por ruta al día):
~300 optimizaciones/mes → dentro del nivel gratuito. Para no pagar de más:

- La pantalla calcula las opciones **una vez** y las guarda; elegir una no vuelve a llamar a Google.
- Si Google falla en la primera opción, no se insiste con las demás.
- Solo se piden los campos necesarios (field mask).

## 7. Problemas comunes

| Síntoma | Causa probable |
|---------|----------------|
| “Google Maps rechazó la llave del navegador” | La llave del navegador no permite tu dominio o no tiene Maps JavaScript API. |
| El mapa no carga y la consola dice *Content Security Policy* | Google agregó un dominio nuevo: añádelo en `app/Libraries/Rutas/CspMapas.php`. |
| Rutas siempre “aproximadas” | Falta `rutas.llaveServidor`, la Routes API no está habilitada o la llave está restringida a otra IP (ver `writable/logs`). |
| “Reoptimizar” dice que no lee la ubicación | El sitio debe abrirse con **HTTPS** y el repartidor debe permitir la ubicación. |
| Un pedido no aparece en el planeador | Debe estar *confirmado* y sin una entrega abierta. |

## 8. Archivos del módulo

- Configuración: `app/Config/Rutas.php`
- Motor: `app/Libraries/Rutas/` (`PlaneadorRutas`, `OptimizadorGoogle`, `OptimizadorLocal`, `Geocodificador`, `UbicadorDirecciones`, `EnlaceNavegacion`, `CspMapas`)
- Modelos: `Modelo_ruta`, `Modelo_almacen` (+ cambios en `Modelo_direccion`, `Modelo_pedido`, `Modelo_entrega`, `Modelo_repartidor`)
- Controladores: `Rutas` (planeador), `Entregas` (mi ruta, reoptimizar, evidencia), `Direcciones`, `Tienda`
- Vistas: `rutas_planeador`, `rutas_detalle`, `rutas_almacen`, `mi_ruta`, `partials/*`
- JavaScript: `public/js/mapas.js`, `geo_direccion.js`, `direccion_pedido.js`, `rutas_planeador.js`, `ruta_mapa.js`
- Comando: `php spark direcciones:geocodificar`
- Pruebas: `tests/unit/RutasTest.php`, `tests/unit/VistasRutasTest.php`

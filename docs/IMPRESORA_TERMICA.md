# Impresión de tickets en impresora térmica — FRUVER

Basado en la rama **landing-pdo-seguridad** (commit `6358aab`, "Código único de producto").
Extrae el ZIP **en la raíz del proyecto** (donde está `composer.json`). No requiere `composer require`
ni cambios en la base de datos.

## Archivos

**Nuevos** (no existían):
`app/Config/Impresora.php`, `app/Libraries/Termica/*` (9 archivos), `app/Views/ticket_html.php`,
`app/Views/ticket_config.php`, `public/js/impresion_ticket.js`.

**Existentes que se reemplazan** (se entregan completos):
`app/Config/Routes.php` (+8 líneas), `app/Controllers/Tickets.php` (se conserva `ver()` de WhatsApp y se
agregan métodos), `app/Views/lista_p_pedido.php` (columna **Imprimir**, meta CSRF y `<script>`).
Si tu copia local de esos tres ya cambió respecto a la rama, aplica en su lugar el parche:
`git apply cambios_archivos_existentes.patch`.

## Cómo funciona

- Los datos salen de `Modelo_pedido::ticket()` (el mismo del ticket de WhatsApp): sin SQL nuevo.
- Rutas (solo superadmin/admin/developer, CSRF global): `GET ticket_impreso/{id}`, `POST imprime_ticket/{id}`,
  `GET impresora`, `POST impresora/prueba`, `POST impresora/conexion`.
- Por defecto (`conexion = navegador`) el botón abre el ticket en vista de 58/80 mm y lanza el diálogo de
  impresión: sirve con **cualquier** impresora con driver. Para imprimir directo (sin diálogo), agrega al `.env`:

```ini
# A) Red / WiFi (la más fiable: además detecta sin papel y tapa abierta)
impresora.conexion = red
impresora.host     = 192.168.1.50
impresora.papelMm  = 80

# B) USB en Windows/XAMPP: comparte la impresora (Propiedades → Compartir) con un nombre corto
impresora.conexion         = windows
impresora.nombreCompartido = POS80
impresora.papelMm          = 58

# C) Bluetooth/USB como puerto COM (Windows) o dispositivo (Linux)
impresora.conexion = puerto
impresora.puerto   = COM5          # Linux: /dev/usb/lp0 o /dev/rfcomm0

# D) Linux con CUPS
impresora.conexion         = cups
impresora.nombreCompartido = POS80

# Datos del negocio (opcionales)
impresora.negocioNombre    = 'FRUVER'
impresora.negocioDireccion = 'Mercado Central, Local 14'
impresora.negocioTelefono  = '722 123 4567'
impresora.mensajePie       = '¡Gracias por su compra!'
```

## Probar
1. Entra a `/impresora` → **Probar conexión** (no gasta papel) → **Imprimir hoja de prueba**.
2. La regla de columnas debe caber exacta y los acentos verse bien.

| Síntoma en la hoja de prueba | Ajuste en `.env` |
|---|---|
| Acentos/ñ con símbolos raros | `impresora.codificacion = cp850` (o `cp437`, `cp1252`) |
| Salen caracteres chinos | `impresora.cancelarKanji = true` |
| Nada funciona con acentos | `impresora.codificacion = ascii` (sin acentos, universal) |
| Texto cortado o con margen | `impresora.papelMm = 58`/`80` o `impresora.columnas = 42` |
| No corta el papel | `impresora.corte = completo` (o `ninguno` si no hay cuchilla) |
| Cajón de dinero | `impresora.abrirCajon = true` |

## Errores que se explican en pantalla (con botón "Imprimir con el navegador" de salida)
Sin conexión, tiempo agotado, puerto rechazado, IP no encontrada, sin papel / tapa abierta / error de
impresora (estos tres solo en conexión `red`), sin permiso o puerto inexistente, impresora ocupada,
sesión vencida, pedido inexistente o sin productos.

## Limitaciones
- Conexión `windows`: el spooler acepta el trabajo aunque la impresora esté apagada; no se puede detectar.
- Si el sistema está en internet (no en el equipo del negocio), el servidor no alcanza una impresora USB local:
  usa la vista por navegador o una impresora de red con IP accesible.
- El ticket no incluye repartidor ni dirección (el origen `Modelo_pedido::ticket()` no los devuelve).
- Probado con una impresora de red simulada y las clases reales de CodeIgniter; **no** con una impresora
  física ni en Windows. Haz la hoja de prueba antes de usarlo en el negocio.

# Seguridad, auditoría y usabilidad (correcciones de la revisión de calidad)

Correcciones a los hallazgos de la revisión de calidad de FRUVER. Para cada uno se indica
qué cambió, dónde está y con qué norma o criterio se relaciona.

Marco de referencia:

- **LFPDPPP** (Ley Federal de Protección de Datos Personales en Posesión de los Particulares, 2025):
  deber de seguridad (medidas administrativas, físicas y técnicas) y principio de proporcionalidad.
- **NOM-151-SCFI-2016**, como buena práctica de integridad y conservación de los mensajes de datos.
  Las bitácoras de FRUVER no son constancias de conservación certificadas.
- **WCAG 2.1 nivel AA**, el criterio técnico que usan en México los lineamientos de accesibilidad web
  derivados de la Ley General para la Inclusión de las Personas con Discapacidad.

## Instalación

```bash
composer install
php spark migrate      # o los .sql de database_cambios/, en orden
```

Las carpetas `writable/cache` y `writable/session` ya vienen en el repositorio, cada una con su
`index.html`. Si una instalación las pierde, `app/Config/Events.php` (`pre_system`) las vuelve a
crear antes de que la caché o la sesión las necesiten. Si no puede crearlas, avisa que hay que
revisar los permisos de `writable/`.

## Configuración

| Hallazgo | Corrección | Archivo |
|---|---|---|
| La sesión duraba 2 h | La sesión se cierra tras **30 min sin actividad** (`Session::$expiration = 1800`). `SesionGuard` lleva la cuenta de la última petición y, al vencer, cierra la sesión y avisa en el login. «Recordarme» quedó apagado porque su cookie de 30 días reabría la sesión. | `app/Config/Session.php`, `app/Filters/SesionGuard.php`, `app/Config/Auth.php` |
| Se aceptaban contraseñas sin números ni símbolos | Validador `ComposicionContrasena`: mínimo 8 caracteres con **minúscula, mayúscula, número y símbolo**, además de las reglas de Shield (sin datos personales y sin contraseñas comunes). El registro muestra cada regla en vivo. | `app/Validation/ComposicionContrasena.php`, `app/Views/Auth/register.php` |
| Ícono de eliminar distinto en Existencias | Se usa `fa-trash-can`, como en el resto, con `aria-label`. | `app/Views/lista_existencia.php` |

> Las contraseñas que ya existían no se revisan de nuevo. La regla aplica al crear una cuenta o
> cambiar la contraseña.

## Auditoría de clientes y productos

- Tabla `auditoria`, que llenan triggers en `cliente` y `producto` al dar de alta, modificar o
  eliminar. Igual que `pedido_bitacora`, cubre todos los caminos (panel, tienda, comandos,
  phpMyAdmin), y el autor sale de `@fruver_id_user` / `@fruver_usuario` (`ConexionPdo`).
- **Minimización:** de un cliente se guarda *qué* campos cambiaron («teléfono, nombre»), no sus
  valores. Así la auditoría no se vuelve una segunda copia de los datos personales, y la
  supresión de un cliente sí borra sus datos. De un producto se guardan el valor anterior y el nuevo.
- Se consulta en **Sistema → Auditoría** (`/auditoria`), con filtros por catálogo, número y texto.

### Bitácoras inalterables

Triggers `BEFORE UPDATE` y `BEFORE DELETE` rechazan cualquier cambio en `auditoria`,
`pedido_bitacora` y `rol_bitacora`, con el error *«la bitácora es de solo consulta»*. La aplicación
no tiene ninguna pantalla para editarlas.

Los triggers no frenan `DROP TABLE`, `TRUNCATE` ni `DROP TRIGGER`. Eso lo cierra la cuenta de la
aplicación: `php spark bd:asegurar` crea `fruver_app`, que solo puede **leer** `auditoria` y
`pedido_bitacora`, solo **agregar** en `rol_bitacora` y `consentimiento`, y no tiene DDL, TRIGGER,
FILE ni GRANT en ninguna tabla. Las migraciones se corren después con `php spark bd:migrar`.
Ver [`PRODUCCION.md`](PRODUCCION.md).

Archivos: `app/Database/Migrations/2026-11-22-100000_CreateAuditoriaCatalogos.php`
(o `database_cambios/auditoria_cambios.sql`), `app/Models/Modelo_auditoria.php`,
`app/Controllers/Auditoria.php`, `app/Views/auditoria.php`.

## Pedidos duplicados al reenviar

1. **Servidor (idempotencia).** Cada formulario que crea un pedido lleva un `token_envio`
   aleatorio (`campo_envio_unico()`). `Modelo_envio_unico::unaVez()` lo registra en la **misma
   transacción** que crea el pedido. Si el token vuelve a llegar (corte de red y reenvío,
   «Reenviar formulario», doble clic), la llave primaria lo rechaza y se responde *«Este pedido
   ya se había guardado (#N); no se creó otro»*. Si el primer intento falló, el token queda libre.
2. **Navegador.** `public/js/envio_unico.js` ignora un segundo envío del mismo formulario
   mientras el primero está en curso.

Los tokens se borran a los 7 días con `php spark privacidad:depurar`.

Cubre: «Nuevo pedido» (Productos por pedido) y el formulario de Pedidos. En la tienda no hacía
falta porque el carrito se vacía en la misma petición que crea el pedido.

## Formularios y ventanas modales

| Hallazgo | Corrección |
|---|---|
| El primer clic en Guardar/Siguiente no funcionaba (el botón se movía ~100 px) y en Mermas y Nuevo pedido la ventana se cerraba con lo capturado | **Causa:** el panel de reglas del campo enfocado se insertaba debajo del campo y se quitaba al perder el foco. El contenido subía ~100 px entre el *mousedown* y el *mouseup*, el clic no llegaba al botón y, en los modales, caía en el fondo, que cerraba la ventana. **Corrección:** el panel flota (`position: fixed`, sin recibir clics) y no mueve nada (`reglas_campo.js/.css`). Además, `public/js/modales.js` hace que un clic en el fondo **no** cierre una ventana con campos y que Escape pregunte antes de descartar lo capturado. |
| 51.5 % de los campos con etiqueta asociada | **100 %** de los campos de las vistas en uso tienen `<label for>` o `aria-label`. Lo vigila la prueba `CorreccionesAuditoriaTest::testTodosLosCamposTienenEtiquetaAsociada`. |
| No se podía elegir producto con teclado | El buscador de producto es un *combobox* ARIA 1.2: se escribe (sin mínimo de letras y sin importar acentos), se elige con ↓/↑ y Enter, y Escape cierra la lista. Al elegir, el foco pasa a Cantidad, y Enter agrega la línea. |
| Crear un pedido: 13 clics, ~52 s para 2 productos | «Nuevo pedido» cabe en **una sola pantalla** (antes era un asistente de 3 pasos). La fecha es hoy, el estatus inicial es «Pedido realizado» con la hora actual, y la unidad y el precio vienen de la última entrada. Con ratón, 2 productos toman 8 clics: abrir, cliente ×2, producto, agregar, producto, agregar y guardar. Con teclado basta con el clic de abrir. |

Las ventanas modales ahora tienen además `role="dialog"` y `aria-modal`, mandan el foco al primer
campo, no dejan que Tab salga de la ventana y, al cerrarse, regresan el foco al botón que las
abrió (WCAG 2.1.2 y 2.4.3).

Archivos: `app/Views/partials/modal_pedido.php`, `public/js/pedido_formulario.js`,
`public/estilos/pedido_formulario.css`, `public/js/modales.js`, `public/js/envio_unico.js`.

## Pruebas

```bash
vendor/bin/phpunit tests/unit/CorreccionesAuditoriaTest.php
```

Se probó además en Microsoft Edge, sobre una copia de la base, con la versión anterior y con la
corregida:

| Escenario | Antes | Ahora |
|---|---|---|
| Mermas: escribir y clic en Guardar | el botón sube 100 px; no se envía; la ventana se cierra | 0 px; se envía al primer clic |
| Clic en el fondo con datos capturados | se cierra y se pierden | sigue abierta con los datos |
| Escape con datos capturados | se cierra sin preguntar | pregunta antes |
| Nuevo pedido de 2 productos solo con teclado | no se podía | 1 clic (abrir) |
| Reenviar el mismo pedido | se duplicaba | «ya se había guardado (#N)»; 1 solo pedido |

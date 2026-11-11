# Privacidad y datos personales (LFPDPPP)

Cómo cumple FRUVER 2.0 el **Aviso de Privacidad Integral** y el **Aviso y Formato de Consentimiento**,
y qué debe configurar u operar el negocio.

## 1. Puesta en marcha

1. Aplica los cambios de base de datos (una de las dos opciones):
   - `php spark migrate`
   - o en phpMyAdmin: `database_cambios/privacidad_cambios.sql` y `database_cambios/trazabilidad_pedidos_cambios.sql`.
2. Completa los datos entre corchetes en `app/Config/Privacidad.php` (razón social, domicilio, contacto,
   plazos, proveedor de reparto). Mientras falten, el aviso los muestra como `[...]` y el personal ve un
   recordatorio en *Privacidad*.
3. Configura el correo (sección 4) y programa las tareas (sección 5).

## 2. Qué hace el sistema

| Documento dice | Dónde está |
|---|---|
| Aviso disponible antes de dar datos | `/aviso-de-privacidad` y `/aviso-de-privacidad/consentimiento` (públicas; enlazadas en login, registro y tienda) |
| Declaración obligatoria + finalidades V1–V4 opcionales | Registro (`app/Views/Auth/register.php`), validado en `App\Controllers\Auth\RegisterController` |
| Constancia: fecha, hora, versión e IP | Tabla `consentimiento` (solo inserciones; la fila más reciente es el estado vigente) |
| Modificar o revocar V1–V4 en cualquier momento | *Mis preferencias de privacidad* (`/privacidad/preferencias`), cualquier rol |
| Nuevo consentimiento al cambiar el aviso | Filtro `consentimiento`: si sube `Privacidad::$version`, todos aceptan de nuevo |
| Usuario que capturó o modificó el pedido | Tabla `pedido_bitacora` (triggers); se ve en *Productos por pedido* al abrir un pedido |
| V1–V3 solo a quien las autorizó, por su canal | Panel → *Comunicaciones* (`/comunicaciones`) |
| V4 recomendaciones personalizadas | Fila "Para ti" en la tienda, solo si la cuenta autorizó V4 |
| Confirmación del pedido por correo | Al pedir en la tienda, si hay correo configurado |
| Plazo del registro de accesos | `php spark privacidad:depurar` |
| Respaldos periódicos | `php spark respaldo:crear` |

### Trazabilidad de pedidos

Los triggers de `pedido`, `producto_pedido` y `estatus` escriben en `pedido_bitacora` quién hizo cada
cambio. La aplicación fija el usuario en cada conexión (`App\Libraries\ConexionPdo`); un cambio hecho por
fuera (phpMyAdmin) queda como "Base de datos (sin sesión)". En un hosting, el usuario de MySQL necesita el
permiso `TRIGGER` (el proyecto ya usa triggers para existencias).

### Para programadores: finalidades voluntarias

Cualquier función nueva que envíe promociones, recordatorios o encuestas, o que personalice ofertas,
debe filtrar con `Modelo_consentimiento::usuariosQueAceptan('v1')`, `destinatarios()` o `autoriza()`.
Si se agrega una finalidad nueva: agrégala en `Config\Privacidad::$finalidades` (y su columna en
`consentimiento`), actualiza el texto del aviso y **sube `$version`**.

## 3. Cambiar el aviso

1. Edita el texto en `app/Views/privacidad/aviso.php` o `consentimiento.php`.
2. Actualiza `$fechaActualizacion` y sube `$version` en `app/Config/Privacidad.php`.
3. Desde ese momento cada cuenta verá el aviso nuevo al entrar y deberá aceptarlo; sus finalidades
   voluntarias anteriores se conservan como propuesta y puede cambiarlas.

## 4. Correo (recuperar acceso, comprobantes y comunicaciones)

Sin correo configurado, el sistema oculta "recuperar acceso" en el login, no envía comprobantes y en
*Comunicaciones* solo ofrece WhatsApp y teléfono. Para activarlo, agrega al `.env`:

```
email.fromEmail  = 'pedidos@tu-dominio.com'
email.fromName   = 'FRUVER'
email.protocol   = 'smtp'
email.SMTPHost   = 'smtp.tu-proveedor.com'
email.SMTPUser   = 'usuario'
email.SMTPPass   = 'contraseña'
email.SMTPPort   = 587
email.SMTPCrypto = 'tls'
```

El proveedor de correo es un encargado (Aviso, sección 8).

## 5. Tareas programadas (una vez al día)

```
php spark privacidad:depurar   # borra inicios de sesión más viejos que Privacidad::$diasRegistroAccesos y "recordarme" vencidos
php spark respaldo:crear       # respaldo comprimido en writable/respaldos; borra los de más de Respaldo::$diasConservar días
```

En Windows: *Programador de tareas* → acción `C:\xampp\php\php.exe`, argumentos `spark respaldo:crear`,
iniciar en la carpeta del proyecto. En Linux: `cron`.

Los respaldos contienen datos personales: guárdalos fuera del servidor en un lugar con acceso
restringido y prueba restaurarlos de vez en cuando:

```
gunzip -c writable/respaldos/fruver_AAAAMMDD_HHMMSS.sql.gz | mysql -u USUARIO -p fruver
```

Las fotos de evidencia de entrega (`writable/uploads/evidencias`) no van en el respaldo de la base:
inclúyelas en el respaldo de archivos del servidor.

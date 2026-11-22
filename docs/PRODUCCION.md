# Publicar FRUVER de forma segura (producción)

Guía paso a paso para poner FRUVER en un servidor real con datos de clientes. Cada medida
corresponde al **deber de seguridad** de la LFPDPPP (medidas administrativas, técnicas y físicas
para evitar daño, pérdida, alteración, destrucción o acceso no autorizado a los datos personales)
y a la sección 10 del Aviso de Privacidad.

Antes y después de publicar, corre:

```bash
php spark seguridad:revisar
```

Muestra cada punto como OK, AVISO o FALLA y termina con error si algo falla. En un servidor
público no debe quedar ninguna FALLA.

## 1. Base de datos con privilegios mínimos

La aplicación **no** debe conectarse como `root`. Si alguien encontrara una falla en la web
(inyección SQL, robo del `.env`), con `root` podría borrar la base, apagar la auditoría o leer
otras bases.

```bash
php spark bd:asegurar
```

Se corre una vez, con la cuenta de administración que hoy está en `.env`. El comando:

1. Crea la cuenta `fruver_app`, solo para `localhost` / `127.0.0.1`, con una contraseña aleatoria
   de 48 caracteres. Si se vuelve a correr, rota la contraseña.
2. Le da permisos tabla por tabla:
   - Tablas de operación: SELECT, INSERT, UPDATE, DELETE.
   - `auditoria`, `pedido_bitacora` y `migrations`: solo lectura.
   - `rol_bitacora` y `consentimiento`: solo agregar. Son constancias.
   - Ninguna tabla: CREATE, ALTER, DROP, TRUNCATE, TRIGGER, FILE ni GRANT.
3. Comprueba que la cuenta entra y que **no** puede crear tablas, borrar triggers ni tocar las bitácoras.
4. Guarda las credenciales en `.env`. Antes copia el `.env` anterior a `writable/respaldos/`.

Desde entonces:

| Tarea | Comando | Cuenta |
|---|---|---|
| Aplicar migraciones | `php spark bd:migrar` (no `migrate`) | administración |
| Respaldo diario | `php spark respaldo:crear` | administración |
| Actualizar roles | `php spark roles:sincronizar` | administración |
| Todo lo demás (la web) | — | `fruver_app` |

**Contraseña de administración.** En el servidor, la cuenta de administración (root u otra) debe
tener contraseña y esa contraseña **no se escribe en `.env`**. Se define solo en la sesión o en la
tarea programada que la necesita:

```bat
:: Windows (Símbolo del sistema)
set database_admin_username=root
set database_admin_password=LA-CONTRASEÑA
php spark bd:migrar
```

```bash
# Linux
database_admin_username=root database_admin_password='LA-CONTRASEÑA' php spark bd:migrar
```

Para el respaldo diario, configura esas dos variables en la tarea programada (Windows: una cuenta
de servicio distinta de la del servidor web; Linux: el `crontab` de un usuario que no sea el de
Apache o Nginx).

Si la base está en otro servidor: `php spark bd:asegurar --host IP-DEL-SERVIDOR-WEB`.

## 2. Servidor web

1. **Solo `public/` es visible.** Lo ideal es que el `DocumentRoot` (Apache) o `root` (Nginx) apunte a
   `…/fruver_2.0/public`. Si el proyecto vive dentro de `htdocs`, el `.htaccess` de la raíz bloquea
   todo lo demás (`.env`, `.git`, respaldos, `*.sql`). No lo borres.
2. **HTTPS obligatorio.** Pon un certificado (Let's Encrypt es gratuito) y en `.env`:
   ```
   CI_ENVIRONMENT = production
   app.baseURL    = 'https://tu-dominio.mx/'
   toolbar.activa = false
   ```
   En `production` FRUVER redirige todo a HTTPS, envía HSTS, marca las cookies como `Secure`
   y deja de mostrar errores internos.
3. **Permisos de archivos.** Solo `writable/` debe poder escribirse, y solo por el usuario del servidor
   web. `.env` debe ser legible únicamente por ese usuario (Linux: `chmod 600 .env`).
4. **MySQL sin acceso desde Internet.** En `my.ini` / `my.cnf`: `bind-address = 127.0.0.1`. No abras el
   puerto 3306 en el firewall ni en el router.
5. **phpMyAdmin.** No lo publiques en el servidor de producción; si hace falta, solo desde la red
   local o por túnel SSH.

## 3. Lo que FRUVER ya hace por sí mismo

| Medida | Dónde |
|---|---|
| Contraseñas guardadas con hash; mínimo 8 caracteres con minúscula, mayúscula, número y símbolo, y no comunes ni personales | Shield + `App\Validation\ComposicionContrasena` |
| Máximo 10 intentos de inicio de sesión por minuto e IP | Filtro `auth-rates` |
| Sesión que se cierra a los 30 min sin actividad, sin «recordarme», ligada al navegador | `Config\Session`, `App\Filters\SesionGuard` |
| Protección CSRF en todo POST, Content-Security-Policy, X-Frame-Options, nosniff | `Config\Filters`, `Config\ContentSecurityPolicy` |
| Consultas preparadas (anti inyección SQL) y escape de toda salida (anti XSS) | `App\Models\PdoModelo`, `esc()` |
| Control de acceso por rol en cada ruta | `app/Config/Routes.php` |
| Auditoría de clientes, productos, pedidos y roles que no se puede modificar ni borrar | Triggers + permisos de `fruver_app` |
| Pedidos sin duplicados al reenviar un formulario | `envio_unico` |
| Aviso de privacidad versionado; cada cambio pide aceptarlo de nuevo | `Config\Privacidad::$version`, filtro `consentimiento` |

## 4. Operación (medidas administrativas)

- **Respaldos diarios** (`php spark respaldo:crear`), copiados fuera del servidor y probados de vez en
  cuando (ver `docs/PRIVACIDAD.md`, sección 5).
- **Depuración diaria** (`php spark privacidad:depurar`).
- **Revisión periódica** de cuentas y roles (*Sistema → Roles*) y de la auditoría (*Sistema → Auditoría*).
- **Actualizaciones**: `composer update` del framework y de Shield cuando salgan versiones de seguridad,
  y luego `php spark bd:migrar`.
- **Vulneraciones**: si ocurre una que afecte de forma significativa los derechos de las personas, hay
  que informarles sin dilación (LFPDPPP; Aviso, sección 10). Anota qué pasó, cuándo, qué datos se
  afectaron y qué se hizo.
- **Datos del responsable**: completa los `[…]` de `app/Config/Privacidad.php` antes de publicar.
  `seguridad:revisar` los marca como FALLA en un servidor público.

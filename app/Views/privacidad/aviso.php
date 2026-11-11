<?php
/**
 * Aviso de Privacidad Integral — FRUVER 2.0 (texto íntegro, versión en Config\Privacidad).
 * Los datos entre corchetes salen de app/Config/Privacidad.php.
 *
 * @var \Config\Privacidad $cfg
 */
$tabla = static function (array $encabezados, array $filas): string {
    $html = '<div class="pv-tabla"><table><thead><tr>';
    foreach ($encabezados as $e) {
        $html .= '<th scope="col">' . esc($e) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($filas as $fila) {
        $html .= '<tr>';
        foreach ($fila as $celda) {
            $html .= '<td>' . esc($celda) . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
};
$cols = ['Dato personal', 'Cómo se obtiene', 'Uso específico', 'Justificación'];
?>
<?= $this->extend('privacidad/layout') ?>

<?= $this->section('title') ?>Aviso de Privacidad Integral<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="pv-contenedor">
<article class="card pv-doc">
<div class="card-body">

<h1 class="card-title">Aviso de Privacidad Integral — FRUVER 2.0</h1>
<p class="pv-subtitulo-doc">Sistema de gestión de inventario, pedidos, mermas y analítica para negocios de frutas y verduras</p>
<p class="pv-meta">
    Versión: <?= esc($cfg->version) ?> | Fecha de última actualización: <?= esc($cfg->fechaActualizacion) ?> |
    Marco normativo: Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP,
    publicada en el DOF el 20 de marzo de 2025) y su normativa complementaria.
</p>

<nav class="pv-indice" aria-label="Contenido">
    <a href="#s1">1. Responsable</a> <a href="#s2">2. A quién va dirigido</a> <a href="#s3">3. Datos</a>
    <a href="#s4">4. Datos sensibles</a> <a href="#s5">5. Finalidades</a> <a href="#s6">6. Principios</a>
    <a href="#s7">7. Tratamiento automatizado</a> <a href="#s8">8. Transferencias</a> <a href="#s9">9. Conservación</a>
    <a href="#s10">10. Seguridad</a> <a href="#s11">11. Derechos ARCO</a> <a href="#s12">12. Revocación</a>
    <a href="#s13">13. Menores</a> <a href="#s14">14. Cambios</a> <a href="#s15">15. Autoridad</a> <a href="#s16">16. Aceptación</a>
</nav>

<h2 id="s1">1. Identidad y domicilio del responsable</h2>
<p>
    <?= esc($cfg->responsable) ?> (en adelante, "el Responsable"), con domicilio en <?= esc($cfg->domicilio) ?>,
    es la persona responsable del tratamiento de los datos personales que se recaban y almacenan a través del
    sistema FRUVER 2.0 (en adelante, "el Sistema").
</p>
<p>Datos de contacto del área o persona encargada de privacidad:</p>
<ul>
    <li>Nombre: <?= esc($cfg->contactoNombre) ?></li>
    <li>Correo electrónico: <?= esc($cfg->contactoCorreo) ?></li>
    <li>Teléfono: <?= esc($cfg->contactoTelefono) ?></li>
    <li>Horario de atención: <?= esc($cfg->contactoHorario) ?></li>
</ul>

<h2 id="s2">2. ¿A quién va dirigido este aviso?</h2>
<p>Este aviso aplica a tres tipos de personas titulares de datos personales:</p>
<ol>
    <li><strong>Clientes:</strong> personas que realizan pedidos de producto, para quienes se registra un perfil, un historial de pedidos y, en su caso, datos de entrega.</li>
    <li><strong>Usuarios internos del Sistema:</strong> personas con cuenta de acceso (administradores, usuarios operativos y repartidores) que consultan o capturan información en el Sistema.</li>
    <li><strong>Visitantes y usuarios en general</strong> del sitio o aplicación web, respecto de los datos técnicos que se generan automáticamente al navegar.</li>
</ol>

<h2 id="s3">3. Datos personales que se recaban</h2>
<p>A continuación se detalla, por módulo, qué datos personales se tratan, cómo se obtienen, para qué se usan y por qué son necesarios.</p>

<h3>3.1. Módulo de autenticación y control de acceso (usuarios internos)</h3>
<?= $tabla($cols, [
    ['Nombre de usuario', 'Lo proporciona la persona al registrarse o el administrador al darla de alta', 'Identificar a la persona dentro del Sistema y mostrar su nombre en la interfaz', 'Necesario para distinguir quién opera el Sistema y asignar responsabilidad a cada acción'],
    ['Correo electrónico', 'Lo proporciona la persona o el administrador', 'Inicio de sesión, recuperación de acceso y comunicaciones operativas de la cuenta', 'Es el identificador único de la cuenta; sin él no se puede autenticar a la persona'],
    ['Contraseña (almacenada únicamente de forma protegida, nunca en texto legible)', 'La define la persona', 'Verificar que quien intenta entrar es el titular de la cuenta', 'Garantiza la confidencialidad del acceso; el Responsable no puede ver ni recuperar la contraseña original'],
    ['Rol asignado (administrador u operador)', 'Lo asigna el administrador', 'Determinar a qué pantallas y funciones accede cada persona (por ejemplo, panel administrativo o panel operativo)', 'Principio de mínimo privilegio: cada persona accede solo a lo que necesita para su función'],
    ['Permisos individuales', 'Los asigna el administrador', 'Habilitar o restringir acciones específicas', 'Control de acceso y segregación de funciones'],
    ['Estado de la cuenta (activa/inactiva), fechas de creación, actualización y última actividad', 'Generado automáticamente', 'Administración de cuentas, depuración de cuentas inactivas, soporte', 'Seguridad y gestión del ciclo de vida de la cuenta'],
    ['Registro de inicios de sesión: dirección IP, navegador y sistema operativo, identificador usado, fecha y hora, resultado (exitoso o fallido)', 'Generado automáticamente en cada intento de acceso', 'Detectar accesos no autorizados e intentos indebidos, y atender incidentes de seguridad', 'Medida de seguridad exigida por el deber de seguridad de la LFPDPPP; permite trazabilidad'],
    ['Identificadores de sesión persistente ("recordarme") y de acceso', 'Generado automáticamente si la persona lo activa o cuando la función lo requiere', 'Mantener la sesión iniciada en el dispositivo y autenticar funciones que lo requieran', 'Comodidad de uso y seguridad de la autenticación; el identificador de sesión persistente es opcional y se elimina al cerrar sesión o al expirar'],
]) ?>

<h3>3.2. Módulo de clientes y pedidos</h3>
<?= $tabla($cols, [
    ['Nombre completo del cliente', 'Lo proporciona el cliente o el personal que captura el pedido', 'Identificar al cliente, asociarlo a sus pedidos y emitir la documentación del pedido', 'Sin identificar al cliente no es posible registrar ni dar seguimiento a un pedido'],
    ['Teléfono', 'Lo proporciona el cliente', 'Confirmar el pedido, coordinar la entrega, avisar de cambios (por ejemplo, producto no disponible)', 'Los productos son perecederos y la coordinación inmediata es indispensable'],
    ['Correo electrónico (si aplica)', 'Lo proporciona el cliente', 'Enviar confirmaciones o comprobantes del pedido', 'Constancia de la operación'],
    ['Domicilio o dirección de entrega y referencias', 'Lo proporciona el cliente', 'Entregar físicamente el pedido', 'Es imprescindible para cumplir con la entrega solicitada'],
    ['Fecha, hora y estado del pedido', 'Generado por el Sistema y por el personal', 'Seguimiento del pedido a lo largo de su ciclo de vida', 'Cumplimiento de la relación comercial'],
    ['Detalle de productos solicitados, cantidades, precios, subtotales y total', 'Generado al armar el pedido', 'Preparar el pedido, calcular el monto, descontar existencias y llevar la contabilidad de ventas', 'Cumplimiento de la relación comercial y obligaciones fiscales y contables'],
    ['Número de compras y estado de cliente preferencial (y, si el personal lo cambia a mano: motivo, fecha y quién lo cambió)', 'Lo calcula el Sistema a partir de los pedidos del cliente; el personal puede asignarlo o retirarlo', 'Proponer un precio preferencial y dar prioridad en la ruta de reparto a los clientes frecuentes (ver sección 7)', 'Reconocer a los clientes frecuentes dentro de la relación comercial; solo otorga beneficios'],
    ['Fecha y estado de entrega', 'Generado por el Sistema y por el personal', 'Programar y confirmar entregas', 'Cumplimiento de la relación comercial'],
    ['Usuario que capturó o modificó el pedido', 'Generado automáticamente', 'Trazabilidad y corrección de errores', 'Control interno y seguridad'],
]) ?>
<p>
    <strong>Datos de pago:</strong> el Sistema no almacena números de tarjeta bancaria, claves, códigos de seguridad
    ni credenciales bancarias. Si en el futuro se integra una pasarela de pagos, este aviso deberá actualizarse.
</p>

<h3>3.3. Módulos de inventario, productos, mermas y existencias</h3>
<p>
    Los módulos de productos, entradas, existencias y mermas registran información operativa del inventario (nombre
    del producto, cantidades, fechas de entrada, fechas de caducidad, pérdidas por descomposición o vencimiento).
    Esta información no constituye datos personales de personas físicas.
</p>
<p>
    Sin embargo, si estos módulos guardan la identidad del usuario que realizó el registro (por ejemplo, la persona
    usuaria que realizó la captura), ese dato sí es un dato personal asociado a la persona y se trata únicamente para
    fines de trazabilidad y control interno.
</p>

<h3>3.4. Módulo de analítica (panel de control)</h3>
<p>
    El panel con indicadores (KPI) y gráficas (productos más vendidos, ingresos, mermas semanales, productos con
    menor rotación, entradas próximas a caducar) trabaja con información agregada y estadística derivada de los
    módulos anteriores. Su finalidad es administrativa y de planeación del negocio. No genera perfiles individuales
    de clientes ni toma decisiones automatizadas que produzcan efectos jurídicos o afecten significativamente a una persona.
    La única clasificación individual que hace el Sistema es la de cliente preferencial, descrita en la sección 3.2 y en la sección 7.
</p>

<h3>3.5. Datos técnicos y cookies</h3>
<?= $tabla(['Dato', 'Origen', 'Uso', 'Justificación'], [
    ['Cookie de sesión de la aplicación', 'Sistema', 'Mantener la sesión activa y asociar solicitudes a la persona autenticada', 'Estrictamente necesaria para el funcionamiento del Sistema'],
    ['Elementos de seguridad de formularios', 'Sistema', 'Proteger formularios contra solicitudes no autorizadas', 'Medida de seguridad'],
    ['Mensajes de la interfaz', 'Sesión', 'Mostrar avisos de éxito o error tras una acción', 'Funcionamiento de la interfaz'],
    ['Dirección IP y datos del navegador', 'Registros del Sistema', 'Seguridad, diagnóstico de errores y auditoría', 'Seguridad de la información'],
    ['Recursos externos de interfaz (por ejemplo, gráficas y tipografías cargadas desde proveedores de terceros)', 'Terceros que sirven el archivo', 'Mostrar gráficas y estilos', 'Al cargarse desde un tercero, el proveedor puede recibir la dirección IP del navegador'],
]) ?>
<p>El Sistema no utiliza cookies de publicidad ni de seguimiento de terceros con fines comerciales.</p>

<h3>3.6. Módulo de repartidores, entregas y rutas de reparto</h3>
<?= $tabla($cols, [
    ['Nombre, apellidos y teléfono del repartidor', 'Los proporciona el repartidor o el administrador al darlo de alta', 'Identificarlo, asignarle entregas y contactarlo durante el reparto', 'Necesario para organizar y dar seguimiento a las entregas'],
    ['Domicilio del repartidor y notas internas', 'Los proporciona el repartidor o el administrador', 'Contacto y administración del personal de reparto', 'Gestión de la relación con el repartidor'],
    ['Coordenadas de la dirección de entrega del cliente', 'Las obtiene el Sistema con un proveedor de mapas a partir de la dirección escrita, del punto que se acomoda en el mapa o de la ubicación registrada al entregar', 'Ubicar el domicilio, planear el orden de la ruta y calcular la hora estimada de llegada', 'Es indispensable para llegar al domicilio y entregar a tiempo un producto perecedero'],
    ['Ubicación GPS del teléfono del repartidor en el momento de registrar una entrega', 'La toma el teléfono del repartidor, con su permiso, solo al marcar el pedido como entregado. Durante la ruta su ubicación solo se muestra en su propio mapa y no se guarda', 'Confirmar dónde se entregó el pedido y corregir la ubicación del domicilio para entregas futuras', 'Comprobación de la entrega y calidad del servicio'],
    ['Foto de evidencia de la entrega (puede mostrar la fachada o la entrada del domicilio)', 'La toma el repartidor al entregar, cuando se requiere', 'Comprobar que el pedido se entregó y atender aclaraciones', 'Constancia de la entrega; se guarda fuera del área pública y solo la ve el personal autorizado'],
    ['Horas de asignación, salida y entrega, intentos, motivo de una entrega fallida, monto cobrado y forma de pago (efectivo, transferencia o tarjeta)', 'Las registran el repartidor y el Sistema', 'Seguimiento de la entrega, cortes de caja y atención de aclaraciones', 'Cumplimiento de la relación comercial y control interno; no se guardan datos de tarjetas'],
    ['Hora estimada de llegada', 'La calcula el Sistema al planear la ruta', 'Avisar al cliente por teléfono o mensajería cuándo llegará su pedido', 'Coordinación de la entrega'],
]) ?>

<h2 id="s4">4. Datos personales sensibles</h2>
<p>
    El Sistema no solicita ni trata datos personales sensibles (origen racial o étnico, estado de salud, información
    genética, creencias religiosas, filosóficas o morales, afiliación sindical, opiniones políticas, preferencia
    sexual, datos biométricos u otros cuya utilización indebida pueda dar origen a discriminación o riesgo grave).
    Pedimos no capturar este tipo de información en campos de texto libre (por ejemplo, observaciones del pedido,
    referencias de entrega, notas del repartidor o motivos de una entrega fallida).
</p>

<h2 id="s5">5. Finalidades del tratamiento</h2>
<h3>5.1. Finalidades necesarias (dan origen y son indispensables para la relación con el titular)</h3>
<p>Estas finalidades no requieren consentimiento adicional porque sin ellas no podemos prestar el servicio o dar cumplimiento a la relación jurídica:</p>
<ol>
    <li>Crear y administrar la cuenta de acceso de los usuarios internos y autenticar su identidad.</li>
    <li>Asignar roles y permisos, y controlar el acceso a las funciones del Sistema.</li>
    <li>Registrar, procesar, confirmar, preparar y dar seguimiento a los pedidos de los clientes.</li>
    <li>Coordinar y realizar la entrega de los pedidos en el domicilio indicado: ubicar la dirección, planear las rutas de reparto, avisar la hora estimada de llegada y comprobar la entrega.</li>
    <li>Calcular montos, subtotales y totales, aplicar el precio preferencial a clientes frecuentes y llevar el registro de ventas.</li>
    <li>Mantener actualizadas las existencias y el control de entradas, caducidades y mermas vinculadas a los pedidos.</li>
    <li>Atender aclaraciones, correcciones, cancelaciones y reclamaciones relacionadas con un pedido.</li>
    <li>Garantizar la seguridad del Sistema: registrar accesos, detectar intentos no autorizados y atender incidentes.</li>
    <li>Cumplir con obligaciones legales, fiscales, contables y requerimientos de autoridad competente.</li>
</ol>
<h3>5.2. Finalidades voluntarias o secundarias (no indispensables; requieren consentimiento)</h3>
<p>Estas finalidades no son necesarias para prestarte el servicio, y puedes negarte a ellas sin que se vea afectada la relación con el Responsable:</p>
<ol>
    <li>Enviar promociones, ofertas, novedades de temporada y recordatorios de recompra por teléfono, mensajería o correo electrónico.</li>
    <li>Realizar encuestas de satisfacción y de calidad del servicio.</li>
    <li>Elaborar análisis de preferencias de compra a nivel cliente para ofrecer productos o promociones personalizados.</li>
</ol>
<p>
    Para manifestar tu consentimiento o tu negativa respecto de las finalidades voluntarias, consulta el
    <a href="<?= site_url('aviso-de-privacidad/consentimiento') ?>">Aviso y Formato de Consentimiento</a> que acompaña
    a este documento. Si no manifiestas tu negativa por los medios indicados, solo se tratarán tus datos para las
    finalidades necesarias.
</p>

<h2 id="s6">6. Justificación del tratamiento y principios aplicados</h2>
<p>El Responsable aplica los principios que establece la LFPDPPP. A continuación se explica cómo se refleja cada uno en el Sistema:</p>
<ul>
    <li><strong>Licitud:</strong> los datos se obtienen de manera directa de la persona titular y se usan para fines lícitos, vinculados con la operación de un negocio de venta de frutas y verduras.</li>
    <li><strong>Consentimiento:</strong> para finalidades necesarias, el tratamiento se funda en la relación jurídica (pedido o cuenta de usuario) y en el aviso de privacidad; para finalidades voluntarias, se solicita consentimiento por separado.</li>
    <li><strong>Información:</strong> este aviso describe qué datos se recaban, para qué y con quién se comparten.</li>
    <li><strong>Calidad:</strong> los datos deben ser exactos y estar actualizados; la persona titular puede rectificarlos en cualquier momento.</li>
    <li><strong>Finalidad:</strong> los datos solo se usan para los fines descritos en la sección 5. Cualquier finalidad nueva requerirá una actualización del aviso y, en su caso, nuevo consentimiento.</li>
    <li><strong>Lealtad:</strong> no se obtienen datos por medios engañosos o fraudulentos.</li>
    <li><strong>Proporcionalidad y minimización:</strong> solo se solicitan los datos estrictamente necesarios. Por ejemplo, no se pide fecha de nacimiento, estado civil, datos bancarios ni identificaciones oficiales.</li>
    <li><strong>Responsabilidad:</strong> el Responsable adopta medidas para dar cumplimiento a estos principios y responde por el tratamiento, incluso el que realicen terceros por su cuenta.</li>
</ul>
<h3>Justificación específica de los datos más relevantes</h3>
<ul>
    <li><strong>Correo y contraseña:</strong> son el mínimo indispensable para un sistema de autenticación seguro. La contraseña se almacena únicamente de forma protegida para que ni el Responsable ni terceros puedan conocerla.</li>
    <li><strong>IP y navegador en el registro de inicios de sesión:</strong> sin este registro no sería posible investigar un acceso indebido. Se conserva por un tiempo limitado (sección 9).</li>
    <li><strong>Teléfono y dirección del cliente:</strong> el producto es perecedero; una entrega fallida genera mermas y pérdida económica, por lo que la coordinación directa con el cliente es indispensable.</li>
    <li><strong>Historial de pedidos:</strong> necesario para atender aclaraciones, garantías de calidad del producto y cumplir con obligaciones fiscales y contables.</li>
    <li><strong>Rol de usuario:</strong> evita que personal sin autorización acceda a información administrativa (por ejemplo, analítica financiera).</li>
</ul>

<h2 id="s7">7. Tratamiento automatizado</h2>
<p>El Sistema emplea procesos automáticos para mantener consistente el inventario, entre otras funciones:</p>
<ul>
    <li>Descontar o bloquear existencias cuando un pedido cambia de estado.</li>
    <li>Detectar productos caducados y generar los registros de merma correspondientes.</li>
    <li>Corregir los registros de merma cuando se actualiza una fecha de caducidad.</li>
</ul>
<p>
    Estos procesos operan sobre datos de inventario, no sobre perfiles de personas.
</p>
<?php
// Mismos valores que usa el Sistema (si cambian en el código, el aviso los muestra actualizados).
$umbralPreferencial    = \App\Models\Modelo_cliente::UMBRAL_PREFERENCIAL;
$descuentoPreferencial = rtrim(rtrim(number_format(\App\Models\Modelo_cliente::DESCUENTO_PREFERENCIAL, 2, '.', ''), '0'), '.');
?>
<p>Además, el Sistema realiza dos procesos automáticos que sí consideran a la persona:</p>
<ul>
    <li>
        <strong>Cliente preferencial:</strong> el cliente que acumula <?= (int) $umbralPreferencial ?> compras o más
        (pedidos entregados, pagados o a crédito) se identifica automáticamente como preferencial. Esto solo le da beneficios: al
        capturar su pedido se le propone un precio con <?= esc($descuentoPreferencial) ?>&nbsp;% de descuento y su
        entrega recibe prioridad al ordenar la ruta de reparto. Nunca se le niega o restringe el servicio por no serlo.
    </li>
    <li>
        <strong>Orden de las rutas de reparto:</strong> el orden de las entregas y la hora estimada de llegada se
        calculan con la ubicación de cada domicilio, el horario de entrega solicitado y la prioridad de los clientes
        preferenciales.
    </li>
</ul>
<p>
    En ambos casos interviene una persona: el personal revisa y puede cambiar el precio de cada producto del pedido,
    puede asignar o retirar manualmente el estado de cliente preferencial (registrando el motivo, la fecha y quién lo
    hizo) y revisa la ruta antes de asignarla. Ninguno de estos procesos produce efectos jurídicos ni afecta de manera
    significativa a la persona titular. Si no estás de acuerdo con tu clasificación, puedes pedir que una persona la
    revise mediante los datos de contacto de la sección 1. Si en el futuro se implementaran decisiones automatizadas
    que pudieran perjudicar a una persona (por ejemplo, bloqueo automático de clientes), este aviso se actualizará y
    se informará el derecho a obtener intervención humana.
</p>

<h2 id="s8">8. Transferencias, remisiones y comunicación de datos</h2>
<p>El Responsable no vende, renta ni comparte tus datos personales con terceros para fines comerciales propios de ellos.</p>
<p>Los datos pueden ser conocidos, sin necesidad de tu consentimiento adicional, por:</p>
<?= $tabla(['Destinatario', 'Finalidad', 'Naturaleza'], [
    ['Proveedores de alojamiento que almacenan la información del Sistema', 'Almacenar y respaldar la información', 'Remisión a un encargado que trata los datos por cuenta del Responsable'],
    ['Proveedores de servicio de correo electrónico o mensajería', 'Enviar confirmaciones o comunicaciones', 'Remisión a un encargado'],
    ['Servicio de reparto o mensajería externo: ' . $cfg->proveedorReparto, 'Entregar pedidos', 'Comunicación indispensable para cumplir la entrega'],
    ['Autoridades fiscales, judiciales o administrativas', 'Atender requerimientos fundados y motivados', 'Obligación legal'],
    ['Proveedores de recursos externos de interfaz', 'Carga de gráficas y estilos', 'Posible recepción de IP del navegador'],
    ['Proveedores de mapas y rutas: Google Maps Platform (Google LLC, Estados Unidos) u openrouteservice (HeiGIT gGmbH, Alemania), según el que esté configurado. Los mapas se muestran con OpenStreetMap', 'Ubicar en el mapa la dirección de entrega y planear las rutas de reparto. Se envía la dirección escrita o sus coordenadas, sin el nombre ni el teléfono del cliente', 'Remisión a un encargado, con servidores fuera de México'],
]) ?>
<p>Los encargados están obligados a guardar confidencialidad y a tratar los datos únicamente conforme a las instrucciones del Responsable.</p>

<h2 id="s9">9. Plazos de conservación</h2>
<?= $tabla(['Información', 'Plazo de conservación', 'Criterio'], [
    ['Cuenta de usuario interno y datos del repartidor', 'Mientras la persona mantenga relación con el negocio; se desactiva al terminar', 'Necesidad operativa'],
    ['Registro de inicios de sesión', $cfg->textoPlazoAccesos(), 'Seguridad y auditoría'],
    ['Datos de cliente y pedidos', $cfg->plazoClientesPedidos, 'Obligaciones legales y contables'],
    ['Datos de las entregas (fotos de evidencia, ubicación al entregar, cobro) e historial de cambios del pedido', 'El mismo plazo que el pedido al que pertenecen', 'Aclaraciones, control interno y obligaciones contables'],
    ['Datos para finalidades voluntarias', 'Hasta que se revoque el consentimiento', 'Consentimiento'],
    ['Identificadores de sesión persistente ("recordarme")', 'Hasta su expiración o cierre de sesión', 'Funcionalidad temporal'],
]) ?>
<p>
    Concluido el plazo, los datos se bloquean (se conservan solo para atender responsabilidades legales) y
    posteriormente se suprimen o anonimizan. Algunos registros "eliminados" pueden permanecer almacenados por un
    tiempo hasta su depuración definitiva, la cual se realizará conforme a esta tabla.
</p>

<h2 id="s10">10. Medidas de seguridad</h2>
<p>
    El Responsable mantiene medidas de seguridad administrativas, técnicas y físicas para proteger los datos contra
    daño, pérdida, alteración, destrucción o acceso, uso o tratamiento no autorizado, entre ellas:
</p>
<h3>Técnicas</h3>
<ul>
    <li>Almacenamiento de contraseñas únicamente de forma protegida.</li>
    <li>Control de acceso por roles y permisos.</li>
    <li>Mecanismos de protección de formularios y solicitudes, y validación de la información ingresada.</li>
    <li>Registro de accesos para trazabilidad.</li>
    <li>Comunicación cifrada.</li>
    <li>Respaldos periódicos de la información.</li>
</ul>
<h3>Administrativas</h3>
<ul>
    <li>Acceso al Sistema limitado a personal autorizado y obligación de confidencialidad.</li>
    <li>Revisión periódica de usuarios activos y permisos.</li>
    <li>Procedimiento de atención de incidentes.</li>
</ul>
<p>
    <strong>Vulneraciones de seguridad:</strong> si ocurre una vulneración que afecte de forma significativa tus
    derechos patrimoniales o morales, el Responsable te lo informará sin dilación para que puedas tomar las medidas
    correspondientes.
</p>

<h2 id="s11">11. Derechos de las personas titulares (derechos ARCO)</h2>
<p>Tienes derecho a:</p>
<ul>
    <li><strong>Acceso:</strong> conocer qué datos personales tenemos de ti, para qué los usamos y cómo los tratamos.</li>
    <li><strong>Rectificación:</strong> solicitar la corrección de tus datos cuando sean inexactos o estén incompletos.</li>
    <li><strong>Cancelación:</strong> solicitar que se supriman tus datos cuando consideres que no se requieren para las finalidades señaladas, o que se han tratado en contravención de la ley. Procederá el bloqueo previo cuando sea necesario conservarlos por obligación legal.</li>
    <li><strong>Oposición:</strong> oponerte al tratamiento de tus datos para fines específicos, o exigir que cese cuando exista causa legítima.</li>
</ul>
<p>El ejercicio de estos derechos es gratuito, salvo costos de reproducción o envío cuando se solicite la entrega de copias.</p>
<h3>¿Cómo ejercerlos?</h3>
<p>Envía una solicitud a <?= esc($cfg->contactoCorreo) ?> o preséntala en <?= esc($cfg->domicilio) ?>, indicando:</p>
<ol>
    <li>Nombre de la persona titular y medio para recibir respuesta.</li>
    <li>Documentos que acrediten tu identidad (o la representación legal, en su caso).</li>
    <li>El derecho específico que deseas ejercer y la descripción clara de los datos a los que se refiere tu solicitud.</li>
    <li>Cualquier elemento que facilite localizar tus datos.</li>
    <li>Si solicitas rectificación, los documentos que sustenten la corrección.</li>
</ol>
<p>
    El Responsable te dará respuesta dentro de los plazos previstos en la ley <?= esc($cfg->plazoRespuestaArco) ?>,
    indicando si la solicitud es procedente y, en su caso, haciéndola efectiva.
</p>

<h2 id="s12">12. Revocación del consentimiento y limitación del uso o divulgación</h2>
<p>
    Puedes revocar en cualquier momento el consentimiento otorgado para finalidades voluntarias, o solicitar que
    dejemos de usar tus datos para fines promocionales, mediante solicitud a <?= esc($cfg->contactoCorreo) ?>
    o desde <a href="<?= site_url('privacidad/preferencias') ?>">Mis preferencias de privacidad</a> en tu cuenta.
    No podrá darse efecto retroactivo a la revocación, y ciertos datos deberán conservarse por obligación legal aun
    después de revocar.
</p>
<p>
    También puedes solicitar la inclusión de tu información en el listado de exclusión interno del Responsable para
    no recibir comunicaciones promocionales.
</p>

<h2 id="s13">13. Datos de menores de edad</h2>
<p>
    El Sistema no está dirigido a menores de edad y el Responsable no recaba intencionalmente datos de menores. Si se
    detecta que una cuenta o un registro corresponde a un menor sin autorización de su madre, padre o tutor, se
    procederá a su cancelación.
</p>

<h2 id="s14">14. Cambios al aviso de privacidad</h2>
<p>
    Este aviso puede modificarse por cambios legislativos, nuevas funciones del Sistema o cambios en nuestras
    prácticas. Las modificaciones se publicarán en <a href="<?= site_url('aviso-de-privacidad') ?>"><?= esc(site_url('aviso-de-privacidad')) ?></a>
    (enlazado desde la pantalla de inicio de sesión) y, cuando impliquen nuevas finalidades que requieran
    consentimiento, se solicitará nuevamente. La versión vigente se identifica con la fecha y el número de versión
    al inicio de este documento.
</p>

<h2 id="s15">15. Autoridad competente</h2>
<p>
    Si consideras que tu derecho a la protección de datos personales ha sido vulnerado, o que existe una respuesta
    insatisfactoria a tu solicitud, puedes acudir a la autoridad en la materia, que actualmente es la Secretaría
    Anticorrupción y Buen Gobierno, conforme a la LFPDPPP vigente desde marzo de 2025.
</p>

<h2 id="s16">16. Aceptación</h2>
<p>
    Al proporcionar tus datos, registrarte en el Sistema, o realizar un pedido, reconoces que se te puso a
    disposición este aviso de privacidad. Para las finalidades voluntarias deberás manifestar tu consentimiento
    conforme al <a href="<?= site_url('aviso-de-privacidad/consentimiento') ?>">formato que acompaña a este aviso</a>.
</p>

</div>
</article>
</div>
<?= $this->endSection() ?>

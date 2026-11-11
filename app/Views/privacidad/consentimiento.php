<?php
/**
 * Aviso y Formato de Consentimiento — FRUVER 2.0 (texto íntegro).
 * El formato de la sección 9 se llena en el registro, en la aceptación del aviso y en
 * "Mis preferencias de privacidad"; aquí se describe y se enlaza.
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
$cols = ['Dato', '¿Para qué lo pedimos?', '¿Por qué es necesario?'];
?>
<?= $this->extend('privacidad/layout') ?>

<?= $this->section('title') ?>Aviso de Consentimiento<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="pv-contenedor">
<article class="card pv-doc">
<div class="card-body">

<h1 class="card-title">Aviso y Formato de Consentimiento para el Tratamiento de Datos Personales — FRUVER 2.0</h1>
<p class="pv-meta">
    Versión: <?= esc($cfg->version) ?> | Fecha de última actualización: <?= esc($cfg->fechaActualizacion) ?> |
    Documento complementario del <a href="<?= site_url('aviso-de-privacidad') ?>">Aviso de Privacidad Integral</a> de FRUVER 2.0
</p>

<h2>1. Propósito de este documento</h2>
<p>Este documento tiene tres objetivos:</p>
<ol>
    <li>Explicarte, en lenguaje claro, qué datos personales vas a entregar al utilizar FRUVER 2.0 y por qué los necesitamos.</li>
    <li>Distinguir entre los usos de tus datos que son indispensables (no requieren que marques ninguna casilla) y los usos opcionales (solo ocurrirán si tú los autorizas expresamente).</li>
    <li>Dejar constancia de tu decisión, que podrás modificar o revocar en cualquier momento.</li>
</ol>

<h2>2. Identificación del responsable</h2>
<p>
    <?= esc($cfg->responsable) ?>, con domicilio en <?= esc($cfg->domicilio) ?>, correo de contacto
    <?= esc($cfg->contactoCorreo) ?>, teléfono <?= esc($cfg->contactoTelefono) ?>, en adelante "el Responsable".
</p>

<h2>3. Datos personales que se tratan y su justificación</h2>
<p>A continuación se resume qué información se recaba según tu relación con el Sistema. El detalle completo está en el Aviso de Privacidad Integral.</p>

<h3>3.1. Si eres CLIENTE</h3>
<?= $tabla($cols, [
    ['Nombre completo', 'Identificarte y asociarte a tus pedidos', 'No se puede registrar un pedido sin saber quién lo hace'],
    ['Teléfono', 'Confirmar tu pedido, coordinar la entrega y avisarte de cambios', 'Los productos son perecederos; la comunicación inmediata evita pérdidas'],
    ['Correo electrónico', 'Enviarte confirmaciones o comprobantes', 'Dejar constancia de tu pedido'],
    ['Domicilio o dirección de entrega y referencias', 'Entregar tu pedido', 'Sin dirección no es posible la entrega a domicilio'],
    ['Productos, cantidades, montos, fechas y estado de tus pedidos', 'Preparar y entregar el pedido, cobrar el monto correcto, atender aclaraciones y llevar registro de ventas', 'Cumplimiento de la relación comercial y obligaciones fiscales y contables'],
    ['Número de compras (cliente preferencial)', 'Identificar automáticamente a los clientes frecuentes para proponerles precio preferencial y prioridad de entrega', 'Solo te da beneficios; el personal lo revisa y puedes pedir que una persona revise tu clasificación'],
    ['Datos de la entrega: ubicación del domicilio en el mapa, hora estimada de llegada, foto de evidencia y forma de pago', 'Planear la ruta, avisarte cuándo llega tu pedido y comprobar la entrega', 'Llegar a tiempo con un producto perecedero y atender aclaraciones'],
]) ?>

<h3>3.2. Si eres USUARIO INTERNO (administrador, operador o repartidor)</h3>
<?= $tabla($cols, [
    ['Nombre de usuario', 'Identificarte dentro del Sistema', 'Asignar responsabilidad a cada acción registrada'],
    ['Correo electrónico', 'Iniciar sesión y recuperar el acceso', 'Es el identificador único de tu cuenta'],
    ['Contraseña (guardada de forma protegida)', 'Verificar que eres tú quien accede', 'Proteger tu cuenta; nadie, incluido el Responsable, puede ver tu contraseña'],
    ['Rol y permisos', 'Definir qué funciones puedes usar', 'Que cada persona acceda solo a lo que necesita'],
    ['IP, navegador, fecha, hora y resultado de cada inicio de sesión', 'Detectar accesos indebidos y atender incidentes', 'Seguridad y trazabilidad'],
    ['Registro de las acciones que realizas (por ejemplo, quién capturó una entrada o un pedido)', 'Control interno y corrección de errores', 'Trazabilidad de la operación'],
    ['Si eres repartidor: nombre, teléfono, domicilio y notas', 'Asignarte entregas y contactarte durante el reparto', 'Organizar y dar seguimiento a las entregas'],
    ['Si eres repartidor: ubicación GPS de tu teléfono solo al registrar una entrega, y fotos de evidencia', 'Confirmar dónde y cuándo se entregó cada pedido', 'Comprobación de la entrega; durante la ruta tu ubicación solo se ve en tu mapa y no se guarda'],
]) ?>

<h3>3.3. Datos técnicos de cualquier persona que use el Sistema</h3>
<ul>
    <li>Cookie de sesión y elementos de seguridad de la sesión, necesarios para que el Sistema funcione y sea seguro.</li>
    <li>Dirección IP y datos del navegador registrados por el Sistema.</li>
</ul>

<h3>3.4. Datos que NO recabamos</h3>
<ul>
    <li>No recabamos datos personales sensibles (salud, origen étnico, religión, afiliación política o sindical, orientación sexual, datos biométricos, entre otros).</li>
    <li>No almacenamos números de tarjeta bancaria ni claves de pago.</li>
    <li>No recabamos datos de menores de edad.</li>
</ul>

<h2>4. Finalidades que NO requieren tu consentimiento adicional (necesarias)</h2>
<p>Usaremos tus datos para lo siguiente, porque sin ello no podemos prestarte el servicio ni cumplir con la ley. Aunque no marques ninguna casilla, tus datos se tratarán para estos fines:</p>
<ol>
    <li>Crear y administrar tu cuenta y autenticar tu identidad (usuarios internos).</li>
    <li>Registrar, procesar, confirmar, preparar y entregar tus pedidos (clientes).</li>
    <li>Calcular montos (incluido el precio preferencial de clientes frecuentes) y llevar el registro de ventas y existencias asociado a tus pedidos.</li>
    <li>Atender aclaraciones, cancelaciones, correcciones o reclamaciones.</li>
    <li>Garantizar la seguridad del Sistema y prevenir accesos no autorizados.</li>
    <li>Cumplir obligaciones fiscales, contables y requerimientos de autoridades competentes.</li>
</ol>

<h2>5. Finalidades que SÍ requieren tu consentimiento (voluntarias)</h2>
<p>
    Los siguientes usos no son indispensables. Solo los realizaremos si los autorizas marcando la casilla
    correspondiente. Puedes negarte a cualquiera de ellos sin que ello afecte tu acceso al Sistema, tus pedidos ni la
    calidad de la atención que recibes.
</p>
<?php
$filasV = [];
foreach ($cfg->finalidades as $f) {
    $filasV[] = [$f['codigo'], $f['titulo'], $f['datos'], $f['justificacion'], $f['medio']];
}
?>
<?= $tabla(['N.º', 'Finalidad voluntaria', 'Datos involucrados', 'Justificación', 'Medio'], $filasV) ?>

<h2>6. Transferencias y remisiones de tus datos</h2>
<p>El Responsable no vende ni cede tus datos a terceros para fines comerciales propios. Pueden tener acceso a ellos, solo para los fines indicados:</p>
<ul>
    <li>Proveedores de alojamiento y respaldo de la información del Sistema.</li>
    <li>Proveedores de correo o mensajería.</li>
    <li>Servicio de reparto externo: <?= esc($cfg->proveedorReparto) ?>.</li>
    <li>Proveedores de mapas y rutas (Google Maps Platform u openrouteservice, con servidores fuera de México): reciben la dirección de entrega, sin tu nombre ni teléfono, para ubicarla y planear la ruta.</li>
    <li>Autoridades, cuando exista un requerimiento legal fundado y motivado.</li>
</ul>
<p>Si en algún momento se requiriera compartir tus datos con un tercero para una finalidad distinta, te pediremos tu consentimiento previamente.</p>

<h2>7. Tus derechos y cómo revocar tu consentimiento</h2>
<ul>
    <li>
        Puedes modificar o retirar tu consentimiento para cualquiera de las finalidades V1–V4 en cualquier momento,
        sin costo, escribiendo a <?= esc($cfg->contactoCorreo) ?> o desde la sección
        <a href="<?= site_url('privacidad/preferencias') ?>">Mis preferencias de privacidad</a> de tu cuenta.
    </li>
    <li>Atenderemos tu solicitud en un plazo razonable <?= esc($cfg->plazoRevocacion) ?> y dejaremos de usar tus datos para esa finalidad. Los cambios hechos desde "Mis preferencias de privacidad" aplican de inmediato.</li>
    <li>La revocación no tiene efectos retroactivos: no invalida los usos realizados antes de que la solicites. Algunos datos deberán conservarse por obligación legal, pero se limitará su uso a ese fin.</li>
    <li>Conservas además tus derechos de acceso, rectificación, cancelación y oposición (ARCO), que se ejercen conforme al Aviso de Privacidad Integral.</li>
    <li>Si consideras que se vulneraron tus derechos, puedes acudir a la Secretaría Anticorrupción y Buen Gobierno, autoridad en materia de protección de datos personales en posesión de particulares.</li>
</ul>

<h2>8. Consecuencias de no otorgar el consentimiento</h2>
<ul>
    <li>Si no autorizas las finalidades V1–V4: seguirás usando el Sistema y realizando pedidos con normalidad. Simplemente no recibirás promociones, recordatorios, encuestas ni recomendaciones personalizadas.</li>
    <li>Si no proporcionas los datos necesarios (por ejemplo, teléfono o dirección de entrega): no será posible registrar o entregar tu pedido, o crear tu cuenta, porque son indispensables para la prestación del servicio.</li>
</ul>

<h2>9. Formato de consentimiento</h2>
<p>
    En FRUVER el formato se llena en línea: al <a href="<?= site_url('register') ?>">crear tu cuenta</a>, al aceptar una
    versión nueva de este aviso, y en cualquier momento desde
    <a href="<?= site_url('privacidad/preferencias') ?>">Mis preferencias de privacidad</a>. Contiene:
</p>
<ul>
    <li><strong>9.1. Declaración de conocimiento (obligatoria para continuar):</strong> "He leído y comprendido el Aviso de Privacidad de FRUVER 2.0 y sé que mis datos serán tratados para las finalidades necesarias descritas en la sección 4 de este documento."</li>
    <li><strong>9.2. Consentimiento para finalidades voluntarias (opcional; marca solo las que aceptes):</strong>
        <ul>
            <?php foreach ($cfg->finalidades as $f): ?>
                <li><?= esc($f['codigo']) ?>. <?= esc($f['autorizo']) ?></li>
            <?php endforeach; ?>
        </ul>
    </li>
    <li><strong>9.3. Canales preferidos para comunicaciones (solo si aceptaste V1–V4):</strong> <?= esc(implode(', ', $cfg->canales)) ?>.</li>
    <li><strong>9.4. Confirmación:</strong> "Declaro que los datos proporcionados son verdaderos, que soy mayor de edad y que puedo modificar o revocar mi consentimiento en cualquier momento escribiendo a <?= esc($cfg->contactoCorreo) ?>."</li>
</ul>
<p>
    <strong>Aceptación electrónica:</strong> al marcar la casilla de declaración y pulsar el botón "Aceptar y continuar"
    (o "Aceptar y crear cuenta"), la persona titular manifiesta su consentimiento por medios electrónicos. El Sistema
    registra la fecha y hora, la versión del aviso aceptada y la dirección IP, como constancia de la manifestación de
    voluntad. El nombre y el tipo de relación (cliente o usuario interno) se toman de tu cuenta.
</p>

<h2>10. Cambios a este documento</h2>
<p>
    Si se agregan nuevas finalidades voluntarias o se modifican las existentes, se te solicitará un nuevo
    consentimiento antes de aplicarlas. La versión vigente se identifica por el número y la fecha al inicio de este documento.
</p>

</div>
</article>
</div>
<?= $this->endSection() ?>

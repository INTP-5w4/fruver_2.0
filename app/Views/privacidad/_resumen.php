<?php
/**
 * Aviso de privacidad simplificado: se muestra antes de crear la cuenta y al aceptar
 * una versión nueva. El texto íntegro está en /aviso-de-privacidad.
 *
 * @var \Config\Privacidad $cfg
 */
?>
<section class="pv-resumen" aria-labelledby="pv-resumen-titulo">
    <h2 id="pv-resumen-titulo">Aviso de privacidad (resumen)</h2>
    <p>
        <strong><?= esc($cfg->responsable) ?></strong> es responsable de los datos personales que se tratan en FRUVER 2.0.
    </p>
    <ul>
        <li><strong>Datos que pedimos:</strong> correo, nombre de usuario y contraseña (guardada de forma protegida) para tu cuenta; si haces pedidos, nombre, teléfono y dirección de entrega. También registramos la IP y el navegador de cada inicio de sesión por seguridad.</li>
        <li><strong>Para qué (necesarias):</strong> crear tu cuenta, registrar, preparar y entregar tus pedidos (ubicamos tu dirección con un proveedor de mapas para planear la ruta), cobrar el monto correcto, atender aclaraciones, proteger el sistema y cumplir obligaciones legales.</li>
        <li><strong>Clientes frecuentes:</strong> el sistema identifica automáticamente a quien compra seguido para ofrecerle precio preferencial y prioridad de entrega. Solo da beneficios y el personal lo revisa.</li>
        <li><strong>Para qué (voluntarias, solo si las marcas):</strong> promociones, recordatorios de recompra, encuestas de satisfacción y recomendaciones personalizadas.</li>
        <li><strong>No recabamos</strong> datos sensibles, datos de tarjetas bancarias ni datos de menores de edad. No vendemos tus datos.</li>
        <li><strong>Tus derechos:</strong> acceso, rectificación, cancelación y oposición (ARCO), y retirar tu consentimiento en cualquier momento escribiendo a <?= esc($cfg->contactoCorreo) ?> o desde "Mis preferencias de privacidad".</li>
    </ul>
    <p class="pv-enlaces">
        Lee el texto completo:
        <a href="<?= site_url('aviso-de-privacidad') ?>" target="_blank" rel="noopener">Aviso de Privacidad Integral</a>
        ·
        <a href="<?= site_url('aviso-de-privacidad/consentimiento') ?>" target="_blank" rel="noopener">Aviso y Formato de Consentimiento</a>
        <span class="pv-version">(versión <?= esc($cfg->version) ?>)</span>
    </p>
</section>

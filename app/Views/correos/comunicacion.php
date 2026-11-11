<?php
/**
 * Correo de una finalidad voluntaria (V1–V3). Solo se envía a quien la autorizó y eligió
 * el correo como canal. El texto ya trae el aviso para darse de baja.
 *
 * @var string                $texto
 * @var array<string,mixed>   $finalidad  definición de Config\Privacidad::$finalidades
 */
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#2B3674;max-width:560px;margin:0 auto;">
    <h1 style="color:#007542;font-size:22px;margin:0 0 12px;">FRUVER</h1>
    <p style="font-size:15px;line-height:1.5;"><?= nl2br(esc($texto)) ?></p>
    <p style="font-size:12px;color:#6b7a99;margin-top:24px;">
        Recibes este correo porque autorizaste "<?= esc($finalidad['titulo']) ?>" (<?= esc($finalidad['codigo']) ?>).
        Puedes retirar tu autorización cuando quieras en
        <a href="<?= esc(site_url('privacidad/preferencias'), 'attr') ?>">Mis preferencias de privacidad</a>.
        Consulta nuestro <a href="<?= esc(site_url('aviso-de-privacidad'), 'attr') ?>">Aviso de Privacidad</a>.
    </p>
</div>

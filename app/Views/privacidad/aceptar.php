<?php
/**
 * Aceptación del Aviso de Privacidad vigente. Aquí llega cualquier cuenta con sesión que
 * no lo haya aceptado (cuentas anteriores al aviso, personal creado desde la consola o
 * una versión nueva del aviso). Ver App\Filters\ConsentimientoGuard.
 *
 * @var \Config\Privacidad          $cfg
 * @var bool                        $esActualizacion
 * @var string|null                 $versionAnterior
 * @var string                      $correo
 * @var array<string,bool|string>   $valores
 * @var array<string,string>        $errores
 */
?>
<?= $this->extend('privacidad/layout') ?>

<?= $this->section('title') ?>Aviso de privacidad<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="pv-contenedor">
    <article class="card">
        <div class="card-body">
            <h1 class="card-title">
                <?= $esActualizacion ? 'Actualizamos nuestro Aviso de Privacidad' : 'Antes de continuar' ?>
            </h1>

            <?php if ($esActualizacion): ?>
                <p>
                    Aceptaste la versión <?= esc((string) $versionAnterior) ?>. La versión vigente es la
                    <strong><?= esc($cfg->version) ?></strong>. Revísala y confirma tu aceptación para seguir usando FRUVER.
                    Conservamos las finalidades voluntarias que ya habías elegido; puedes cambiarlas abajo.
                </p>
            <?php else: ?>
                <p>
                    Para usar FRUVER con tu cuenta necesitamos que leas y aceptes el Aviso de Privacidad.
                    Es un solo paso y podrás cambiar tus preferencias cuando quieras.
                </p>
            <?php endif; ?>

            <?= view('privacidad/_resumen', ['cfg' => $cfg]) ?>

            <form action="<?= site_url('privacidad/aceptar') ?>" method="post">
                <?= csrf_field() ?>

                <?= view('privacidad/_formulario', [
                    'cfg'            => $cfg,
                    'valores'        => $valores,
                    'errores'        => $errores,
                    'conDeclaracion' => true,
                    'correo'         => $correo,
                ]) ?>

                <p class="pv-nota">
                    Al pulsar "Aceptar y continuar" manifiestas tu consentimiento por medios electrónicos.
                    Registramos la fecha y hora, la versión del aviso aceptada y tu dirección IP como constancia.
                </p>

                <div class="pv-acciones">
                    <a class="btn btn-secundario" href="<?= site_url('logout') ?>">No acepto, cerrar sesión</a>
                    <button type="submit" class="btn btn-primary">Aceptar y continuar</button>
                </div>
            </form>
        </div>
    </article>
</div>
<?= $this->endSection() ?>

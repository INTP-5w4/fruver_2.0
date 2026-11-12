<?php
/**
 * Mis preferencias de privacidad (cualquier rol con sesión).
 *
 * @var \Config\Privacidad                  $cfg
 * @var array<string,mixed>                 $vigente     constancia más reciente
 * @var list<array<string,mixed>>           $historial
 * @var string                              $correo
 * @var array<string,bool|string>           $valores
 * @var array<string,string>                $errores
 * @var list<string>                        $pendientes  datos del aviso sin completar (solo personal)
 */
$origenes = [
    'registro'     => 'Registro de la cuenta',
    'aceptacion'   => 'Aceptación del aviso',
    'preferencias' => 'Cambio de preferencias',
    'revocacion'   => 'Revocación de todas',
];
$fecha = static fn (?string $f): string => $f ? date('d/m/Y H:i', strtotime($f)) : '';
$autorizadas = [];
foreach ($cfg->finalidades as $clave => $f) {
    if ((int) ($vigente[$clave] ?? 0) === 1) {
        $autorizadas[] = $f['codigo'];
    }
}
?>
<?= $this->extend('privacidad/layout') ?>

<?= $this->section('title') ?>Mis preferencias de privacidad<?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="pv-contenedor">

    <?php if ($pendientes !== []): ?>
        <div class="alert alert-info" role="note">
            <strong>Pendiente para el negocio:</strong> el aviso publicado aún muestra datos de ejemplo
            (<?= esc(implode(', ', $pendientes)) ?>).
            <?php if (es_tecnico()): ?>Complétalos en <code>app/Config/Privacidad.php</code>.<?php else: ?>Pide al administrador del sistema que los complete.<?php endif; ?>
        </div>
    <?php endif; ?>

    <article class="card">
        <div class="card-body">
            <h1 class="card-title">Mis preferencias de privacidad</h1>

            <dl class="pv-estado">
                <div>
                    <dt>Aviso aceptado</dt>
                    <dd>Versión <?= esc((string) $vigente['version_aviso']) ?></dd>
                </div>
                <div>
                    <dt>Finalidades voluntarias autorizadas</dt>
                    <dd><?= $autorizadas === [] ? 'Ninguna' : esc(implode(', ', $autorizadas)) ?></dd>
                </div>
                <div>
                    <dt>Último cambio</dt>
                    <dd><?= esc($fecha($vigente['created_at'] ?? null)) ?></dd>
                </div>
            </dl>

            <form action="<?= site_url('privacidad/preferencias') ?>" method="post">
                <?= csrf_field() ?>

                <?= view('privacidad/_formulario', [
                    'cfg'            => $cfg,
                    'valores'        => $valores,
                    'errores'        => $errores,
                    'conDeclaracion' => false,
                    'correo'         => $correo,
                ]) ?>

                <p class="pv-nota">
                    Los cambios aplican desde que los guardas; la revocación no tiene efectos retroactivos.
                </p>

                <div class="pv-acciones">
                    <button type="submit" class="btn btn-primary">Guardar preferencias</button>
                </div>
            </form>

            <?php if ($autorizadas !== []): ?>
                <form action="<?= site_url('privacidad/revocar') ?>" method="post" class="pv-revocar">
                    <?= csrf_field() ?>
                    <p>¿Prefieres no recibir nada? Retira con un clic tu consentimiento para todas las finalidades voluntarias.</p>
                    <button type="submit" class="btn btn-secundario">Retirar todos mis consentimientos</button>
                </form>
            <?php endif; ?>
        </div>
    </article>

    <article class="card">
        <div class="card-body">
            <h2 class="pv-subtitulo">Tus derechos (ARCO)</h2>
            <p>
                Puedes pedir <strong>acceso</strong> a tus datos, su <strong>rectificación</strong>, su
                <strong>cancelación</strong> u <strong>oponerte</strong> a su uso. Escribe a
                <strong><?= esc($cfg->contactoCorreo) ?></strong>, llama al <?= esc($cfg->contactoTelefono) ?>
                (<?= esc($cfg->contactoHorario) ?>) o acude a <?= esc($cfg->domicilio) ?>.
                Indica tu nombre, un medio para responderte, el derecho que quieres ejercer y un documento que acredite tu identidad.
                Para cerrar tu cuenta y cancelar tus datos también se usa este medio.
            </p>
            <p>
                Consulta el <a href="<?= site_url('aviso-de-privacidad') ?>">Aviso de Privacidad Integral</a> y el
                <a href="<?= site_url('aviso-de-privacidad/consentimiento') ?>">Aviso de Consentimiento</a>.
            </p>
        </div>
    </article>

    <article class="card">
        <div class="card-body">
            <h2 class="pv-subtitulo">Historial de tus decisiones</h2>
            <div class="pv-tabla">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Movimiento</th>
                            <th scope="col">Versión</th>
                            <?php foreach ($cfg->finalidades as $f): ?>
                                <th scope="col"><?= esc($f['codigo']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $fila): ?>
                            <tr>
                                <td><?= esc($fecha($fila['created_at'] ?? null)) ?></td>
                                <td><?= esc($origenes[$fila['origen']] ?? (string) $fila['origen']) ?></td>
                                <td><?= esc((string) $fila['version_aviso']) ?></td>
                                <?php foreach (array_keys($cfg->finalidades) as $clave): ?>
                                    <td><?= (int) $fila[$clave] === 1 ? 'Sí' : 'No' ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </article>
</div>
<?= $this->endSection() ?>

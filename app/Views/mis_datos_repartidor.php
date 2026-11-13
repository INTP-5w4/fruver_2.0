<?php
/**
 * El repartidor completa o corrige su propia ficha (Repartidores::mis_datos).
 *
 * @var array $rep        su fila de `repartidor`
 * @var bool  $incompleta ¿le faltan datos obligatorios?
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis datos · Fruver</title>
</head>
<body>
<?php include 'Header.php'; ?>
    <div class="formulario-pagina">

        <header class="modal-header">
            <h2><?= $incompleta ? 'Completa tus datos de repartidor' : 'Mis datos de repartidor' ?></h2>
        </header>

        <form action="<?= base_url('repartidor/mis-datos') ?>" method="post" class="modal-form">
            <?= csrf_field() ?>

            <?php if ($incompleta): ?>
                <div class="aviso aviso-info" role="status">
                    <i class="fa-solid fa-id-badge" aria-hidden="true"></i>
                    <span>Ya tienes el rol de repartidor. Antes de ver tus entregas, llena tus datos:
                        el personal los usa para contactarte y para planear tus rutas (tipo de vehículo y cuánto rinde).</span>
                </div>
            <?php endif; ?>

            <?= view('partials/aviso_formulario') ?>

            <?= view('partials/form_repartidor', ['rep' => $rep, 'sufijo' => '_mio']) ?>

            <p class="opcional" style="margin-top:12px;">
                Tus datos se tratan conforme al <a href="<?= base_url('aviso-de-privacidad') ?>" target="_blank" rel="noopener">Aviso de Privacidad</a> (sección de repartidores).
            </p>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar"><i class="fa-solid fa-check" aria-hidden="true"></i> Guardar mis datos</button>
                <?php if (! $incompleta): ?>
                    <a href="<?= base_url('repartidor/entregas') ?>" class="btn-cancelar btn-enlace">Cancelar</a>
                <?php endif; ?>
            </footer>
        </form>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Pedido</title>
</head>
<body>
<?php include 'Header.php'; ?>
    <div class="formulario-pagina">

        <header class="modal-header">
            <h2>Registrar Pedido</h2>
        </header>

        <form action="<?= base_url('guarda_pedido') ?>" method="post" class="modal-form">
<?= csrf_field() ?>
<?= campo_envio_unico() ?>

            <label for="nuevo-pedido-fecha"><b>Fecha</b></label>
            <input id="nuevo-pedido-fecha" type="date" name="fecha" class="modal-input" required>

            <label for="nuevo-pedido-id-cliente"><b>Cliente</b></label>
            <select id="nuevo-pedido-id-cliente" name="id_cliente" class="modal-input" required>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= esc($c['id']) ?>"><?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="nuevo-pedido-id-repartidor"><b>Repartidor</b></label>
            <select id="nuevo-pedido-id-repartidor" name="id_repartidor" class="modal-input" required>
                <?php foreach ($repartidores as $r): ?>
                    <option value="<?= esc($r['id']) ?>"><?= esc($r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat']) ?></option>
                <?php endforeach; ?>
            </select>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
            </footer>

        </form>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Pedido</title>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
</head>
<body>
    <div class="formulario-pagina">

        <header class="modal-header">
            <h2>Registrar Pedido</h2>
        </header>

        <form action="<?= base_url('guarda_pedido') ?>" method="post" class="modal-form">

            <label><b>Fecha</b></label>
            <input type="date" name="fecha" class="modal-input" required>

            <label><b>Cliente</b></label>
            <select name="id_cliente" class="modal-input" required>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= $c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat'] ?></option>
                <?php endforeach; ?>
            </select>

            <label><b>Repartidor</b></label>
            <select name="id_repartidor" class="modal-input" required>
                <?php foreach ($repartidores as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= $r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat'] ?></option>
                <?php endforeach; ?>
            </select>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
            </footer>

        </form>
    </div>
</body>
</html>
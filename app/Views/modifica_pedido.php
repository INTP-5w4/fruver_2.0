<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea pedido</title>
</head>
<body>
    <form action="<?= base_url('modifica_pedido') ?>" method="post">
        <input type="hidden" name="id" value="<?= $pedidos['id'] ?>">
        <label for="fecha">Fecha</label><br>
        <input type="date" name="fecha" id="" value="<?= $pedidos['fecha']?>"><br>

        <label for="id_cliente">Cliente</label><br>
        <select name="id_cliente">
            <?php foreach ($clientes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($c['id'] == $pedidos['id_cliente']) ? 'selected' : '' ?>>
        <?= $c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat'] ?>
    </option>
<?php endforeach; ?>
</select><br>
                
        <label for="id_repartidor">Repartidor</label><br>
        <select name="id_repartidor" id="">

            <?php foreach ($repartidores as $r): ?>
                <option value="<?= $r['id'] ?>" <?= ($r['id'] == $pedidos['id_cliente']) ? 'selected' :'' ?>>
                    <?= $r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat'] ?></option>
                <?php endforeach; ?>
        </select><br>
        <input type="submit" value="Enviar">
    </form>
    
</body>
</html>
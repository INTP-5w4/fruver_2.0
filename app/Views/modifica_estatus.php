<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica estatus</title>
</head>
<body>
    <form action="<?= base_url('modifica_estatus') ?>" method="post">
        <input type="hidden" name="id" value="<?= $estatus['id'] ?>">
        <label for="">Estado</label><br>
        <select name="edo" id="">
            <option value="<?= esc('pedido_realizado') ?>" <?= ($estatus['estado'] == 'pedido_realizado') ? 'selected' : '' ?>>Pedido realizado</option>
            <option value="<?= esc('pedido_confirmado') ?>" <?= ($estatus['estado'] == 'pedido_confirmado') ? 'selected' : '' ?>>Pedido confirmado</option>
            <option value="<?= esc('pedido_en_transito') ?>" <?= ($estatus['estado'] == 'pedido_en_transito') ? 'selected' : '' ?>>Pedido en tránsito</option>
            <option value="<?= esc('pedido_entregado') ?>" <?= ($estatus['estado'] == 'pedido_entregado') ? 'selected' : '' ?>>Pedido entregado</option>
            <option value="<?= esc('pedido_a_credito') ?>" <?= ($estatus['estado'] == 'pedido_a_credito') ? 'selected' : '' ?>>Pedido a crédito</option>
            <option value="<?= esc('pedido_pagado') ?>" <?= ($estatus['estado'] == 'pedido_pagado') ? 'selected' : '' ?>>Pedido pagado</option>
            <option value="<?= esc('pedido_cancelado') ?>" <?= ($estatus['estado'] == 'pedido_cancelado') ? 'selected' : '' ?>>Pedido cancelado</option>
        </select><br>
        <label for="">Fecha</label><br>
        <input type="date" name="fecha" value="<?= $estatus['fecha'] ?>"><br>
        <label for="">ID Pedido</label><br>
        <input type="number" name="id_pedido" value="<?= $estatus['id_pedido'] ?>"><br>
        <input type="submit" value="Enviar">
        <br>
    </form>
    
</body>
</html>
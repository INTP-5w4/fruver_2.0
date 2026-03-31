<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea estatus</title>
</head>
<body>
    <form action="<?= base_url('guarda_estatus')?>" method="post">
        <label for="estado">Estado:</label><br>
        <select name="edo" id="">
            <option value="pedido_realizado">Pedido realizado</option>
            <option value="pedido_confirmado">Pedido confirmado</option>
            <option value="pedido_en_transito">Pedido en tránsito</option>
            <option value="pedido_entregado">Pedido entregado</option>
            <option value="pedido_a_credito">Pedido a crédito</option>
            <option value="pedido_pagado">Pedido pagado</option>
            <option value="pedido_cancelado">Pedido cancelado</option>
        </select><br>
        <label for="fecha">Fecha:</label><br>
        <input type="timestamp" name="fecha" id="" value="<?= $timestamp ?>"><br>
        
        <label for="id_pedido">ID Pedido:</label><br>
        <select name="id_pedido" id="">
            <?php foreach($pedidos as $p): ?>
                <option value="<?= $p['id'] ?>"><?= $p['id'] ?></option>
            <?php endforeach; ?>
        </select><br>
        <input type="submit" value="Enviar">
    </form>
</body>
</html>
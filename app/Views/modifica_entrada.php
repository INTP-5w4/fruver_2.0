<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica entrada</title>
</head>
<body>
    <form action="<?= base_url('modifica_entrada')?>" method="post">
        <input type="hidden" name="id" value="<?= $entradas['id'] ?>">
        <label>Fecha de entrada</label><br>
        <input type="date" name="f_ent" id="" value="<?= $entradas['fecha'] ?>"><br>
        
        <label>Fecha de caducidad</label><br>
        <input type="date" name="f_cad" id=""value="<?= $entradas['fecha_cad'] ?>"><br>
        
        <label>Cantidad</label><br>
        <input type="number" name="cant" id="" value="<?= $entradas['cantidad'] ?>"><br>
        
        <label>Unidad de compra</label><br>
        <select name="u_com" id="">
            <option value="<?= $entradas['u_compra'] ?>" selected><?= $entradas['u_compra'] ?></option>
            <option value="Caja">Caja</option>
            <option value="Arpilla">Arpilla</option>
            <option value="Bulto">Bulto</option>
            <option value="Tonelada">Tonelada</option>
        </select><br>
        
        <label>Unidad de Venta</label><br>
        <select name="u_ven" id="">
            <option value="<?= $entradas['u_venta'] ?>" selected><?= $entradas['u_venta'] ?></option>
            <option value="Kilogramo">Kilogramo</option>
            <option value="Litro">Litro</option>
            <option value="Caja">Caja</option>
        </select><br>
        <label for="equivalente">Equivalente</label><br>
        <input type="number" name="equi" id="equivalente" value="<?= $entradas['equivalente'] ?>"><br>
        <label for="conversion">Conversion</label><br>
        <input type="number" name="conv" id="conversion" value="<?= $entradas['conversion'] ?>"><br>

        <label>Precio de compra</label><br>
        <input type="number" name="precio_compra" id="" value="<?= $entradas['precio_compra'] ?>"><br>
        
        <label>ID del producto</label><br>
        <select name="id_producto" id="">
            <option value="<?= $entradas['id_producto'] ?>" selected><?= $entradas['id_producto'] ?></option>
            <?php foreach ($productos as $p): ?>
                <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
            <?php endforeach; ?>
        </select><br>

        <input type="submit" value="Enviar">
    </form>
</body>
</html>
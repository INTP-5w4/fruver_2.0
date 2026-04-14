<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Entrada</title>
</head>
<body>
    <form action="<?= base_url('guarda_entrada')?>" method="post">
        <label>Fecha de entrada</label><br>
        <input type="date" name="f_ent" id=""><br>
        
        <label>Fecha de caducidad</label><br>
        <input type="date" name="f_cad" id="" readonly><br>
        
        <label>Cantidad</label><br>
        <input type="number" name="cant" id=""><br>
        
        <label>Unidad de compra</label><br>
        <select name="u_com" id="">
            <option value="Caja">Caja</option>
            <option value="Arpilla">Arpilla</option>
            <option value="Bulto">Bulto</option>
            <option value="Tonelada">Tonelada</option>
        </select><br>
        
        <label>Unidad de Venta</label><br>
        <select name="u_ven" id="">
            <option value="Kilogramo">Kilogramo</option>
            <option value="Litro">Litro</option>
            <option value="Caja">Caja</option>
        </select><br>
        <label for="equivalente">Equivalente</label><br>
        <input type="number" name="equi" id="equivalente"><br>
        
        <label for="conversion">Conversion</label><br>
        <input type="number" name="conv" id="conversion" readonly><br>

        <label>Precio por unidad de compra</label><br>
        <input type="number" name="p_compra" id=""><br>
        
        <label>ID del producto</label><br>
        <select name="id_producto" id="">
            <?php foreach ($productos as $p): ?>
            <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
            <?php endforeach; ?>
        </select><br>

        <input type="submit" value="Enviar">
    </form>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Entrada</title>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
</head>
<body>
    <div class="formulario-pagina">
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
        <header class="modal-header">
            <h2>Modificar Entrada</h2>
        </header>

        <form action="<?= base_url('modifica_entrada') ?>" method="post" class="modal-form">

            <input type="hidden" name="id" value="<?= $entradas['id'] ?>">

            <label><b>Fecha de entrada</b></label>
            <input type="date" name="f_ent" class="modal-input" value="<?= $entradas['fecha'] ?>" required>

            <label><b>Fecha de caducidad</b></label>
            <input type="date" name="f_cad" class="modal-input" value="<?= $entradas['fecha_cad'] ?>" required>

            <label><b>Cantidad</b></label>
            <input type="number" name="cant" class="modal-input" value="<?= $entradas['cantidad'] ?>" required>

            <label><b>Unidad de compra</b></label>
            <select name="u_com" class="modal-input" required>
                <option value="Caja" <?= $entradas['u_compra'] == 'Caja' ? 'selected' : '' ?>>Caja</option>
                <option value="Arpilla" <?= $entradas['u_compra'] == 'Arpilla' ? 'selected' : '' ?>>Arpilla</option>
                <option value="Bulto" <?= $entradas['u_compra'] == 'Bulto' ? 'selected' : '' ?>>Bulto</option>
                <option value="Tonelada" <?= $entradas['u_compra'] == 'Tonelada' ? 'selected' : '' ?>>Tonelada</option>
            </select>

            <label><b>Unidad de venta</b></label>
            <select name="u_ven" class="modal-input" required>
                <option value="Kilogramo" <?= $entradas['u_venta'] == 'Kilogramo' ? 'selected' : '' ?>>Kilogramo</option>
                <option value="Litro" <?= $entradas['u_venta'] == 'Litro' ? 'selected' : '' ?>>Litro</option>
                <option value="Caja" <?= $entradas['u_venta'] == 'Caja' ? 'selected' : '' ?>>Caja</option>
            </select>

            <label><b>Precio de compra</b></label>
            <input type="number" name="precio_compra" class="modal-input" value="<?= $entradas['precio_compra'] ?>" required>

            <label><b>Producto</b></label>
            <select name="id_producto" class="modal-input" required>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $entradas['id_producto'] == $p['id'] ? 'selected' : '' ?>>
                        <?= $p['nombre'] ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
            </footer>

        </form>
    </div>
</body>
</html>
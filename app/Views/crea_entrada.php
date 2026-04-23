<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Entrada</title>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
</head>
<body>
    <div class="formulario-pagina">
<form action="<?= base_url('guarda_entrada')?>" method="post">
        <label>Fecha de entrada</label><br>
        <input type="date" name="f_ent" id=""><br>
        
        <label>Fecha de caducidad</label><br>
        <input type="date" name="f_cad" id=""><br>
        
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
        <header class="modal-header">
            <h2>Registrar Entrada</h2>
        </header>

        <form action="<?= base_url('guarda_entrada') ?>" method="post" class="modal-form">

            <label><b>Fecha de entrada</b></label>
            <input type="date" name="f_ent" class="modal-input" required>

            <label><b>Fecha de caducidad</b></label>
            <input type="date" name="f_cad" class="modal-input">

            <label><b>Cantidad</b></label>
            <input type="number" name="cant" class="modal-input" required>

            <label><b>Unidad de compra</b></label>
            <select name="u_com" class="modal-input" required>
                <option value="Caja">Caja</option>
                <option value="Arpilla">Arpilla</option>
                <option value="Bulto">Bulto</option>
                <option value="Tonelada">Tonelada</option>
            </select>

            <label><b>Unidad de venta</b></label>
            <select name="u_ven" class="modal-input" required>
                <option value="Kilogramo">Kilogramo</option>
                <option value="Litro">Litro</option>
                <option value="Caja">Caja</option>
            </select>

            <label><b>Precio de compra</b></label>
            <input type="number" name="p_compra" class="modal-input" required>

            <label><b>Producto</b></label>
            <select name="id_producto" class="modal-input" required>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                <?php endforeach; ?>
            </select>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
            </footer>

        </form>
    </div>
</body>
</html>
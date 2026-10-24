<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/entrada_rapida.css') ?>">
    <title>Entrada rápida</title>
</head>
<body>
    <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>

<div class="lista-wrapper">

<div class="flash-container">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="w3-panel w3-red w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('error')) ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="er-barra">
    <h2 class="er-titulo">Entrada rápida</h2>
    <a href="<?= base_url('lista_entrada') ?>" class="w3-button w3-border">
        <i class="fa-solid fa-arrow-left"></i> Volver a entradas
    </a>
</div>

<p class="er-ayuda">
    Escribe solo la <b>cantidad</b> de lo que llegó. La unidad de compra y de venta, la equivalencia y los
    precios se copian de la <b>última entrada</b> de cada producto (puedes cambiar los precios si hoy son
    distintos). La caducidad se calcula sola.
    <?= $todos
        ? 'Se muestran todos los productos que ya tienen entradas.'
        : 'Se muestran los productos que más se han vendido en los últimos ' . (int) $dias . ' días.' ?>
</p>

<form method="post" action="<?= base_url('guarda_entrada_rapida') ?>" id="form-rapida" autocomplete="off">
    <?= csrf_field() ?>

    <div class="er-fecha">
        <label for="er-fecha"><b>Fecha de entrada</b></label>
        <input type="date" id="er-fecha" name="fecha" value="<?= esc($hoy, 'attr') ?>" max="<?= esc($hoy, 'attr') ?>"
               class="w3-input w3-border" required>
    </div>

    <?php if ($productos === []): ?>
        <div class="w3-panel w3-pale-yellow w3-border">
            <p>
                <?= $todos
                    ? 'Todavía no hay productos con entradas registradas.'
                    : 'No hay ventas en los últimos ' . (int) $dias . ' días.' ?>
                <?php if (! $todos): ?>
                    <a href="<?= base_url('entrada_rapida?todos=1') ?>">Ver todos los productos con entradas</a>.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="tabla-wrapper">
            <table class="er-tabla">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Última entrada</th>
                        <th>Ventas <?= (int) $dias ?> d</th>
                        <th>Cantidad*</th>
                        <th>Precio compra</th>
                        <th>Precio venta</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $p): $id = (int) $p['id_producto']; ?>
                    <tr>
                        <td><b><?= esc($p['nombre']) ?></b></td>
                        <td class="er-ultima">
                            <?= esc($p['ultima_fecha']) ?><br>
                            <small><?= esc($p['u_compra']) ?> → <?= esc($p['u_venta']) ?> (×<?= esc($p['equivalente'] + 0) ?>)</small>
                        </td>
                        <td>
                            <?= (int) $p['pedidos'] ?> pedido(s)<br>
                            <small><?= esc($p['vendido'] + 0) ?> <?= esc(mb_strtolower((string) $p['u_venta'])) ?></small>
                        </td>
                        <td>
                            <input type="number" inputmode="decimal" name="cant[<?= $id ?>]" class="w3-input w3-border er-cant"
                                   min="0.01" max="99999999" step="0.01" placeholder="<?= esc($p['u_compra'], 'attr') ?>(s)"
                                   aria-label="Cantidad de <?= esc($p['nombre'], 'attr') ?>">
                        </td>
                        <td>
                            <input type="number" inputmode="decimal" name="p_compra[<?= $id ?>]" class="w3-input w3-border er-precio"
                                   min="0" max="99999999" step="0.01" placeholder="<?= esc(number_format((float) $p['precio_compra_u'], 2, '.', ''), 'attr') ?>"
                                   aria-label="Precio de compra de <?= esc($p['nombre'], 'attr') ?>">
                        </td>
                        <td>
                            <input type="number" inputmode="decimal" name="p_venta[<?= $id ?>]" class="w3-input w3-border er-precio"
                                   min="0" max="99999999" step="0.01" placeholder="<?= esc(number_format((float) $p['precio_venta_u'], 2, '.', ''), 'attr') ?>"
                                   aria-label="Precio de venta de <?= esc($p['nombre'], 'attr') ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="er-acciones">
            <button type="submit" id="er-guardar" class="btn-agregar" disabled>
                <i class="fa-solid fa-boxes-packing"></i> Registrar entradas
                <span id="er-contador">(0)</span>
            </button>
            <?php if (! $todos): ?>
                <a href="<?= base_url('entrada_rapida?todos=1') ?>" class="w3-button w3-border">Ver todos los productos</a>
            <?php else: ?>
                <a href="<?= base_url('entrada_rapida') ?>" class="w3-button w3-border">Solo los de mayor venta</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</form>

<?php if ((int) $sinEntrada > 0): ?>
    <p class="er-nota">
        <?= (int) $sinEntrada ?> producto(s) aún no tienen ninguna entrada y no aparecen aquí:
        regístralos una primera vez con <a href="<?= base_url('lista_entrada') ?>">«Nueva Entrada»</a>.
    </p>
<?php endif; ?>

</div>

<?php include 'Footer.php'; ?>

<script src="<?= base_url('js/entrada_rapida.js') ?>"></script>
</body>
</html>

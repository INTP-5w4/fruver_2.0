<?php
/**
 * Mis pedidos (cliente con sesión). No muestra el número de pedido:
 * cada pedido se identifica por su fecha, su estado y sus productos.
 *
 * Reglas de esta vista: no hay <style>, atributos style ni onclick; todo dato dinámico pasa por esc().
 *
 * @var string                       $saludo   nombre para el saludo
 * @var list<array<string,mixed>>    $pedidos  de Modelo_pedido::pedidosDeCliente
 */
$cant = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis pedidos | Fruver</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= base_url('estilos/tienda.css') ?>">
</head>
<body>

<header class="barra">
    <div class="barra-fila">
        <a class="logo" href="<?= base_url('main_page_4') ?>" aria-label="Fruver, inicio">Fruver</a>
        <nav class="cuenta" aria-label="Tu cuenta">
            <span class="cuenta-saludo">Hola, <strong><?= esc($saludo) ?></strong></span>
            <a class="cuenta-enlace" href="<?= base_url('main_page_4') ?>">Tienda</a>
            <a class="cuenta-enlace" href="<?= base_url('logout') ?>">Salir</a>
        </nav>
    </div>
</header>

<main class="catalogo mis-pedidos">
    <h1 class="mis-pedidos-titulo">Mis pedidos</h1>

    <?php if (empty($pedidos)): ?>
        <p class="aviso aviso-info">
            Todavía no has hecho ningún pedido. <a href="<?= base_url('main_page_4') ?>">Ir a la tienda</a>
        </p>
    <?php endif; ?>

    <?php foreach ($pedidos as $p): ?>
        <?php $marca = $p['fecha'] ? strtotime((string) $p['fecha']) : false; ?>
        <article class="mi-pedido">
            <div class="mi-pedido-cab">
                <h2>Pedido<?= $marca ? ' del ' . esc(date('d/m/Y', $marca)) : '' ?></h2>
                <span class="mi-pedido-estado"><?= esc(estado_pedido_texto($p['estado'])) ?></span>
            </div>

            <table class="mi-pedido-tabla">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th class="mi-pedido-importe">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($p['items'] as $item): ?>
                        <tr>
                            <td><?= esc($item['nombre_producto']) ?></td>
                            <td><?= esc($cant($item['cant']) . ' ' . tienda_unidad($item['unidad_venta'])) ?></td>
                            <td class="mi-pedido-importe"><?= esc(tienda_dinero($item['total'] ?? ((float) $item['cant'] * (float) $item['precio_venta']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">Total</th>
                        <th class="mi-pedido-importe"><?= esc(tienda_dinero($p['total'])) ?></th>
                    </tr>
                </tfoot>
            </table>
        </article>
    <?php endforeach; ?>
</main>

<footer class="pie">
    <p>&copy; <?= date('Y') ?> Fruver. Todos los derechos reservados.</p>
    <p><a href="<?= base_url('aviso-de-privacidad') ?>">Aviso de privacidad</a></p>
</footer>

</body>
</html>
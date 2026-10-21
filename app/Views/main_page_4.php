<?php
/**
 * Landing de la tienda (main_page_4).
 *
 * Datos que recibe de Tienda::index
 *   $titulos     categoria => título de la fila
 *   $filas       categoria => lista de productos (una sola consulta, ver Modelo_producto::catalogoAgrupado)
 *   $buscar      texto buscado (puede venir vacío)
 *   $conSesion   ¿hay sesión iniciada?
 *   $esPersonal  ¿es admin/superadmin/developer?
 *   $saludo      nombre para el saludo
 *   $tienePerfil ¿ya tiene datos de contacto guardados (tabla cliente)?
 *   $carrito     estado del carrito (null si no hay sesión)
 *
 * Reglas de esta vista: no hay <style>, atributos style ni onclick; todo dato dinámico
 * pasa por esc(); el JS vive en public/js/tienda.js.
 */
$pedidoOk     = session()->getFlashdata('pedido_ok');
$flashError   = session()->getFlashdata('error');
$avisosRecarga = session()->getFlashdata('aviso_recarga');
$abrirCarrito = (bool) session()->getFlashdata('abrir_carrito');

$totalProductos = 0;
foreach ($filas as $fila) {
    $totalProductos += count($fila);
}

// Configuración para tienda.js. Se entrega en un bloque application/json (no se ejecuta)
// y json_encode con las banderas HEX_* no deja escapar ningún texto del bloque.
$config = [
    'sesion'      => $conSesion,
    'abrir'       => $abrirCarrito,
    'carrito'     => $carrito,
    'csrfHeader'  => csrf_header(),
    'urlLogin'    => base_url('login'),
    'urls'        => [
        'agregar' => base_url('tienda/carrito/agregar'),
        'cambiar' => base_url('tienda/carrito/cambiar'),
        'quitar'  => base_url('tienda/carrito/quitar'),
        'vaciar'  => base_url('tienda/carrito/vaciar'),
    ],
];
$configJson = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <title>Fruver | Frutas, hierbas y verduras</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= base_url('estilos/tienda.css') ?>">
</head>
<body>
<a class="saltar" href="#catalogo">Ir a los productos</a>

<header class="barra">
    <div class="barra-fila">
        <a class="logo" href="<?= base_url('main_page_4') ?>" aria-label="Fruver, inicio">Fruver</a>

        <form class="buscador" method="get" action="<?= base_url('main_page_4') ?>" role="search">
            <label class="solo-lector" for="q">Buscar productos</label>
            <input id="q" type="search" name="q" value="<?= esc($buscar, 'attr') ?>" maxlength="100"
                   placeholder="Busca jitomate, cilantro, mango…" autocomplete="off">
            <button type="submit" aria-label="Buscar">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M15.5 15.5 21 21" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
            </button>
        </form>

        <nav class="cuenta" aria-label="Tu cuenta">
            <?php if ($conSesion): ?>
                <span class="cuenta-saludo">Hola, <strong><?= esc($saludo) ?></strong></span>
                <?php if ($esPersonal): ?>
                    <a class="cuenta-enlace" href="<?= base_url('/') ?>">Panel</a>
                <?php endif; ?>
                <a class="cuenta-enlace" href="<?= base_url('crea_sugerencia') ?>">Sugerencias</a>
                <a class="cuenta-enlace" href="<?= base_url('logout') ?>">Salir</a>
            <?php else: ?>
                <a class="cuenta-enlace" href="<?= base_url('login') ?>">Iniciar sesión</a>
                <a class="cuenta-enlace" href="<?= base_url('register') ?>">Crear cuenta</a>
            <?php endif; ?>
        </nav>

        <?php if ($conSesion): ?>
            <button class="btn-carrito" type="button" data-abrir-carrito aria-label="Abrir carrito">
                <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path d="M3 4h2.4l2.2 10.2a1.6 1.6 0 0 0 1.6 1.3h8.1a1.6 1.6 0 0 0 1.6-1.2L20.4 8H6.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9.6" cy="19.4" r="1.4" fill="currentColor"/><circle cx="17" cy="19.4" r="1.4" fill="currentColor"/></svg>
                <span class="btn-carrito-cuenta" data-cuenta><?= (int) ($carrito['cuenta'] ?? 0) ?></span>
                <span class="btn-carrito-texto">Carrito</span>
            </button>
        <?php endif; ?>
    </div>

    <nav class="categorias" aria-label="Categorías">
        <?php foreach ($titulos as $clave => $titulo): ?>
            <a href="#fila-<?= esc($clave, 'attr') ?>"><?= esc($titulo) ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<div class="avisos" aria-live="polite" data-avisos>
    <?php if (is_array($pedidoOk)): ?>
        <p class="aviso aviso-ok" role="status">
            Pedido #<?= (int) $pedidoOk['id'] ?> registrado por <strong><?= esc(tienda_dinero($pedidoOk['total'])) ?></strong>.
            El personal de Fruver lo revisará para confirmarlo.
        </p>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <p class="aviso aviso-error" role="alert"><?= esc($flashError) ?></p>
    <?php endif; ?>
    <?php if (is_array($avisosRecarga)): ?>
        <p class="aviso aviso-info" role="status">Cargamos tu pedido anterior en el carrito. Revísalo antes de confirmar.</p>
    <?php foreach ($avisosRecarga as $aviso): ?>
        <p class="aviso aviso-info"><?= esc($aviso) ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (! $conSesion): ?>
        <p class="aviso aviso-info">
            Puedes ver todo el catálogo. Para agregar al carrito y hacer un pedido,
            <a href="<?= base_url('login') ?>">inicia sesión</a> o <a href="<?= base_url('register') ?>">crea tu cuenta</a>.
        </p>
    <?php endif; ?>
</div>

<main id="catalogo" class="catalogo">
    <?php if ($buscar !== ''): ?>
        <p class="resultado-busqueda">
            <?= $totalProductos === 0 ? 'Sin resultados' : 'Resultados' ?> para «<strong><?= esc($buscar) ?></strong>».
            <a href="<?= base_url('main_page_4') ?>">Ver todo el catálogo</a>
        </p>
    <?php endif; ?>

    <?php foreach ($titulos as $clave => $titulo): ?>
        <?php $productos = $filas[$clave] ?? []; ?>
        <section class="fila fila-<?= esc($clave, 'attr') ?>" id="fila-<?= esc($clave, 'attr') ?>" aria-labelledby="titulo-<?= esc($clave, 'attr') ?>">
            <div class="fila-cabeza">
                <h2 id="titulo-<?= esc($clave, 'attr') ?>"><?= esc($titulo) ?></h2>
                <p class="fila-conteo"><?= count($productos) ?> <?= count($productos) === 1 ? 'producto' : 'productos' ?></p>
                <?php if (count($productos) > 1): ?>
                    <div class="fila-flechas">
                        <button type="button" data-flecha="-1" aria-label="Ver anteriores de <?= esc($titulo, 'attr') ?>">&#8249;</button>
                        <button type="button" data-flecha="1" aria-label="Ver siguientes de <?= esc($titulo, 'attr') ?>">&#8250;</button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($productos === []): ?>
                <p class="fila-vacia">
                    <?= $buscar !== ''
                        ? 'Ninguna de las ' . esc(mb_strtolower($titulo)) . ' coincide con tu búsqueda.'
                        : 'Por ahora no hay ' . esc(mb_strtolower($titulo)) . ' disponibles. Vuelve pronto.' ?>
                </p>
            <?php else: ?>
                <ul class="pista" tabindex="0" aria-label="<?= esc($titulo, 'attr') ?>">
                    <?php foreach ($productos as $p): ?>
                        <?php
                        $stock   = (int) floor((float) $p['stock']);
                        $agotado = $stock < 1;
                        $foto    = tienda_img_url($p['img']);
                        ?>
                        <li class="producto<?= $agotado ? ' agotado' : '' ?>">
                            <div class="foto">
                                <?php if ($foto !== ''): ?>
                                    <img src="<?= esc($foto, 'attr') ?>" alt="<?= esc($p['nombre'], 'attr') ?>" loading="lazy" width="260" height="200">
                                <?php else: ?>
                                    <span class="inicial" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr((string) $p['nombre'], 0, 1))) ?></span>
                                <?php endif; ?>
                                <?php if ($agotado): ?><span class="sello">Agotado</span><?php endif; ?>
                            </div>

                            <div class="cuerpo">
                                <h3 class="nombre"><?= esc($p['nombre']) ?></h3>
                                <p class="desc"><?= esc($p['descripcion']) ?></p>

                                <p class="etiqueta">
                                    <span class="precio"><?= esc(tienda_dinero($p['precio'])) ?></span>
                                    <span class="unidad">por <?= esc(tienda_unidad($p['unidad'])) ?></span>
                                </p>

                                <?php if (! $agotado && $stock <= 10): ?>
                                    <p class="stock-bajo">Quedan <?= $stock ?></p>
                                <?php endif; ?>

                                <?php if (! $conSesion): ?>
                                    <a class="btn btn-borde" href="<?= base_url('login') ?>">Inicia sesión para pedir</a>
                                <?php elseif ($agotado): ?>
                                    <button class="btn btn-borde" type="button" disabled>No disponible</button>
                                <?php else: ?>
                                    <form class="agregar" method="post" action="<?= base_url('tienda/carrito/agregar') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_producto" value="<?= (int) $p['id'] ?>">
                                        <div class="cantidad" role="group" aria-label="Cantidad de <?= esc($p['nombre'], 'attr') ?>">
                                            <button type="button" class="paso" data-paso="-1" aria-label="Una menos">&minus;</button>
                                            <input type="number" name="cantidad" value="1" min="1" max="<?= $stock ?>" inputmode="numeric" aria-label="Cantidad">
                                            <button type="button" class="paso" data-paso="1" aria-label="Una más">+</button>
                                        </div>
                                        <button class="btn btn-primario" type="submit">Agregar al carrito</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>

<?php if ($conSesion): ?>
<dialog class="panel-carrito" id="panel-carrito" aria-labelledby="carrito-titulo">
    <div class="panel-cabeza">
        <h2 id="carrito-titulo">Tu carrito</h2>
        <button type="button" class="panel-cerrar" data-cerrar-carrito aria-label="Cerrar carrito">&times;</button>
    </div>

    <div class="panel-cuerpo">
        <p class="panel-vacio" data-vacio>Tu carrito está vacío. Agrega productos de las filas de arriba.</p>
        <ul class="lineas" data-lineas></ul>
                <?php if (! empty($pedidosAnteriores)): ?>
            <details class="anteriores">
                <summary>Volver a pedir un pedido anterior</summary>
                <ul class="anteriores-lista">
                    <?php foreach ($pedidosAnteriores as $ped): ?>
                        <li class="anterior">
                            <div class="anterior-datos">
                                <strong>Pedido #<?= (int) $ped['id'] ?></strong>
                                <span class="anterior-fecha"><?= esc($ped['fecha']) ?> · <?= esc(tienda_dinero($ped['total'])) ?></span>
                                <span class="anterior-productos"><?= esc($ped['productos'] ?? '') ?></span>
                            </div>
                            <form method="post" action="<?= base_url('tienda/pedido/' . (int) $ped['id'] . '/recargar') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-borde" type="submit">Volver a pedir</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </div>

    <form class="panel-pie" method="post" action="<?= base_url('tienda/pedido') ?>" data-pedido>
        <?= csrf_field() ?>
        <p class="panel-error" role="alert" data-panel-error hidden></p>

        <?php if (! $tienePerfil): ?>
            <fieldset class="contacto">
                <legend>Tus datos de contacto</legend>
                <p class="contacto-ayuda">Los pedimos una sola vez para que el personal pueda avisarte de tu pedido.</p>
                <label><span class="rotulo">Nombre</span>
                    <input type="text" name="nom" value="<?= esc(old('nom'), 'attr') ?>" maxlength="100" autocomplete="given-name" required>
                </label>
                <label><span class="rotulo">Apellido paterno</span>
                    <input type="text" name="ape_pat" value="<?= esc(old('ape_pat'), 'attr') ?>" maxlength="100" autocomplete="family-name" required>
                </label>
                <label><span class="rotulo">Apellido materno <span class="opcional">(opcional)</span></span>
                    <input type="text" name="ape_mat" value="<?= esc(old('ape_mat'), 'attr') ?>" maxlength="100">
                </label>
                <label><span class="rotulo">Teléfono</span>
                    <input type="tel" name="tel" value="<?= esc(old('tel'), 'attr') ?>" maxlength="12" inputmode="numeric" autocomplete="tel-national" required>
                </label>
            </fieldset>
        <?php endif; ?>

        <div class="total">
            <span>Total</span>
            <strong data-total><?= esc($carrito['total_texto'] ?? '$0.00') ?></strong>
        </div>
        <button class="btn btn-primario btn-grande" type="submit" data-confirmar disabled>Realizar pedido</button>
        <button class="btn-texto" type="button" data-vaciar>Vaciar carrito</button>
    </form>
</dialog>
<?php endif; ?>

<footer class="pie">
    <p>&copy; <?= date('Y') ?> Fruver. Todos los derechos reservados.</p>
</footer>

<script type="application/json" id="tienda-config"><?= $configJson ?></script>
<script src="<?= base_url('js/tienda.js') ?>" defer></script>
</body>
</html>

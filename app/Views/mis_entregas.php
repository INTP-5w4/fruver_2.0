<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= base_url('estilos/rutas.css') ?>">
    <title>Mis entregas · Fruver</title>
</head>
<body>
    <?php include 'Header.php'; ?>

<div class="flash-container">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="w3-panel w3-red"><p><?= esc(session()->getFlashdata('error')) ?></p></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('mensaje')): ?>
        <div class="w3-panel w3-green"><p><?= esc(session()->getFlashdata('mensaje')) ?></p></div>
    <?php endif; ?>
</div>

<main class="repartidor-pagina">
    <div class="repartidor-cabeza">
        <h1>Mis entregas</h1>
    </div>

    <?php if (! empty($rutasPendientes)): ?>
        <a href="<?= base_url('repartidor/ruta') ?>" class="aviso-ruta">
            <i class="fa-solid fa-route" aria-hidden="true"></i>
            <span><b>Tienes <?= (int) $rutasPendientes ?> ruta<?= (int) $rutasPendientes > 1 ? 's' : '' ?> por hacer</b>
                <span>Ver el orden de entregas y el mapa →</span></span>
        </a>
    <?php endif; ?>

    <?php
    $filtros = [
        ''          => 'Todas',
        'asignada'  => 'Asignadas',
        'en_ruta'   => 'En ruta',
        'entregada' => 'Entregadas',
        'fallida'   => 'No entregadas',
    ];
    ?>
    <nav class="pestanas-estado" aria-label="Filtrar entregas">
        <?php foreach ($filtros as $valor => $texto): ?>
            <?php $activo = ($estado ?? '') === $valor; ?>
            <a href="<?= base_url('repartidor/entregas' . ($valor === '' ? '' : '?estado=' . $valor)) ?>"
               class="pestana<?= $activo ? ' activa' : '' ?>"<?= $activo ? ' aria-current="page"' : '' ?>><?= esc($texto) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($entregas)): ?>
        <div class="vacio">
            <b>No tienes entregas<?= ($estado ?? '') !== '' ? ' con este estado' : ' asignadas por ahora' ?></b>
            <span>Cuando el personal te asigne un pedido aparecerá aquí.</span>
        </div>
    <?php endif; ?>

    <?php foreach ($entregas as $e): ?>
        <article class="entrega">
            <div class="entrega-cab">
                <h2>Pedido #<?= esc($e['id_pedido']) ?> <small class="opcional">· intento <?= esc($e['intento']) ?></small></h2>
                <?= estado_entrega($e['estado']) ?>
            </div>

            <div class="entrega-datos">
                <span><i class="fa-solid fa-user" aria-hidden="true"></i> <?= esc($e['cliente']) ?></span>
                <span><i class="fa-solid fa-phone" aria-hidden="true"></i> <a href="tel:<?= esc($e['telefono_cliente'], 'attr') ?>"><?= esc($e['telefono_cliente']) ?></a></span>
                <?php if (array_key_exists('calle', $e)): ?>
                    <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= esc(\App\Models\Modelo_ruta::textoDireccion($e) ?: 'Sin dirección registrada') ?></span>
                    <?php if (! empty($e['referencias'])): ?><small><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <?= esc($e['referencias']) ?></small><?php endif; ?>
                    <?php if (isset($e['total_pedido'])): ?><span><i class="fa-solid fa-money-bill" aria-hidden="true"></i> Cobrar <b>$<?= esc(number_format((float) $e['total_pedido'], 2)) ?></b></span><?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (! empty($e['navegar']) && in_array($e['estado'], ['asignada', 'en_ruta'], true)): ?>
                <a class="btn btn-primario btn-grande" href="<?= esc($e['navegar'], 'attr') ?>" target="_blank" rel="noopener">
                    <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i>Navegar</a>
            <?php endif; ?>

            <?php if ($e['incidencia']): ?>
                <p class="aviso aviso-error"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?= esc($e['incidencia']) ?></p>
            <?php endif; ?>

            <?php if ($e['estado'] === 'entregada'): ?>
                <p class="parada-nota">
                    Entregada el <?= esc($e['fecha_entrega']) ?>
                    <?php if ($e['monto_cobrado'] !== null): ?>
                        · Cobrado $<?= esc(number_format((float) $e['monto_cobrado'], 2)) ?> (<?= esc($e['metodo_cobro']) ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($e['estado'] === 'asignada'): ?>
                <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $e['id'] . '/salir') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primario btn-grande btn-bloque">
                        <i class="fa-solid fa-truck" aria-hidden="true"></i>Salir a entregar
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($e['estado'] === 'en_ruta'): ?>
                <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $e['id'] . '/entregar') ?>" class="form-entrega">
                    <?= csrf_field() ?>
                    <div class="campos-dobles">
                        <label>Monto cobrado <span class="opcional">(opcional)</span>
                            <input type="number" name="monto" min="0" step="0.01" inputmode="decimal" class="w3-input w3-border">
                        </label>
                        <label>Forma de pago
                            <select name="metodo" class="w3-select w3-border">
                                <option value="">Elegir</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option>
                            </select>
                        </label>
                    </div>
                    <label>Notas <span class="opcional">(opcional)</span>
                        <input type="text" name="notas" maxlength="500" class="w3-input w3-border">
                    </label>
                    <button type="submit" class="btn btn-primario btn-grande btn-bloque btn-entregado">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>Marcar entregada
                    </button>
                </form>

                <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $e['id'] . '/fallar') ?>" class="form-falla">
                    <?= csrf_field() ?>
                    <label>¿No se pudo entregar? Cuéntanos qué pasó
                        <input type="text" name="incidencia" maxlength="500" required placeholder="Cliente ausente, dirección incorrecta…" class="w3-input w3-border">
                    </label>
                    <button type="submit" class="btn btn-peligro btn-grande btn-bloque">
                        <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>No se pudo entregar
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?= $pager->links('default', 'w3_pager') ?>
</main>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= view('partials/mapa_config', ['almacenMapa' => $almacenMapa]) ?>
    <script src="<?= base_url('js/ruta_mapa.js') ?>" defer></script>
    <title>Mi ruta · Fruver</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php
/*
 * Ruta del repartidor (celular primero). La siguiente parada va destacada con todo lo
 * necesario para resolverla: dirección, referencias, hora estimada, monto a cobrar y botones grandes.
 */
$pendientes = array_filter($paradas, static fn ($p) => in_array($p['estado'], ['asignada', 'en_ruta'], true));
$porSalir   = array_filter($paradas, static fn ($p) => $p['estado'] === 'asignada');
$entregadas = array_filter($paradas, static fn ($p) => $p['estado'] === 'entregada');
$activa     = in_array($ruta['estado'], ['asignada', 'en_curso'], true);
$volverRuta = (int) $ruta['id'];
?>
<main class="mi-ruta">
    <?php foreach (['error' => 'aviso-error', 'mensaje' => 'aviso-ok', 'aviso' => 'aviso-atencion'] as $clave => $clase): ?>
        <?php if (session()->getFlashdata($clave)): ?>
            <p class="aviso <?= $clase ?>" role="status"><?= esc(session()->getFlashdata($clave)) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>

    <header class="ruta-cabeza">
        <div class="ruta-cabeza-fila">
            <div>
                <h1>Mi ruta · <?= esc(date('d/m', strtotime($ruta['fecha']))) ?></h1>
                <small>
                    <?= esc(rutas_km($ruta['distancia_m'])) ?> · <?= esc(rutas_duracion($ruta['duracion_s'])) ?> manejando
                    <?= $ruta['proveedor'] === 'local' ? '· ruta aproximada' : '' ?>
                </small>
            </div>
            <a href="<?= base_url('repartidor/entregas') ?>" class="btn btn-secundario btn-chico"><i class="fa-solid fa-list" aria-hidden="true"></i>Mis entregas</a>
        </div>
        <progress class="ruta-progreso" max="<?= max(1, count($paradas)) ?>" value="<?= count($entregadas) ?>"
                  aria-label="Entregas hechas"><?= count($entregadas) ?> de <?= count($paradas) ?></progress>
        <span class="ruta-progreso-texto"><?= count($entregadas) ?> de <?= count($paradas) ?> entregas hechas</span>

        <?php if ($activa && ($porSalir !== [] || count($pendientes) >= 2)): ?>
            <div class="ruta-acciones">
                <?php if ($porSalir !== []): ?>
                    <form method="post" action="<?= base_url('repartidor/ruta/' . (int) $ruta['id'] . '/iniciar') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primario btn-grande btn-bloque"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i>Salir a ruta (<?= count($porSalir) ?>)</button>
                    </form>
                <?php endif; ?>
                <?php if (count($pendientes) >= 2): ?>
                    <form method="post" action="<?= base_url('repartidor/ruta/' . (int) $ruta['id'] . '/reoptimizar') ?>" data-gps="requerido"
                          onsubmit="return this.__gpsListo || confirm('¿Reordenar las paradas pendientes desde donde estás ahora?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="gps_lat"><input type="hidden" name="gps_lng">
                        <button type="submit" class="btn btn-secundario btn-bloque"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>Reordenar desde aquí</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="mapa" id="mapaRuta"></div>
    <p class="geo-estado" data-mapa-estado hidden></p>

    <?php foreach ($paradas as $i => $p): ?>
        <?php $esSiguiente = (int) $p['id'] === $siguiente; ?>
        <article class="parada estado-<?= esc($p['estado'], 'attr') ?><?= $esSiguiente ? ' siguiente' : '' ?>" id="parada-<?= (int) $p['id'] ?>">
            <?php if ($esSiguiente): ?><span class="parada-etiqueta">Siguiente · parada <?= $i + 1 ?> de <?= count($paradas) ?></span><?php endif; ?>
            <div class="cab">
                <h2><span class="parada-num"><?= $i + 1 ?></span><?= esc($p['cliente']) ?></h2>
                <?= estado_entrega($p['estado']) ?>
            </div>

            <p class="dir">
                <?= esc(\App\Models\Modelo_ruta::textoDireccion($p) ?: 'Sin dirección registrada') ?>
                <?php if ($p['referencias']): ?><span class="ref"><?= esc($p['referencias']) ?></span><?php endif; ?>
            </p>

            <?php if (in_array($p['estado'], ['asignada', 'en_ruta'], true)): ?>
                <div class="parada-datos">
                    <div>
                        <small>Llegada aprox.</small>
                        <strong class="num"><?= esc(rutas_hora($p['eta'])) ?></strong>
                        <?php if ($p['ventana_fin']): ?><em>antes de <?= esc(substr($p['ventana_fin'], 0, 5)) ?></em><?php endif; ?>
                    </div>
                    <div>
                        <small>Cobrar · pedido #<?= (int) $p['id_pedido'] ?></small>
                        <strong class="num">$<?= esc(number_format((float) $p['total_pedido'], 2)) ?></strong>
                    </div>
                </div>

                <div class="acciones">
                    <?php if ($p['navegar']): ?>
                        <a class="btn btn-primario btn-grande" href="<?= esc($p['navegar'], 'attr') ?>" target="_blank" rel="noopener">
                            <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i>Navegar
                        </a>
                    <?php endif; ?>
                    <?php if ($p['telefono_cliente']): ?>
                        <a class="btn btn-secundario btn-grande" href="tel:<?= esc($p['telefono_cliente'], 'attr') ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i>Llamar</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($p['estado'] === 'en_ruta'): ?>
                <details class="registrar" <?= $esSiguiente ? 'open' : '' ?>>
                    <summary><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Registrar la entrega</summary>
                    <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $p['id'] . '/entregar') ?>" enctype="multipart/form-data" data-gps class="form-entrega">
                        <?= csrf_field() ?>
                        <input type="hidden" name="volver_ruta" value="<?= $volverRuta ?>">
                        <input type="hidden" name="gps_lat"><input type="hidden" name="gps_lng">
                        <div class="campos-dobles">
                            <label>Monto cobrado
                                <input type="number" name="monto" min="0" step="0.01" inputmode="decimal" placeholder="<?= esc(number_format((float) $p['total_pedido'], 2), 'attr') ?>" class="w3-input w3-border">
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
                        <label>Foto de evidencia <span class="opcional">(opcional)</span>
                            <input type="file" name="evidencia" accept="image/jpeg,image/png,image/webp" capture="environment" class="w3-input">
                        </label>
                        <label>Notas <span class="opcional">(opcional)</span>
                            <input type="text" name="notas" maxlength="500" class="w3-input w3-border">
                        </label>
                        <button type="submit" class="btn btn-primario btn-grande btn-bloque btn-entregado"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Marcar entregado</button>
                    </form>

                    <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $p['id'] . '/fallar') ?>" class="form-falla">
                        <?= csrf_field() ?>
                        <input type="hidden" name="volver_ruta" value="<?= $volverRuta ?>">
                        <label>¿No se pudo entregar? Cuéntanos qué pasó
                            <input type="text" name="incidencia" maxlength="500" required list="motivos-falla" placeholder="Cliente ausente, dirección incorrecta…" class="w3-input w3-border">
                        </label>
                        <button type="submit" class="btn btn-peligro btn-grande btn-bloque"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>No se pudo entregar</button>
                    </form>
                </details>
            <?php elseif ($p['estado'] === 'entregada'): ?>
                <p class="parada-nota">Entregado a las <?= esc(rutas_hora($p['fecha_entrega'])) ?>
                    <?php if ($p['monto_cobrado'] !== null): ?> · cobrado $<?= esc(number_format((float) $p['monto_cobrado'], 2)) ?> (<?= esc($p['metodo_cobro']) ?>)<?php endif; ?>
                </p>
            <?php elseif ($p['estado'] === 'fallida'): ?>
                <p class="parada-nota parada-nota--falla"><?= esc($p['incidencia']) ?>
                    <?php if ($p['telefono_cliente']): ?> · <a href="tel:<?= esc($p['telefono_cliente'], 'attr') ?>">Llamar al cliente</a><?php endif; ?>
                </p>
            <?php elseif ($p['estado'] === 'asignada'): ?>
                <p class="parada-nota">Pulsa «Salir a ruta» para empezar.</p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?php if ($regreso && $pendientes === [] && $paradas !== []): ?>
        <a class="btn btn-primario btn-grande btn-bloque" href="<?= esc($regreso, 'attr') ?>" target="_blank" rel="noopener">
            <i class="fa-solid fa-warehouse" aria-hidden="true"></i>Regresar al almacén
        </a>
    <?php endif; ?>

    <datalist id="motivos-falla">
        <option value="Cliente ausente">
        <option value="Dirección incorrecta o no encontrada">
        <option value="Cliente rechazó el pedido">
        <option value="No pagó">
        <option value="Acceso cerrado">
    </datalist>

    <script type="application/json" id="ruta-datos"><?= json_encode([
        'almacen'   => ['lat' => $ruta['almacen_lat'], 'lng' => $ruta['almacen_lng']],
        'regresa'   => (bool) $ruta['regresa_almacen'],
        'polyline'  => $ruta['polyline'],
        'proveedor' => $ruta['proveedor'],
        'paradas'   => array_map(static fn ($p, $i) => [
            'id' => (int) $p['id'], 'n' => $i + 1, 'lat' => $p['lat'], 'lng' => $p['lng'],
            'titulo' => $p['cliente'], 'estado' => $p['estado'],
        ], $paradas, array_keys($paradas)),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</main>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <?= view('partials/mapa_config', ['almacenMapa' => $almacenMapa]) ?>
    <script src="<?= base_url('js/rutas_planeador.js') ?>" defer></script>
    <title>Rutas de reparto</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="lista-wrapper">
    <div class="flash-container" style="padding-top:70px;">
        <?php foreach (['error' => 'w3-red', 'mensaje' => 'w3-green', 'aviso' => 'w3-pale-yellow w3-border'] as $clave => $clase): ?>
            <?php if (session()->getFlashdata($clave)): ?>
                <div class="w3-panel <?= $clase ?> w3-animate-opacity"><p><?= esc(session()->getFlashdata($clave)) ?></p></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <h2 style="margin:0;"><i class="fa-solid fa-route"></i> Rutas de reparto</h2>
        <div>
            <a href="<?= base_url('rutas/almacen') ?>" class="w3-button w3-border"><i class="fa-solid fa-warehouse"></i> Almacén</a>
            <a href="<?= base_url('lista_direccion') ?>" class="w3-button w3-border"><i class="fa-solid fa-map-location-dot"></i> Direcciones</a>
        </div>
    </div>

    <?php if (! $conServicio): ?>
        <div class="w3-panel w3-pale-yellow w3-border">
            <p><b>No hay servicio de rutas configurado.</b> Las rutas se calcularán de forma aproximada
               (línea recta, sin tráfico) y las direcciones solo se pueden ubicar con el pin.
               Para rutas reales y búsqueda de direcciones, agrega <code>rutas.llaveOrs</code> (cuenta gratuita de openrouteservice)
               al archivo <code>.env</code> (ver <code>docs/RUTAS_REPARTO.md</code>).</p>
        </div>
    <?php else: ?>
        <p class="w3-small w3-text-grey" style="margin:4px 0 0;">Servicio de rutas: <?= esc($proveedor) ?></p>
    <?php endif; ?>

    <?php if ($almacenes === []): ?>
        <div class="w3-panel w3-pale-red w3-border">
            <p>Antes de planear, <a href="<?= base_url('rutas/almacen') ?>"><b>registra el almacén</b></a> desde donde salen los repartidores.</p>
        </div>
    <?php endif; ?>

    <div class="rutas-layout w3-margin-top">
        <section class="rutas-panel">
            <h3>1. Datos de la ruta</h3>
            <div class="rutas-campos">
                <div>
                    <label for="r_fecha">Fecha</label>
                    <input type="date" id="r_fecha" class="w3-input w3-border" value="<?= esc($fecha, 'attr') ?>">
                </div>
                <div>
                    <label for="r_hora">Hora de salida</label>
                    <input type="time" id="r_hora" class="w3-input w3-border" value="<?= esc(substr($horaSalida, 0, 5), 'attr') ?>">
                </div>
                <div>
                    <label for="r_repartidor">Repartidor</label>
                    <select id="r_repartidor" class="w3-select w3-border">
                        <?php foreach ($repartidores as $r): ?>
                            <option value="<?= (int) $r['id'] ?>">
                                <?= esc(trim($r['nombre'] . ' ' . $r['ape_pat'])) ?>
                                (<?= esc(\App\Models\Modelo_repartidor::VEHICULOS[$r['vehiculo'] ?? 'moto'] ?? 'Moto') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="r_almacen">Sale de</label>
                    <select id="r_almacen" class="w3-select w3-border">
                        <?php foreach ($almacenes as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"><?= esc($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label class="geo-check"><input type="checkbox" id="r_regresa" checked> Regresa al almacén al terminar</label>

            <h3 style="margin-top:14px;">2. Pedidos a entregar <small class="w3-text-grey" data-contador>(0 / <?= (int) $maxParadas ?>)</small></h3>
            <?php if ($candidatos === []): ?>
                <p class="w3-text-grey">No hay pedidos confirmados pendientes de entrega. Confirma pedidos en <a href="<?= base_url('lista_p_pedido') ?>">Productos por pedido</a>.</p>
            <?php else: ?>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="w3-button w3-small w3-border" data-sel="todos">Todos</button>
                    <button type="button" class="w3-button w3-small w3-border" data-sel="ninguno">Ninguno</button>
                    <button type="button" class="w3-button w3-small w3-border" data-sel="repartidor" title="Los ya asignados al repartidor elegido">Del repartidor</button>
                </div>
                <ul class="rutas-lista" id="listaCandidatos">
                    <?php foreach ($candidatos as $c): ?>
                        <?php $ubicada = $c['lat'] !== null && $c['lng'] !== null; ?>
                        <li class="<?= $ubicada ? '' : 'sin-ubicar' ?>" data-id="<?= (int) $c['id_pedido'] ?>"
                            data-repartidor="<?= (int) ($c['id_repartidor_asignado'] ?? 0) ?>">
                            <input type="checkbox" value="<?= (int) $c['id_pedido'] ?>" <?= $ubicada ? '' : 'disabled' ?>
                                   aria-label="Incluir pedido <?= (int) $c['id_pedido'] ?>">
                            <span class="num" data-num hidden></span>
                            <div class="detalle">
                                <b>#<?= (int) $c['id_pedido'] ?> · <?= esc($c['cliente']) ?></b>
                                <?php if ($c['ventana_fin']): ?><span class="geo-tag geo-tag--aprox">antes de <?= esc(substr($c['ventana_fin'], 0, 5)) ?></span><?php endif; ?>
                                <br>
                                <?php if ($c['id_direccion'] === null): ?>
                                    <span class="geo-tag geo-tag--no">Sin dirección</span>
                                    <a href="<?= base_url('lista_direccion') ?>">registrar</a>
                                <?php else: ?>
                                    <?= esc(\App\Models\Modelo_ruta::textoDireccion($c)) ?>
                                    <?php if (! $ubicada): ?><span class="geo-tag geo-tag--no">Sin ubicar</span><?php endif; ?>
                                    <?php if (($c['precision_geo'] ?? '') === 'APPROXIMATE'): ?><span class="geo-tag geo-tag--aprox">aproximada</span><?php endif; ?>
                                <?php endif; ?>
                                <br><small>
                                    $<?= esc(number_format((float) $c['total_pedido'], 2)) ?> · pedido del <?= esc($c['fecha_pedido']) ?>
                                    <?php if ($c['tipo'] === 'entrega'): ?> · ya asignado a un repartidor<?php endif; ?>
                                    <span data-eta></span>
                                </small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="w3-small w3-text-grey">Los pedidos «sin ubicar» no pueden entrar a la ruta hasta corregir su dirección.</p>
            <?php endif; ?>

            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="w3-button w3-green" id="btnCalcular" <?= $almacenes === [] ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Calcular rutas óptimas
                </button>
                <button type="button" class="w3-button w3-border" id="btnManual" hidden title="Arrastra los pedidos de la lista para cambiar el orden">
                    <i class="fa-solid fa-hand"></i> Recalcular con mi orden
                </button>
            </div>
            <p class="w3-small" id="estadoCalculo" role="status"></p>

            <div id="bloqueOpciones" hidden>
                <h3 style="margin-top:14px;">3. Escoge la ruta</h3>
                <div class="rutas-opciones" id="opciones"></div>
                <form method="post" action="<?= base_url('rutas/guardar') ?>" id="formGuardar" class="w3-margin-top">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" id="g_token">
                    <input type="hidden" name="estrategia" id="g_estrategia">
                    <button type="submit" class="w3-button w3-green w3-block"><i class="fa-solid fa-check"></i> Asignar esta ruta al repartidor</button>
                </form>
            </div>
        </section>

        <section>
            <div class="rutas-mapa" id="mapaRutas"></div>
            <p class="geo-estado" data-mapa-estado hidden></p>
        </section>
    </div>

    <div class="rutas-panel w3-margin-top">
        <h3>Rutas del <?= esc(date('d/m/Y', strtotime($fecha))) ?></h3>
        <?php if ($rutas === []): ?>
            <p class="w3-text-grey">Aún no hay rutas para esta fecha.</p>
        <?php else: ?>
            <div class="tabla-wrapper">
                <table>
                    <thead><tr><th>#</th><th>Repartidor</th><th>Estado</th><th>Paradas</th><th>Avance</th><th>Distancia</th><th>Tiempo</th><th>Tipo</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rutas as $r): ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td><?= esc($r['repartidor']) ?></td>
                            <td><?= esc(str_replace('_', ' ', $r['estado'])) ?></td>
                            <td><?= (int) $r['paradas'] ?></td>
                            <td><?= (int) $r['entregadas'] ?> entregadas<?= (int) $r['fallidas'] ? ' · ' . (int) $r['fallidas'] . ' fallidas' : '' ?></td>
                            <td><?= esc(rutas_km($r['distancia_m'])) ?></td>
                            <td><?= esc(rutas_duracion($r['duracion_s'])) ?></td>
                            <td><?= esc(str_replace('_', ' ', $r['estrategia'])) ?><?= $r['proveedor'] === 'local' ? ' (aprox.)' : '' ?></td>
                            <td><a href="<?= base_url('rutas/' . (int) $r['id']) ?>" class="w3-button w3-small w3-border">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php
    $puntosCandidatos = [];
    foreach ($candidatos as $c) {
        if ($c['lat'] !== null && $c['lng'] !== null) {
            $puntosCandidatos[(int) $c['id_pedido']] = ['lat' => (float) $c['lat'], 'lng' => (float) $c['lng'], 'cliente' => $c['cliente']];
        }
    }
    $almacenesJs = [];
    foreach ($almacenes as $a) {
        $almacenesJs[(int) $a['id']] = ['lat' => (float) $a['lat'], 'lng' => (float) $a['lng'], 'nombre' => $a['nombre']];
    }
    ?>
    <script type="application/json" id="rutas-config"><?= json_encode([
        'urlOpciones' => base_url('rutas/opciones'),
        'urlFecha'    => base_url('rutas'),
        'max'         => (int) $maxParadas,
        'candidatos'  => $puntosCandidatos,
        'almacenes'   => $almacenesJs,
        'nombres'     => ['rapida' => 'Más rápida', 'prioridad' => 'Por horario y preferentes', 'sin_cuotas' => 'Sin casetas ni autopistas', 'manual' => 'Mi orden', 'aproximada' => 'Aproximada (sin servicio de mapas)'],
        'csrfNombre'  => csrf_token(),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

    <?php include 'Footer.php'; ?>
</div>
</body>
</html>

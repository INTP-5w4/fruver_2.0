<?php
/**
 * Comunicaciones voluntarias (V1–V3). Ver App\Controllers\Comunicaciones.
 *
 * @var \Config\Privacidad                $cfg
 * @var string                            $finalidad
 * @var array<string,array<string,mixed>> $finalidades
 * @var array<string,int>                 $conteos
 * @var string                            $mensaje
 * @var list<array<string,mixed>>         $filas
 * @var int                               $porCorreo
 * @var int                               $porWhatsapp
 * @var bool                              $correoListo
 * @var string                            $asunto
 * @var bool                              $bloqueado  el mensaje tiene groserías
 * @var bool                              $esPedidos  pestaña "Avisos de pedidos"
 * @var array<string,array{0:string,1:string}> $plantillas mensajes listos de la pestaña
 * @var string                            $plantilla  clave del mensaje listo elegido
 * @var string|null                       $estado     filtro de estado (solo pedidos)
 * @var list<string>                      $estados
 * @var int                               $diasPedidos
 */
$def = $esPedidos ? null : $cfg->finalidades[$finalidad];
$etiquetas = $esPedidos
    ? ['{nombre}', '{pedido}', '{detalle}', '{total}', '{estado}', '{hora}', '{fecha}', '{tienda}']
    : array_merge(['{nombre}', '{tienda}'], $finalidad === 'v2' ? ['{productos}'] : [], $finalidad === 'v3' ? ['{pedido}'] : []);
$urlPestana = static fn (array $extra): string => base_url('comunicaciones') . '?' . http_build_query(array_filter($extra, static fn ($v) => $v !== null && $v !== ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= base_url('estilos/comunicaciones.css') . '?v=' . @filemtime(FCPATH . 'estilos/comunicaciones.css') ?>">
    <script src="<?= base_url('js/comunicaciones.js') . '?v=' . @filemtime(FCPATH . 'js/comunicaciones.js') ?>" defer></script>
    <title>Comunicaciones</title>
</head>
<body>
    <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>

<div class="lista-wrapper com-wrapper">

    <div class="flash-container">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="w3-panel w3-red"><p><?= esc(session()->getFlashdata('error')) ?></p></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('mensaje')): ?>
            <div class="w3-panel w3-green"><p><?= esc(session()->getFlashdata('mensaje')) ?></p></div>
        <?php endif; ?>
    </div>

    <h2 class="com-titulo">Comunicaciones con clientes</h2>
    <p class="com-intro">
        Solo aparecen las personas que <strong>autorizaron</strong> cada finalidad en su cuenta, y solo por los canales
        que eligieron. Si alguien retira su autorización, desaparece de esta lista de inmediato.
        Las recomendaciones personalizadas (V4) se muestran solas en la tienda, en la fila "Para ti".
        Los avisos sobre un pedido (detalle, en camino, entregado…) están en la pestaña <b>Avisos de pedidos</b>.
    </p>

    <details class="com-tarjeta com-como" open>
        <summary>¿Cómo aviso de mis ofertas? <span class="com-ayuda">Nada se envía solo: tú decides cuándo y a quién.</span></summary>
        <ol>
            <li>Elige la pestaña de lo que quieres comunicar (por ejemplo <b>V1 · Promociones</b> para avisar de ofertas).</li>
            <li>Elige uno de los <b>Mensajes listos</b> o escribe el tuyo. Puedes editar el que elegiste; completa lo que esté entre [corchetes].
                La lista de abajo se actualiza mientras escribes, ya personalizada con el nombre de cada persona.</li>
            <li><b>WhatsApp:</b> pulsa <b>Enviar a todos por WhatsApp</b> (o <b>Enviar</b> en una fila). Se abre WhatsApp (web o en el celular) con el mensaje listo; tú pulsas enviar ahí.
                La fila queda marcada como "Abierto" para que sepas a quién ya le escribiste.</li>
            <li><b>Correo:</b> si hay servidor de correo configurado, <b>Enviar</b> en "Enviar a todos" lo manda de una vez a quienes eligieron correo.</li>
            <li><b>Llamar:</b> el número aparece como enlace para marcar desde el celular.</li>
        </ol>
    </details>

    <nav class="com-pestanas" aria-label="Finalidad">
        <?php foreach ($finalidades as $clave => $f): ?>
            <a href="<?= base_url('comunicaciones') . '?finalidad=' . esc($clave, 'url') ?>"
               class="com-pestana<?= $clave === $finalidad ? ' com-pestana--activa' : '' ?>">
                <?= esc($f['codigo'] . ' · ' . $f['titulo']) ?> <strong><?= (int) ($conteos[$clave] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
        <a href="<?= base_url('comunicaciones') . '?finalidad=pedidos' ?>"
           class="com-pestana<?= $esPedidos ? ' com-pestana--activa' : '' ?>" title="Detalle, confirmación, en camino, entregado, pago… a los clientes de pedidos recientes">
            <i class="fa-solid fa-receipt" aria-hidden="true"></i> Avisos de pedidos
        </a>
    </nav>

    <?php if ($esPedidos): ?>
        <p class="com-ayuda">
            Avisos sobre un pedido (su detalle, que ya va en camino, que se entregó, un pago pendiente…). Son parte de atender el pedido,
            así que no necesitan la autorización de promociones: se mandan al teléfono del cliente por WhatsApp o llamada.
            Aparecen los pedidos de los últimos <?= (int) $diasPedidos ?> días.
        </p>
        <form method="get" action="<?= base_url('comunicaciones') ?>" class="com-filtro">
            <input type="hidden" name="finalidad" value="pedidos">
            <label for="com-estado"><b>Pedidos en estado</b></label>
            <select id="com-estado" name="estado" class="w3-select w3-border" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= esc($e, 'attr') ?>"<?= $estado === $e ? ' selected' : '' ?>><?= esc(estado_pedido_texto($e)) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="w3-button w3-border">Filtrar</button></noscript>
        </form>
    <?php endif; ?>

    <section class="com-tarjeta">
        <div class="com-plantillas" role="group" aria-labelledby="com-plantillas-titulo">
            <span class="com-plantillas-titulo" id="com-plantillas-titulo"><b>Mensajes listos</b> <span class="com-ayuda">(elige uno y edítalo si quieres)</span></span>
            <div class="com-plantillas-lista">
                <?php foreach ($plantillas as $clave => [$titulo, $texto]): ?>
                    <a href="<?= esc($urlPestana(['finalidad' => $finalidad, 'estado' => $estado, 'plantilla' => $clave]), 'attr') ?>"
                       class="com-plantilla<?= $clave === $plantilla ? ' com-plantilla--activa' : '' ?>"
                       data-com-plantilla="<?= esc($texto, 'attr') ?>" data-com-plantilla-clave="<?= esc($clave, 'attr') ?>"
                       title="<?= esc($texto, 'attr') ?>"<?= $clave === $plantilla ? ' aria-current="true"' : '' ?>><?= esc($titulo) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="get" action="<?= base_url('comunicaciones') ?>" id="com-form-mensaje" data-pie="<?= esc($pie, 'attr') ?>"
              data-revisar="<?= esc(base_url('comunicaciones/revisar'), 'attr') ?>">
            <input type="hidden" name="finalidad" value="<?= esc($finalidad, 'attr') ?>">
            <input type="hidden" name="plantilla" value="<?= esc($plantilla, 'attr') ?>" data-com-plantilla-actual>
            <?php if ($esPedidos && $estado !== null): ?><input type="hidden" name="estado" value="<?= esc($estado, 'attr') ?>"><?php endif; ?>
            <label for="mensaje"><b>Mensaje</b></label>
            <textarea id="mensaje" name="mensaje" rows="<?= $esPedidos ? 5 : 3 ?>" maxlength="500" class="w3-input w3-border"
                      data-reglas="<?= esc(json_encode(array_values(array_filter(['Etiquetas: ' . implode(', ', $etiquetas), $esPedidos ? null : 'Al final se agrega cómo dejar de recibir mensajes', 'Completa lo que esté entre [corchetes]'])), JSON_UNESCAPED_UNICODE), 'attr') ?>"><?= esc($mensaje) ?></textarea>
            <p class="com-ayuda">
                Puedes usar
                <?php if ($esPedidos): ?>
                    <code>{nombre}</code>, <code>{pedido}</code> (número), <code>{detalle}</code> (productos, cantidades e importes),
                    <code>{total}</code>, <code>{estado}</code>, <code>{hora}</code> (llegada estimada si va en una ruta), <code>{fecha}</code> y <code>{tienda}</code>.
                <?php else: ?>
                    <code>{nombre}</code>, <code>{tienda}</code><?= $finalidad === 'v2' ? ', <code>{productos}</code> (lo que más pide)' : '' ?><?= $finalidad === 'v3' ? ', <code>{pedido}</code> (su último pedido)' : '' ?>.
                    Al final se agrega siempre cómo dejar de recibir mensajes.
                <?php endif; ?>
                Completa lo que esté entre <b>[corchetes]</b> antes de enviar.
                <span class="com-vivo" data-com-vivo hidden><i class="fa-solid fa-bolt" aria-hidden="true"></i> Los mensajes de abajo se actualizan mientras escribes.</span>
            </p>
            <div class="aviso aviso-atencion com-corchetes" role="status" data-com-corchetes<?= preg_match('/\[[^\]]+\]/u', $mensaje) === 1 ? '' : ' hidden' ?>>
                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                <span>El mensaje tiene datos por completar entre [corchetes].</span>
            </div>
            <div class="aviso aviso-error com-groseria" role="alert" data-com-groseria<?= $bloqueado ? '' : ' hidden' ?>>
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <span>El mensaje tiene palabras que no están permitidas. Corrígelo para poder enviarlo.</span>
            </div>
            <!-- Sin JavaScript, el botón vuelve a generar los mensajes en el servidor. -->
            <button type="submit" class="w3-button w3-green" data-com-actualizar><i class="fa-solid fa-rotate"></i> Actualizar mensajes</button>
        </form>
    </section>

    <section class="com-tarjeta com-todos" aria-labelledby="com-todos-titulo">
        <h3 class="com-subtitulo" id="com-todos-titulo">Enviar a todos</h3>
        <p class="com-ayuda">Se envía el mensaje de arriba, personalizado, a cada persona por el canal que eligió. Quien eligió WhatsApp y correo lo recibe por los dos.</p>

        <div class="com-todos-canales">
        <div class="com-canal">
        <h4 class="com-canal-titulo"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp (<?= (int) $porWhatsapp ?>)</h4>
        <?php if ($porWhatsapp === 0): ?>
            <p class="com-ayuda">Nadie eligió WhatsApp para esta finalidad.</p>
        <?php else: ?>
            <div class="com-cola" data-com-cola hidden>
                <button type="button" class="w3-button w3-green" data-com-cola-boton>
                    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> <span data-com-cola-texto>Enviar a todos por WhatsApp</span>
                </button>
                <div class="com-cola-avance" aria-hidden="true"><span data-com-cola-barra></span></div>
                <span class="com-ayuda" data-com-cola-estado aria-live="polite"></span>
                <button type="button" class="com-enlace" data-com-cola-reiniciar hidden>Volver a empezar</button>
            </div>
            <p class="com-ayuda">
                WhatsApp no permite mandar mensajes en masa desde un enlace: se abre un chat por persona con el
                mensaje ya escrito. Pulsa <b>Enviar</b> en WhatsApp, regresa aquí y pulsa el botón para la siguiente persona.
            </p>
            <noscript><p class="com-ayuda">Usa el botón <b>Enviar</b> de cada fila en la lista de abajo.</p></noscript>
        <?php endif; ?>
        </div>

        <?php if (! $esPedidos): ?>
        <div class="com-canal">
        <h4 class="com-canal-titulo"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Correo electrónico (<?= (int) $porCorreo ?>)</h4>
        <?php if (! $correoListo): ?>
            <p class="com-ayuda"><?php if (es_tecnico()): ?>No hay servidor de correo configurado. Ver <code>docs/PRIVACIDAD.md</code>.<?php else: ?>El envío por correo aún no está activado. Si lo necesitas, avisa al administrador del sistema.<?php endif; ?></p>
        <?php elseif ($porCorreo === 0): ?>
            <p class="com-ayuda">Nadie eligió el correo para esta finalidad.</p>
        <?php else: ?>
            <form method="post" action="<?= base_url('comunicaciones/correo') ?>" class="com-form-correo">
                <?= csrf_field() ?>
                <input type="hidden" name="finalidad" value="<?= esc($finalidad, 'attr') ?>">
                <input type="hidden" name="mensaje" value="<?= esc($mensaje, 'attr') ?>" data-com-mensaje-correo>
                <label for="asunto"><b>Asunto</b></label>
                <input id="asunto" name="asunto" type="text" maxlength="120" required class="w3-input w3-border" value="<?= esc($asunto, 'attr') ?>">
                <button type="submit" class="w3-button w3-green">
                    <i class="fa-solid fa-envelope"></i> Enviar a <?= (int) $porCorreo ?> persona(s)
                </button>
                <span class="com-ayuda">Se envían todos de una vez. Cada persona recibe su propio correo; nadie ve la dirección de los demás.</span>
            </form>
        <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>
    </section>

    <section class="com-tarjeta">
        <h3 class="com-subtitulo"><?= $esPedidos ? 'Pedidos recientes' : 'Personas que autorizaron ' . esc($def['codigo']) ?> (<?= count($filas) ?>)</h3>
        <?php if ($filas === []): ?>
            <p class="com-ayuda"><?= $esPedidos ? 'No hay pedidos con ese criterio en los últimos ' . (int) $diasPedidos . ' días.' : 'Nadie ha autorizado esta finalidad todavía.' ?></p>
        <?php else: ?>
            <div class="com-tabla">
                <table class="w3-table w3-bordered w3-striped">
                    <thead>
                        <tr><th><?= $esPedidos ? 'Cliente y pedido' : 'Persona' ?></th><th>Mensaje</th><th>WhatsApp</th><th>Llamar</th><?php if (! $esPedidos): ?><th>Correo</th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $f): ?>
                            <tr data-com-fila
                                data-clave="<?= esc($f['clave'], 'attr') ?>"
                                data-nombre="<?= esc($f['nombre_mostrar'], 'attr') ?>"
                                data-reemplazos="<?= esc(json_encode($f['reemplazos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>"
                                data-tel="<?= esc((string) ($f['puede_wa'] ? $f['tel_wa'] : ''), 'attr') ?>">
                                <td>
                                    <?= esc($f['nombre_mostrar']) ?>
                                    <small class="com-ayuda"><?= esc($f['detalle']) ?></small>
                                </td>
                                <td class="com-texto">
                                    <p class="com-previa" data-com-texto><?= nl2br(esc($f['texto'])) ?></p>
                                    <button type="button" class="com-enlace" data-com-ver hidden>
                                        <i class="fa-regular fa-eye" aria-hidden="true"></i> Ver mensaje
                                    </button>
                                </td>
                                <td>
                                    <?php if ($f['puede_wa']): ?>
                                        <a href="<?= esc($f['whatsapp'] ?? '#', 'attr') ?>"<?= $f['whatsapp'] === null ? ' aria-disabled="true"' : '' ?> target="_blank" rel="noopener noreferrer" class="w3-button w3-small w3-green" title="Abrir WhatsApp con el mensaje" data-com-wa>
                                            <i class="fa-brands fa-whatsapp"></i> Enviar
                                        </a>
                                        <span class="est est-entregado com-abierto" data-com-abierto hidden>Abierto</span>
                                    <?php else: ?>
                                        <span class="com-ayuda" data-com-sinwa><?= esc($f['sin_wa'] ?? '—') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($f['llamar'] !== null): ?>
                                        <a href="tel:<?= esc($f['llamar'], 'attr') ?>"><?= esc($f['llamar']) ?></a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <?php if (! $esPedidos): ?>
                                    <td><?= $f['por_correo'] ? '<i class="fa-solid fa-check" title="Eligió correo"></i>' : '—' ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

    <!-- Mensaje completo de una persona (lo llena comunicaciones.js con la fila elegida). -->
    <dialog class="com-dialogo" id="com-dialogo" aria-labelledby="com-dialogo-titulo">
        <header class="com-dialogo-cabeza">
            <h3 id="com-dialogo-titulo">Mensaje para <span data-com-d-nombre></span></h3>
            <button type="button" class="com-dialogo-cerrar" data-com-d-cerrar aria-label="Cerrar">&times;</button>
        </header>
        <div class="com-dialogo-cuerpo">
            <p class="com-dialogo-texto" data-com-d-texto></p>
            <p class="com-ayuda" data-com-d-sinwa hidden></p>
        </div>
        <footer class="com-dialogo-pie">
            <a href="#" target="_blank" rel="noopener noreferrer" class="w3-button w3-green" data-com-d-wa hidden>
                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Enviar por WhatsApp
            </a>
            <button type="button" class="w3-button w3-white w3-border" data-com-d-copiar>
                <i class="fa-regular fa-copy" aria-hidden="true"></i> <span>Copiar texto</span>
            </button>
            <button type="button" class="w3-button w3-white w3-border" data-com-d-cerrar>Cerrar</button>
        </footer>
    </dialog>

    <?php include 'Footer.php'; ?>
</body>
</html>

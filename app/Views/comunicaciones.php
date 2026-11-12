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
 * @var bool                              $correoListo
 * @var string                            $asunto
 */
$def = $cfg->finalidades[$finalidad];
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
    </p>

    <details class="com-tarjeta com-como" open>
        <summary>¿Cómo aviso de mis ofertas? <span class="com-ayuda">Nada se envía solo: tú decides cuándo y a quién.</span></summary>
        <ol>
            <li>Elige la pestaña de lo que quieres comunicar (por ejemplo <b>V1 · Promociones</b> para avisar de ofertas).</li>
            <li>Escribe el mensaje. La lista de abajo se actualiza mientras escribes, ya personalizada con el nombre de cada persona.</li>
            <li><b>WhatsApp:</b> pulsa <b>Enviar</b> en cada fila. Se abre WhatsApp (web o en el celular) con el mensaje listo; tú pulsas enviar ahí.
                La fila queda marcada como "Abierto" para que sepas a quién ya le escribiste.</li>
            <li><b>Correo:</b> si hay servidor de correo configurado, un solo botón lo envía a todas las personas que eligieron correo.</li>
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
    </nav>

    <section class="com-tarjeta">
        <form method="get" action="<?= base_url('comunicaciones') ?>" id="com-form-mensaje" data-pie="<?= esc($pie, 'attr') ?>">
            <input type="hidden" name="finalidad" value="<?= esc($finalidad, 'attr') ?>">
            <label for="mensaje"><b>Mensaje</b></label>
            <textarea id="mensaje" name="mensaje" rows="3" maxlength="500" class="w3-input w3-border"
                      data-reglas="<?= esc(json_encode(['Etiquetas: {nombre}, {tienda}' . ($finalidad === 'v2' ? ', {productos}' : '') . ($finalidad === 'v3' ? ', {pedido}' : ''), 'Al final se agrega cómo dejar de recibir mensajes'], JSON_UNESCAPED_UNICODE), 'attr') ?>"><?= esc($mensaje) ?></textarea>
            <p class="com-ayuda">
                Puedes usar <code>{nombre}</code>, <code>{tienda}</code><?= $finalidad === 'v2' ? ', <code>{productos}</code> (lo que más pide)' : '' ?><?= $finalidad === 'v3' ? ', <code>{pedido}</code> (su último pedido)' : '' ?>.
                Al final se agrega siempre cómo dejar de recibir mensajes.
                <span class="com-vivo" data-com-vivo hidden><i class="fa-solid fa-bolt" aria-hidden="true"></i> Los mensajes de abajo se actualizan mientras escribes.</span>
            </p>
            <!-- Sin JavaScript, el botón vuelve a generar los mensajes en el servidor. -->
            <button type="submit" class="w3-button w3-green" data-com-actualizar><i class="fa-solid fa-rotate"></i> Actualizar mensajes</button>
        </form>
    </section>

    <section class="com-tarjeta">
        <h3 class="com-subtitulo">Por correo electrónico (<?= (int) $porCorreo ?>)</h3>
        <?php if (! $correoListo): ?>
            <p class="com-ayuda">No hay servidor de correo configurado. Ver <code>docs/PRIVACIDAD.md</code>.</p>
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
                <span class="com-ayuda">Cada persona recibe su propio correo; nadie ve la dirección de los demás.</span>
            </form>
        <?php endif; ?>
    </section>

    <section class="com-tarjeta">
        <h3 class="com-subtitulo">Personas que autorizaron <?= esc($def['codigo']) ?> (<?= count($filas) ?>)</h3>
        <?php if ($filas === []): ?>
            <p class="com-ayuda">Nadie ha autorizado esta finalidad todavía.</p>
        <?php else: ?>
            <div class="com-tabla">
                <table class="w3-table w3-bordered w3-striped">
                    <thead>
                        <tr><th>Persona</th><th>Mensaje</th><th>WhatsApp</th><th>Llamar</th><th>Correo</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $f): ?>
                            <tr data-com-fila
                                data-reemplazos="<?= esc(json_encode($f['reemplazos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>"
                                data-tel="<?= esc((string) ($f['whatsapp'] !== null ? $f['tel_wa'] : ''), 'attr') ?>">
                                <td>
                                    <?= esc($f['nombre_mostrar']) ?>
                                    <small class="com-ayuda">desde <?= esc(date('d/m/Y', strtotime((string) $f['autorizado_en']))) ?></small>
                                </td>
                                <td class="com-texto" data-com-texto><?= nl2br(esc($f['texto'])) ?></td>
                                <td>
                                    <?php if ($f['whatsapp'] !== null): ?>
                                        <a href="<?= esc($f['whatsapp'], 'attr') ?>" target="_blank" rel="noopener noreferrer" class="w3-button w3-small w3-green" title="Abrir WhatsApp con el mensaje" data-com-wa>
                                            <i class="fa-brands fa-whatsapp"></i> Enviar
                                        </a>
                                        <span class="est est-entregado com-abierto" data-com-abierto hidden>Abierto</span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($f['llamar'] !== null): ?>
                                        <a href="tel:<?= esc($f['llamar'], 'attr') ?>"><?= esc($f['llamar']) ?></a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= $f['por_correo'] ? '<i class="fa-solid fa-check" title="Eligió correo"></i>' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

    <?php include 'Footer.php'; ?>
</body>
</html>

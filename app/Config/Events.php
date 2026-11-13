<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

/*
 * --------------------------------------------------------------------
 * Repartidores automáticos
 * --------------------------------------------------------------------
 * Al iniciar sesión, una cuenta del grupo "repartidor" que todavía no tiene ficha en la
 * tabla `repartidor` recibe una, ya enlazada, para que vea sus entregas desde el primer
 * día. Él mismo completa sus datos la primera vez que entra (filtro repartidordatos →
 * repartidor/mis-datos); el personal también puede hacerlo en Repartidores. (También se revisa al abrir
 * Repartidores, Entregas, Rutas y los formularios de pedido.)
 */
Events::on('login', static function ($usuario): void {
    if (! is_object($usuario) || ! method_exists($usuario, 'inGroup') || ! $usuario->inGroup('repartidor')) {
        return;
    }

    try {
        (new \App\Models\Modelo_repartidor())->sincronizarCuentas((int) $usuario->id);
    } catch (\Throwable $e) {
        log_message('error', 'No se pudo crear la ficha de repartidor al iniciar sesión: ' . $e->getMessage());
    }
});

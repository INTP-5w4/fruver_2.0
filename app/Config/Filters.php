<?php

namespace Config;

use App\Filters\SesionGuard;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * Alias de filtros. Los de Shield (session, group, permission, auth-rates, ...)
     * los registra automáticamente CodeIgniter\Shield\Config\Registrar.
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'sesionguard'   => SesionGuard::class,
    ];

    public array $required = [
        'before' => [
            'forcehttps', // En producción fuerza HTTPS (y envía HSTS)
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            'toolbar',
        ],
    ];

    /**
     * Filtros que se aplican a TODAS las peticiones.
     *
     * csrf          -> rechaza cualquier POST/PUT/PATCH/DELETE sin token válido (CSRF).
     * invalidchars  -> rechaza UTF-8 mal formado y caracteres de control en GET/POST/COOKIE.
     * sesionguard   -> liga la sesión al navegador que inició sesión (anti secuestro de sesión).
     * secureheaders -> X-Frame-Options, X-Content-Type-Options, Referrer-Policy, etc.
     *
     * El control de acceso (quién puede entrar a qué) se declara por grupo de rutas
     * en app/Config/Routes.php con los filtros "session" y "group" de Shield.
     */
    public array $globals = [
        'before' => [
            'csrf',
            'invalidchars',
            'sesionguard',
        ],
        'after' => [
            'secureheaders',
        ],
    ];

    public array $methods = [];

    /**
     * Filtros por patrón de URI.
     * auth-rates (Shield): limita a 10 peticiones por minuto y por IP las rutas de
     * login/registro (frena la fuerza bruta de contraseñas).
     */
    public array $filters = [
        'auth-rates' => [
            'before' => [
                'login*',
                'register',
                'auth/a/*',
                'magic-link*',
            ],
        ],
    ];
}

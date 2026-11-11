<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Aviso de Privacidad Integral y Aviso de Consentimiento de FRUVER 2.0 (LFPDPPP 2025).
 *
 * Todo lo que el negocio debe completar está aquí: las vistas del aviso
 * (app/Views/privacidad/*) toman estos valores, así el texto publicado y lo que el
 * sistema registra siempre coinciden.
 *
 *  - Un valor que empieza con "[" es un dato pendiente: se muestra tal cual en el aviso
 *    y el personal ve un recordatorio en "Mis preferencias de privacidad".
 *  - Al cambiar el texto del aviso o las finalidades, sube $version. Todas las cuentas
 *    tendrán que volver a aceptarlo en su siguiente visita (sección 14 del aviso).
 */
class Privacidad extends BaseConfig
{
    /**
     * Versión vigente del aviso. Se guarda en cada consentimiento como constancia.
     *  1.0 · texto original.
     *  1.1 · cliente preferencial (3.2, 7), repartidores y entregas (3.6), proveedores de mapas (8), plazos (9).
     */
    public string $version = '1.1';

    /** Fecha de última actualización del aviso (DD/MM/AAAA). */
    public string $fechaActualizacion = '[DD/MM/AAAA]';

    // ─── 1. Identidad y domicilio del responsable ─────────────────────────
    public string $responsable = '[NOMBRE COMPLETO O RAZÓN SOCIAL DEL NEGOCIO]';
    public string $domicilio   = '[CALLE, NÚMERO, COLONIA, CÓDIGO POSTAL, MUNICIPIO, PUEBLA, MÉXICO]';

    // Área o persona encargada de privacidad (también atiende derechos ARCO y revocaciones).
    public string $contactoNombre   = '[NOMBRE DE LA PERSONA O DEPARTAMENTO DE DATOS PERSONALES]';
    public string $contactoCorreo   = '[correo@dominio.com]';
    public string $contactoTelefono = '[10 DÍGITOS]';
    public string $contactoHorario  = '[DÍAS Y HORARIO]';

    // ─── 8. Transferencias ────────────────────────────────────────────────
    public string $proveedorReparto = '[NOMBRE DEL PROVEEDOR]';

    // ─── 9. Plazos de conservación ────────────────────────────────────────
    /**
     * Días que se conserva el registro de inicios de sesión (tabla auth_logins).
     * null = aún no definido: el aviso muestra [PLAZO] y `php spark privacidad:depurar`
     * no borra nada. Al definirlo, la depuración usa este mismo número.
     */
    public ?int $diasRegistroAccesos = null;

    public string $plazoClientesPedidos = '[PLAZO LEGAL DE CONSERVACIÓN]';

    // ─── 11. Derechos ARCO ────────────────────────────────────────────────
    public string $plazoRespuestaArco = '[PLAZO]';

    /** Plazo para atender una revocación de las finalidades voluntarias (aviso de consentimiento, sección 7). */
    public string $plazoRevocacion = '[PLAZO]';

    /**
     * Finalidades voluntarias (aviso de consentimiento, sección 5 y formato 9.2).
     * La clave es la columna de la tabla `consentimiento`.
     *
     * @var array<string, array{codigo:string, titulo:string, autorizo:string, datos:string, justificacion:string, medio:string, usaCanal:bool}>
     */
    public array $finalidades = [
        'v1' => [
            'codigo'        => 'V1',
            'titulo'        => 'Promociones, ofertas y novedades de temporada',
            'autorizo'      => 'Autorizo que me envíen promociones, ofertas y novedades de temporada.',
            'datos'         => 'Nombre, teléfono, correo',
            'justificacion' => 'Informarte sobre precios y productos que podrían interesarte',
            'medio'         => 'Llamada, mensajería o correo',
            'usaCanal'      => true,
        ],
        'v2' => [
            'codigo'        => 'V2',
            'titulo'        => 'Recordatorios de recompra',
            'autorizo'      => 'Autorizo que me envíen recordatorios de recompra y avisos de productos que suelo pedir.',
            'datos'         => 'Nombre, teléfono, correo, historial de pedidos',
            'justificacion' => 'Facilitarte volver a pedir de forma oportuna',
            'medio'         => 'Mensajería o correo',
            'usaCanal'      => true,
        ],
        'v3' => [
            'codigo'        => 'V3',
            'titulo'        => 'Encuestas de satisfacción',
            'autorizo'      => 'Autorizo que me inviten a contestar encuestas de satisfacción.',
            'datos'         => 'Nombre, teléfono, correo, datos del pedido evaluado',
            'justificacion' => 'Mejorar nuestro servicio y la calidad del producto',
            'medio'         => 'Mensajería o correo',
            'usaCanal'      => true,
        ],
        'v4' => [
            'codigo'        => 'V4',
            'titulo'        => 'Recomendaciones personalizadas',
            'autorizo'      => 'Autorizo el análisis de mi historial de compras a nivel individual para recibir recomendaciones personalizadas.',
            'datos'         => 'Historial de pedidos asociado a tu identidad',
            'justificacion' => 'Personalizar ofertas. (Los análisis agregados y estadísticos del negocio, como los del panel de control, no requieren este consentimiento porque no te identifican)',
            'medio'         => 'Dentro del Sistema o mensajería',
            'usaCanal'      => false,
        ],
    ];

    /**
     * Canales preferidos (formato 9.3). La clave es la columna de la tabla `consentimiento`.
     *
     * @var array<string, string>
     */
    public array $canales = [
        'canal_telefono'   => 'Teléfono / llamada',
        'canal_mensajeria' => 'Mensajería',
        'canal_correo'     => 'Correo electrónico',
    ];

    /** Nombres de los campos que todavía tienen el texto de ejemplo "[...]". */
    public function pendientes(): array
    {
        $campos = [
            'fechaActualizacion' => 'Fecha de actualización',
            'responsable'        => 'Nombre o razón social',
            'domicilio'          => 'Domicilio',
            'contactoNombre'     => 'Encargado de privacidad',
            'contactoCorreo'     => 'Correo de privacidad',
            'contactoTelefono'   => 'Teléfono de privacidad',
            'contactoHorario'    => 'Horario de atención',
            'proveedorReparto'   => 'Proveedor de reparto',
            'plazoClientesPedidos' => 'Plazo de conservación de pedidos',
            'plazoRespuestaArco' => 'Plazo de respuesta ARCO',
            'plazoRevocacion'    => 'Plazo para revocaciones',
        ];

        $faltan = [];
        foreach ($campos as $campo => $nombre) {
            if (str_starts_with(trim($this->{$campo}), '[')) {
                $faltan[] = $nombre;
            }
        }

        if ($this->diasRegistroAccesos === null) {
            $faltan[] = 'Días de conservación del registro de accesos';
        }

        return $faltan;
    }

    /** Texto del plazo del registro de inicios de sesión para el aviso. */
    public function textoPlazoAccesos(): string
    {
        return $this->diasRegistroAccesos === null ? '[PLAZO]' : $this->diasRegistroAccesos . ' días';
    }
}

<?php

namespace App\Libraries\Termica;

use App\Models\Modelo_pedido;
use App\Models\PdoModelo;
use Config\Impresora;
use Throwable;

/**
 * Orquesta la impresión de tickets: carga el pedido, arma los comandos ESC/POS
 * y los envía por el conector configurado, traduciendo cualquier fallo a un
 * ImpresoraException con mensaje claro para el usuario.
 */
class ServicioTicket
{
    private Impresora $config;

    public function __construct(?Impresora $config = null)
    {
        $this->config = $config ?? config(Impresora::class);
    }

    public function config(): Impresora
    {
        return $this->config;
    }

    /** true cuando no hay impresión directa y se usa la vista imprimible del navegador. */
    public function usaNavegador(): bool
    {
        return strtolower($this->config->conexion) === 'navegador';
    }

    // ═════════════════════════════════════════════════════════════
    // Datos
    // ═════════════════════════════════════════════════════════════

    /**
     * Datos del ticket de un pedido, o null si el pedido no existe.
     * Reutiliza Modelo_pedido::ticket() (el mismo origen que el ticket de WhatsApp).
     *
     * @return array<string,mixed>|null
     * @throws ImpresoraException si la base de datos falla (mensaje seguro, sin detalles técnicos)
     */
    public function cargarPedido(int $id): ?array
    {
        try {
            $ticket = (new Modelo_pedido())->ticket($id);
        } catch (Throwable $e) {
            throw ImpresoraException::fallo(
                PdoModelo::mensajeSeguro($e, 'No se pudo leer el pedido.'),
                'Inténtalo de nuevo; si continúa, revisa el registro del sistema (writable/logs).',
                $e->getMessage()
            );
        }

        return $ticket === null ? null : $this->normalizar($ticket);
    }

    /**
     * Convierte el arreglo de Modelo_pedido::ticket() a la estructura que usan el
     * ticket ESC/POS y la vista HTML (método puro, fácil de probar).
     *
     * @param array<string,mixed> $t
     * @return array<string,mixed>
     */
    public function normalizar(array $t): array
    {
        $items = [];

        foreach ($t['items'] ?? [] as $i) {
            $cant   = (float) ($i['cant'] ?? 0);
            $precio = (float) ($i['precio_venta'] ?? 0);

            $items[] = [
                'nombre' => (string) ($i['nombre_producto'] ?? 'Producto'),
                'cant'   => $cant,
                'unidad' => (string) ($i['unidad_venta'] ?? ''),
                'precio' => $precio,
                'total'  => isset($i['total']) ? (float) $i['total'] : round($cant * $precio, 2),
            ];
        }

        $estado = (string) ($t['estado'] ?? 'pedido_pendiente');

        return [
            'id'           => (int) $t['id'],
            'fecha'        => (string) ($t['fecha'] ?? ''),
            'estado'       => $estado,
            'estado_texto' => $this->textoEstado($estado),
            'cancelado'    => $estado === 'pedido_cancelado',
            'cliente'      => trim((string) ($t['cliente'] ?? '')),
            'telefono'     => trim((string) ($t['telefono'] ?? '')),
            'items'        => $items,
            'total'        => round((float) ($t['total'] ?? 0), 2),
        ];
    }

    // ═════════════════════════════════════════════════════════════
    // Impresión
    // ═════════════════════════════════════════════════════════════

    /**
     * Imprime el ticket de un pedido.
     *
     * @return array{ok:bool,mensaje:string,advertencias:list<string>}
     * @throws ImpresoraException
     */
    public function imprimir(int $idPedido): array
    {
        $pedido = $this->cargarPedido($idPedido);

        if ($pedido === null) {
            throw new ImpresoraException('no_existe', "El pedido #$idPedido no existe.", 'Actualiza la lista de pedidos e inténtalo de nuevo.');
        }

        if ($pedido['items'] === []) {
            throw new ImpresoraException('sin_productos', "El pedido #$idPedido no tiene productos.", 'Agrega productos al pedido antes de imprimir su ticket.');
        }

        $aviso = $this->enviar($this->construirEscPos($pedido));

        log_message('info', "Ticket del pedido #$idPedido impreso por " . $this->usuarioActual() . ' vía ' . $this->config->conexion);

        return [
            'ok'           => true,
            'mensaje'      => "Ticket del pedido #$idPedido enviado a la impresora.",
            'advertencias' => $aviso,
        ];
    }

    /**
     * Imprime una hoja de prueba (ancho, acentos, alineación, tamaños y corte).
     *
     * @return array{ok:bool,mensaje:string,advertencias:list<string>}
     * @throws ImpresoraException
     */
    public function imprimirPrueba(): array
    {
        $aviso = $this->enviar($this->construirPrueba());

        return ['ok' => true, 'mensaje' => 'Hoja de prueba enviada a la impresora.', 'advertencias' => $aviso];
    }

    /**
     * Comprueba la conexión enviando solo "ESC @" (reiniciar): no gasta papel.
     *
     * @return array{ok:bool,mensaje:string,advertencias:list<string>}
     * @throws ImpresoraException
     */
    public function probarConexion(): array
    {
        $aviso = $this->enviar((new EscPos())->inicializar()->obtener());

        return ['ok' => true, 'mensaje' => 'Conexión correcta con la impresora.', 'advertencias' => $aviso];
    }

    /** Datos de configuración que se pueden mostrar en pantalla. */
    public function resumen(): array
    {
        $c = $this->config;

        $destino = match (strtolower($c->conexion)) {
            'red'      => $c->host . ':' . $c->puertoRed,
            'windows'  => '\\\\' . ($c->host !== '' ? $c->host : 'localhost') . '\\' . $c->nombreCompartido,
            'cups'     => $c->nombreCompartido,
            'puerto'   => $c->puerto,
            'archivo'  => 'writable/tickets',
            default    => 'Vista del navegador',
        };

        return [
            'Conexión'            => $c->conexion,
            'Destino'             => $destino,
            'Papel'               => $c->papelMm . ' mm (' . $c->columnasEfectivas() . ' columnas)',
            'Tabla de caracteres' => $c->codificacion,
            'Cancelar Kanji'      => $c->cancelarKanji ? 'sí' : 'no',
            'Corte'               => $c->corte,
            'Copias'              => (string) $c->copiasEfectivas(),
            'Cajón de dinero'     => $c->abrirCajon ? 'sí' : 'no',
        ];
    }

    // ═════════════════════════════════════════════════════════════
    // Construcción de los bytes
    // ═════════════════════════════════════════════════════════════

    /**
     * Genera los bytes ESC/POS del ticket (método puro: no toca la BD ni la impresora).
     *
     * @param array<string,mixed> $p Datos devueltos por cargarPedido().
     */
    public function construirEscPos(array $p): string
    {
        $c   = $this->config;
        $esc = new EscPos($c->columnasEfectivas(), $c->codificacion, $c->cancelarKanji);

        $esc->inicializar();

        for ($copia = 1; $copia <= $c->copiasEfectivas(); $copia++) {
            $this->cuerpoTicket($esc, $p);

            if ($c->abrirCajon && $copia === 1) {
                $esc->abrirCajon();
            }

            $esc->avanzar($c->lineasFinales)->cortar($c->corte);
        }

        return $esc->obtener();
    }

    private function cuerpoTicket(EscPos $esc, array $p): void
    {
        $c = $this->config;

        // Encabezado del negocio
        $esc->alinear('centro')->negrita(true)->tamano(2, 2)->parrafo($c->negocioNombre)->normal();

        if ($c->negocioSlogan !== '') {
            $esc->parrafo($c->negocioSlogan);
        }
        if ($c->negocioDireccion !== '') {
            $esc->parrafo($c->negocioDireccion);
        }
        if ($c->negocioTelefono !== '') {
            $esc->parrafo('Tel: ' . $c->negocioTelefono);
        }

        $esc->alinear('izquierda')->separador();

        // Datos del pedido
        $esc->negrita(true)->izquierdaDerecha('Pedido #' . $p['id'], $this->fecha($p['fecha']))->negrita(false);
        $esc->parrafo('Cliente: ' . ($p['cliente'] !== '' ? $p['cliente'] : '-'));

        if ($p['telefono'] !== '') {
            $esc->linea('Tel: ' . $p['telefono']);
        }

        $esc->linea('Estado: ' . $p['estado_texto']);

        if ($p['cancelado']) {
            $esc->alinear('centro')->negrita(true)->linea('*** PEDIDO CANCELADO ***')->negrita(false)->alinear('izquierda');
        }

        $esc->separador();

        // Productos: nombre y, debajo, "cantidad x precio ........ importe"
        foreach ($p['items'] as $it) {
            $esc->negrita(true)->parrafo($it['nombre'])->negrita(false);
            $esc->izquierdaDerecha(
                '  ' . $this->cantidad($it['cant']) . ' ' . $this->unidad($it['unidad']) . ' x ' . $this->dinero($it['precio']),
                $this->dinero($it['total'])
            );
        }

        $esc->separador();
        $esc->linea('Artículos: ' . count($p['items']));

        // Total destacado (la fila se maqueta con el ancho reducido por el doble tamaño)
        $esc->negrita(true)->tamano(2, 2)->izquierdaDerecha('TOTAL', $this->dinero($p['total']))->normal();

        // Pie
        $esc->separador()->alinear('centro');

        if ($c->mensajePie !== '') {
            $esc->parrafo($c->mensajePie);
        }

        $esc->linea('Impreso: ' . date('d/m/Y H:i'))->alinear('izquierda');
    }

    private function construirPrueba(): string
    {
        $c   = $this->config;
        $esc = new EscPos($c->columnasEfectivas(), $c->codificacion, $c->cancelarKanji);
        $n   = $c->columnasEfectivas();

        $esc->inicializar()
            ->alinear('centro')->negrita(true)->tamano(2, 2)->linea('PRUEBA')->normal()
            ->linea($c->negocioNombre)
            ->alinear('izquierda')->separador()
            ->linea('Papel: ' . $c->papelMm . ' mm / ' . $n . ' columnas')
            ->linea('Tabla: ' . $c->codificacion . ' | Conexion: ' . $c->conexion)
            ->separador()
            ->linea('Regla de columnas (debe caber exacta):');

        // Regla 1234567890 repetida hasta el ancho configurado
        $regla = '';
        for ($i = 1; $i <= $n; $i++) {
            $regla .= (string) ($i % 10);
        }
        $esc->linea($regla)->separador();

        $esc->linea('Acentos y n (deben verse bien):')
            ->linea('áéíóú ÁÉÍÓÚ ñ Ñ ü Ü ¿? ¡!')
            ->linea('Si salen simbolos raros, cambia')
            ->linea('impresora.codificacion o activa')
            ->linea('impresora.cancelarKanji en .env')
            ->separador()
            ->alinear('izquierda')->linea('Izquierda')
            ->alinear('centro')->linea('Centro')
            ->alinear('derecha')->linea('Derecha')
            ->alinear('izquierda')
            ->negrita(true)->linea('Texto en negrita')->negrita(false)
            ->tamano(2, 1)->linea('Doble ancho')->normal()
            ->tamano(1, 2)->linea('Doble alto')->normal()
            ->separador()
            ->izquierdaDerecha('Subtotal', $this->dinero(1234.5))
            ->negrita(true)->tamano(2, 2)->izquierdaDerecha('TOTAL', $this->dinero(1234.5))->normal()
            ->separador()
            ->alinear('centro')->linea('Impreso: ' . date('d/m/Y H:i'))->alinear('izquierda');

        $esc->avanzar($c->lineasFinales)->cortar($c->corte);

        return $esc->obtener();
    }

    // ═════════════════════════════════════════════════════════════
    // Envío
    // ═════════════════════════════════════════════════════════════

    /**
     * Envía bytes por el conector configurado con exclusión mutua para que dos
     * tickets simultáneos no se mezclen.
     *
     * @return list<string> advertencias no críticas
     * @throws ImpresoraException
     */
    private function enviar(string $bytes): array
    {
        $conector = $this->crearConector();
        $bloqueo  = $this->bloquear();

        try {
            $conector->enviar($bytes);

            return $conector->advertencias();
        } catch (ImpresoraException $e) {
            log_message('error', '[Impresora] ' . $e->tipo() . ' — ' . ($e->detalleTecnico() ?: $e->getMessage()));
            throw $e;
        } catch (Throwable $e) {
            log_message('error', '[Impresora] Error inesperado: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

            throw ImpresoraException::fallo(
                'Ocurrió un error inesperado al imprimir.',
                'Inténtalo de nuevo; si continúa, revisa el registro del sistema (writable/logs).',
                $e->getMessage()
            );
        } finally {
            if (is_resource($bloqueo)) {
                flock($bloqueo, LOCK_UN);
                fclose($bloqueo);
            }
        }
    }

    private function crearConector(): Conector
    {
        $c = $this->config;

        return match (strtolower($c->conexion)) {
            'red'     => new ConectorRed($c->host, $c->puertoRed, $c->timeoutSegundos, $c->consultarEstado),
            'windows' => new ConectorWindows($c->host, $c->nombreCompartido, $c->timeoutSegundos + 4),
            'puerto'  => new ConectorPuerto($c->puerto, $c->baudios),
            'cups'    => new ConectorCups($c->nombreCompartido, $c->timeoutSegundos + 4),
            'archivo' => new ConectorArchivo(),
            'navegador' => throw ImpresoraException::noConfigurada('conexion=navegador'),
            default   => throw ImpresoraException::configInvalida(
                'El tipo de conexión "' . $c->conexion . '" no es válido.',
                'Usa uno de: navegador, red, windows, puerto, cups, archivo.'
            ),
        };
    }

    /**
     * Candado de archivo: serializa los envíos a la impresora.
     *
     * @return resource|null
     */
    private function bloquear()
    {
        $dir = WRITEPATH . 'cache';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $f = @fopen($dir . DIRECTORY_SEPARATOR . 'impresora.lock', 'c');

        if ($f === false) {
            return null; // sin candado, pero se puede imprimir igual
        }

        $limite = microtime(true) + 6;

        while (! flock($f, LOCK_EX | LOCK_NB)) {
            if (microtime(true) > $limite) {
                fclose($f);

                throw new ImpresoraException(
                    'ocupada',
                    'La impresora está ocupada imprimiendo otro ticket.',
                    'Espera unos segundos e inténtalo de nuevo.'
                );
            }
            usleep(100000);
        }

        return $f;
    }

    // ═════════════════════════════════════════════════════════════
    // Formato
    // ═════════════════════════════════════════════════════════════

    public function dinero(float $monto): string
    {
        return $this->config->simboloMoneda . number_format($monto, 2, '.', ',');
    }

    public function cantidad(float $cant): string
    {
        $t = rtrim(rtrim(number_format($cant, 2, '.', ''), '0'), '.');

        return $t === '' ? '0' : $t;
    }

    public function unidad(string $unidad): string
    {
        return ['Kilogramo' => 'kg'][$unidad] ?? $unidad;
    }

    public function fecha(string $fecha): string
    {
        $t = strtotime($fecha);

        return $t ? date('d/m/Y', $t) : $fecha;
    }

    private function textoEstado(string $estado): string
    {
        return ucfirst(str_replace('_', ' ', preg_replace('/^pedido_/', '', $estado) ?? $estado));
    }

    private function usuarioActual(): string
    {
        try {
            $u = function_exists('auth') ? auth()->user() : null;

            return $u ? (string) ($u->username ?: $u->email ?: ('id ' . $u->id)) : 'sistema';
        } catch (Throwable) {
            return 'sistema';
        }
    }
}

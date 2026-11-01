<?php

namespace App\Libraries\Termica;

/**
 * Impresoras Ethernet/WiFi: socket TCP directo (normalmente puerto 9100, "RAW/JetDirect").
 *
 * Antes de imprimir consulta el estado en tiempo real (DLE EOT n) si la impresora
 * lo soporta, para avisar de "sin papel" o "tapa abierta" en lugar de perder el ticket.
 * Si la impresora no responde a la consulta, se imprime de todos modos.
 */
class ConectorRed implements Conector
{
    /** @var list<string> */
    private array $avisos = [];

    public function __construct(
        private string $host,
        private int $puerto = 9100,
        private int $timeout = 4,
        private bool $consultarEstado = true
    ) {
        $this->timeout = max(1, min(30, $timeout));
    }

    public function destino(): string
    {
        return "{$this->host}:{$this->puerto}";
    }

    public function advertencias(): array
    {
        return $this->avisos;
    }

    public function enviar(string $datos): void
    {
        $this->avisos = [];

        if (! $this->hostValido($this->host)) {
            throw ImpresoraException::configInvalida(
                'La dirección de la impresora de red no es válida.',
                'Configura impresora.host con una IP (192.168.1.50) o un nombre de equipo.'
            );
        }

        if ($this->puerto < 1 || $this->puerto > 65535) {
            throw ImpresoraException::configInvalida(
                'El puerto de la impresora de red no es válido.',
                'Configura impresora.puertoRed (normalmente 9100).'
            );
        }

        $errno = 0;
        $errstr = '';
        $flujo = @stream_socket_client(
            "tcp://{$this->host}:{$this->puerto}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if ($flujo === false) {
            throw $this->errorDeConexion($errno, $errstr);
        }

        try {
            stream_set_timeout($flujo, $this->timeout);

            if ($this->consultarEstado) {
                $this->revisarEstado($flujo);
            }

            $this->escribirTodo($flujo, $datos);

            // Da tiempo a que el último paquete salga antes de cerrar el socket.
            usleep(150000);
        } finally {
            fclose($flujo);
        }
    }

    // ── Estado en tiempo real (DLE EOT) ─────────────────────────────

    /** @param resource $flujo */
    private function revisarEstado($flujo): void
    {
        // n=2: causa de "fuera de línea" (tapa abierta, error, sin papel).
        $causa = $this->consultar($flujo, 2);

        if ($causa !== null) {
            if ($causa & 0x04) {
                throw ImpresoraException::tapaAbierta();
            }
            if ($causa & 0x20) {
                throw ImpresoraException::sinPapel();
            }
            if ($causa & 0x40) {
                throw ImpresoraException::errorImpresora();
            }
        }

        // n=4: sensor de papel.
        $papel = $this->consultar($flujo, 4);

        if ($papel !== null) {
            if ($papel & 0x60) {
                throw ImpresoraException::sinPapel();
            }
            if ($papel & 0x0C) {
                $this->avisos[] = 'El papel está por terminarse; ten un rollo de repuesto.';
            }
        }
    }

    /**
     * Envía DLE EOT n y lee 1 byte. Devuelve null si la impresora no contesta
     * o si la respuesta no tiene el formato del estándar (bits fijos 0x12).
     *
     * @param resource $flujo
     */
    private function consultar($flujo, int $n): ?int
    {
        // Vacía bytes antiguos que pudiera haber enviado la impresora.
        stream_set_blocking($flujo, false);
        while (($basura = fread($flujo, 64)) !== false && $basura !== '') {
            // descartar
        }
        stream_set_blocking($flujo, true);

        if (@fwrite($flujo, "\x10\x04" . chr($n)) === false) {
            return null;
        }

        stream_set_timeout($flujo, 0, 400000); // 400 ms
        $byte = @fread($flujo, 1);
        stream_set_timeout($flujo, $this->timeout);

        if (! is_string($byte) || $byte === '') {
            return null;
        }

        $valor = ord($byte);

        return (($valor & 0x93) === 0x12) ? $valor : null;
    }

    // ── Escritura robusta ───────────────────────────────────────────

    /** @param resource $flujo */
    private function escribirTodo($flujo, string $datos): void
    {
        $total   = strlen($datos);
        $escrito = 0;

        while ($escrito < $total) {
            $n = @fwrite($flujo, substr($datos, $escrito, 4096));

            if ($n === false || $n === 0) {
                $meta = stream_get_meta_data($flujo);

                if (! empty($meta['timed_out'])) {
                    throw ImpresoraException::tiempoAgotado(
                        $this->destino(),
                        "Timeout al escribir ($escrito/$total bytes)"
                    );
                }

                throw ImpresoraException::conexionPerdida("Escritos $escrito de $total bytes en {$this->destino()}");
            }

            $escrito += $n;
        }

        @fflush($flujo);
    }

    // ── Clasificación de errores ────────────────────────────────────

    private function errorDeConexion(int $errno, string $errstr): ImpresoraException
    {
        $detalle = "stream_socket_client({$this->destino()}) errno=$errno: $errstr";
        $texto   = strtolower($errstr);

        // 110 (Linux) / 10060 (Windows): tiempo de espera agotado.
        if (in_array($errno, [110, 10060], true) || str_contains($texto, 'timed out')) {
            return ImpresoraException::tiempoAgotado($this->destino(), $detalle);
        }

        // 111 / 10061: la IP responde pero el puerto está cerrado.
        if (in_array($errno, [111, 10061], true) || str_contains($texto, 'refused')) {
            return new ImpresoraException(
                'rechazada',
                "La impresora ({$this->destino()}) rechazó la conexión.",
                'Confirma que el puerto sea el correcto (normalmente 9100) y que no haya otro equipo conectado a la impresora.',
                $detalle
            );
        }

        // 113 / 10065 / 101 / 10051: sin ruta hacia el equipo.
        if (in_array($errno, [113, 101, 10065, 10051], true) || str_contains($texto, 'unreachable') || str_contains($texto, 'no route')) {
            return new ImpresoraException(
                'sin_ruta',
                "No se encontró la impresora ({$this->destino()}) en la red.",
                'Comprueba que la impresora y este equipo estén en la misma red y que la IP no haya cambiado.',
                $detalle
            );
        }

        if (str_contains($texto, 'getaddrinfo') || str_contains($texto, 'resolve')) {
            return new ImpresoraException(
                'host_desconocido',
                "No se reconoce el nombre de la impresora ({$this->host}).",
                'Usa la dirección IP de la impresora en lugar del nombre.',
                $detalle
            );
        }

        return ImpresoraException::sinConexion($this->destino(), $detalle);
    }

    private function hostValido(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false
            || (bool) preg_match('/^(?=.{1,253}$)([A-Za-z0-9]([A-Za-z0-9\-]{0,61}[A-Za-z0-9])?)(\.[A-Za-z0-9]([A-Za-z0-9\-]{0,61}[A-Za-z0-9])?)*$/', $host);
    }
}

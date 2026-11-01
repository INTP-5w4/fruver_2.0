<?php

namespace App\Libraries\Termica;

/**
 * Generador de comandos ESC/POS (estándar de facto de las impresoras térmicas).
 *
 * - Sin dependencias externas.
 * - El texto se maqueta en UTF-8 y se codifica a la tabla de caracteres de la
 *   impresora al final, por lo que las columnas siempre cuadran.
 * - Todo texto del usuario se sanea: los caracteres de control se eliminan para
 *   que un nombre con bytes ESC/GS no pueda inyectar comandos a la impresora.
 */
class EscPos
{
    private const ESC = "\x1B";
    private const GS  = "\x1D";
    private const FS  = "\x1C";
    private const LF  = "\x0A";

    /** Número de tabla que se envía con ESC t n. */
    private const CODIGO_TABLA = [
        'cp437'  => 0,
        'cp850'  => 2,
        'cp1252' => 16,
        'cp858'  => 19,
    ];

    /** Caracteres con la misma posición en CP437, CP850 y CP858. */
    private const MAPA_COMUN = [
        'Ç' => 0x80, 'ü' => 0x81, 'é' => 0x82, 'â' => 0x83, 'ä' => 0x84, 'à' => 0x85,
        'ç' => 0x87, 'ê' => 0x88, 'ë' => 0x89, 'è' => 0x8A, 'ï' => 0x8B, 'î' => 0x8C,
        'ì' => 0x8D, 'Ä' => 0x8E, 'É' => 0x90, 'ô' => 0x93, 'ö' => 0x94, 'ò' => 0x95,
        'û' => 0x96, 'ù' => 0x97, 'ÿ' => 0x98, 'Ö' => 0x99, 'Ü' => 0x9A, '£' => 0x9C,
        'á' => 0xA0, 'í' => 0xA1, 'ó' => 0xA2, 'ú' => 0xA3, 'ñ' => 0xA4, 'Ñ' => 0xA5,
        'ª' => 0xA6, 'º' => 0xA7, '¿' => 0xA8, '¬' => 0xAA, '½' => 0xAB, '¼' => 0xAC,
        '¡' => 0xAD, '«' => 0xAE, '»' => 0xAF, '±' => 0xF1, '°' => 0xF8, '·' => 0xFA,
        '²' => 0xFD,
    ];

    /** Mayúsculas acentuadas y otros: existen en CP850/CP858 pero no en CP437. */
    private const MAPA_850 = [
        'Á' => 0xB5, 'Â' => 0xB6, 'À' => 0xB7, '¢' => 0xBD, '¥' => 0xBE, 'ã' => 0xC6,
        'Ã' => 0xC7, 'Ê' => 0xD2, 'Ë' => 0xD3, 'È' => 0xD4, 'Í' => 0xD6, 'Î' => 0xD7,
        'Ï' => 0xD8, 'Ì' => 0xDE, 'Ó' => 0xE0, 'Ô' => 0xE2, 'Ò' => 0xE3, 'õ' => 0xE4,
        'Õ' => 0xE5, 'Ú' => 0xE9, 'Û' => 0xEA, 'Ù' => 0xEB,
    ];

    private string $buffer = '';
    private int $factorAncho = 1;

    public function __construct(
        private int $columnas = 32,
        private string $codificacion = 'cp858',
        private bool $cancelarKanji = false
    ) {
        $this->columnas = max(16, min(80, $columnas));
        $this->codificacion = strtolower($codificacion);

        if ($this->codificacion !== 'ascii' && ! isset(self::CODIGO_TABLA[$this->codificacion])) {
            $this->codificacion = 'ascii';
        }
    }

    // ═════════════════════════════════════════════════════════════
    // Comandos de control
    // ═════════════════════════════════════════════════════════════

    /** ESC @ (reiniciar) + selección de tabla de caracteres. */
    public function inicializar(): static
    {
        $this->buffer .= self::ESC . '@';

        if ($this->cancelarKanji) {
            $this->buffer .= self::FS . '.';
        }

        if ($this->codificacion !== 'ascii') {
            $this->buffer .= self::ESC . 't' . chr(self::CODIGO_TABLA[$this->codificacion]);
        }

        return $this;
    }

    /** ESC a n — izquierda | centro | derecha. */
    public function alinear(string $modo): static
    {
        $n = ['izquierda' => 0, 'centro' => 1, 'derecha' => 2][$modo] ?? 0;
        $this->buffer .= self::ESC . 'a' . chr($n);

        return $this;
    }

    /** ESC E n — negrita. */
    public function negrita(bool $activa): static
    {
        $this->buffer .= self::ESC . 'E' . chr($activa ? 1 : 0);

        return $this;
    }

    /** GS ! n — tamaño del texto (1 = normal, 2 = doble). */
    public function tamano(int $ancho = 1, int $alto = 1): static
    {
        $ancho = max(1, min(2, $ancho));
        $alto  = max(1, min(2, $alto));
        $this->factorAncho = $ancho;
        $this->buffer .= self::GS . '!' . chr((($ancho - 1) << 4) | ($alto - 1));

        return $this;
    }

    /** Vuelve a tamaño y negrita normales. */
    public function normal(): static
    {
        return $this->tamano(1, 1)->negrita(false);
    }

    /** ESC d n — avanza n líneas. */
    public function avanzar(int $lineas): static
    {
        $lineas = max(0, min(20, $lineas));
        if ($lineas > 0) {
            $this->buffer .= self::ESC . 'd' . chr($lineas);
        }

        return $this;
    }

    /**
     * Corta el papel.
     *   parcial  → GS V 66 0  (avanza hasta la cuchilla y corta parcialmente)
     *   completo → GS V 65 0
     *   ninguno  → no envía nada
     */
    public function cortar(string $modo = 'parcial'): static
    {
        if ($modo === 'completo') {
            $this->buffer .= self::GS . 'V' . chr(65) . chr(0);
        } elseif ($modo === 'parcial') {
            $this->buffer .= self::GS . 'V' . chr(66) . chr(0);
        }

        return $this;
    }

    /** ESC p 0 25 250 — pulso al cajón de dinero (pin 2). */
    public function abrirCajon(): static
    {
        $this->buffer .= self::ESC . 'p' . chr(0) . chr(25) . chr(250);

        return $this;
    }

    // ═════════════════════════════════════════════════════════════
    // Texto y maquetación
    // ═════════════════════════════════════════════════════════════

    /** Caracteres por línea con el tamaño de letra actual. */
    public function ancho(): int
    {
        return intdiv($this->columnas, $this->factorAncho);
    }

    /** Escribe una línea (con salto de línea). No hace ajuste de palabras. */
    public function linea(string $texto = ''): static
    {
        // Solo se neutralizan los caracteres de control: los espacios de relleno se respetan.
        $this->buffer .= $this->codificar($this->sinControl($texto)) . self::LF;

        return $this;
    }

    /** Escribe un párrafo ajustando palabras al ancho disponible. */
    public function parrafo(string $texto): static
    {
        foreach ($this->envolver($this->sanear($texto), $this->ancho()) as $l) {
            $this->linea($l);
        }

        return $this;
    }

    /** Línea de separación (por defecto guiones). */
    public function separador(string $caracter = '-'): static
    {
        return $this->linea(str_repeat(mb_substr($caracter, 0, 1) ?: '-', $this->ancho()));
    }

    /**
     * Texto a la izquierda y a la derecha de la misma línea. Si el texto de la
     * izquierda es largo, se parte en varias líneas y el de la derecha va al
     * final (o en su propia línea si ya no cabe).
     */
    public function izquierdaDerecha(string $izq, string $der): static
    {
        $ancho = $this->ancho();
        $izq   = $this->sanear($izq);
        $der   = $this->sanear($der);
        $lenD  = mb_strlen($der);

        // Si el lado derecho no cabe junto a algo de texto, va solo y alineado a la derecha.
        if ($lenD >= $ancho - 1) {
            foreach ($this->envolver($izq, $ancho) as $l) {
                $this->linea($l);
            }

            return $this->linea(mb_substr($der, 0, $ancho));
        }

        $lineas = $this->envolver($izq, max(1, $ancho - $lenD - 1));
        $ultima = array_pop($lineas) ?? '';

        foreach ($lineas as $l) {
            $this->linea($l);
        }

        $relleno = max(1, $ancho - mb_strlen($ultima) - $lenD);

        return $this->linea($ultima . str_repeat(' ', $relleno) . $der);
    }

    /** Bytes generados hasta el momento. */
    public function obtener(): string
    {
        return $this->buffer;
    }

    // ═════════════════════════════════════════════════════════════
    // Internos
    // ═════════════════════════════════════════════════════════════

    /** Reemplaza UTF-8 inválido y caracteres de control (ESC, GS, LF...) por espacios/"?". */
    private function sinControl(string $texto): string
    {
        $texto = mb_scrub($texto, 'UTF-8'); // bytes inválidos → "?"

        return preg_replace('/[\x00-\x1F\x7F\x{0080}-\x{009F}]/u', ' ', $texto) ?? '';
    }

    /** sinControl + colapsa espacios repetidos y recorta (para texto que se va a ajustar a columnas). */
    private function sanear(string $texto): string
    {
        return trim(preg_replace('/ {2,}/', ' ', $this->sinControl($texto)) ?? '');
    }

    /**
     * Ajuste de palabras. Las palabras más largas que el ancho se parten.
     *
     * @return list<string>
     */
    private function envolver(string $texto, int $ancho): array
    {
        $ancho = max(1, $ancho);

        if ($texto === '') {
            return [''];
        }

        $lineas = [];
        $actual = '';

        foreach (preg_split('/ +/', $texto) ?: [] as $palabra) {
            while (mb_strlen($palabra) > $ancho) {
                if ($actual !== '') {
                    $lineas[] = $actual;
                    $actual   = '';
                }
                $lineas[] = mb_substr($palabra, 0, $ancho);
                $palabra  = mb_substr($palabra, $ancho);
            }

            if ($actual === '') {
                $actual = $palabra;
            } elseif (mb_strlen($actual) + 1 + mb_strlen($palabra) <= $ancho) {
                $actual .= ' ' . $palabra;
            } else {
                $lineas[] = $actual;
                $actual   = $palabra;
            }
        }

        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas ?: [''];
    }

    /** Convierte UTF-8 a la tabla de caracteres de la impresora (1 byte por carácter). */
    private function codificar(string $texto): string
    {
        $salida = '';

        foreach (preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
            $salida .= $this->byteDe($c);
        }

        return $salida;
    }

    private function byteDe(string $c): string
    {
        $cp = mb_ord($c, 'UTF-8');

        if ($cp >= 0x20 && $cp <= 0x7E) {
            return $c;
        }

        $byte = $this->buscarEnTabla($c, $cp);

        if ($byte !== null) {
            return chr($byte);
        }

        // Sin equivalente: quitar el acento (é → e) o, en último caso, "?".
        $base = $this->quitarAcento($c);

        return ($base !== '' && ord($base[0]) >= 0x20 && ord($base[0]) <= 0x7E) ? $base[0] : '?';
    }

    private function buscarEnTabla(string $c, int $cp): ?int
    {
        switch ($this->codificacion) {
            case 'cp437':
                return self::MAPA_COMUN[$c] ?? ['¢' => 0x9B, '¥' => 0x9D][$c] ?? null;

            case 'cp850':
                return self::MAPA_COMUN[$c] ?? self::MAPA_850[$c] ?? null;

            case 'cp858':
                return self::MAPA_COMUN[$c] ?? self::MAPA_850[$c] ?? ($c === '€' ? 0xD5 : null);

            case 'cp1252':
                if ($cp >= 0xA0 && $cp <= 0xFF) {
                    return $cp;
                }

                return $c === '€' ? 0x80 : null;
        }

        return null; // ascii
    }

    private function quitarAcento(string $c): string
    {
        if (class_exists(\Normalizer::class)) {
            $d = \Normalizer::normalize($c, \Normalizer::FORM_D);
            if (is_string($d)) {
                return preg_replace('/\p{Mn}+/u', '', $d) ?? '';
            }
        }

        return strtr($c, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            '¿' => '?', '¡' => '!', '€' => 'E',
        ]);
    }
}

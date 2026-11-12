<?php

namespace App\Libraries;

use Config\Groserias;

/**
 * Detecta groserías en un texto con la lista de Config\Groserias.
 *
 * Antes de comparar, cada palabra se "normaliza" para descubrir los disfraces comunes:
 *  - minúsculas y sin acentos (la ñ se conserva: "coño" no es "cono");
 *  - números y símbolos por letras: 4/@→a, 3→e, 1/!→i, 0→o, 5/$→s, 7→t;
 *  - letras repetidas: "puuuto" → "puto" (se prueba también dejando dos: "perrrra" → "perra");
 *  - v por u y k por c: "pvto", "kabron";
 *  - letras sueltas seguidas se juntan: "p u t o", "p.u.t.o" → "puto".
 * Las palabras se comparan completas, así que "Culiacán" o "Cabrera" no se bloquean.
 *
 * Uso: FiltroGroserias::crear()->buscar($texto) devuelve la entrada de la lista que
 * coincidió, o null si el texto está limpio. No se guarda en el log: puede traer datos
 * personales.
 */
final class FiltroGroserias
{
    private const LETRAS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
    ];

    private const SIMBOLOS = [
        '4' => 'a', '@' => 'a', '3' => 'e', '1' => 'i', '!' => 'i', '0' => 'o', '5' => 's', '$' => 's', '7' => 't',
    ];

    private static ?self $instancia = null;

    /** @var array<string,true> */
    private array $exactas = [];

    /** @var list<string> */
    private array $prefijos = [];

    /** @var list<string> */
    private array $frases = [];

    /** @var array<string,true> */
    private array $permitidas = [];

    /** @var list<string> */
    private array $permitidasDentro = [];

    /**
     * @param list<string> $bloqueadas
     * @param list<string> $permitidas       palabras completas que no se bloquean
     * @param list<string> $permitidasDentro partes de palabras normales (para buscarDentro)
     */
    public function __construct(array $bloqueadas, array $permitidas = [], array $permitidasDentro = [])
    {
        foreach ($permitidasDentro as $parte) {
            $parte = $this->limpiar($parte);
            if ($parte !== '') {
                $this->permitidasDentro[] = $parte;
            }
        }
        // Las más largas primero: "computadora" antes que "comput".
        usort($this->permitidasDentro, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($bloqueadas as $entrada) {
            $entrada = $this->limpiar($entrada);
            if ($entrada === '') {
                continue;
            }
            if (str_contains($entrada, ' ')) {
                $this->frases[] = $entrada;
            } elseif (str_ends_with($entrada, '*')) {
                $this->prefijos[] = rtrim($entrada, '*');
            } else {
                $this->exactas[$entrada] = true;
            }
        }
        foreach ($permitidas as $palabra) {
            $this->permitidas[$this->limpiar($palabra)] = true;
        }
    }

    /** Filtro con la lista de Config\Groserias (se arma una sola vez por petición). */
    public static function crear(): self
    {
        if (self::$instancia === null) {
            $cfg             = config(Groserias::class);
            self::$instancia = new self($cfg->bloqueadas, $cfg->permitidas, $cfg->permitidasDentro);
        }

        return self::$instancia;
    }

    /** ¿El texto trae alguna palabra de la lista? */
    public function contiene(?string $texto): bool
    {
        return $this->buscar($texto) !== null;
    }

    /** Entrada de la lista que coincidió, o null si el texto está limpio. */
    public function buscar(?string $texto): ?string
    {
        if ($texto === null || trim($texto) === '') {
            return null;
        }

        $palabras = $this->palabras($texto);

        foreach ($palabras as $palabra) {
            if (isset($this->permitidas[$palabra])) {
                continue;
            }
            foreach ($this->formas($palabra) as $forma) {
                if (isset($this->permitidas[$forma])) {
                    continue 2;
                }
                if (isset($this->exactas[$forma])) {
                    return $forma;
                }
                foreach ($this->prefijos as $prefijo) {
                    if (str_starts_with($forma, $prefijo)) {
                        return $prefijo . '*';
                    }
                }
            }
        }

        if ($this->frases !== []) {
            $unido = ' ' . implode(' ', array_map(fn (string $p): string => $this->formas($p)[0], $palabras)) . ' ';
            foreach ($this->frases as $frase) {
                if (str_contains($unido, ' ' . $frase . ' ')) {
                    return $frase;
                }
            }
        }

        return null;
    }

    /**
     * Para textos sin espacios (nombres de usuario como "elputo99"): busca las palabras de la
     * lista DENTRO del texto, no solo completas. Solo usa entradas de 4 letras o más y primero
     * quita las partes de palabras normales de Config\Groserias::$permitidasDentro
     * ("computo", "diputado", "vehiculo"…) para no bloquearlas.
     */
    public function buscarDentro(?string $texto): ?string
    {
        if ($texto === null || trim($texto) === '') {
            return null;
        }

        $junto = strtr($this->limpiar($texto), self::SIMBOLOS);
        $junto = preg_replace('/[^\p{L}]+/u', '', $junto) ?? '';
        // Se quitan antes de armar las variantes (con v→u "vehiculo" ya no se reconocería).
        $junto = str_replace($this->permitidasDentro, '', $junto);
        if ($junto === '') {
            return null;
        }

        foreach ($this->formas($junto) as $forma) {
            $forma = str_replace($this->permitidasDentro, '', $forma);
            foreach (array_keys($this->exactas) as $entrada) {
                if (mb_strlen($entrada) >= 4 && str_contains($forma, $entrada)) {
                    return $entrada;
                }
            }
            foreach ($this->prefijos as $prefijo) {
                if (mb_strlen($prefijo) >= 4 && str_contains($forma, $prefijo)) {
                    return $prefijo . '*';
                }
            }
        }

        return null;
    }

    /** Minúsculas, sin acentos (salvo la ñ) y espacios simples. */
    private function limpiar(string $texto): string
    {
        $texto = strtr(mb_strtolower($texto, 'UTF-8'), self::LETRAS);

        return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    }

    /**
     * Palabras del texto ya normalizadas, más las letras sueltas juntadas ("p u t o" → "puto").
     *
     * @return list<string>
     */
    private function palabras(string $texto): array
    {
        $texto = $this->limpiar($texto);

        // Separa por todo lo que no sea letra, número o símbolo usado como letra.
        $trozos = preg_split('/[^\p{L}\p{N}@$!]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $palabras = [];
        $sueltas  = '';
        foreach ($trozos as $trozo) {
            // "hola!" o "$500": ! y $ al inicio o al final son puntuación (la @ sí cuenta: "put@").
            $palabra = trim($trozo, '!$');
            if ($palabra === '') {
                continue;
            }
            // Los números puros (teléfonos, cantidades) no se convierten en letras.
            if (preg_match('/\p{L}/u', $palabra) === 1) {
                $palabra = strtr($palabra, self::SIMBOLOS);
            }
            $palabras[] = $palabra;

            if (mb_strlen($palabra) === 1) {
                $sueltas .= $palabra;
            } else {
                if (mb_strlen($sueltas) >= 3) {
                    $palabras[] = $sueltas;
                }
                $sueltas = '';
            }
        }
        if (mb_strlen($sueltas) >= 3) {
            $palabras[] = $sueltas;
        }

        return $palabras;
    }

    /**
     * Variantes de una palabra para comparar. La primera es la "principal" (para las frases).
     *
     * @return list<string>
     */
    private function formas(string $palabra): array
    {
        $formas = [];
        foreach ([$palabra, strtr($palabra, ['v' => 'u', 'k' => 'c'])] as $base) {
            $formas[] = preg_replace('/(.)\1{2,}/u', '$1', $base) ?? $base;     // 3 o más iguales → 1
            $formas[] = preg_replace('/(.)\1{2,}/u', '$1$1', $base) ?? $base;   // 3 o más iguales → 2
            $formas[] = preg_replace('/(.)\1+/u', '$1', $base) ?? $base;        // 2 o más iguales → 1
        }

        return array_values(array_unique($formas));
    }
}

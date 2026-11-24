<?php

namespace App\Libraries;

/**
 * Generador de códigos únicos de producto (requerimiento #8).
 *
 * Formato:  CAT-NOMBRE        ->  FRU-UVAAZU
 *           CAT-NOMBRE-NN     ->  FRU-UVAAZU-02   (cuando el código base ya existe)
 *
 *  - CAT    : 3 letras fijas por categoría (frutas=FRU, verduras=VER, hierbas=HIE;
 *             sin categoría = GEN).
 *  - NOMBRE : 3 letras de la 1.ª palabra + 3 de la 2.ª ("uva azul" -> UVAAZU,
 *             "uva morada" -> UVAMOR). Con una sola palabra, hasta 6 letras
 *             ("zanahoria" -> ZANAHO). Se ignoran "de", "la", "con", etc.
 *  - NN     : consecutivo que se agrega SOLO si dos productos distintos generan el
 *             mismo código base (p. ej. "uva azul" y "uva azulada").
 *
 * Esta clase no toca la base de datos: solo calcula texto. Quien la usa (el modelo o la
 * migración) le dice qué códigos ya existen. La garantía final de unicidad es el índice
 * UNIQUE de la columna producto.codigo.
 */
class CodigoProducto
{
    public const PREFIJOS = [
        'frutas'   => 'FRU',
        'verduras' => 'VER',
        'hierbas'  => 'HIE',
    ];

    public const PREFIJO_GENERICO = 'GEN';

    private const PALABRAS_IGNORADAS = ['DE', 'DEL', 'LA', 'EL', 'LOS', 'LAS', 'Y', 'CON', 'EN', 'A'];

    private const SIN_ACENTOS = [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ü' => 'U', 'ñ' => 'N',
    ];

    /** Código base (sin consecutivo) a partir de la categoría y el nombre. */
    public static function base(string $categoria, string $nombre): string
    {
        $prefijo = self::PREFIJOS[strtolower(trim($categoria))] ?? self::PREFIJO_GENERICO;

        return $prefijo . '-' . self::abreviarNombre($nombre);
    }

    /**
     * Primer código libre a partir del base: BASE, BASE-02, BASE-03...
     *
     * @param list<string> $existentes Códigos que ya están en uso (los que empiezan con $base).
     */
    public static function siguienteLibre(string $base, array $existentes): string
    {
        $usados = array_flip(array_map('strtoupper', $existentes));

        if (! isset($usados[$base])) {
            return $base;
        }

        $n = 2;
        while (isset($usados[sprintf('%s-%02d', $base, $n)])) {
            $n++;
        }

        return sprintf('%s-%02d', $base, $n);
    }

    private static function abreviarNombre(string $nombre): string
    {
        $texto = strtr($nombre, self::SIN_ACENTOS);
        $texto = strtoupper(preg_replace('/[^A-Za-z0-9 ]+/', ' ', $texto) ?? '');

        $palabras = array_values(array_filter(
            preg_split('/\s+/', trim($texto)) ?: [],
            static fn (string $p): bool => $p !== '' && ! in_array($p, self::PALABRAS_IGNORADAS, true)
        ));

        if ($palabras === []) {
            return 'PROD';
        }

        if (count($palabras) === 1) {
            return substr($palabras[0], 0, 6);
        }

        return substr($palabras[0], 0, 3) . substr($palabras[1], 0, 3);
    }
}

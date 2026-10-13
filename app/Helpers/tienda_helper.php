<?php

/**
 * Funciones de presentación de la tienda (formato de dinero y unidades).
 * Se cargan con helper('tienda').
 */

if (! function_exists('tienda_dinero')) {
    /** 1234.5 => "$1,234.50" */
    function tienda_dinero(float|int|string|null $valor): string
    {
        return '$' . number_format((float) $valor, 2, '.', ',');
    }
}

if (! function_exists('tienda_unidad')) {
    /** Nombre corto de la unidad de venta: "Kilogramo" => "kg". */
    function tienda_unidad(?string $unidad): string
    {
        return match (trim((string) $unidad)) {
            'Kilogramo' => 'kg',
            'Litro'     => 'L',
            'Caja'      => 'caja',
            'Pieza'     => 'pza',
            'Domo'      => 'domo',
            'Ramo', 'Ramos' => 'ramo',
            default     => 'pza',
        };
    }
}

if (! function_exists('tienda_img_url')) {
    /** URL pública de la foto de un producto, o cadena vacía si no tiene. */
    function tienda_img_url(?string $img): string
    {
        $img = basename(trim((string) $img));

        // Si el archivo ya no existe en public/uploads se muestra el marcador de posición.
        if ($img === '' || ! is_file(FCPATH . 'uploads/' . $img)) {
            return '';
        }

        return base_url('uploads/' . rawurlencode($img));
    }
}

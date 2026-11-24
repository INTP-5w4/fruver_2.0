<?php

/**
 * Mensajes de validación de CodeIgniter en español.
 *
 * CodeIgniter solo trae el idioma inglés (system/Language/en/Validation.php); este archivo
 * lo reemplaza para el idioma "es" de la app (Config\App::$defaultLocale). Lo usan los
 * formularios de Shield (registro, inicio de sesión) y cualquier validación que no traiga
 * mensajes propios. {field} es el nombre del campo y {param} el valor de la regla.
 */
return [
    // Mensajes internos
    'noRuleSets'      => 'No se especificaron conjuntos de reglas en la configuración de validación.',
    'ruleNotFound'    => '"{0}" no es una regla de validación válida.',
    'groupNotFound'   => '"{0}" no es un grupo de reglas de validación.',
    'groupNotArray'   => 'El grupo de reglas "{0}" debe ser un arreglo.',
    'invalidTemplate' => '"{0}" no es una plantilla de validación válida.',

    // Reglas
    'alpha'                 => 'El campo {field} solo puede contener letras.',
    'alpha_dash'            => 'El campo {field} solo puede contener letras, números, guiones bajos y guiones.',
    'alpha_numeric'         => 'El campo {field} solo puede contener letras y números.',
    'alpha_numeric_punct'   => 'El campo {field} solo puede contener letras, números, espacios y los caracteres ~ ! # $ % & * - _ + = | : .',
    'alpha_numeric_space'   => 'El campo {field} solo puede contener letras, números y espacios.',
    'alpha_space'           => 'El campo {field} solo puede contener letras y espacios.',
    'decimal'               => 'El campo {field} debe ser un número (puede llevar decimales).',
    'differs'               => 'El campo {field} debe ser distinto del campo {param}.',
    'equals'                => 'El campo {field} debe ser exactamente: {param}.',
    'exact_length'          => 'El campo {field} debe tener exactamente {param} caracteres.',
    'field_exists'          => 'El campo {field} debe existir.',
    'greater_than'          => 'El campo {field} debe ser un número mayor que {param}.',
    'greater_than_equal_to' => 'El campo {field} debe ser un número mayor o igual a {param}.',
    'hex'                   => 'El campo {field} solo puede contener caracteres hexadecimales.',
    'in_list'               => 'El campo {field} debe ser uno de: {param}.',
    'integer'               => 'El campo {field} debe ser un número entero.',
    'is_natural'            => 'El campo {field} solo puede contener dígitos.',
    'is_natural_no_zero'    => 'El campo {field} solo puede contener dígitos y debe ser mayor que cero.',
    'is_not_unique'         => 'El valor del campo {field} no existe en el sistema.',
    'is_unique'             => 'El valor del campo {field} ya está registrado.',
    'less_than'             => 'El campo {field} debe ser un número menor que {param}.',
    'less_than_equal_to'    => 'El campo {field} debe ser un número menor o igual a {param}.',
    'matches'               => 'El campo {field} no coincide con el campo {param}.',
    'max_length'            => 'El campo {field} no puede tener más de {param} caracteres.',
    'min_length'            => 'El campo {field} debe tener al menos {param} caracteres.',
    'not_equals'            => 'El campo {field} no puede ser: {param}.',
    'not_in_list'           => 'El campo {field} no puede ser ninguno de: {param}.',
    'numeric'               => 'El campo {field} solo puede contener números.',
    'regex_match'           => 'El campo {field} no tiene el formato correcto.',
    'required'              => 'El campo {field} es obligatorio.',
    'usuario_sin_groserias' => 'Ese nombre de usuario no está permitido. Elige otro sin groserías.',
    'sin_groserias'         => 'Lo que escribiste tiene palabras que no están permitidas. Revísalo y escríbelo de otra forma.',
    'required_with'         => 'El campo {field} es obligatorio cuando {param} tiene un valor.',
    'required_without'      => 'El campo {field} es obligatorio cuando {param} no tiene un valor.',
    'string'                => 'El campo {field} debe ser un texto válido.',
    'timezone'              => 'El campo {field} debe ser una zona horaria válida.',
    'valid_base64'          => 'El campo {field} debe ser un texto base64 válido.',
    'valid_email'           => 'El campo {field} debe ser un correo electrónico válido (por ejemplo, nombre@dominio.com).',
    'valid_emails'          => 'El campo {field} debe contener solo correos electrónicos válidos.',
    'valid_ip'              => 'El campo {field} debe ser una dirección IP válida.',
    'valid_url'             => 'El campo {field} debe ser una dirección web (URL) válida.',
    'valid_url_strict'      => 'El campo {field} debe ser una dirección web (URL) válida.',
    'valid_date'            => 'El campo {field} debe ser una fecha válida.',
    'valid_json'            => 'El campo {field} debe ser un JSON válido.',

    // Tarjetas
    'valid_cc_num' => 'El campo {field} no parece ser un número de tarjeta válido.',

    // Archivos
    'uploaded' => '{field} no es un archivo subido válido.',
    'max_size' => 'El archivo {field} es demasiado grande.',
    'is_image' => '{field} no es una imagen válida.',
    'mime_in'  => '{field} no tiene un tipo de archivo permitido.',
    'ext_in'   => '{field} no tiene una extensión de archivo permitida.',
    'max_dims' => '{field} no es una imagen o es demasiado ancha o alta.',
    'min_dims' => '{field} no es una imagen o no es lo bastante ancha o alta.',
];

<?php

namespace App\Models;

use CodeIgniter\Model;

class Modelo_sugerencia extends Model
{
    protected $table      = 'sugerencia';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    // Lista blanca: solo estos campos pueden escribirse (evita mass assignment).
    // `fecha_creacion` no está aquí: la asigna la base de datos (DEFAULT CURRENT_TIMESTAMP).
    protected $allowedFields = [
        'titulo',
        'descripcion',
        'valoracion',
        'id_usuario',
        'usuario',
        'rol',
    ];

    // Valores permitidos de `valoracion` (deben coincidir con el ENUM de la tabla).
    public const VALORACIONES = [
        'No muy importante',
        'Importante',
        'Muy importante',
    ];

    protected $validationRules = [
        'titulo'      => 'required|min_length[3]|max_length[100]',
        'descripcion' => 'required|min_length[10]|max_length[2000]',
        'valoracion'  => 'required',
        'id_usuario'  => 'permit_empty|is_natural_no_zero',
        'usuario'     => 'required|max_length[100]',
        'rol'         => 'required|max_length[100]',
    ];

    protected $validationMessages = [
        'titulo' => [
            'required'   => 'El título es obligatorio.',
            'min_length' => 'El título debe tener al menos 3 caracteres.',
            'max_length' => 'El título no puede superar los 100 caracteres.',
        ],
        'descripcion' => [
            'required'   => 'La descripción es obligatoria.',
            'min_length' => 'La descripción debe tener al menos 10 caracteres.',
            'max_length' => 'La descripción no puede superar los 2000 caracteres.',
        ],
        'valoracion' => [
            'required' => 'Selecciona qué tan importante es tu sugerencia.',
            'in_list'  => 'La valoración seleccionada no es válida.',
        ],
    ];

    protected function initialize(): void
    {
        // La lista de valoraciones vive en un solo lugar (la constante) y se reutiliza aquí.
        $this->validationRules['valoracion'] .= '|in_list[' . implode(',', self::VALORACIONES) . ']';
    }
}

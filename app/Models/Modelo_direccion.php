<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_direccion extends Model{
    protected $table      = 'direccion';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['colonia','calle','numero','municipio','estado','id_cliente'];

public function filtrar(string $buscar = null): static
{
    if (empty($buscar)) {
        return $this;
    }

    if (is_numeric($buscar)) {
        $this->where('id', (int)$buscar);
    } else {
        $this->groupStart()
            ->like('id', $buscar)
            ->orLike('colonia', $buscar)
            ->orLike('calle', $buscar)
            ->orLike('numero', $buscar)
            ->orLike('municipio', $buscar)
            ->orLike('estado', $buscar)

        ->groupEnd();
    }
    return $this;
}
}
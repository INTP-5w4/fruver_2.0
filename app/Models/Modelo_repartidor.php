<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_repartidor extends Model{
    protected $table      = 'repartidor';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre','ape_pat','ape_mat','telefono','direccion','notas'];

public function filtrar(string $buscar): static
{
    if (!empty($buscar)) {
        $this->groupStart()
                ->where('id', $buscar)
                ->orLike('nombre', $buscar)
                ->orLike('ape_pat', $buscar)
                ->orLike('ape_mat', $buscar)
             ->groupEnd();
    }

    return $this;
}
}
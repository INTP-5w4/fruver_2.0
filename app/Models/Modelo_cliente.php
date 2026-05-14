<?php 
namespace App\Models;

use CodeIgniter\Model;

class Modelo_cliente extends Model{
    protected $table      = 'cliente';
    // Uncomment below if you want add primary key
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre','ape_pat','ape_mat','telefono']; 
    
public function filtrar(string $buscar = null): static
{
    if (empty($buscar)) {
        return $this;
    }

    if (is_numeric($buscar)) {
        $this->where('id', (int)$buscar);
    } else {
        $this->groupStart()
            ->orLike('nombre', $buscar)
            ->orLike('ape_pat', $buscar)
            ->orLike('ape_mat', $buscar)
            ->orLike('telefono', $buscar)
        ->groupEnd();
    }

    return $this;
}
}
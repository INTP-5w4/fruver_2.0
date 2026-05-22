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

    $this->groupStart();

    if (ctype_digit($buscar)) {
        $this->orWhere('id', (int)$buscar);
    }

    $this->orLike('nombre', $buscar)
        ->orLike('ape_pat', $buscar)
        ->orLike('ape_mat', $buscar)
        ->orLike('telefono', $buscar);

    $this->groupEnd();

    return $this;
}
}
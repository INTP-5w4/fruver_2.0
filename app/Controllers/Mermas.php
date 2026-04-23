<?php 
namespace App\Controllers;

use App\Models\Modelo_entrada;
use App\Models\Modelo_merma;
use App\Models\Modelo_producto;
use CodeIgniter\Controller;

class Mermas extends Controller{

public function crea_merma()
{
    $m_entrada = new Modelo_entrada();
    $date = date('Y-m-d');

    $datos = [
        'entradas' => $m_entrada->getEntradasConProducto(),
        'date'     => $date,
    ];

    return view('crea_merma', $datos);
}

public function guarda_merma(){
    $m_merma = new Modelo_merma();
    $datos = [
        'cantidad' => $this->request->getPost('cant'),
        'fecha' => $this->request->getPost('fecha'),
        'notas' => $this->request->getPost('notas'),
        'id_entrada' => $this->request->getPost('id_entrada')
    ];
    if (
        empty($datos['cantidad']) || 
        empty($datos['fecha']) || 
        empty($datos['notas']) || 
        empty($datos['id_entrada'])
    ){
        return redirect()->to('crea_merma')->with('error', 'Por favor, complete todos los campos obligatorios.');
    }else{
        $m_merma->insert($datos);
        return redirect()->to('lista_merma');
    }
}

public function lista_merma(){
    $m_merma = new Modelo_merma();
    $m_entrada = new Modelo_entrada();
    $datos = [
        'mermas' => $m_merma->findAll(),
        'entradas' => $m_entrada->findAll(),
    ];
    return view('lista_merma', $datos);
}

public function recupera($id=null){
    $m_merma = new Modelo_merma();
    $m_entrada = new Modelo_entrada();
    $datos=[
        'mermas' => $m_merma->find($id),
        'entradas' => $m_entrada->findAll(),
    ];
    return view('modifica_merma', $datos);
}
public function eliminar_datos($id=null){
    $m_merma = new Modelo_merma();
    $m_merma->delete($id);
    return redirect()->to('lista_merma');
    
}
public function modifica(){
        $m_merma = new Modelo_merma();
        $id = $this->request->getPost('id');
        $datos = [
            'cantidad' => $this->request->getPost('cantidad'),
            'fecha' => $this->request->getPost('fecha'),
            'notas' => $this->request->getPost('notas'),
            'id_entrada' => $this->request->getPost('id_entrada')
        ];
        if (
            empty($datos['cantidad']) ||
            empty($datos['fecha']) ||
            empty($datos['id_entrada'])
        ){
            return redirect()->to('lista_merma')->with('error', 'Por favor, complete todos los campos obligatorios.');
        }else{
            $m_merma->update($id, $datos);
            return redirect()->to('lista_merma');
        }
    }
}
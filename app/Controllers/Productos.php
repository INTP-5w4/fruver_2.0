<?php

namespace App\Controllers;

use App\Models\Modelo_producto;

class Productos extends BaseController
{
public function main_page(){
    return view('main_page');
}
public function crea_producto(){
    return view('crea_producto');
}
public function guarda_producto(){
    $m_producto= new Modelo_producto();
    $datos=[
        'nombre'=>$this->request->getPost('nom'),
        'descripcion'=>$this->request->getPost('desc'),
    ];
    if(
        empty($datos['nombre'])||
        empty($datos['descripcion'])
    ){
        return view('crea_producto');
    }else{
    $m_producto->insert($datos);
    return redirect()->to('lista_producto');
    }
}
public function lista_producto(){
    $m_producto = new Modelo_producto();
    $datos=[
        'productos'=>$m_producto->orderBy('nombres','ASC')-> paginate(10,'default'),
        'pager'=>$m_producto->pager
    ];
    //$datos['productos']=$m_producto->findAll();
    return view('lista_producto', $datos);
}

public function modifica(){
        $id=$this->request->getPost('id');
         $datos_de_producto=[
            'nombre'=>$this->request->getPost('nom'),
            'descripcion'=>$this->request->getPost('desc'),
         ];
         $m_producto= new Modelo_producto();
         if($m_producto->update($id,$datos_de_producto)){
            //echo "Datos almacenados exitosamente";
            return redirect()->to('lista_producto');
         }
            
    }
public function recupera($id = null){
    $m_producto = new Modelo_producto();
    $datos['productos'] = $m_producto->find($id);
    if(!$datos['productos']){
        return redirect()->to('/lista_producto');
    }
    return view('modifica_producto', $datos);
}

public function eliminar_datos($id = null){
    $m_producto = new Modelo_producto();
    if(!$m_producto->find($id)){
        return redirect()->to('/lista_producto');
    }
    $m_producto->delete($id);
    return redirect()->to('/lista_producto');
}
}

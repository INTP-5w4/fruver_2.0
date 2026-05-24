<?php 
namespace App\Controllers;

use App\Models\Modelo_cliente;
use CodeIgniter\Controller;

class Clientes extends Controller{
    private $configuracion = [
        'reglas' => [
            'nom'     => 'required',
            'ape_pat' => 'required',
            'ape_mat' => 'required',
            'tel'     => 'required|numeric|max_length[12]',
        ],
        'errores' => [
            'nom' => [
                'required' => 'El campo Nombre es obligatorio.'
            ],
            'ape_pat' => [
                'required' => 'El Apellido Paterno es obligatorio.'
            ],
            'ape_mat' => [
                'required' => 'El Apellido Materno es obligatorio.'
            ],
            'tel' => [
                'required'    => 'El Teléfono es obligatorio.',
                'numeric'     => 'El Teléfono debe contener solo números.',
                'max_length'  => 'El Teléfono debe tener máximo 12 dígitos.' // Nota: sin los corchetes
            ]
        ]
    ];
public function crea_cliente(){
    return view('crea_cliente');
}
public function guarda_cliente()
{
    $m_cliente = new Modelo_cliente();



// BIEN: Accedes a la propiedad de la clase usando $this->
if (!$this->validate($this->configuracion['reglas'], $this->configuracion['errores'])) {
    $l_error = $this->validator->getErrors();
    // El withInput() es lo que "guarda" los datos en la sesión para la siguiente petición
    return redirect()->back()->withInput()->with('error', reset($l_error));
}

    // 3. Procesamiento
    $datos = [
        'nombre'   => $this->request->getPost('nom'),
        'ape_pat'  => $this->request->getPost('ape_pat'),
        'ape_mat'  => $this->request->getPost('ape_mat'),
        'telefono' => $this->request->getPost('tel'),
    ];

    try {
        // En CodeIgniter, si el modelo tiene validaciones internas, 
        // a veces lanza DatabaseException. Capturarla aquí evita la pantalla de error.
        if (!$m_cliente->insert($datos)) {
            // Si el modelo falla por reglas de validación internas o DB
            return redirect()->back()->withInput()->with('error', 'Error al guardar en base de datos');
        }

        $destino = ($this->request->getPost('origen') === 'main_page') ? '/' : 'lista_cliente';
        return redirect()->to($destino)->with('mensaje', 'Cliente registrado correctamente');

    } catch (\Exception $e) {
        // 4. Captura de errores inesperados (ej. problemas de conexión, campos duplicados)
        return redirect()->to('lista_cliente')->with('error', 'Ocurrió un error inesperado al procesar la solicitud.');
    }
}
public function lista_cliente()
{
    $buscar = $this->request->getGet('buscar');
    $m_cliente = new Modelo_cliente();

    $datos['clientes'] = $m_cliente
        ->filtrar($buscar)
        ->orderBy('id', 'DESC')
        ->paginate(20);

    $datos['pager'] = $m_cliente->pager;
    $datos['buscar'] = $buscar;

    return view('lista_cliente', $datos);
}
public function modifica()
{
    $id = $this->request->getPost('id');
    
    // 1. Validación de campos
    if (!$this->validate($this->configuracion['reglas'], $this->configuracion['errores'])) {
        $l_error = $this->validator->getErrors();
        // Retornamos con 'error2' como solicitaste
        return redirect()->back()->withInput()->with('error2', reset($l_error));
    }

    $m_cliente = new Modelo_cliente();

    // 2. Datos a actualizar
    $datos = [
        'nombre'   => $this->request->getPost('nom'),
        'ape_pat'  => $this->request->getPost('ape_pat'),
        'ape_mat'  => $this->request->getPost('ape_mat'),
        'telefono' => $this->request->getPost('tel'),
    ];

    try {
        // 3. Intento de actualización
        if (!$m_cliente->update($id, $datos)) {
            return redirect()->back()->withInput()->with('error2', 'Error al actualizar en base de datos');
        }

        return redirect()->to('/lista_cliente')->with('mensaje', 'Cliente actualizado correctamente');

    } catch (\Exception $e) {
        // 4. Captura de errores inesperados (ej. problemas de conexión)
        return redirect()->to('lista_cliente')->with('error2', 'Ocurrió un error inesperado al procesar la actualización.');
    }
}
public function recupera($id = null){
    $m_cliente = new Modelo_cliente();
    $datos['clientes'] = $m_cliente->find($id);
    if(!$datos['clientes']){
        return redirect()->to('/lista_cliente');
    }
    return view('modifica_cliente', $datos);
}
public function eliminar_datos($id = null){
    $m_cliente = new Modelo_cliente();
    if(!$m_cliente->find($id)){
        return redirect()->to('/lista_cliente');
    }
    try {
        $m_cliente->delete($id);
        return redirect()->to('/lista_cliente')->with('mensaje', 'Cliente eliminado correctamente.');
    } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
        return redirect()->to('/lista_cliente')->with('error', 'No se puede eliminar el cliente porque tiene registros relacionados (pedidos, direcciones, etc.).');
    }
}
}
<?php

namespace App\Controllers;

use App\Models\Modelo_oferta;
use App\Models\Modelo_producto;
use Throwable;

/**
 * Ofertas (promociones) de la tienda. Toda la administración vive en una sola pantalla
 * (lista_oferta): la lista y un formulario en ventana para crear o editar, con selección
 * por productos sueltos o por categoría. El precio con descuento lo calcula el servidor
 * en Modelo_producto::sqlCatalogo, no esta pantalla.
 */
class Ofertas extends BaseController
{
    private function reglas(): array
    {
        return [
            'nombre'       => 'required|max_length[100]',
            'tipo'         => 'required|in_list[' . implode(',', Modelo_oferta::TIPOS) . ']',
            'valor'        => 'required|decimal|greater_than[0]',
            'alcance'      => 'required|in_list[' . implode(',', Modelo_oferta::ALCANCES) . ']',
            'fecha_inicio' => 'permit_empty|valid_date[Y-m-d]',
            'fecha_fin'    => 'permit_empty|valid_date[Y-m-d]',
        ];
    }

    public function index()
    {
        $buscar = trim((string) $this->request->getGet('buscar'));

        $modelo = new Modelo_oferta();
        $datos  = [
            'ofertas'            => $modelo->listar($buscar !== '' ? $buscar : null),
            'productosPorOferta' => $modelo->mapaProductos(),
            'productos'          => (new Modelo_producto())->findAll(),
            'categorias'         => Modelo_producto::CATEGORIAS,
            'hoy'                => date('Y-m-d'),
            'buscar'             => $buscar,
        ];

        return view('lista_oferta', $datos);
    }

    public function guardar()
    {
        $id = (int) $this->request->getPost('id');

        if (! $this->validate($this->reglas())) {
            return redirect()->to('ofertas')->with('error', implode(' ', $this->validator->getErrors()));
        }

        $tipo    = (string) $this->post('tipo');
        $alcance = (string) $this->post('alcance');
        $valor   = round((float) $this->post('valor'), 2);

        // Reglas que dependen de otros campos.
        if ($tipo === 'porcentaje' && $valor > 100) {
            return redirect()->to('ofertas')->with('error', 'Un descuento por porcentaje no puede pasar de 100%.');
        }
        if ($alcance === 'categoria' && $tipo === 'precio') {
            return redirect()->to('ofertas')->with('error', 'Un precio fijo solo aplica a productos sueltos, no a una categoría entera. Usa porcentaje.');
        }

        $categoria = null;
        $productos = [];
        if ($alcance === 'categoria') {
            $categoria = (string) $this->post('categoria');
            if (! in_array($categoria, Modelo_producto::CATEGORIAS, true)) {
                return redirect()->to('ofertas')->with('error', 'Elige una categoría válida.');
            }
        } else {
            $productos = array_map('intval', (array) $this->request->getPost('productos'));
            $productos = array_values(array_filter($productos, static fn ($i) => $i > 0));
            if ($productos === []) {
                return redirect()->to('ofertas')->with('error', 'Selecciona al menos un producto para la oferta.');
            }
        }

        $inicio = $this->post('fecha_inicio') ?: null;
        $fin    = $this->post('fecha_fin') ?: null;
        if ($inicio !== null && $fin !== null && $fin < $inicio) {
            return redirect()->to('ofertas')->with('error', 'La fecha de fin no puede ser anterior a la de inicio.');
        }

        $datos = [
            'nombre'       => trim((string) $this->post('nombre')),
            'tipo'         => $tipo,
            'valor'        => $valor,
            'alcance'      => $alcance,
            'categoria'    => $categoria,
            'fecha_inicio' => $inicio,
            'fecha_fin'    => $fin,
            'activa'       => $this->request->getPost('activa') !== null ? 1 : 0,
        ];

        try {
            (new Modelo_oferta())->guardar($datos, $productos, $id > 0 ? $id : null);
        } catch (Throwable $e) {
            log_message('error', 'Ofertas::guardar ' . $e->getMessage());

            return redirect()->to('ofertas')->with('error', 'No se pudo guardar la oferta. Inténtalo de nuevo.');
        }

        return redirect()->to('ofertas')->with('mensaje', $id > 0 ? 'Oferta actualizada.' : 'Oferta creada.');
    }

    public function activar(int $id)
    {
        (new Modelo_oferta())->alternarActiva($id);

        return redirect()->to('ofertas');
    }

    public function borra(int $id)
    {
        (new Modelo_oferta())->eliminar($id);

        return redirect()->to('ofertas')->with('mensaje', 'Oferta eliminada.');
    }
}

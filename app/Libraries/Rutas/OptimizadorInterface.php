<?php

namespace App\Libraries\Rutas;

interface OptimizadorInterface
{
    /**
     * Calcula la ruta desde $origen por todas las $paradas.
     * Si $op->optimizaOrden() es false, respeta el orden recibido.
     *
     * @param list<Parada> $paradas
     *
     * @throws ErrorProveedorRutas si el proveedor falla
     */
    public function calcular(Punto $origen, array $paradas, OpcionesRuta $op): ResultadoRuta;
}

<?php

if (! function_exists('agrupar_por_estado')) {
    /**
     * Agrupa pedidos por el estado de su dirección de entrega y sugiere un repartidor
     * por grupo (reparto equitativo round-robin; los repartidores con cuenta primero,
     * para que la sugerencia recaiga en quien sí verá la entrega).
     *
     * Se reutiliza en la asignación de entregas y en el planeador de rutas, así que el
     * nombre de la columna del estado se puede cambiar ('estado' o 'estado_dir').
     *
     * @param list<array<string,mixed>> $pedidos
     * @param list<array<string,mixed>> $repartidores
     *
     * @return list<array{estado:string, repartidor_sugerido:?int, pedidos:list<array<string,mixed>>}>
     */
    function agrupar_por_estado(array $pedidos, array $repartidores, string $campoEstado = 'estado'): array
    {
        if ($pedidos === []) {
            return [];
        }

        $porEstado = [];
        foreach ($pedidos as $p) {
            $estado = trim((string) ($p[$campoEstado] ?? ''));
            $porEstado[$estado !== '' ? $estado : 'Sin estado registrado'][] = $p;
        }
        ksort($porEstado);

        // Los repartidores con cuenta van primero: así la sugerencia recae en quien sí la verá.
        usort($repartidores, static fn (array $a, array $b): int => (($a['id_user'] ?? null) === null) <=> (($b['id_user'] ?? null) === null));
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $repartidores);

        $grupos = [];
        $i      = 0;
        foreach ($porEstado as $estado => $lista) {
            $grupos[] = [
                'estado'              => $estado,
                'repartidor_sugerido' => $ids !== [] ? $ids[$i % count($ids)] : null,
                'pedidos'             => $lista,
            ];
            $i++;
        }

        return $grupos;
    }
}

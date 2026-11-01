<?php

namespace App\Libraries\Termica;

/**
 * Medio por el que viajan los bytes ESC/POS hasta la impresora.
 */
interface Conector
{
    /**
     * Envía los bytes a la impresora.
     *
     * @throws ImpresoraException con un mensaje apto para el usuario.
     */
    public function enviar(string $datos): void;

    /**
     * Avisos no críticos detectados durante el último envío
     * (por ejemplo, "el papel está por terminarse").
     *
     * @return list<string>
     */
    public function advertencias(): array;

    /** Descripción corta del destino, para mensajes y logs. */
    public function destino(): string;
}

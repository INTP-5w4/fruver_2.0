<?php

use App\Models\Modelo_oferta;
use App\Models\Modelo_producto;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Ofertas (promociones). Sin base de datos: comprueba que el catálogo aplica la oferta en su
 * SQL (para que un refactor no lo quite por descuido) y que el modelo acota tipos y alcances.
 * El comportamiento de punta a punta (crear, editar, ver en la tienda) se verificó en Edge.
 *
 * @internal
 */
final class OfertasTest extends CIUnitTestCase
{
    private function sqlCatalogo(): string
    {
        // Sin constructor para no abrir conexión: sqlCatalogo solo arma texto, no consulta.
        $m   = (new ReflectionClass(Modelo_producto::class))->newInstanceWithoutConstructor();
        $ref = new ReflectionMethod($m, 'sqlCatalogo');
        $ref->setAccessible(true);

        return (string) $ref->invoke($m);
    }

    public function testElCatalogoCalculaElPrecioConLaMejorOfertaVigente(): void
    {
        $sql = $this->sqlCatalogo();

        // La oferta se aplica dentro del catálogo (vale igual en la tienda y en el carrito).
        $this->assertStringContainsString('FROM oferta o', $sql);
        $this->assertStringContainsString('oferta_producto', $sql);
        // El original queda disponible para tacharlo y el precio final es el más barato.
        $this->assertStringContainsString('AS precio_antes', $sql);
        $this->assertStringContainsString('LEAST(e.precio_venta_u', $sql);
        // Solo ofertas activas y dentro de su vigencia.
        $this->assertStringContainsString('o.activa = 1', $sql);
        $this->assertStringContainsString('CURDATE()', $sql);
        // Los dos alcances: categoría del producto o producto elegido.
        $this->assertStringContainsString("o.alcance = 'categoria'", $sql);
        $this->assertStringContainsString("o.alcance = 'productos'", $sql);
    }

    public function testTiposYAlcancesEstanAcotados(): void
    {
        $this->assertSame(['porcentaje', 'precio'], Modelo_oferta::TIPOS);
        $this->assertSame(['productos', 'categoria'], Modelo_oferta::ALCANCES);
    }
}

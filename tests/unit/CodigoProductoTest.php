<?php

use App\Libraries\CodigoProducto;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CodigoProductoTest extends CIUnitTestCase
{
    public function testUvaAzulYUvaMoradaTienenCodigosDistintos(): void
    {
        $this->assertSame('FRU-UVAAZU', CodigoProducto::base('frutas', 'Uva azul'));
        $this->assertSame('FRU-UVAMOR', CodigoProducto::base('frutas', 'uva morada'));
    }

    public function testUnaSolaPalabraUsaHastaSeisLetras(): void
    {
        $this->assertSame('VER-ZANAHO', CodigoProducto::base('verduras', 'Zanahoria'));
        $this->assertSame('FRU-KIWI', CodigoProducto::base('frutas', 'kiwi'));
    }

    public function testIgnoraArticulosAcentosYSimbolos(): void
    {
        $this->assertSame('VER-FLOCAL', CodigoProducto::base('verduras', 'Flor de calabaza'));
        $this->assertSame('FRU-PLAMAC', CodigoProducto::base('frutas', 'Plátano Macho'));
        $this->assertSame('HIE-EPAZOT', CodigoProducto::base('hierbas', 'Epazote'));
        $this->assertSame('VER-CHAYOT', CodigoProducto::base('verduras', 'Chayóte!!'));
    }

    public function testCategoriaVaciaOInvalidaUsaGEN(): void
    {
        $this->assertSame('GEN-PERA', CodigoProducto::base('', 'pera'));
        $this->assertSame('GEN-PERA', CodigoProducto::base('otra', 'pera'));
    }

    public function testNombreSinLetrasUsaPROD(): void
    {
        $this->assertSame('FRU-PROD', CodigoProducto::base('frutas', '***'));
    }

    public function testSiguienteLibreAgregaConsecutivo(): void
    {
        $this->assertSame('FRU-UVAAZU', CodigoProducto::siguienteLibre('FRU-UVAAZU', []));
        $this->assertSame('FRU-UVAAZU-02', CodigoProducto::siguienteLibre('FRU-UVAAZU', ['FRU-UVAAZU']));
        $this->assertSame(
            'FRU-UVAAZU-03',
            CodigoProducto::siguienteLibre('FRU-UVAAZU', ['FRU-UVAAZU', 'FRU-UVAAZU-02'])
        );
    }

    public function testSiguienteLibreRellenaHuecosYNoEsSensibleAMayusculas(): void
    {
        $this->assertSame(
            'FRU-UVAAZU-02',
            CodigoProducto::siguienteLibre('FRU-UVAAZU', ['fru-uvaazu', 'FRU-UVAAZU-03'])
        );
    }
}

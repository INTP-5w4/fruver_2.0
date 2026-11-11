<?php

use App\Libraries\Privacidad\FormularioConsentimiento;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Privacidad;

/**
 * Formato de consentimiento (Aviso de Consentimiento, sección 9).
 *
 * @internal
 */
final class FormularioConsentimientoTest extends CIUnitTestCase
{
    private Privacidad $cfg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cfg = new Privacidad();
    }

    public function testSinDeclaracionNoSePuedeRegistrar(): void
    {
        $r = FormularioConsentimiento::leer([], $this->cfg, true);

        $this->assertArrayHasKey('aviso_leido', $r['errores']);
        $this->assertArrayHasKey('mayor_edad', $r['errores']);
    }

    public function testSoloLaDeclaracionBastaYNadaVoluntarioQuedaAutorizado(): void
    {
        $r = FormularioConsentimiento::leer(['aviso_leido' => '1', 'mayor_edad' => '1'], $this->cfg, true);

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['datos']['acepta_aviso']);
        foreach (['v1', 'v2', 'v3', 'v4', 'canal_telefono', 'canal_mensajeria', 'canal_correo'] as $campo) {
            $this->assertSame(0, $r['datos'][$campo], $campo);
        }
        $this->assertNull($r['datos']['telefono_contacto']);
    }

    public function testCanalesYTelefonoSeDescartanSinFinalidadQueLosUse(): void
    {
        // V4 no usa canal: aunque lleguen canales y teléfono, no se guardan (minimización).
        $r = FormularioConsentimiento::leer(
            ['v4' => '1', 'canal_telefono' => '1', 'tel_contacto' => '2221234567'],
            $this->cfg,
            false
        );

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['datos']['v4']);
        $this->assertSame(0, $r['datos']['canal_telefono']);
        $this->assertNull($r['datos']['telefono_contacto']);
    }

    public function testPromocionesExigenUnCanal(): void
    {
        $r = FormularioConsentimiento::leer(['v1' => '1'], $this->cfg, false);

        $this->assertArrayHasKey('canales', $r['errores']);
    }

    public function testMensajeriaExigeTelefonoDeDiezDigitos(): void
    {
        $mal = FormularioConsentimiento::leer(['v2' => '1', 'canal_mensajeria' => '1', 'tel_contacto' => '12345'], $this->cfg, false);
        $this->assertArrayHasKey('tel_contacto', $mal['errores']);

        $bien = FormularioConsentimiento::leer(['v2' => '1', 'canal_mensajeria' => '1', 'tel_contacto' => '222 123 4567'], $this->cfg, false);
        $this->assertSame([], $bien['errores']);
        $this->assertSame('2221234567', $bien['datos']['telefono_contacto']);
    }

    public function testCorreoNoPideTelefono(): void
    {
        $r = FormularioConsentimiento::leer(['v3' => '1', 'canal_correo' => '1'], $this->cfg, false);

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['datos']['canal_correo']);
        $this->assertNull($r['datos']['telefono_contacto']);
    }

    public function testValoresDistintosDeUnoNoCuentanComoMarcados(): void
    {
        $r = FormularioConsentimiento::leer(['v1' => 'on', 'v2' => ['1'], 'aviso_leido' => 'true'], $this->cfg, true);

        $this->assertSame(0, $r['datos']['v1']);
        $this->assertSame(0, $r['datos']['v2']);
        $this->assertArrayHasKey('aviso_leido', $r['errores']);
    }

    public function testElFormularioSeLlenaConElEstadoVigente(): void
    {
        $vigente = ['v1' => 1, 'v2' => 0, 'v3' => 0, 'v4' => 1, 'canal_correo' => 1, 'telefono_contacto' => null];
        $valores = FormularioConsentimiento::valoresFormulario($this->cfg, $vigente, null);

        $this->assertTrue($valores['v1']);
        $this->assertFalse($valores['v2']);
        $this->assertTrue($valores['v4']);
        $this->assertTrue($valores['canal_correo']);
        $this->assertFalse($valores['aviso_leido']);
        $this->assertSame('', $valores['tel_contacto']);
    }
}

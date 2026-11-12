<?php

use App\Libraries\FiltroGroserias;
use Config\Groserias;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FiltroGroseriasTest extends TestCase
{
    private FiltroGroserias $filtro;

    protected function setUp(): void
    {
        $cfg          = new Groserias();
        $this->filtro = new FiltroGroserias($cfg->bloqueadas, $cfg->permitidas, $cfg->permitidasDentro);
    }

    /** @return iterable<string, array{string}> */
    public static function textosBloqueados(): iterable
    {
        yield 'directa'            => ['eres un pendejo'];
        yield 'mayúsculas'         => ['PENDEJO'];
        yield 'con acento'         => ['estúpido'];
        yield 'números por letras' => ['p3nd3j0'];
        yield 'símbolos'           => ['put@'];
        yield 'letras repetidas'   => ['puuuuuto'];
        yield 'doble repetida'     => ['perrrrra'];
        yield 'separada espacios'  => ['p u t o'];
        yield 'separada puntos'    => ['p.u.t.o'];
        yield 'v por u'            => ['pvto'];
        yield 'k por c'            => ['kabron'];
        yield 'prefijo'            => ['qué chingadera'];
        yield 'frase'              => ['hijo de puta'];
        yield 'abreviatura'        => ['ptm'];
        yield 'con ñ'              => ['coño'];
        yield 'entre puntuación'   => ['¡cabrón!'];
    }

    /** @dataProvider textosBloqueados */
    public function testDetectaGroserias(string $texto): void
    {
        $this->assertNotNull($this->filtro->buscar($texto), $texto);
    }

    /** @return iterable<string, array{string}> */
    public static function textosPermitidos(): iterable
    {
        yield 'ciudad'          => ['Culiacán, Sinaloa'];
        yield 'apellido'        => ['María Cabrera Pitol'];
        yield 'productos'       => ['1 kg de huevos, chile serrano, pepino y plátano'];
        yield 'fruta pera'      => ['peras y perones'];
        yield 'cono'            => ['un cono de helado'];
        yield 'vergüenza'       => ['qué vergüenza'];
        yield 'calle'           => ['Calle Alv. Obregón 15, colonia CTM'];
        yield 'teléfono'        => ['2491086712'];
        yield 'precio'          => ['$500.00'];
        yield 'exclamación'     => ['¡Hola! ¿Cómo estás?'];
        yield 'excepción'       => ['vi un chingolo'];
        yield 'zorro'           => ['El Zorro'];
        yield 'mensaje tienda'  => ['Hola Ana, esta semana tenemos ofertas de temporada en FRUVER.'];
        yield 'vacío'           => [''];
    }

    /** @dataProvider textosPermitidos */
    public function testNoBloqueaTextoNormal(string $texto): void
    {
        $this->assertNull($this->filtro->buscar($texto), $texto);
    }

    public function testUsuariosConPalabrasPegadas(): void
    {
        foreach (["elputo99", "pendejito.mx", "xx.cabron.xx", "chingon2000", "put0n"] as $usuario) {
            $this->assertNotNull($this->filtro->buscarDentro($usuario), $usuario);
        }
        foreach (["ana.lopez", "computo2026", "diputado.mx", "vehiculo1", "articulos", "juan.perez", "calculo.mx"] as $usuario) {
            $this->assertNull($this->filtro->buscarDentro($usuario), $usuario);
        }
    }
}

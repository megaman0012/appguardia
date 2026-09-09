<?php

namespace Tests\Unit;

use App\Support\NombreDePersona;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NombreDePersonaTest extends TestCase
{
    #[Test]
    public function el_nombre_completo_va_con_los_apellidos_primero(): void
    {
        // La decision del 2026-09-09. Asi el listado de 880 personas queda
        // ordenado por apellido, que es como se busca a alguien en una nomina.
        $r = NombreDePersona::componer('MARIA FERNANDA', 'CASTRO CEDEÑO');

        $this->assertSame('CASTRO CEDEÑO MARIA FERNANDA', $r['usu_nmbcom']);
        $this->assertSame('MARIA', $r['usu_nmb1']);
        $this->assertSame('FERNANDA', $r['usu_nmb2']);
        $this->assertSame('CASTRO', $r['usu_ape1']);
        $this->assertSame('CEDEÑO', $r['usu_ape2']);
    }

    #[Test]
    public function con_un_solo_nombre_se_repite_el_primero(): void
    {
        // Las cuatro columnas son NOT NULL. Repetir es lo que hizo el ETL con
        // los 665 usuarios que vinieron sin desglosar; cambiarlo ahora dejaria
        // dos convenciones conviviendo en la misma tabla.
        $r = NombreDePersona::componer('JUAN', 'PEREZ');

        $this->assertSame('JUAN', $r['usu_nmb1']);
        $this->assertSame('JUAN', $r['usu_nmb2']);
        $this->assertSame('PEREZ', $r['usu_ape1']);
        $this->assertSame('PEREZ', $r['usu_ape2']);
        $this->assertSame('PEREZ JUAN', $r['usu_nmbcom']);
    }

    #[Test]
    public function los_espacios_de_mas_no_se_cuelan(): void
    {
        // Llegan solos al copiar y pegar de una planilla, y un nombre con dos
        // espacios en el medio rompe la busqueda por texto.
        $r = NombreDePersona::componer("  MARIA   FERNANDA \n", '  CASTRO  CEDEÑO  ');

        $this->assertSame('CASTRO CEDEÑO MARIA FERNANDA', $r['usu_nmbcom']);
    }

    #[Test]
    public function ida_y_vuelta_de_un_usuario_con_desglose(): void
    {
        $columnas = NombreDePersona::componer('MARLON ALFREDO', 'REA ESCALANTE');
        $partes   = NombreDePersona::descomponer($columnas);

        $this->assertSame('MARLON ALFREDO', $partes['nombres']);
        $this->assertSame('REA ESCALANTE', $partes['apellidos']);
        $this->assertFalse($partes['adivinado'], 'Con desglose real no se adivina nada.');
    }

    #[Test]
    public function la_repeticion_del_etl_no_se_muestra_dos_veces(): void
    {
        // `usu_nmb2 = usu_nmb1` significa «no tiene segundo nombre», no que se
        // llame JUAN JUAN. Si el formulario mostrara la repeticion, guardar
        // volveria a duplicarla y el nombre creceria en cada edicion.
        $partes = NombreDePersona::descomponer([
            'usu_nmbcom' => 'PEREZ JUAN', 'usu_nmb1' => 'JUAN', 'usu_nmb2' => 'JUAN',
            'usu_ape1' => 'PEREZ', 'usu_ape2' => 'PEREZ',
        ]);

        $this->assertSame('JUAN', $partes['nombres']);
        $this->assertSame('PEREZ', $partes['apellidos']);
    }

    #[Test]
    public function sin_desglose_se_supone_y_se_avisa(): void
    {
        // 749 de 880 usuarios estan asi. No hay forma de saber donde terminan
        // los apellidos, se asume el patron dominante y **se marca como
        // adivinado** para que el formulario lo advierta.
        $partes = NombreDePersona::descomponer([
            'usu_nmbcom' => 'CHALEN FIGUEROA RUBEN ANDRES',
            'usu_nmb1' => '', 'usu_nmb2' => '', 'usu_ape1' => '', 'usu_ape2' => '',
        ]);

        $this->assertSame('CHALEN FIGUEROA', $partes['apellidos']);
        $this->assertSame('RUBEN ANDRES', $partes['nombres']);
        $this->assertTrue($partes['adivinado']);
    }

    #[Test]
    public function el_apellido_compuesto_sale_mal_y_esta_asumido(): void
    {
        // ⚠️ Este test NO describe lo correcto: describe lo que pasa. «DE LA
        // TORRE» son tres palabras de apellido y la suposicion corta en dos, asi
        // que sale «DE LA» + «TORRE PEREZ JUAN». No hay heuristica que lo
        // resuelva bien, por eso `adivinado` es true y el formulario avisa.
        //
        // Si algun dia se agrega una lista de particulas («DE», «DEL», «LA»,
        // «LOS», «VAN»), este test cambia -- y ese es el punto de tenerlo.
        $partes = NombreDePersona::descomponer([
            'usu_nmbcom' => 'DE LA TORRE PEREZ JUAN',
            'usu_nmb1' => '', 'usu_ape1' => '',
        ]);

        $this->assertSame('DE LA', $partes['apellidos']);
        $this->assertSame('TORRE PEREZ JUAN', $partes['nombres']);
        $this->assertTrue($partes['adivinado'], 'Lo importante es que quede marcado como suposicion.');
    }

    #[Test]
    #[DataProvider('nombresCortos')]
    public function con_pocas_palabras_no_se_inventa_un_nombre(string $completo, string $apellidos, string $nombres): void
    {
        $partes = NombreDePersona::descomponer(['usu_nmbcom' => $completo, 'usu_nmb1' => '', 'usu_ape1' => '']);

        $this->assertSame($apellidos, $partes['apellidos']);
        $this->assertSame($nombres, $partes['nombres']);
    }

    /** @return array<string, array{string, string, string}> */
    public static function nombresCortos(): array
    {
        return [
            'una palabra'  => ['MERCEDES', 'MERCEDES', ''],
            'dos palabras' => ['PEREZ JUAN', 'PEREZ JUAN', ''],
            'vacio'        => ['', '', ''],
        ];
    }

    #[Test]
    public function los_tres_caminos_de_alta_producen_lo_mismo(): void
    {
        // El motivo de que exista esta clase: la logica estaba duplicada en
        // `UsuarioImportService` y en `CrearUsuario`, y ademas el formulario del
        // panel pedia las cinco columnas a mano. La carga masiva escribia el
        // nombre completo al REVES que el resto.
        $esperado = NombreDePersona::componer('ANA MARIA', 'LOPEZ SUAREZ');

        $this->assertSame('LOPEZ SUAREZ ANA MARIA', $esperado['usu_nmbcom']);
        $this->assertStringNotContainsString('ANA MARIA LOPEZ', $esperado['usu_nmbcom']);
    }
}

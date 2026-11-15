<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Region::create(['idRegion' => '01', 'nombreRegion' => 'AMAZONAS', 'pointX' => -78.0, 'pointY' => -5.5, 'zoom' => 7]);
        Region::create(['idRegion' => '02', 'nombreRegion' => 'ANCASH', 'pointX' => -77.6, 'pointY' => -9.4, 'zoom' => 7]);

        Province::create(['idProvincia' => '0101', 'nombreProvincia' => 'CHACHAPOYAS', 'pointX' => -77.87, 'pointY' => -6.23]);
        Province::create(['idProvincia' => '0102', 'nombreProvincia' => 'BAGUA', 'pointX' => -78.53, 'pointY' => -5.64]);
        Province::create(['idProvincia' => '0201', 'nombreProvincia' => 'HUARAZ', 'pointX' => -77.53, 'pointY' => -9.53]);

        District::create(['idDistrito' => '010101', 'nombreDistrito' => 'CHACHAPOYAS']);
        District::create(['idDistrito' => '010102', 'nombreDistrito' => 'ASUNCION']);
        District::create(['idDistrito' => '010201', 'nombreDistrito' => 'BAGUA']);

        School::create(['codMod' => '0123456', 'idDistrito' => '010101', 'nombreCenEdu' => 'IE 18001', 'cenPob' => 'CHACHAPOYAS', 'dirCen' => 'JR. AMAZONAS 100']);
        School::create(['codMod' => '0654321', 'idDistrito' => '010102', 'nombreCenEdu' => 'IE 18002']);
    }

    public function test_lista_todas_las_regiones(): void
    {
        $this->getJson('/api/regiones')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.idRegion', '01')
            ->assertJsonPath('data.0.nombreRegion', 'AMAZONAS');
    }

    public function test_tipos_del_json_de_regiones(): void
    {
        // Coordenadas como texto con 7 decimales (igual que DECIMAL(10,7) en MySQL) y zoom como entero.
        $this->getJson('/api/regiones')
            ->assertJsonPath('data.0.pointX', '-78.0000000')
            ->assertJsonPath('data.0.pointY', '-5.5000000')
            ->assertJsonPath('data.0.zoom', 7);
    }

    public function test_lista_provincias_de_una_region(): void
    {
        $this->getJson('/api/provincias/01')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.idProvincia', '0101')
            ->assertJsonPath('data.0.pointX', '-77.8700000');
    }

    public function test_provincias_inexistentes_responden_404(): void
    {
        $this->getJson('/api/provincias/99')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron provincias para esta región.',
            ]);
    }

    public function test_lista_distritos_de_una_provincia(): void
    {
        $this->getJson('/api/distritos/0101')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.idDistrito', '010102');
    }

    public function test_distritos_inexistentes_responden_404(): void
    {
        $this->getJson('/api/distritos/9999')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron distritos para esta provincia.',
            ]);
    }

    public function test_lista_colegios_de_un_distrito(): void
    {
        $this->getJson('/api/colegios/010101')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codMod', '0123456')
            ->assertJsonPath('data.0.nombreCenEdu', 'IE 18001')
            ->assertJsonPath('data.0.director', null);
    }

    public function test_colegios_inexistentes_responden_404(): void
    {
        $this->getJson('/api/colegios/999999')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 'error',
                'message' => 'No se encontraron escuelas para este distrito.',
            ]);
    }

    public function test_ruta_api_desconocida_responde_json_aunque_no_se_pida(): void
    {
        $this->get('/api/no-existe')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_cors_abierto_para_cualquier_origen(): void
    {
        $this->getJson('/api/regiones', ['Origin' => 'http://cliente.example'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_limite_de_60_peticiones_por_minuto(): void
    {
        $this->getJson('/api/regiones')->assertHeader('X-RateLimit-Limit', '60');

        for ($i = 0; $i < 59; $i++) {
            $this->getJson('/api/regiones');
        }

        $this->getJson('/api/regiones')->assertStatus(429);
    }
}

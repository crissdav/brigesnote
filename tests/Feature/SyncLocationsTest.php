<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncLocationsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://escale.minedu.gob.pe/padron/rest';

    protected function setUp(): void
    {
        parent::setUp();

        // Sin esperas entre reintentos durante las pruebas.
        config(['services.escale.retries' => 1]);
        Http::preventStrayRequests();
    }

    public function test_sincroniza_listas_y_objetos_sueltos(): void
    {
        Http::fake([
            self::BASE.'/regiones*' => Http::response(['region' => [
                ['idRegion' => '01', 'nombreRegion' => 'AMAZONAS', 'pointX' => -78.0, 'pointY' => -5.5, 'zoom' => 7],
            ]]),
            // Una sola provincia: ESCALE la devuelve como objeto, no como lista.
            self::BASE.'/provincias*' => Http::response(['provincia' => [
                'idProvincia' => '0101', 'nombreProvincia' => 'CHACHAPOYAS', 'pointX' => -77.87, 'pointY' => -6.23,
            ]]),
            self::BASE.'/distritos*' => Http::response(['distrito' => [
                ['idDistrito' => '010101', 'nombreDistrito' => 'CHACHAPOYAS'],
                ['idDistrito' => '010102', 'nombreDistrito' => 'ASUNCION'],
            ]]),
            self::BASE.'/instituciones*' => function (Request $request) {
                return $request['ubigeo'] === '010101'
                    // Lista con un colegio al que le faltan campos (antes rompía en PHP 8).
                    ? Http::response(['items' => [
                        ['codMod' => '0123456', 'cenEdu' => 'IE 18001', 'cenPob' => 'CHACHAPOYAS', 'dirCen' => 'JR. AMAZONAS 100', 'director' => 'PEREZ'],
                        ['codMod' => '0123457', 'cenEdu' => 'IE 18003'],
                    ]])
                    // Distrito sin colegios: ESCALE no envía "items".
                    : Http::response([]);
            },
        ]);

        $this->artisan('sync:locations')
            ->expectsOutput('No se encontraron registros de escuelas')
            ->expectsOutput('Sincronización completa.')
            ->assertSuccessful();

        $this->assertSame(1, Region::count());
        $this->assertSame(7, Region::find('01')->zoom);
        $this->assertSame('CHACHAPOYAS', Province::find('0101')->nombreProvincia);
        $this->assertSame(2, District::count());
        $this->assertSame(2, School::count());

        $school = School::find('0123457');
        $this->assertSame('010101', $school->idDistrito);
        $this->assertNull($school->dirCen);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), self::BASE.'/instituciones')
            && $r['estados'] == 1 && $r['ubigeo'] === '010102');
    }

    public function test_pagina_los_colegios_de_un_distrito(): void
    {
        // ESCALE corta en 50 por defecto; aquí la página es de 2 para simular varias páginas.
        config(['services.escale.page_size' => 2]);

        $all = [
            ['codMod' => 'A1', 'cenEdu' => 'IE 1'],
            ['codMod' => 'A2', 'cenEdu' => 'IE 2'],
            ['codMod' => 'A3', 'cenEdu' => 'IE 3'],
            ['codMod' => 'A4', 'cenEdu' => 'IE 4'],
            ['codMod' => 'A5', 'cenEdu' => 'IE 5'],
        ];

        Http::fake([
            self::BASE.'/regiones*' => Http::response(['region' => [['idRegion' => '15', 'nombreRegion' => 'LIMA']]]),
            self::BASE.'/provincias*' => Http::response(['provincia' => ['idProvincia' => '1501', 'nombreProvincia' => 'LIMA']]),
            self::BASE.'/distritos*' => Http::response(['distrito' => ['idDistrito' => '150101', 'nombreDistrito' => 'LIMA']]),
            self::BASE.'/instituciones*' => function (Request $request) use ($all) {
                $page = array_slice($all, (int) $request['start'], (int) $request['max']);

                // Con un único resultado ESCALE devuelve un objeto, no una lista.
                return Http::response(['items' => count($page) === 1 ? $page[0] : $page]);
            },
        ]);

        $this->artisan('sync:locations')->assertSuccessful();

        $this->assertSame(5, School::where('idDistrito', '150101')->count());
        Http::assertSentCount(3 + 3); // región, provincia, distrito + 3 páginas de colegios
    }

    public function test_es_idempotente_y_actualiza_registros(): void
    {
        $this->fakeSingleSchool();

        $this->schoolName = 'IE ORIGINAL';
        $this->artisan('sync:locations')->assertSuccessful();

        $this->schoolName = 'IE RENOMBRADA';
        $this->artisan('sync:locations')->assertSuccessful();

        $this->assertSame(1, School::count());
        $this->assertSame('IE RENOMBRADA', School::find('0000001')->nombreCenEdu);
    }

    public function test_un_error_http_no_detiene_el_resto_y_termina_con_fallo(): void
    {
        Http::fake([
            self::BASE.'/regiones*' => Http::response(['region' => [
                ['idRegion' => '01', 'nombreRegion' => 'AMAZONAS'],
                ['idRegion' => '02', 'nombreRegion' => 'ANCASH'],
            ]]),
            self::BASE.'/provincias*' => function (Request $request) {
                return $request['idRegion'] === '01'
                    ? Http::response('error', 500)
                    : Http::response(['provincia' => ['idProvincia' => '0201', 'nombreProvincia' => 'HUARAZ']]);
            },
            self::BASE.'/distritos*' => Http::response(['distrito' => ['idDistrito' => '020101', 'nombreDistrito' => 'HUARAZ']]),
            self::BASE.'/instituciones*' => Http::response(['items' => ['codMod' => '0999999', 'cenEdu' => 'IE HUARAZ']]),
        ]);

        $this->artisan('sync:locations')
            ->expectsOutput('Error al obtener provincias para la región 01')
            ->assertFailed();

        $this->assertSame(2, Region::count());
        $this->assertNotNull(Province::find('0201'));
        $this->assertSame('020101', School::find('0999999')->idDistrito);
    }

    public function test_falla_si_no_se_pueden_obtener_regiones(): void
    {
        Http::fake([self::BASE.'/regiones*' => Http::response('', 503)]);

        $this->artisan('sync:locations')
            ->expectsOutput('Error al obtener regiones')
            ->assertFailed();

        $this->assertSame(0, Region::count());
    }

    private string $schoolName = '';

    private function fakeSingleSchool(): void
    {
        Http::fake([
            self::BASE.'/regiones*' => Http::response(['region' => [['idRegion' => '01', 'nombreRegion' => 'AMAZONAS']]]),
            self::BASE.'/provincias*' => Http::response(['provincia' => ['idProvincia' => '0101', 'nombreProvincia' => 'CHACHAPOYAS']]),
            self::BASE.'/distritos*' => Http::response(['distrito' => ['idDistrito' => '010101', 'nombreDistrito' => 'CHACHAPOYAS']]),
            self::BASE.'/instituciones*' => fn () => Http::response(['items' => ['codMod' => '0000001', 'cenEdu' => $this->schoolName]]),
        ]);
    }
}

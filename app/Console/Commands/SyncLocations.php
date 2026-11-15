<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SyncLocations extends Command
{
    protected $signature = 'sync:locations';

    protected $description = 'Sincroniza regiones, provincias, distritos y escuelas desde la API y las registra en la base de datos';

    /** Cantidad de llamadas a ESCALE que fallaron durante la ejecución. */
    private int $failures = 0;

    public function handle(): int
    {
        $this->info('Iniciando sincronización de ubicaciones...');

        $this->syncRegions();

        if ($this->failures > 0) {
            $this->warn("Sincronización completa con {$this->failures} error(es). Revise los mensajes anteriores.");

            return self::FAILURE;
        }

        $this->info('Sincronización completa.');

        return self::SUCCESS;
    }

    private function syncRegions(): void
    {
        $this->info('Sincronizando regiones...');

        $regions = $this->fetchList('regiones', [], 'region', 'idRegion');

        if ($regions === null) {
            $this->error('Error al obtener regiones');

            return;
        }

        foreach ($regions as $region) {
            Region::updateOrCreate(
                ['idRegion' => $region['idRegion']],
                [
                    'nombreRegion' => $region['nombreRegion'] ?? null,
                    'pointX' => $region['pointX'] ?? null,
                    'pointY' => $region['pointY'] ?? null,
                    'zoom' => $region['zoom'] ?? null,
                ]
            );

            $this->syncProvinces($region['idRegion']);
        }

        $this->info('Regiones sincronizadas correctamente.');
    }

    private function syncProvinces(string $regionId): void
    {
        $this->info("Sincronizando provincias para la región $regionId...");

        $provinces = $this->fetchList('provincias', ['idRegion' => $regionId], 'provincia', 'idProvincia');

        if ($provinces === null) {
            $this->error("Error al obtener provincias para la región $regionId");

            return;
        }

        foreach ($provinces as $province) {
            Province::updateOrCreate(
                ['idProvincia' => $province['idProvincia']],
                [
                    'nombreProvincia' => $province['nombreProvincia'] ?? null,
                    'pointX' => $province['pointX'] ?? null,
                    'pointY' => $province['pointY'] ?? null,
                ]
            );

            $this->syncDistricts($province['idProvincia']);
        }

        $this->info("Provincias para la región $regionId sincronizadas correctamente.");
    }

    private function syncDistricts(string $provinceId): void
    {
        $this->info("Sincronizando distritos para la provincia $provinceId...");

        $districts = $this->fetchList('distritos', ['idProvincia' => $provinceId], 'distrito', 'idDistrito');

        if ($districts === null) {
            $this->error("Error al obtener distritos para la provincia $provinceId");

            return;
        }

        foreach ($districts as $district) {
            District::updateOrCreate(
                ['idDistrito' => $district['idDistrito']],
                [
                    'nombreDistrito' => $district['nombreDistrito'] ?? null,
                ]
            );

            $this->syncSchools($district['idDistrito']);
        }

        $this->info("Distritos para la provincia $provinceId sincronizados correctamente.");
    }

    private function syncSchools(string $districtId): void
    {
        $this->info("Sincronizando escuelas para el distrito $districtId...");

        // ESCALE devuelve 50 registros por defecto; se pagina con "start" y "max".
        $pageSize = max(1, (int) config('services.escale.page_size'));
        $schools = [];

        for ($start = 0; ; $start += $pageSize) {
            $json = $this->request('instituciones', [
                'estados' => 1,
                'ubigeo' => $districtId,
                'start' => $start,
                'max' => $pageSize,
            ]);

            if ($json === null) {
                $this->error("Error al obtener escuelas para el distrito $districtId");

                return;
            }

            $page = $this->normalizeList($json['items'] ?? [], 'codMod');
            array_push($schools, ...$page);

            if (count($page) < $pageSize) {
                break;
            }
        }

        if ($schools === []) {
            // Un distrito sin colegios activos es un caso normal, no un fallo.
            $this->error('No se encontraron registros de escuelas');

            return;
        }

        foreach ($schools as $school) {
            School::updateOrCreate(
                ['codMod' => $school['codMod']],
                [
                    'idDistrito' => $districtId,
                    'nombreCenEdu' => $school['cenEdu'] ?? null,
                    'cenPob' => $school['cenPob'] ?? null,
                    'dirCen' => $school['dirCen'] ?? null,
                    'director' => $school['director'] ?? null,
                    'dis_crecer' => $school['dis_crecer'] ?? null,
                    'dis_juntos' => $school['dis_juntos'] ?? null,
                ]
            );
        }

        $this->info("Escuelas para el distrito $districtId sincronizadas correctamente.");
    }

    /**
     * Obtiene la lista bajo $key de la respuesta, o null si la llamada falló.
     *
     * @return list<array<string, mixed>>|null
     */
    private function fetchList(string $endpoint, array $query, string $key, string $idField): ?array
    {
        $json = $this->request($endpoint, $query);

        if ($json === null) {
            return null;
        }

        return $this->normalizeList($json[$key] ?? [], $idField);
    }

    /**
     * Hace un GET a ESCALE con timeout y reintentos. Devuelve el JSON decodificado
     * o null si la llamada falló (y la cuenta como fallo).
     */
    private function request(string $endpoint, array $query): ?array
    {
        $config = config('services.escale');

        try {
            $response = Http::acceptJson()
                ->timeout($config['timeout'])
                // Espera creciente (1 s, 2 s, 3 s...) para tolerar cortes breves de red o DNS.
                ->retry($config['retries'], fn (int $attempt) => $attempt * 1000, throw: false)
                ->get(rtrim($config['base_url'], '/').'/'.$endpoint, $query);
        } catch (ConnectionException $e) {
            $this->failures++;
            $this->error("Sin conexión con ESCALE ($endpoint): {$e->getMessage()}");

            return null;
        }

        $json = $response->json();

        if ($response->failed() || ! is_array($json)) {
            $this->failures++;

            return null;
        }

        return $json;
    }

    /**
     * ESCALE devuelve un objeto suelto cuando hay un solo resultado y una lista
     * cuando hay varios. Esta función siempre devuelve una lista y descarta
     * los elementos sin identificador.
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeList(mixed $items, string $idField): array
    {
        if (! is_array($items)) {
            return [];
        }

        if (isset($items[$idField])) {
            $items = [$items];
        }

        return array_values(array_filter(
            $items,
            fn ($item) => is_array($item) && isset($item[$idField]) && $item[$idField] !== ''
        ));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use App\Models\Region;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class LocationController extends Controller
{
    // Obtener todas las regiones desde la base de datos
    public function getRegions(): JsonResponse
    {
        return $this->success(Region::all());
    }

    // Obtener provincias de una región (los 2 primeros caracteres de idProvincia = idRegion)
    public function getProvinces(string $idRegion): JsonResponse
    {
        // SUBSTR funciona igual en MySQL y SQLite (LEFT no existe en SQLite, usado en los tests).
        $provinces = Province::whereRaw('SUBSTR(idProvincia, 1, 2) = ?', [$idRegion])->get();

        return $provinces->isEmpty()
            ? $this->notFound('No se encontraron provincias para esta región.')
            : $this->success($provinces);
    }

    // Obtener distritos de una provincia (los 4 primeros caracteres de idDistrito = idProvincia)
    public function getDistricts(string $idProvince): JsonResponse
    {
        $districts = District::whereRaw('SUBSTR(idDistrito, 1, 4) = ?', [$idProvince])->get();

        return $districts->isEmpty()
            ? $this->notFound('No se encontraron distritos para esta provincia.')
            : $this->success($districts);
    }

    // Obtener escuelas de un distrito (según idDistrito / ubigeo completo)
    public function getSchools(string $ubigeo): JsonResponse
    {
        $schools = School::where('idDistrito', $ubigeo)->get();

        return $schools->isEmpty()
            ? $this->notFound('No se encontraron escuelas para este distrito.')
            : $this->success($schools);
    }

    private function success(Collection $data): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], 404);
    }
}

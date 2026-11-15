<?php

use Illuminate\Support\Facades\Route;

// Proyecto solo-API: la raíz devuelve una descripción breve en JSON en lugar de una vista.
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'logo' => config('app.logo'),
        'endpoints' => [
            'GET /api/regiones',
            'GET /api/provincias/{idRegion}',
            'GET /api/distritos/{idProvince}',
            'GET /api/colegios/{ubigeo}',
        ],
    ]);
});

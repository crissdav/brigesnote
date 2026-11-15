<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'regions';

    protected $primaryKey = 'idRegion';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'idRegion',
        'nombreRegion',
        'pointX',
        'pointY',
        'zoom',
    ];

    /**
     * Tipos explícitos para que el JSON no dependa del driver de PDO.
     * Las coordenadas se mantienen como texto con 7 decimales (igual que
     * DECIMAL(10,7) en MySQL); zoom se expone como entero.
     */
    protected function casts(): array
    {
        return [
            'pointX' => 'decimal:7',
            'pointY' => 'decimal:7',
            'zoom' => 'integer',
        ];
    }
}

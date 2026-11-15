<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $table = 'provinces';

    protected $primaryKey = 'idProvincia';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'idProvincia',
        'nombreProvincia',
        'pointX',
        'pointY',
    ];

    protected function casts(): array
    {
        return [
            'pointX' => 'decimal:7',
            'pointY' => 'decimal:7',
        ];
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Los datos de ubigeo y colegios no se siembran: se obtienen de MINEDU
     * con `php artisan sync:locations`.
     */
    public function run(): void
    {
        //
    }
}

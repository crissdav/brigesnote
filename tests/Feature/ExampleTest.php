<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_describe_la_api(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertJsonPath('name', config('app.name'))
            ->assertJsonFragment(['GET /api/regiones']);
    }

    public function test_endpoint_de_salud(): void
    {
        $this->get('/up')->assertOk();
    }
}

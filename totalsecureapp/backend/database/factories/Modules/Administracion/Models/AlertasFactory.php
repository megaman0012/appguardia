<?php

namespace Database\Factories\Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

class AlertasFactory extends Factory
{
    protected $model = \Modules\Administracion\Models\Alertas::class;

    public function definition(): array
    {
        return [
            'al_ins_code'      => 1,
            'al_usu_id'        => 1,
            'al_lat'           => $this->faker->latitude(-1, 0),
            'al_lng'           => $this->faker->longitude(-79, -78),
            'al_anio'          => now()->year,
            'al_estado_alerta' => 'pendiente',
            'al_estado'        => 1,
            'al_prioridad'     => 'media',
            'al_observacion'   => $this->faker->sentence(),
            'al_fecha'         => now(),
            'al_created_user'  => 1,
        ];
    }
}

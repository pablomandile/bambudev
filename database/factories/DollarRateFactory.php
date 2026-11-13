<?php

namespace Database\Factories;

use App\Models\DollarRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DollarRate>
 */
class DollarRateFactory extends Factory
{
    public function definition(): array
    {
        $sell = fake()->randomFloat(2, 1400, 1700);
        $date = fake()->dateTimeBetween('-1 month', 'yesterday');

        return [
            'type' => 'blue',
            'date' => $date->format('Y-m-d'),
            'buy' => $sell - 20,
            'sell' => $sell,
            'source' => 'DolarApi',
            'captured_at' => $date,
        ];
    }
}

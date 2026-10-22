<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\MicroApp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MicroApp>
 */
class MicroAppFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::ucfirst(fake()->unique()->word()).'App';

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'category_id' => Category::factory(),
            'icon' => 'ph-lightning',
            'hue' => fake()->randomElement([140, 20, 60, 85, 330, 275, 235, 195]),
            'tags' => null,
            'visible' => true,
            'target_type' => MicroApp::TARGET_EXTERNAL,
            'target' => 'https://example.com/'.Str::slug($name),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['visible' => false]);
    }

    public function internal(string $path): static
    {
        return $this->state(fn () => [
            'target_type' => MicroApp::TARGET_INTERNAL,
            'target' => $path,
        ]);
    }
}

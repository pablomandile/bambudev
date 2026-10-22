<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => config('bambudev.admin.email')],
            [
                'name' => config('bambudev.admin.name'),
                'password' => config('bambudev.admin.password'),
                'email_verified_at' => now(),
            ],
        );
        $admin->forceFill(['is_admin' => true])->save();
    }
}

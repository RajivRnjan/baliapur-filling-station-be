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
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'msbaliapurfillingstation@gmail.com')],
            [
                'name' => 'Super Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'SUPER_ADMIN',
            ]
        );

        $this->call([
            NozzleSeeder::class,
            LubricantSeeder::class,
            CreditorSeeder::class,
        ]);
    }
}

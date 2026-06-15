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

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'root@abemandiri.co.id',
            'password' => bcrypt('password'),
            'level' => 0,
            'is_active' => true,
        ]);

        $admin->email_verified_at = now();
        $admin->save();
    }
}

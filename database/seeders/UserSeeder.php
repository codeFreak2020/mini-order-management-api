<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the users table with a demo user plus 50 random users.
     */
    public function run(): void
    {
        // Demo user with a known password for manual testing.
        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        User::factory()->count(50)->create();
    }
}

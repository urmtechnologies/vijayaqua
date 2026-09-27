<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'info@vijayaqua.com')->exists()) {
            $this->command->info('Admin email already exists.');

            return;
        }

        $password = $this->command->secret('Admin@143');

        User::create([
            'name' => 'Arjun Yadav',
            'email' => 'info@vijayaqua.com',
            'mobile' => '1234567890',
            'role' => 'admin',
            'password' => 'Admin@143',
        ]);

        $this->command->info('Admin created successfully.');
    }
}

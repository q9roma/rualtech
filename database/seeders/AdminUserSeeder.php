<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Удаляем существующих пользователей
        User::truncate();
        
        User::create([
            'name' => 'Admin',
            'email' => 'admin@test.ru',
            'password' => Hash::make('password'),
        ]);
        
        User::create([
            'name' => 'RuAltech Admin',
            'email' => 'admin@rualtech.ru',
            'password' => Hash::make('admin123'),
        ]);
    }
}

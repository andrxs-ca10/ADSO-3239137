<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $configuredUsername = config('initial_admin.username');
        $password = config('initial_admin.password');

        $username = is_string($configuredUsername)
            ? Str::lower(trim($configuredUsername))
            : '';

        if ($username === '' || Str::length($username) > 255) {
            throw new RuntimeException('Configura un ADMIN_USERNAME de entre 1 y 255 caracteres.');
        }

        if (
            ! is_string($password)
            || mb_strlen($password) < 8
            || strlen($password) > 72
            || str_contains($password, "\0")
        ) {
            throw new RuntimeException('Configura un ADMIN_PASSWORD de al menos 8 caracteres y hasta 72 bytes, sin caracteres nulos.');
        }

        $user = User::query()->firstOrCreate(
            ['username' => $username],
            [
                'password_hash' => Hash::make($password),
                'role' => 'admin',
            ],
        );

        if ($user->role !== 'admin') {
            throw new RuntimeException('ADMIN_USERNAME ya pertenece a un usuario que no es administrador.');
        }
    }
}
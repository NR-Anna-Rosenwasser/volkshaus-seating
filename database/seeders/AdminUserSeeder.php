<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('seating.admin.email');
        $password = config('seating.admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL or ADMIN_PASSWORD is not set; skipping the admin user.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => config('seating.admin.name'), 'password' => $password],
        );
    }
}

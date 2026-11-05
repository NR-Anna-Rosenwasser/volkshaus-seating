<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;

beforeEach(fn () => config([
    'seating.admin.email' => 'admin@example.test',
    'seating.admin.password' => 'secret-password',
]));

it('seeds the admin from the environment and stays idempotent', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::count())->toBe(1)
        ->and(User::first()->email)->toBe('admin@example.test');
});

it('skips the admin user when no credentials are configured', function () {
    config(['seating.admin.password' => null]);

    $this->seed(AdminUserSeeder::class);

    expect(User::count())->toBe(0);
});

it('sends guests to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk();
});

it('lets the seeded admin into the panel outside local environments', function () {
    $this->seed(AdminUserSeeder::class);

    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::first())->get('/admin')->assertOk();
});

<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('vatsim oauth redirect works', function () {
    $response = $this->get(route('auth.vatsim'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('auth');
});

test('vatsim oauth callback creates new user', function () {
    // Mock VATSIM API responses
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        '*/api/user' => Http::response([
            'data' => [
                'cid' => 1234567,
                'personal' => [
                    'name_first' => 'John',
                    'name_last' => 'Doe',
                    'email' => 'john.doe@vatsim.net',
                ],
                'vatsim' => [
                    'rating' => [
                        'id' => 3,
                    ],
                    'subdivision' => [
                        'code' => 'GER',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->get(route('auth.vatsim.callback', [
        'code' => 'test_code',
        'state' => 'test_state',
    ]));

    $this->assertDatabaseHas('users', [
        'vatsim_id' => 1234567,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@vatsim.net',
        'rating' => 3,
        'subdivision' => 'GER',
    ]);

    $this->assertAuthenticated();
});

test('vatsim oauth callback updates existing user', function () {
    $existingUser = User::factory()->create([
        'vatsim_id' => 1234567,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'rating' => 2,
        'subdivision' => 'GER',
    ]);

    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        '*/api/user' => Http::response([
            'data' => [
                'cid' => 1234567,
                'personal' => [
                    'name_first' => 'John',
                    'name_last' => 'Doe',
                    'email' => 'john.doe@vatsim.net',
                ],
                'vatsim' => [
                    'rating' => [
                        'id' => 3, // Updated rating
                    ],
                    'subdivision' => [
                        'code' => 'GER',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->get(route('auth.vatsim.callback', [
        'code' => 'test_code',
        'state' => 'test_state',
    ]));

    $existingUser->refresh();
    expect($existingUser->rating)->toBe(3); // Rating should be updated
    $this->assertAuthenticated();
});

test('vatsim oauth assigns roles based on teams', function () {
    Role::factory()->create(['name' => 'EDGG Mentor']);
    Role::factory()->create(['name' => 'ATD Leitung']);

    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        '*/api/user' => Http::response([
            'data' => [
                'cid' => 1234567,
                'personal' => [
                    'name_first' => 'Mentor',
                    'name_last' => 'User',
                    'email' => 'mentor@vatsim.net',
                ],
                'vatsim' => [
                    'rating' => [
                        'id' => 5,
                    ],
                    'subdivision' => [
                        'code' => 'GER',
                    ],
                ],
                'teams' => ['EDGG Mentor', 'ATD Leitung'],
            ],
        ], 200),
    ]);

    $this->get(route('auth.vatsim.callback', [
        'code' => 'test_code',
        'state' => 'test_state',
    ]));

    $user = User::where('vatsim_id', 1234567)->first();
    expect($user->roles)->toHaveCount(2)
        ->and($user->isMentor())->toBeTrue()
        ->and($user->isLeadership())->toBeTrue()
        ->and($user->is_staff)->toBeTrue()
        ->and($user->is_superuser)->toBeTrue();
});

test('admin login works with correct credentials', function () {
    $admin = User::factory()->create([
        'vatsim_id' => 9000001,
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($admin);
});

test('admin login fails with incorrect credentials', function () {
    User::factory()->create([
        'vatsim_id' => 9000001,
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect($response->getSession()->get('errors')->first('email'))
        ->toContain('Too many login attempts');
});

test('non admin users cannot login via admin login', function () {
    $regularUser = User::factory()->create([
        'vatsim_id' => 1234567,
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
        'is_admin' => false,
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create(['vatsim_id' => 1234567]);

    $this->actingAs($user);

    $response = $this->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

test('oauth code cannot be reused', function () {
    Http::fake([
        '*/oauth/token' => Http::response([
            'access_token' => 'test_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], 200),
        '*/api/user' => Http::response([
            'data' => [
                'cid' => 1234567,
                'personal' => [
                    'name_first' => 'John',
                    'name_last' => 'Doe',
                    'email' => 'john.doe@vatsim.net',
                ],
                'vatsim' => [
                    'rating' => ['id' => 3],
                    'subdivision' => ['code' => 'GER'],
                ],
            ],
        ], 200),
    ]);

    $code = 'test_code_unique';

    // First use - should work
    $this->get(route('auth.vatsim.callback', [
        'code' => $code,
        'state' => 'test_state',
    ]))->assertRedirect();

    // Logout
    $this->post(route('logout'));

    // Second use - should fail
    $response = $this->get(route('auth.vatsim.callback', [
        'code' => $code,
        'state' => 'test_state',
    ]));

    $response->assertRedirect(route('login'));
});
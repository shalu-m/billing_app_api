<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_use_token_and_logout(): void
    {
        User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->getJson('/api/config')->assertStatus(401);

        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $loginResponse
            ->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'name',
                    'username',
                    'role',
                    'navigation' => [
                        'default_shop',
                        'shops' => [
                            [
                                'key',
                                'label',
                                'default_page',
                                'pages',
                            ],
                        ],
                    ],
                ],
            ]);

        $token = $loginResponse->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/config')
            ->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.navigation.default_shop', 'supermarket')
            ->assertJsonPath('user.navigation.shops.0.key', 'supermarket')
            ->assertJsonPath('user.navigation.shops.0.pages.4.key', 'reports');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/config')
            ->assertStatus(401);
    }

    public function test_non_admin_cannot_access_report_endpoints(): void
    {
        config(['app.enabled_shops' => ['supermarket', 'egg']]);

        User::create([
            'name' => 'User',
            'username' => 'user',
            'email' => 'user@example.com',
            'password' => Hash::make('user123'),
            'role' => 'user',
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'user',
            'password' => 'user123',
        ]);

        $loginResponse
            ->assertStatus(200)
            ->assertJsonPath('user.navigation.shops.0.key', 'supermarket')
            ->assertJsonPath('user.navigation.shops.1.key', 'egg')
            ->assertJsonPath('user.navigation.shops.1.pages.0.key', 'entry')
            ->assertJsonMissingPath('user.navigation.shops.0.pages.4');

        $token = $loginResponse->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/bills/summary')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/egg-entries/summary')
            ->assertStatus(403);
    }

    public function test_disabled_shop_is_not_returned_in_navigation(): void
    {
        config([
            'app.default_shop' => 'egg',
            'app.enabled_shops' => ['egg'],
        ]);

        User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin2@example.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $loginResponse
            ->assertStatus(200)
            ->assertJsonPath('user.navigation.default_shop', 'egg')
            ->assertJsonPath('user.navigation.shops.0.key', 'egg')
            ->assertJsonMissingPath('user.navigation.shops.1');
    }
}

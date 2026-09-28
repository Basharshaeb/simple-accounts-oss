<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_login_and_stats(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@accounts.com',
            'password' => bcrypt('password123'),
            'is_super_admin' => true,
        ]);

        $currency = Currency::create(['code' => 'SAR', 'name' => 'SAR', 'symbol' => 'SAR', 'decimal_places' => 2]);
        $company = Company::create(['name' => 'Test Company', 'code' => 'TEST', 'base_currency_id' => $currency->id, 'status' => 'ACTIVE']);

        // Superadmin Login
        $loginRes = $this->postJson('/api/v1/superadmin/login', [
            'email' => 'admin@accounts.com',
            'password' => 'password123',
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $token = $loginRes->json('data.token');

        // Superadmin Stats
        $statsRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/superadmin/stats');

        $statsRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_companies', 1);

        // Toggle Company Status
        $toggleRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/v1/superadmin/companies/{$company->id}/status");

        $toggleRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'INACTIVE');
    }

    public function test_regular_user_cannot_login_to_superadmin(): void
    {
        User::factory()->create([
            'email' => 'regular@accounts.com',
            'password' => bcrypt('password123'),
            'is_super_admin' => false,
        ]);

        $response = $this->postJson('/api/v1/superadmin/login', [
            'email' => 'regular@accounts.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}

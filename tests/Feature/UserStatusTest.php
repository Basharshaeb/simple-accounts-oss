<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::create(['code' => 'SAR', 'name' => 'SAR', 'symbol' => 'SAR', 'decimal_places' => 2]);
        $this->company = Company::create(['name' => 'Test Company', 'code' => 'TEST', 'base_currency_id' => $currency->id, 'status' => 'ACTIVE']);
    }

    private function member(string $email, string $role, bool $isActive = true): CompanyUser
    {
        $user = User::factory()->create(['email' => $email, 'password' => bcrypt('secret-pass')]);

        return CompanyUser::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'role' => $role,
            'is_default' => true,
            'is_active' => $isActive,
        ]);
    }

    public function test_login_records_last_login_and_users_list_exposes_it(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');
        $this->assertNull($admin->last_login_at);

        $this->postJson('/api/v1/auth/login', [
            'company_code' => 'TEST',
            'email' => 'admin@test.com',
            'password' => 'secret-pass',
        ])->assertStatus(200);

        $this->assertNotNull($admin->fresh()->last_login_at);

        Sanctum::actingAs($admin->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->getJson('/api/v1/users')
            ->assertStatus(200)
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.0.last_login_at', $admin->fresh()->last_login_at->toJSON());
    }

    public function test_new_member_is_active_and_has_never_logged_in(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');
        Sanctum::actingAs($admin->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->postJson('/api/v1/users', [
                'name' => 'New Accountant',
                'email' => 'new@test.com',
                'password' => 'secret-pass',
                'role' => 'ACCOUNTANT',
            ])
            ->assertStatus(201);

        $created = CompanyUser::whereHas('user', fn ($q) => $q->where('email', 'new@test.com'))->firstOrFail();

        $this->assertTrue($created->is_active);
        $this->assertNull($created->last_login_at);
    }

    public function test_inactive_member_cannot_login(): void
    {
        $this->member('stopped@test.com', 'ACCOUNTANT', isActive: false);

        $this->postJson('/api/v1/auth/login', [
            'company_code' => 'TEST',
            'email' => 'stopped@test.com',
            'password' => 'secret-pass',
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_toggle_member_status_and_deactivated_member_loses_access(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');
        $accountant = $this->member('acc@test.com', 'ACCOUNTANT');

        Sanctum::actingAs($admin->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->putJson("/api/v1/users/{$accountant->id}/status")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($accountant->fresh()->is_active);

        Sanctum::actingAs($accountant->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->getJson('/api/v1/users')
            ->assertStatus(403);

        Sanctum::actingAs($admin->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->putJson("/api/v1/users/{$accountant->id}/status")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');
        Sanctum::actingAs($admin->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->putJson("/api/v1/users/{$admin->id}/status")
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_non_admin_cannot_toggle_status(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');
        $accountant = $this->member('acc@test.com', 'ACCOUNTANT');

        Sanctum::actingAs($accountant->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->putJson("/api/v1/users/{$admin->id}/status")
            ->assertStatus(403);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_login_is_logged_with_cloudflare_client_ip_and_user_agent(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');

        $this->withHeaders(['CF-Connecting-IP' => '203.0.113.7', 'User-Agent' => 'TestBrowser/1.0'])
            ->postJson('/api/v1/auth/login', [
                'company_code' => 'TEST',
                'email' => 'admin@test.com',
                'password' => 'secret-pass',
            ])->assertStatus(200);

        $this->assertDatabaseHas('login_logs', [
            'user_id' => $admin->user_id,
            'company_id' => $this->company->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'TestBrowser/1.0',
        ]);
        $this->assertSame('203.0.113.7', $admin->fresh()->last_login_ip);
    }

    public function test_invalid_cloudflare_header_falls_back_to_request_ip(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');

        $this->withHeaders(['CF-Connecting-IP' => 'not-an-ip'])
            ->postJson('/api/v1/auth/login', [
                'company_code' => 'TEST',
                'email' => 'admin@test.com',
                'password' => 'secret-pass',
            ])->assertStatus(200);

        $this->assertSame('127.0.0.1', $admin->fresh()->last_login_ip);
    }

    public function test_failed_login_is_not_logged(): void
    {
        $this->member('admin@test.com', 'COMPANY_ADMIN');

        $this->postJson('/api/v1/auth/login', [
            'company_code' => 'TEST',
            'email' => 'admin@test.com',
            'password' => 'wrong-pass',
        ])->assertStatus(401);

        $this->assertDatabaseCount('login_logs', 0);
    }

    public function test_requests_with_a_real_token_mark_the_member_online_and_logout_marks_offline(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');

        $token = $this->postJson('/api/v1/auth/login', [
            'company_code' => 'TEST',
            'email' => 'admin@test.com',
            'password' => 'secret-pass',
        ])->json('data.token');

        $admin->update(['last_seen_at' => now()->subHour()]);
        $this->assertFalse($admin->fresh()->is_online);

        $headers = ['Authorization' => 'Bearer '.$token, 'X-Company-ID' => (string) $this->company->id];

        $this->withHeaders($headers)
            ->getJson('/api/v1/users')
            ->assertStatus(200)
            ->assertJsonPath('data.0.is_online', true);

        $this->assertTrue($admin->fresh()->is_online);

        $this->withHeaders($headers)->postJson('/api/v1/auth/logout')->assertStatus(200);

        $this->assertFalse($admin->fresh()->is_online);
    }

    public function test_member_not_seen_recently_is_offline(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');
        $idle = $this->member('idle@test.com', 'ACCOUNTANT');
        $idle->update(['last_seen_at' => now()->subMinutes(CompanyUser::ONLINE_WINDOW_MINUTES + 1)]);

        Sanctum::actingAs($admin->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->getJson('/api/v1/users')
            ->assertStatus(200)
            ->assertJsonPath('data.1.is_online', false);
    }

    public function test_admin_can_view_member_login_history_scoped_to_company(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');
        $accountant = $this->member('acc@test.com', 'ACCOUNTANT');

        $this->withHeaders(['CF-Connecting-IP' => '198.51.100.20'])
            ->postJson('/api/v1/auth/login', [
                'company_code' => 'TEST',
                'email' => 'acc@test.com',
                'password' => 'secret-pass',
            ])->assertStatus(200);

        Sanctum::actingAs($admin->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->getJson("/api/v1/users/{$accountant->id}/logins")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ip_address', '198.51.100.20');
    }

    public function test_non_admin_cannot_view_login_history(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');
        $accountant = $this->member('acc@test.com', 'ACCOUNTANT');

        Sanctum::actingAs($accountant->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->getJson("/api/v1/users/{$admin->id}/logins")
            ->assertStatus(403);
    }

    public function test_cannot_toggle_member_of_another_company(): void
    {
        $admin = $this->member('admin@test.com', 'COMPANY_ADMIN');

        $otherCompany = Company::create(['name' => 'Other', 'code' => 'OTHER', 'base_currency_id' => $this->company->base_currency_id, 'status' => 'ACTIVE']);
        $outsider = User::factory()->create();
        $outsiderMembership = CompanyUser::create(['user_id' => $outsider->id, 'company_id' => $otherCompany->id, 'role' => 'ACCOUNTANT']);

        Sanctum::actingAs($admin->user);

        $this->withHeader('X-Company-ID', (string) $this->company->id)
            ->putJson("/api/v1/users/{$outsiderMembership->id}/status")
            ->assertStatus(404);

        $this->assertTrue($outsiderMembership->fresh()->is_active);
    }
}

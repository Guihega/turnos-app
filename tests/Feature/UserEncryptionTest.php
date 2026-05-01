<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    // ── Encryption basics ──

    public function test_email_is_encrypted_in_database(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
        ]);

        // Read raw from database — should NOT be plaintext
        $raw = \DB::table('users')->where('id', $user->id)->value('email');
        $this->assertNotEquals('test@example.com', $raw);
        $this->assertNotEmpty($raw);

        // But reading through Eloquent decrypts it
        $user->refresh();
        $this->assertEquals('test@example.com', $user->email);
    }

    public function test_phone_is_encrypted_in_database(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+52 555 123 4567',
        ]);

        $raw = \DB::table('users')->where('id', $user->id)->value('phone');
        $this->assertNotEquals('+52 555 123 4567', $raw);

        $user->refresh();
        $this->assertEquals('+52 555 123 4567', $user->phone);
    }

    public function test_last_login_ip_is_encrypted_in_database(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'last_login_ip' => '192.168.1.100',
        ]);

        $raw = \DB::table('users')->where('id', $user->id)->value('last_login_ip');
        $this->assertNotEquals('192.168.1.100', $raw);

        $user->refresh();
        $this->assertEquals('192.168.1.100', $user->last_login_ip);
    }

    public function test_nullable_fields_remain_null_when_empty(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => null,
            'last_login_ip' => null,
        ]);

        $user->refresh();
        $this->assertNull($user->phone);
        $this->assertNull($user->last_login_ip);
    }

    // ── Blind index search ──

    public function test_find_by_email_using_blind_index(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'findme@example.com',
        ]);

        $found = User::findByEmail('findme@example.com');
        $this->assertNotNull($found);
        $this->assertEquals('findme@example.com', $found->email);
    }

    public function test_find_by_email_returns_null_for_nonexistent(): void
    {
        $found = User::findByEmail('nobody@example.com');
        $this->assertNull($found);
    }

    public function test_where_blind_returns_correct_user(): void
    {
        $user1 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'alice@example.com',
        ]);

        $user2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'bob@example.com',
        ]);

        $found = User::whereBlind('email', 'email_index', 'bob@example.com')->first();
        $this->assertNotNull($found);
        $this->assertEquals($user2->id, $found->id);
    }

    // ── Name is NOT encrypted (intentional) ──

    public function test_name_is_stored_in_plaintext_for_search(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Juan García',
        ]);

        // Name should be readable directly from DB (not encrypted)
        $raw = \DB::table('users')->where('id', $user->id)->value('name');
        $this->assertEquals('Juan García', $raw);
    }

    public function test_name_supports_like_search(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Juan García López',
        ]);

        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'María Rodríguez',
        ]);

        $results = User::where('name', 'LIKE', '%García%')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Juan García López', $results->first()->name);
    }

    // ── Login flow ──

    public function test_login_works_with_encrypted_email(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
    }

    // ── Update preserves encryption ──

    public function test_updating_email_re_encrypts(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'original@example.com',
        ]);

        $user->update(['email' => 'updated@example.com']);

        // Verify blind index is updated too
        $found = User::findByEmail('updated@example.com');
        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);

        // Old email should no longer be findable
        $notFound = User::findByEmail('original@example.com');
        $this->assertNull($notFound);
    }
}

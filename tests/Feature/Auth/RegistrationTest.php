<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationTest extends TestCase
{
    /**
     * Registration routes are disabled in Olinora.
     * Users are created by tenant admins via /admin/usuarios.
     * These tests verify the routes return 404 (not accessible).
     */
    public function test_registration_screen_is_not_accessible(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_registration_post_is_not_accessible(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(404);
    }
}

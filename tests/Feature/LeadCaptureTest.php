<?php

namespace Tests\Feature;

use App\Mail\NewLeadNotification;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('lead-submissions:127.0.0.1');
    }

    public function test_landing_page_carga_publicamente(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_lead_valido_se_persiste_y_notifica(): void
    {
        Mail::fake();

        $response = $this->post('/leads', [
            'name' => 'Juan Pérez',
            'email' => 'juan@clinicademo.com',
            'organization' => 'Clínica Demo',
            'sector' => 'salud',
            'size' => '2-5',
            'message' => 'Atendemos 200 pacientes al día.',
            'website' => '', // honeypot vacío
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('leadSubmitted', true);

        $this->assertDatabaseHas('leads', [
            'email' => 'juan@clinicademo.com',
            'organization' => 'Clínica Demo',
            'sector' => 'salud',
            'size' => '2-5',
            'status' => 'new',
        ]);

        Mail::assertSent(NewLeadNotification::class);
    }

    public function test_honeypot_lleno_rechaza_el_lead(): void
    {
        Mail::fake();

        $response = $this->post('/leads', [
            'name' => 'Bot Spammer',
            'email' => 'spam@bot.com',
            'organization' => 'Bot Inc',
            'sector' => 'otro',
            'size' => '1',
            'website' => 'http://spam.example', // bot llenó el honeypot
        ]);

        $response->assertSessionHasErrors('website');
        $this->assertDatabaseCount('leads', 0);
        Mail::assertNothingSent();
    }

    public function test_campos_requeridos_son_validados(): void
    {
        $response = $this->post('/leads', []);

        $response->assertSessionHasErrors(['name', 'email', 'organization', 'sector', 'size']);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_email_invalido_es_rechazado(): void
    {
        $response = $this->post('/leads', [
            'name' => 'Juan',
            'email' => 'no-es-un-email',
            'organization' => 'Test',
            'sector' => 'salud',
            'size' => '1',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_sector_fuera_de_lista_es_rechazado(): void
    {
        $response = $this->post('/leads', [
            'name' => 'Juan',
            'email' => 'juan@demo.com',
            'organization' => 'Test',
            'sector' => 'minería', // no está en SECTORS
            'size' => '1',
        ]);

        $response->assertSessionHasErrors('sector');
    }

    public function test_mensaje_excede_500_caracteres_es_rechazado(): void
    {
        $response = $this->post('/leads', [
            'name' => 'Juan',
            'email' => 'juan@demo.com',
            'organization' => 'Test',
            'sector' => 'salud',
            'size' => '1',
            'message' => str_repeat('a', 501),
        ]);

        $response->assertSessionHasErrors('message');
    }

    public function test_rate_limit_bloquea_despues_de_5_envios(): void
    {
        Mail::fake();

        $payload = [
            'name' => 'Juan',
            'email' => 'juan@demo.com',
            'organization' => 'Test',
            'sector' => 'salud',
            'size' => '1',
        ];

        // 5 envíos permitidos
        for ($i = 0; $i < 5; $i++) {
            $this->post('/leads', array_merge($payload, [
                'email' => "juan{$i}@demo.com",
            ]))->assertRedirect();
        }

        // El 6to debe ser bloqueado
        $response = $this->post('/leads', array_merge($payload, [
            'email' => 'juan6@demo.com',
        ]));

        $response->assertStatus(429);
        $this->assertDatabaseCount('leads', 5);
    }

    public function test_metadatos_se_guardan_correctamente(): void
    {
        Mail::fake();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Test Browser',
            'Referer' => 'https://linkedin.com/feed/',
        ])->post('/leads', [
            'name' => 'Juan',
            'email' => 'juan@demo.com',
            'organization' => 'Test',
            'sector' => 'salud',
            'size' => '1',
        ]);

        $lead = Lead::first();
        $this->assertNotNull($lead);
        $this->assertStringContainsString('Test Browser', $lead->user_agent);
        $this->assertEquals('https://linkedin.com/feed/', $lead->referrer);
        $this->assertNotNull($lead->ip);
    }

    public function test_mail_falla_pero_lead_se_guarda(): void
    {
        // Forzamos que Mail truene para verificar que el lead aún persista
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $response = $this->post('/leads', [
            'name' => 'Juan',
            'email' => 'juan@demo.com',
            'organization' => 'Test',
            'sector' => 'salud',
            'size' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_lead_se_persiste_con_parametros_utm(): void
    {
        Mail::fake();

        $response = $this->post('/leads', [
            'name' => 'Juan',
            'email' => 'juan@demo.com',
            'organization' => 'Test',
            'sector' => 'salud',
            'size' => '1',
            'utm_source' => 'facebook',
            'utm_medium' => 'organic',
            'utm_campaign' => 'lanzamiento-2026-04',
            'utm_term' => 'gestion-turnos',
            'utm_content' => 'post-dashboard',
        ]);

        $response->assertRedirect();

        $lead = Lead::first();
        $this->assertNotNull($lead);
        $this->assertEquals('facebook', $lead->utm_source);
        $this->assertEquals('organic', $lead->utm_medium);
        $this->assertEquals('lanzamiento-2026-04', $lead->utm_campaign);
        $this->assertEquals('gestion-turnos', $lead->utm_term);
        $this->assertEquals('post-dashboard', $lead->utm_content);
    }

    public function test_lead_sin_utms_guarda_nulls(): void
    {
        Mail::fake();

        $this->post('/leads', [
            'name' => 'Juan',
            'email' => 'juan@demo.com',
            'organization' => 'Test',
            'sector' => 'salud',
            'size' => '1',
        ]);

        $lead = Lead::first();
        $this->assertNotNull($lead);
        $this->assertNull($lead->utm_source);
        $this->assertNull($lead->utm_medium);
        $this->assertNull($lead->utm_campaign);
        $this->assertNull($lead->utm_term);
        $this->assertNull($lead->utm_content);
    }

    public function test_utm_mayor_a_255_caracteres_es_rechazado(): void
    {
        $response = $this->post('/leads', [
            'name' => 'Juan',
            'email' => 'juan@demo.com',
            'organization' => 'Test',
            'sector' => 'salud',
            'size' => '1',
            'utm_source' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors('utm_source');
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_utms_vacios_se_normalizan_a_null(): void
    {
        Mail::fake();

        $this->post('/leads', [
            'name' => 'Juan',
            'email' => 'juan@demo.com',
            'organization' => 'Test',
            'sector' => 'salud',
            'size' => '1',
            'utm_source' => '',
            'utm_medium' => '   ', // solo whitespace
            'utm_campaign' => '',
        ]);

        $lead = Lead::first();
        $this->assertNotNull($lead);
        $this->assertNull($lead->utm_source);
        $this->assertNull($lead->utm_medium);
        $this->assertNull($lead->utm_campaign);
    }
}

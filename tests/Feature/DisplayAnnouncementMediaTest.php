<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\DisplayAnnouncement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisplayAnnouncementMediaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::TENANT_ADMIN,
        ]);
        $this->branch = Branch::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->branches()->attach($this->branch->id);
    }

    // ─── Subida de imágenes ───

    public function test_can_create_announcement_with_image(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type'     => 'promo',
            'title'    => 'Promo con imagen',
            'body'     => 'Descripción de la promo',
            'media'    => UploadedFile::fake()->image('promo.jpg', 800, 600),
            'priority' => 5,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $announcement = DisplayAnnouncement::where('title', 'Promo con imagen')->first();
        $this->assertNotNull($announcement);
        $this->assertNotNull($announcement->media_url);
        $this->assertEquals('image', $announcement->media_type);

        // Verificar que el archivo existe en storage
        $path = str_replace('/storage/', '', $announcement->media_url);
        Storage::disk('public')->assertExists($path);
    }

    public function test_can_create_announcement_with_video(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type'     => 'announcement',
            'title'    => 'Anuncio con video',
            'media'    => UploadedFile::fake()->create('promo.mp4', 5000, 'video/mp4'),
            'priority' => 0,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $announcement = DisplayAnnouncement::where('title', 'Anuncio con video')->first();
        $this->assertNotNull($announcement);
        $this->assertEquals('video', $announcement->media_type);
    }

    public function test_can_create_announcement_without_media(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type'     => 'news',
            'title'    => 'Noticia sin media',
            'priority' => 0,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $announcement = DisplayAnnouncement::where('title', 'Noticia sin media')->first();
        $this->assertNotNull($announcement);
        $this->assertNull($announcement->media_url);
        $this->assertNull($announcement->media_type);
    }

    // ─── Validación de media ───

    public function test_rejects_invalid_file_type(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type'     => 'announcement',
            'title'    => 'Test',
            'media'    => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
            'priority' => 0,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('media');
    }

    public function test_rejects_file_over_20mb(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type'     => 'announcement',
            'title'    => 'Test',
            'media'    => UploadedFile::fake()->create('huge.jpg', 25000, 'image/jpeg'),
            'priority' => 0,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('media');
    }

    // ─── Actualización de media ───

    public function test_can_replace_media_on_update(): void
    {
        // Crear con imagen inicial
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type'     => 'promo',
            'title'    => 'Reemplazar media',
            'media'    => UploadedFile::fake()->image('original.jpg', 400, 300),
            'priority' => 0,
            'is_active' => true,
        ]);

        $announcement = DisplayAnnouncement::where('title', 'Reemplazar media')->first();
        $oldPath = str_replace('/storage/', '', $announcement->media_url);

        // Actualizar con nueva imagen
        $this->actingAs($this->admin)->post(route('admin.announcements.update', $announcement), [
            '_method'  => 'PUT',
            'type'     => 'promo',
            'title'    => 'Reemplazar media',
            'media'    => UploadedFile::fake()->image('nueva.png', 600, 400),
            'priority' => 0,
            'is_active' => true,
        ]);

        $announcement->refresh();
        $this->assertNotNull($announcement->media_url);

        // Archivo anterior debería haberse eliminado
        Storage::disk('public')->assertMissing($oldPath);

        // Nuevo archivo debe existir
        $newPath = str_replace('/storage/', '', $announcement->media_url);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_can_remove_media_on_update(): void
    {
        // Crear con imagen
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type'     => 'announcement',
            'title'    => 'Eliminar media',
            'media'    => UploadedFile::fake()->image('borrar.jpg', 400, 300),
            'priority' => 0,
            'is_active' => true,
        ]);

        $announcement = DisplayAnnouncement::where('title', 'Eliminar media')->first();
        $oldPath = str_replace('/storage/', '', $announcement->media_url);

        // Actualizar con remove_media
        $this->actingAs($this->admin)->post(route('admin.announcements.update', $announcement), [
            '_method'      => 'PUT',
            'type'         => 'announcement',
            'title'        => 'Eliminar media',
            'remove_media' => true,
            'priority'     => 0,
            'is_active'    => true,
        ]);

        $announcement->refresh();
        $this->assertNull($announcement->media_url);
        $this->assertNull($announcement->media_type);

        // Archivo debe haberse eliminado
        Storage::disk('public')->assertMissing($oldPath);
    }

    // ─── Eliminación ───

    public function test_deleting_announcement_removes_media_file(): void
    {
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type'     => 'promo',
            'title'    => 'Eliminar todo',
            'media'    => UploadedFile::fake()->image('delete-me.jpg', 400, 300),
            'priority' => 0,
            'is_active' => true,
        ]);

        $announcement = DisplayAnnouncement::where('title', 'Eliminar todo')->first();
        $path = str_replace('/storage/', '', $announcement->media_url);

        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->admin)->delete(route('admin.announcements.destroy', $announcement));

        $this->assertDatabaseMissing('display_announcements', ['id' => $announcement->id]);
        Storage::disk('public')->assertMissing($path);
    }

    // ─── Display incluye media ───

    public function test_public_display_includes_media_in_announcements(): void
    {
        DisplayAnnouncement::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'title'      => 'Con media',
            'media_url'  => '/storage/announcements/test/image.jpg',
            'media_type' => 'image',
            'is_active'  => true,
        ]);

        $response = $this->get(route('display.public', $this->branch));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Display/Screen')
            ->has('announcements', 1)
            ->where('announcements.0.media_url', '/storage/announcements/test/image.jpg')
            ->where('announcements.0.media_type', 'image')
        );
    }

    // ─── Aislamiento de tenant en media ───

    public function test_media_stored_in_tenant_specific_folder(): void
    {
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'type'     => 'announcement',
            'title'    => 'Carpeta tenant',
            'media'    => UploadedFile::fake()->image('tenant-img.jpg', 400, 300),
            'priority' => 0,
            'is_active' => true,
        ]);

        $announcement = DisplayAnnouncement::where('title', 'Carpeta tenant')->first();

        // Media URL debe contener el ID del tenant
        $this->assertStringContainsString("announcements/{$this->tenant->id}", $announcement->media_url);
    }
}

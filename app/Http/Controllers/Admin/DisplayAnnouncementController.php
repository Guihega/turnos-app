<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DisplayAnnouncement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DisplayAnnouncementController extends Controller
{
    /**
     * Invalida el cache de anuncios de todas las branches del tenant.
     */
    private function invalidateAnnouncementCache(string $tenantId): void
    {
        Branch::where('tenant_id', $tenantId)->pluck('id')->each(function ($branchId) {
            Cache::forget("display:announcements:{$branchId}");
        });
    }
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        $announcements = DisplayAnnouncement::where('tenant_id', $tenant->id)
            ->with('branch:id,name,code')
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->paginate(20);

        $branches = Branch::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'code']);

        return Inertia::render('Admin/Announcements/Index', [
            'announcements' => $announcements,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'type'      => ['required', 'in:announcement,news,promo'],
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'priority'  => ['integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        // Validar que la branch pertenezca al tenant
        if (!empty($validated['branch_id'])) {
            $branch = Branch::where('id', $validated['branch_id'])
                ->where('tenant_id', $tenant->id)
                ->first();
            if (!$branch) {
                return back()->withErrors(['branch_id' => 'Sucursal no válida.']);
            }
        }

        DisplayAnnouncement::create([
            ...$validated,
            'tenant_id' => $tenant->id,
        ]);

        $this->invalidateAnnouncementCache($tenant->id);

        return back()->with('success', 'Anuncio creado correctamente.');
    }

    public function update(Request $request, DisplayAnnouncement $announcement): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ($announcement->tenant_id !== $tenant->id) {
            abort(403);
        }

        $validated = $request->validate([
            'type'      => ['required', 'in:announcement,news,promo'],
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'priority'  => ['integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        if (!empty($validated['branch_id'])) {
            $branch = Branch::where('id', $validated['branch_id'])
                ->where('tenant_id', $tenant->id)
                ->first();
            if (!$branch) {
                return back()->withErrors(['branch_id' => 'Sucursal no válida.']);
            }
        }

        $announcement->update($validated);

        $this->invalidateAnnouncementCache($tenant->id);

        return back()->with('success', 'Anuncio actualizado.');
    }

    public function destroy(Request $request, DisplayAnnouncement $announcement): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ($announcement->tenant_id !== $tenant->id) {
            abort(403);
        }

        $announcement->delete();

        $this->invalidateAnnouncementCache($tenant->id);

        return back()->with('success', 'Anuncio eliminado.');
    }

    /**
     * Toggle activo/inactivo rápido.
     */
    public function toggle(Request $request, DisplayAnnouncement $announcement): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ($announcement->tenant_id !== $tenant->id) {
            abort(403);
        }

        $announcement->update(['is_active' => !$announcement->is_active]);

        $this->invalidateAnnouncementCache($tenant->id);

        return back()->with('success', $announcement->is_active ? 'Anuncio activado.' : 'Anuncio desactivado.');
    }
}

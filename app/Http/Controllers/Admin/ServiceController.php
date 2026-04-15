<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesTenantOwnership;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ServiceController extends Controller
{
    use AuthorizesTenantOwnership;

    public function index(Request $request)
    {
        $services = Service::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('sort_order')->get()
            ->map(fn($s) => [
                'id' => $s->id, 'name' => $s->name, 'code' => $s->code, 'color' => $s->color,
                'estimated_duration_minutes' => $s->estimated_duration_minutes, 'is_active' => $s->is_active,
                'requires_appointment' => $s->requires_appointment,
            ]);

        return Inertia::render('Admin/Services/Index', ['services' => $services]);
    }

    public function create()
    {
        return Inertia::render('Admin/Services/Form', ['service' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:5|alpha_num',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
            'estimated_duration_minutes' => 'required|integer|min:1|max:240',
            'requires_appointment' => 'boolean',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = true;

        Service::create($data);
        return redirect()->route('admin.servicios.index')->with('success', 'Servicio creado.');
    }

    public function edit(Request $request, Service $service)
    {
        $this->authorizeTenantOwnership($service, $request);

        return Inertia::render('Admin/Services/Form', [
            'service' => $service->only(['id', 'name', 'code', 'color', 'description', 'estimated_duration_minutes', 'requires_appointment', 'is_active']),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $this->authorizeTenantOwnership($service, $request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:5',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:500',
            'estimated_duration_minutes' => 'required|integer|min:1|max:240',
            'requires_appointment' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $service->update($data);
        return redirect()->route('admin.servicios.index')->with('success', 'Servicio actualizado.');
    }

    public function destroy(Request $request, Service $service)
    {
        $this->authorizeTenantOwnership($service, $request);

        $service->delete();
        return redirect()->route('admin.servicios.index')->with('success', 'Servicio eliminado.');
    }
}

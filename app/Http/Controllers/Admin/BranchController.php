<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesTenantOwnership;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BranchController extends Controller
{
    use AuthorizesTenantOwnership;

    public function index(Request $request)
    {
        $branches = Branch::where('tenant_id', $request->user()->tenant_id)
            ->withCount(['tickets as today_tickets' => fn ($q) => $q->whereDate('created_at', today())])
            ->orderBy('name')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name, 'code' => $b->code, 'city' => $b->city,
                'is_active' => $b->is_active, 'is_open' => $b->isOpen(), 'phone' => $b->phone,
                'today_tickets' => $b->today_tickets, 'max_daily_tickets' => $b->max_daily_tickets,
            ]);

        return Inertia::render('Admin/Branches/Index', ['branches' => $branches]);
    }

    public function create()
    {
        return Inertia::render('Admin/Branches/Form', ['branch' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|alpha_num',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:2',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'timezone' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'max_daily_tickets' => 'nullable|integer|min:1|max:9999',
            'max_concurrent_waiting' => 'nullable|integer|min:1|max:999',
            'accepts_walkins' => 'boolean',
            'accepts_appointments' => 'boolean',
            'operating_hours' => 'nullable|array',
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = true;

        Branch::create($data);

        return redirect()->route('admin.sucursales.index')->with('success', 'Sucursal creada.');
    }

    public function edit(Request $request, Branch $branch)
    {
        $this->authorizeTenantOwnership($branch, $request);

        return Inertia::render('Admin/Branches/Form', [
            'branch' => [
                'id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code,
                'address' => $branch->address, 'city' => $branch->city, 'state' => $branch->state,
                'country' => $branch->country, 'phone' => $branch->phone, 'email' => $branch->email,
                'timezone' => $branch->timezone, 'latitude' => $branch->latitude, 'longitude' => $branch->longitude,
                'max_daily_tickets' => $branch->max_daily_tickets, 'max_concurrent_waiting' => $branch->max_concurrent_waiting,
                'accepts_walkins' => $branch->accepts_walkins, 'accepts_appointments' => $branch->accepts_appointments,
                'operating_hours' => $branch->operating_hours, 'is_active' => $branch->is_active,
            ],
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorizeTenantOwnership($branch, $request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:2',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'max_daily_tickets' => 'nullable|integer|min:1',
            'accepts_walkins' => 'boolean',
            'accepts_appointments' => 'boolean',
            'operating_hours' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $branch->update($data);

        return redirect()->route('admin.sucursales.index')->with('success', 'Sucursal actualizada.');
    }

    public function destroy(Request $request, Branch $branch)
    {
        $this->authorizeTenantOwnership($branch, $request);

        $branch->delete();

        return redirect()->route('admin.sucursales.index')->with('success', 'Sucursal eliminada.');
    }
}

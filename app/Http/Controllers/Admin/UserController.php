<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $users = User::where('tenant_id', $tenantId)
            ->with('branches:id,name,code')
            ->orderBy('name')->get()
            ->map(fn($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'role' => $u->role->value, 'role_label' => $u->role->label(),
                'is_active' => $u->is_active,
                'branches' => $u->branches->map(fn($b) => ['id' => $b->id, 'name' => $b->name]),
                'last_login_at' => $u->last_login_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => collect(UserRole::cases())->map(fn($r) => ['value' => $r->value, 'label' => $r->label()])->values(),
        ]);
    }

    public function create(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'roles' => collect(UserRole::cases())->filter(fn($r) => $r !== UserRole::SUPER_ADMIN)->map(fn($r) => ['value' => $r->value, 'label' => $r->label()])->values(),
            'branches' => Branch::where('tenant_id', $tenantId)->where('is_active', true)->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->where('tenant_id', $request->user()->tenant_id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::enum(UserRole::class)],
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        $user = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        if (!empty($data['branch_ids'])) {
            foreach ($data['branch_ids'] as $branchId) {
                $user->branches()->attach($branchId, [
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'role' => $data['role'],
                    'is_active' => true,
                ]);
            }
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $user)
    {
        $tenantId = $user->tenant_id;
        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'phone' => $user->phone, 'role' => $user->role->value, 'is_active' => $user->is_active,
                'branch_ids' => $user->branches->pluck('id'),
            ],
            'roles' => collect(UserRole::cases())->filter(fn($r) => $r !== UserRole::SUPER_ADMIN)->map(fn($r) => ['value' => $r->value, 'label' => $r->label()])->values(),
            'branches' => Branch::where('tenant_id', $tenantId)->where('is_active', true)->get(['id', 'name', 'code']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)->where('tenant_id', $user->tenant_id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => 'boolean',
            'branch_ids' => 'nullable|array',
        ]);

        $updateData = collect($data)->except(['password', 'branch_ids', 'password_confirmation'])->toArray();
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        if (isset($data['branch_ids'])) {
            $syncData = [];
            foreach ($data['branch_ids'] as $branchId) {
                $syncData[$branchId] = [
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'role' => $data['role'],
                    'is_active' => true,
                ];
            }
            $user->branches()->sync($syncData);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $user)
    {
        $user->update(['is_active' => false]);
        $user->delete();
        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario desactivado.');
    }
}

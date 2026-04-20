<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Admin\Concerns\AuthorizesTenantOwnership;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\LaravelCipherSweet\Rules\EncryptedUniqueRule;

class UserController extends Controller
{
    use AuthorizesTenantOwnership;

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
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', new EncryptedUniqueRule(User::class, 'email_index')],
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::enum(UserRole::class)],
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        // Verify all branch_ids belong to the user's tenant
        if (!empty($data['branch_ids'])) {
            $this->validateBranchIds($data['branch_ids'], $tenantId);
        }

        // Prevent escalation: non-super-admin cannot create super_admin
        if ($data['role'] === UserRole::SUPER_ADMIN->value) {
            abort(403, 'No puede crear usuarios con este rol.');
        }

        $user = User::create([
            'tenant_id' => $tenantId,
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
                    'id' => (string) Str::ulid(),
                    'role' => $data['role'],
                    'is_active' => true,
                ]);
            }
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario creado.');
    }

    public function edit(Request $request, User $user)
    {
        $this->authorizeTenantOwnership($user, $request);

        $tenantId = $request->user()->tenant_id;
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
        $this->authorizeTenantOwnership($user, $request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', (new EncryptedUniqueRule(User::class, 'email_index'))->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => 'boolean',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        // Verify all branch_ids belong to the user's tenant
        if (!empty($data['branch_ids'])) {
            $this->validateBranchIds($data['branch_ids'], $tenantId);
        }

        // Prevent escalation
        if ($data['role'] === UserRole::SUPER_ADMIN->value) {
            abort(403, 'No puede asignar este rol.');
        }

        $updateData = collect($data)->except(['password', 'branch_ids', 'password_confirmation'])->toArray();
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        if (isset($data['branch_ids'])) {
            $syncData = [];
            foreach ($data['branch_ids'] as $branchId) {
                $syncData[$branchId] = [
                    'id' => (string) Str::ulid(),
                    'role' => $data['role'],
                    'is_active' => true,
                ];
            }
            $user->branches()->sync($syncData);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeTenantOwnership($user, $request);

        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'No puede eliminar su propia cuenta desde aquí.']);
        }

        $user->update(['is_active' => false]);
        $user->delete();
        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario desactivado.');
    }

    /**
     * Verify that all branch IDs belong to the given tenant.
     */
    private function validateBranchIds(array $branchIds, string $tenantId): void
    {
        $validCount = Branch::withoutGlobalScopes()
            ->whereIn('id', $branchIds)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($validCount !== count($branchIds)) {
            abort(403, 'Una o más sucursales no pertenecen a su organización.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesTenantOwnership;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CounterController extends Controller
{
    use AuthorizesTenantOwnership;

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $branches = Branch::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $branchId = $request->input('branch_id', $branches->first()?->id);

        $counters = $branchId ? Counter::where('branch_id', $branchId)
            ->with(['currentOperator:id,name', 'currentTicket:id,display_number'])
            ->orderBy('number')->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'number' => $c->number, 'status' => $c->status,
                'operator_name' => $c->currentOperator?->name,
                'current_ticket' => $c->currentTicket?->display_number,
            ]) : collect();

        return Inertia::render('Admin/Counters/Index', [
            'counters' => $counters,
            'branches' => $branches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name]),
            'currentBranchId' => $branchId,
        ]);
    }

    public function create(Request $request)
    {
        $branches = Branch::where('tenant_id', $request->user()->tenant_id)->where('is_active', true)->get(['id', 'name']);

        return Inertia::render('Admin/Counters/Form', ['counter' => null, 'branches' => $branches]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'number' => 'required|string|max:10',
        ]);

        // Verify branch belongs to user's tenant
        $this->authorizeBranchBelongsToTenant($data['branch_id'], $request);

        Counter::create(array_merge($data, ['status' => 'closed']));

        return redirect()->route('admin.ventanillas.index', ['branch_id' => $data['branch_id']])->with('success', 'Ventanilla creada.');
    }

    public function edit(Request $request, Counter $counter)
    {
        $this->authorizeBranchChild($counter, $request);

        $branches = Branch::where('tenant_id', $request->user()->tenant_id)->where('is_active', true)->get(['id', 'name']);

        return Inertia::render('Admin/Counters/Form', [
            'counter' => $counter->only(['id', 'branch_id', 'name', 'number', 'status']),
            'branches' => $branches,
        ]);
    }

    public function update(Request $request, Counter $counter)
    {
        $this->authorizeBranchChild($counter, $request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'number' => 'required|string|max:10',
            'status' => 'nullable|in:open,closed,paused',
        ]);

        $counter->update($data);

        return redirect()->route('admin.ventanillas.index', ['branch_id' => $counter->branch_id])->with('success', 'Ventanilla actualizada.');
    }

    public function destroy(Request $request, Counter $counter)
    {
        $this->authorizeBranchChild($counter, $request);

        $branchId = $counter->branch_id;
        $counter->delete();

        return redirect()->route('admin.ventanillas.index', ['branch_id' => $branchId])->with('success', 'Ventanilla eliminada.');
    }
}

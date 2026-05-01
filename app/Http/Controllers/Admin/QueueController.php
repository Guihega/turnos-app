<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesTenantOwnership;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QueueController extends Controller
{
    use AuthorizesTenantOwnership;

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $branches = Branch::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $branchId = $request->input('branch_id', $branches->first()?->id);

        $queues = $branchId ? Queue::where('branch_id', $branchId)
            ->with(['services:id,name,color'])
            ->withCount(['tickets as waiting_count' => fn ($q) => $q->where('status', 'waiting')])
            ->orderBy('prefix')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id, 'name' => $q->name, 'prefix' => $q->prefix,
                'priority_algorithm' => ucfirst($q->priority_algorithm ?? 'FIFO'),
                'max_capacity' => $q->max_capacity, 'waiting' => $q->waiting_count,
                'is_active' => $q->is_active,
                'services' => $q->services->map(fn ($s) => ['name' => $s->name, 'color' => $s->color]),
            ]) : collect();

        return Inertia::render('Admin/Queues/Index', [
            'queues' => $queues,
            'branches' => $branches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name]),
            'currentBranchId' => $branchId,
        ]);
    }

    public function create(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        return Inertia::render('Admin/Queues/Form', [
            'queue' => null,
            'branches' => Branch::where('tenant_id', $tenantId)->where('is_active', true)->get(['id', 'name']),
            'services' => Service::where('tenant_id', $tenantId)->where('is_active', true)->get(['id', 'name', 'color']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'prefix' => 'required|string|max:3|alpha',
            'priority_algorithm' => 'nullable|in:fifo,priority,weighted_fair',
            'max_capacity' => 'nullable|integer|min:1|max:999',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',
        ]);

        // Verify branch belongs to user's tenant
        $this->authorizeBranchBelongsToTenant($data['branch_id'], $request);

        $queue = Queue::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'prefix' => strtoupper($data['prefix']),
            'priority_algorithm' => $data['priority_algorithm'] ?? 'fifo',
            'max_capacity' => $data['max_capacity'] ?? 100,
            'is_active' => true,
        ]);

        if (! empty($data['service_ids'])) {
            $queue->services()->sync($data['service_ids']);
        }

        return redirect()->route('admin.colas.index')->with('success', "Cola {$queue->name} creada.");
    }

    public function edit(Request $request, Queue $queue)
    {
        $this->authorizeBranchChild($queue, $request);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('Admin/Queues/Form', [
            'queue' => [
                'id' => $queue->id, 'branch_id' => $queue->branch_id, 'name' => $queue->name,
                'prefix' => $queue->prefix, 'priority_algorithm' => $queue->priority_algorithm,
                'max_capacity' => $queue->max_capacity, 'is_active' => $queue->is_active,
                'service_ids' => $queue->services->pluck('id')->toArray(),
            ],
            'branches' => Branch::where('tenant_id', $tenantId)->where('is_active', true)->get(['id', 'name']),
            'services' => Service::where('tenant_id', $tenantId)->where('is_active', true)->get(['id', 'name', 'color']),
        ]);
    }

    public function update(Request $request, Queue $queue)
    {
        $this->authorizeBranchChild($queue, $request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'prefix' => 'required|string|max:3',
            'priority_algorithm' => 'nullable|in:fifo,priority,weighted_fair',
            'max_capacity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'service_ids' => 'nullable|array',
        ]);

        $serviceIds = $data['service_ids'] ?? [];
        unset($data['service_ids']);

        $queue->update($data);
        $queue->services()->sync($serviceIds);

        return redirect()->route('admin.colas.index')->with('success', "Cola {$queue->name} actualizada.");
    }

    public function destroy(Request $request, Queue $queue)
    {
        $this->authorizeBranchChild($queue, $request);

        $queue->services()->detach();
        $queue->delete();

        return redirect()->route('admin.colas.index')->with('success', 'Cola eliminada.');
    }
}

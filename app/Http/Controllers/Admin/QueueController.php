<?php
// ═══════════════════════════════════════════
// app/Http/Controllers/Admin/QueueController.php
// ═══════════════════════════════════════════

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $branches = Branch::where('tenant_id', $tenantId)->active()->get();
        $branchId = $request->input('branch_id', $branches->first()?->id);

        $queues = $branchId ? Queue::where('branch_id', $branchId)
            ->with('services:id,name,color')
            ->withCount(['tickets as waiting_count' => fn($q) => $q->where('status', 'waiting')->whereDate('created_at', today())])
            ->orderBy('sort_order')->get()
            ->map(fn($q) => [
                'id' => $q->id, 'name' => $q->name, 'prefix' => $q->prefix,
                'priority_algorithm' => $q->priority_algorithm, 'max_capacity' => $q->max_capacity,
                'is_active' => $q->is_active, 'waiting_count' => $q->waiting_count,
                'services' => $q->services->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'color' => $s->color]),
            ]) : collect();

        return Inertia::render('Admin/Queues/Index', [
            'queues' => $queues,
            'branches' => $branches->map(fn($b) => ['id' => $b->id, 'name' => $b->name]),
            'currentBranchId' => $branchId,
        ]);
    }

    public function create(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        return Inertia::render('Admin/Queues/Form', [
            'queue' => null,
            'branches' => Branch::where('tenant_id', $tenantId)->active()->get(['id', 'name']),
            'services' => Service::where('tenant_id', $tenantId)->active()->get(['id', 'name', 'color']),
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

        $queue = Queue::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'prefix' => strtoupper($data['prefix']),
            'priority_algorithm' => $data['priority_algorithm'] ?? 'fifo',
            'max_capacity' => $data['max_capacity'] ?? 100,
            'is_active' => true,
        ]);

        if (!empty($data['service_ids'])) {
            $queue->services()->attach($data['service_ids']);
        }

        return redirect()->route('admin.colas.index', ['branch_id' => $data['branch_id']])->with('success', 'Cola creada.');
    }

    public function edit(Queue $queue)
    {
        $tenantId = $queue->branch->tenant_id;
        return Inertia::render('Admin/Queues/Form', [
            'queue' => [
                'id' => $queue->id, 'branch_id' => $queue->branch_id, 'name' => $queue->name,
                'prefix' => $queue->prefix, 'priority_algorithm' => $queue->priority_algorithm,
                'max_capacity' => $queue->max_capacity, 'is_active' => $queue->is_active,
                'service_ids' => $queue->services->pluck('id'),
            ],
            'branches' => Branch::where('tenant_id', $tenantId)->active()->get(['id', 'name']),
            'services' => Service::where('tenant_id', $tenantId)->active()->get(['id', 'name', 'color']),
        ]);
    }

    public function update(Request $request, Queue $queue)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'prefix' => 'required|string|max:3',
            'priority_algorithm' => 'nullable|in:fifo,priority,weighted_fair',
            'max_capacity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'service_ids' => 'nullable|array',
        ]);

        $queue->update($data);
        if (isset($data['service_ids'])) {
            $queue->services()->sync($data['service_ids']);
        }

        return redirect()->route('admin.colas.index', ['branch_id' => $queue->branch_id])->with('success', 'Cola actualizada.');
    }

    public function destroy(Queue $queue)
    {
        $branchId = $queue->branch_id;
        $queue->delete();
        return redirect()->route('admin.colas.index', ['branch_id' => $branchId])->with('success', 'Cola eliminada.');
    }
}

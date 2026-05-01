<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CallNextTicketAction;
use App\Actions\CompleteTicketAction;
use App\Actions\IssueTicketAction;
use App\Actions\IssueTicketData;
use App\Actions\TransferTicketAction;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueTicketRequest;
use App\Http\Requests\TransferTicketRequest;
use App\Http\Resources\TicketResource;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketRepositoryInterface $ticketRepo,
    ) {}

    /**
     * List tickets for a branch with filters.
     */
    public function index(Request $request, string $branchId): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Ticket::class, $branchId]);

        $tickets = $this->ticketRepo->getForBranchPaginated(
            $branchId,
            $request->only([
                'status', 'queue_id', 'service_id', 'served_by',
                'date_from', 'date_to', 'search', 'sort_by', 'sort_dir',
            ]),
            $request->integer('per_page', 25),
        );

        return TicketResource::collection($tickets);
    }

    /**
     * Get active tickets for a branch (real-time board).
     */
    public function active(string $branchId): AnonymousResourceCollection
    {
        $tickets = $this->ticketRepo->getActiveForBranch($branchId);

        return TicketResource::collection($tickets);
    }

    /**
     * Issue a new ticket.
     */
    public function store(IssueTicketRequest $request, IssueTicketAction $action): JsonResponse
    {
        try {
            $data = IssueTicketData::fromRequest(
                $request->validated(),
                $request->user()?->id,
            );

            $ticket = $action->execute($data);

            return (new TicketResource($ticket))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Show a single ticket with full details.
     */
    public function show(string $id): TicketResource
    {
        $ticket = $this->ticketRepo->findById($id);

        abort_if(! $ticket, 404, 'Turno no encontrado.');

        return new TicketResource($ticket->load('events'));
    }

    /**
     * Operator calls next ticket.
     */
    public function callNext(
        Request $request,
        CallNextTicketAction $action,
    ): JsonResponse {
        $request->validate([
            'counter_id' => 'required|ulid|exists:counters,id',
            'queue_id' => 'nullable|ulid|exists:queues,id',
        ]);

        try {
            $ticket = $action->execute(
                $request->input('counter_id'),
                $request->user()->id,
                $request->input('queue_id'),
            );

            return (new TicketResource($ticket->fresh(['queue', 'service', 'counter'])))
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Start serving a called ticket.
     */
    public function startServing(string $id, Request $request): TicketResource
    {
        $ticket = $this->ticketRepo->findById($id);
        abort_if(! $ticket, 404);

        $ticket->transitionTo(TicketStatus::IN_PROGRESS, $request->user()->id);

        return new TicketResource($ticket->fresh());
    }

    /**
     * Complete a ticket.
     */
    public function complete(
        string $id,
        Request $request,
        CompleteTicketAction $action,
    ): JsonResponse {
        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $ticket = $action->execute(
                $id,
                $request->user()->id,
                $request->input('rating'),
                $request->input('notes'),
            );

            return (new TicketResource($ticket))
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Transfer a ticket to another queue.
     */
    public function transfer(
        string $id,
        TransferTicketRequest $request,
        TransferTicketAction $action,
    ): JsonResponse {
        try {
            $newTicket = $action->execute(
                $id,
                $request->input('target_queue_id'),
                $request->user()->id,
                $request->input('reason'),
            );

            return (new TicketResource($newTicket))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Cancel a ticket.
     */
    public function cancel(string $id, Request $request): TicketResource
    {
        $ticket = $this->ticketRepo->findById($id);
        abort_if(! $ticket, 404);

        $ticket->transitionTo(TicketStatus::CANCELLED, $request->user()->id);

        return new TicketResource($ticket->fresh());
    }
}

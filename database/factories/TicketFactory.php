<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $priority = fake()->randomElement(TicketPriority::cases());
        $seq = fake()->numberBetween(1, 999);

        return [
            'branch_id' => Branch::factory(),
            'queue_id' => Queue::factory(),
            'service_id' => Service::factory(),
            'ticket_number' => 'A-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'daily_sequence' => $seq,
            'display_number' => 'TST-A' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'status' => TicketStatus::WAITING,
            'priority' => $priority,
            'priority_score' => $priority->weight(),
            'issued_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::COMPLETED,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'wait_time_seconds' => fake()->numberBetween(60, 600),
            'service_time_seconds' => fake()->numberBetween(120, 900),
            'total_time_seconds' => fake()->numberBetween(180, 1500),
        ]);
    }

    public function waiting(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::WAITING,
            'issued_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn () => [
            'priority' => TicketPriority::URGENT,
            'priority_score' => TicketPriority::URGENT->weight(),
        ]);
    }
}

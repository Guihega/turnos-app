<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant ──
        $tenant = Tenant::create([
            'name' => 'Clínica San Rafael',
            'slug' => 'clinica-san-rafael',
            'email' => 'admin@clinicasanrafael.mx',
            'phone' => '+52 222 123 4567',
            'timezone' => 'America/Mexico_City',
            'is_active' => true,
            'plan' => 'professional',
        ]);

        // ── Services ──
        $services = collect([
            ['name' => 'Consulta General', 'code' => 'CG', 'color' => '#3B82F6', 'duration' => 20],
            ['name' => 'Laboratorio', 'code' => 'LB', 'color' => '#10B981', 'duration' => 10],
            ['name' => 'Farmacia', 'code' => 'FM', 'color' => '#F59E0B', 'duration' => 5],
            ['name' => 'Urgencias', 'code' => 'UR', 'color' => '#EF4444', 'duration' => 30],
            ['name' => 'Caja / Pagos', 'code' => 'CP', 'color' => '#8B5CF6', 'duration' => 8],
            ['name' => 'Especialidades', 'code' => 'ES', 'color' => '#EC4899', 'duration' => 25],
        ])->map(fn ($s) => Service::create([
            'tenant_id' => $tenant->id,
            'name' => $s['name'],
            'slug' => Str::slug($s['name']),
            'code' => $s['code'],
            'color' => $s['color'],
            'estimated_duration_minutes' => $s['duration'],
            'is_active' => true,
        ]));

        // ── Branches ──
        $branches = collect([
            ['name' => 'Sede Centro', 'code' => 'CTR', 'city' => 'Puebla'],
            ['name' => 'Sede Angelópolis', 'code' => 'ANG', 'city' => 'Puebla'],
            ['name' => 'Sede Cholula', 'code' => 'CHO', 'city' => 'San Pedro Cholula'],
        ])->map(fn ($b) => Branch::create([
            'tenant_id' => $tenant->id,
            'name' => $b['name'],
            'slug' => Str::slug($b['name']),
            'code' => $b['code'],
            'city' => $b['city'],
            'state' => 'Puebla',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
            'is_active' => true,
            'max_daily_tickets' => 300,
            'operating_hours' => [
                'mon' => ['open' => '07:00', 'close' => '20:00'],
                'tue' => ['open' => '07:00', 'close' => '20:00'],
                'wed' => ['open' => '07:00', 'close' => '20:00'],
                'thu' => ['open' => '07:00', 'close' => '20:00'],
                'fri' => ['open' => '07:00', 'close' => '20:00'],
                'sat' => ['open' => '08:00', 'close' => '14:00'],
            ],
        ]));

        // ── Users ──
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Dr. Carlos Mendoza',
            'email' => 'admin@clinicasanrafael.mx',
            'password' => bcrypt('password'),
            'role' => UserRole::TENANT_ADMIN,
            'is_active' => true,
        ]);

        $operators = [];
        $operatorNames = [
            'Ana García', 'Roberto Silva', 'María López',
            'José Hernández', 'Laura Martínez', 'Pedro Sánchez',
            'Elena Torres', 'Miguel Ramírez', 'Sofía Cruz',
        ];

        foreach ($operatorNames as $i => $name) {
            $operators[] = User::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => Str::slug($name, '.').'@clinicasanrafael.mx',
                'password' => bcrypt('password'),
                'role' => $i < 3 ? UserRole::BRANCH_MANAGER : UserRole::OPERATOR,
                'is_active' => true,
            ]);
        }

        // ── Assign users to branches, create queues & counters ──
        foreach ($branches as $bi => $branch) {
            // Create queues per branch
            $queues = [];
            foreach (['A' => 'General', 'B' => 'Prioritaria', 'C' => 'Express'] as $prefix => $qName) {
                $queue = Queue::create([
                    'branch_id' => $branch->id,
                    'name' => "Cola {$qName}",
                    'prefix' => $prefix,
                    'priority_algorithm' => $prefix === 'B' ? 'priority' : 'fifo',
                    'max_capacity' => 80,
                    'is_active' => true,
                ]);
                // Attach services to queues
                $queue->services()->attach($services->random(rand(2, 4))->pluck('id'));
                $queues[] = $queue;
            }

            // Create counters
            for ($c = 1; $c <= 6; $c++) {
                Counter::create([
                    'branch_id' => $branch->id,
                    'name' => "Ventanilla {$c}",
                    'number' => (string) $c,
                    'status' => $c <= 4 ? 'open' : 'closed',
                    'current_operator_id' => $c <= 3 ? ($operators[$bi * 3 + $c - 1]->id ?? null) : null,
                ]);
            }

            // Assign operators to branch
            $branchOperators = array_slice($operators, $bi * 3, 3);
            foreach ($branchOperators as $op) {
                $branch->users()->attach($op->id, [
                    'id' => (string) Str::ulid(),
                    'role' => $op->role->value,
                    'is_active' => true,
                ]);
            }

            // ── Generate demo tickets for today ──
            $statuses = [
                TicketStatus::COMPLETED, TicketStatus::COMPLETED, TicketStatus::COMPLETED,
                TicketStatus::COMPLETED, TicketStatus::WAITING, TicketStatus::WAITING,
                TicketStatus::IN_PROGRESS, TicketStatus::CALLED, TicketStatus::CANCELLED,
            ];

            for ($t = 1; $t <= 45; $t++) {
                $status = $statuses[array_rand($statuses)];
                $queue = $queues[array_rand($queues)];
                $service = $services->random();
                $priority = fake()->randomElement(TicketPriority::cases());
                $issuedAt = now()->subMinutes(rand(10, 480));

                $ticketData = [
                    'branch_id' => $branch->id,
                    'queue_id' => $queue->id,
                    'service_id' => $service->id,
                    'ticket_number' => "{$queue->prefix}-".str_pad((string) $t, 3, '0', STR_PAD_LEFT),
                    'daily_sequence' => $t,
                    'display_number' => "{$branch->code}-{$queue->prefix}".str_pad((string) $t, 3, '0', STR_PAD_LEFT),
                    'customer_name' => fake('es_MX')->name(),
                    'customer_phone' => fake('es_MX')->phoneNumber(),
                    'status' => $status,
                    'priority' => $priority,
                    'priority_score' => $priority->weight(),
                    'issued_at' => $issuedAt,
                ];

                if ($status === TicketStatus::COMPLETED) {
                    $waitSec = rand(60, 600);
                    $svcSec = rand(120, 900);
                    $ticketData += [
                        'served_by' => $branchOperators[array_rand($branchOperators)]->id,
                        'called_at' => $issuedAt->copy()->addSeconds($waitSec - 30),
                        'started_at' => $issuedAt->copy()->addSeconds($waitSec),
                        'completed_at' => $issuedAt->copy()->addSeconds($waitSec + $svcSec),
                        'wait_time_seconds' => $waitSec,
                        'service_time_seconds' => $svcSec,
                        'total_time_seconds' => $waitSec + $svcSec,
                        'rating' => fake()->optional(0.6)->numberBetween(3, 5),
                    ];
                } elseif ($status === TicketStatus::IN_PROGRESS) {
                    $waitSec = rand(60, 300);
                    $ticketData += [
                        'served_by' => $branchOperators[array_rand($branchOperators)]->id,
                        'called_at' => $issuedAt->copy()->addSeconds($waitSec - 30),
                        'started_at' => $issuedAt->copy()->addSeconds($waitSec),
                        'wait_time_seconds' => $waitSec,
                    ];
                } elseif ($status === TicketStatus::CALLED) {
                    $ticketData += [
                        'served_by' => $branchOperators[array_rand($branchOperators)]->id,
                        'called_at' => now()->subMinutes(rand(1, 3)),
                    ];
                }

                Ticket::create($ticketData);
            }
        }

        $this->command->info("Demo data created: {$tenant->name}");
        $this->command->info('Admin login: admin@clinicasanrafael.mx / password');
        $this->command->info('Branches: '.$branches->pluck('name')->implode(', '));
    }
}

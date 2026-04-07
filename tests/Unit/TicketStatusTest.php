<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    public function test_waiting_can_transition_to_called(): void
    {
        $this->assertTrue(TicketStatus::WAITING->canTransitionTo(TicketStatus::CALLED));
    }

    public function test_waiting_can_transition_to_cancelled(): void
    {
        $this->assertTrue(TicketStatus::WAITING->canTransitionTo(TicketStatus::CANCELLED));
    }

    public function test_waiting_cannot_transition_to_completed(): void
    {
        $this->assertFalse(TicketStatus::WAITING->canTransitionTo(TicketStatus::COMPLETED));
    }

    public function test_called_can_transition_to_in_progress(): void
    {
        $this->assertTrue(TicketStatus::CALLED->canTransitionTo(TicketStatus::IN_PROGRESS));
    }

    public function test_called_can_transition_to_no_show(): void
    {
        $this->assertTrue(TicketStatus::CALLED->canTransitionTo(TicketStatus::NO_SHOW));
    }

    public function test_in_progress_can_transition_to_completed(): void
    {
        $this->assertTrue(TicketStatus::IN_PROGRESS->canTransitionTo(TicketStatus::COMPLETED));
    }

    public function test_in_progress_can_transition_to_transferred(): void
    {
        $this->assertTrue(TicketStatus::IN_PROGRESS->canTransitionTo(TicketStatus::TRANSFERRED));
    }

    public function test_completed_cannot_transition_anywhere(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertFalse(
                TicketStatus::COMPLETED->canTransitionTo($status),
                "COMPLETED should not transition to {$status->value}"
            );
        }
    }

    public function test_cancelled_cannot_transition_anywhere(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertFalse(
                TicketStatus::CANCELLED->canTransitionTo($status),
                "CANCELLED should not transition to {$status->value}"
            );
        }
    }

    public function test_all_statuses_have_labels(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertNotEmpty($status->label(), "{$status->value} should have a label");
        }
    }

    public function test_all_statuses_have_colors(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertNotEmpty($status->color(), "{$status->value} should have a color");
        }
    }
}

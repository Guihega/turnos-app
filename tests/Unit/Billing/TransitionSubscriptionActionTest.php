<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Enums\Billing\SubscriptionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage of the transition matrix in ADR-014.
 *
 * No database, no Laravel container — just isAllowed() driven by a
 * data provider that enumerates every (from, to) cell.
 *
 * If anyone edits TransitionSubscriptionAction::ALLOWED without
 * updating ADR-014 (or vice versa), the offending cell will fail
 * here with a clear message.
 */
final class TransitionSubscriptionActionTest extends TestCase
{
    /**
     * The 13 transitions explicitly permitted by ADR-014.
     *
     * @return list<array{0: SubscriptionStatus, 1: SubscriptionStatus}>
     */
    public static function allowedTransitionsProvider(): array
    {
        return [
            // pilot →
            [SubscriptionStatus::Pilot, SubscriptionStatus::Trialing],
            [SubscriptionStatus::Pilot, SubscriptionStatus::Active],
            [SubscriptionStatus::Pilot, SubscriptionStatus::Canceled],
            // trialing →
            [SubscriptionStatus::Trialing, SubscriptionStatus::Active],
            [SubscriptionStatus::Trialing, SubscriptionStatus::PastDue],
            [SubscriptionStatus::Trialing, SubscriptionStatus::Canceled],
            // active →
            [SubscriptionStatus::Active, SubscriptionStatus::PastDue],
            [SubscriptionStatus::Active, SubscriptionStatus::Paused],
            [SubscriptionStatus::Active, SubscriptionStatus::Canceled],
            // past_due →
            [SubscriptionStatus::PastDue, SubscriptionStatus::Active],
            [SubscriptionStatus::PastDue, SubscriptionStatus::Suspended],
            [SubscriptionStatus::PastDue, SubscriptionStatus::Canceled],
            // paused →
            [SubscriptionStatus::Paused, SubscriptionStatus::Active],
            [SubscriptionStatus::Paused, SubscriptionStatus::Canceled],
            // suspended →
            [SubscriptionStatus::Suspended, SubscriptionStatus::Active],
            [SubscriptionStatus::Suspended, SubscriptionStatus::Canceled],
        ];
    }

    /**
     * Every (from, to) cell where from === to. Same-state must NOT
     * be reported as "allowed" by isAllowed(); execute() handles it
     * as a silent no-op separately.
     *
     * @return list<array{0: SubscriptionStatus}>
     */
    public static function sameStateProvider(): array
    {
        return array_map(
            static fn (SubscriptionStatus $s): array => [$s],
            SubscriptionStatus::cases(),
        );
    }

    #[Test]
    #[DataProvider('allowedTransitionsProvider')]
    public function it_allows_every_transition_listed_in_adr_014(
        SubscriptionStatus $from,
        SubscriptionStatus $to,
    ): void {
        $this->assertTrue(
            TransitionSubscriptionAction::isAllowed($from, $to),
            sprintf(
                'Expected %s → %s to be allowed by the matrix.',
                $from->value,
                $to->value,
            ),
        );
    }

    #[Test]
    public function it_rejects_every_transition_not_listed_in_the_matrix(): void
    {
        $allowedPairs = [];
        foreach (self::allowedTransitionsProvider() as [$from, $to]) {
            $allowedPairs[$from->value.'→'.$to->value] = true;
        }

        $rejected = 0;
        foreach (SubscriptionStatus::cases() as $from) {
            foreach (SubscriptionStatus::cases() as $to) {
                if ($from === $to) {
                    continue; // same-state covered separately
                }

                $key = $from->value.'→'.$to->value;
                $expectedAllowed = isset($allowedPairs[$key]);

                $this->assertSame(
                    $expectedAllowed,
                    TransitionSubscriptionAction::isAllowed($from, $to),
                    sprintf(
                        '%s → %s: matrix disagreement.',
                        $from->value,
                        $to->value,
                    ),
                );

                if (! $expectedAllowed) {
                    $rejected++;
                }
            }
        }

        // Sanity: 7 states × 7 states = 49 cells; 7 are same-state;
        // 42 cross-state cells; 16 allowed; therefore 26 rejected.
        $this->assertSame(26, $rejected, 'Expected exactly 26 rejected cross-state transitions.');
    }

    #[Test]
    #[DataProvider('sameStateProvider')]
    public function same_state_pairs_are_not_reported_as_allowed(SubscriptionStatus $state): void
    {
        $this->assertFalse(
            TransitionSubscriptionAction::isAllowed($state, $state),
            sprintf(
                '%s → %s must not be reported as allowed (same-state is a no-op, not a transition).',
                $state->value,
                $state->value,
            ),
        );
    }

    #[Test]
    public function the_matrix_covers_every_enum_value_as_a_from_key(): void
    {
        foreach (SubscriptionStatus::cases() as $state) {
            $this->assertArrayHasKey(
                $state->value,
                TransitionSubscriptionAction::ALLOWED,
                sprintf(
                    'Matrix is missing a row for "%s". Every SubscriptionStatus must have an entry in ALLOWED, even if the list is empty (terminal state).',
                    $state->value,
                ),
            );
        }
    }

    #[Test]
    public function canceled_is_terminal_and_has_no_outgoing_transitions(): void
    {
        $this->assertSame(
            [],
            TransitionSubscriptionAction::ALLOWED['canceled'],
            'Canceled is the only terminal state per SubscriptionStatus::isTerminal().',
        );
    }

    #[Test]
    public function active_set_matches_the_partial_unique_index_clause(): void
    {
        // The partial unique index `one_active_subscription_per_customer`
        // (PR-A) covers exactly: pilot, trialing, active, past_due, paused.
        $this->assertSame(
            ['pilot', 'trialing', 'active', 'past_due', 'paused'],
            TransitionSubscriptionAction::ACTIVE_SET,
            'ACTIVE_SET must mirror the WHERE clause of one_active_subscription_per_customer.',
        );
    }

    #[Test]
    public function active_set_membership_matches_grants_access_minus_paused(): void
    {
        // grantsAccess() returns true for: pilot, trialing, active, past_due.
        // ACTIVE_SET also includes paused (occupies the slot, but no access).
        // This test guards against accidental drift between the two concepts.
        foreach (SubscriptionStatus::cases() as $state) {
            $inActiveSet = in_array($state->value, TransitionSubscriptionAction::ACTIVE_SET, true);
            $grantsAccess = $state->grantsAccess();

            if ($state === SubscriptionStatus::Paused) {
                $this->assertTrue($inActiveSet, 'paused must be in ACTIVE_SET');
                $this->assertFalse($grantsAccess, 'paused must NOT grant access');

                continue;
            }

            $this->assertSame(
                $grantsAccess,
                $inActiveSet,
                sprintf('%s: ACTIVE_SET membership and grantsAccess() should agree (paused is the only documented exception).', $state->value),
            );
        }
    }
}

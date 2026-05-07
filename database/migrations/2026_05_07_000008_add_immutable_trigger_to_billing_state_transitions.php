<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aplica un trigger PostgreSQL que rechaza UPDATE y DELETE en
 * billing_subscription_state_transitions, garantizando inmutabilidad
 * a nivel motor (no en la app).
 *
 * Esto protege contra:
 *   1. Bugs de aplicación que intenten "corregir" historial.
 *   2. Acceso administrativo manual a la BD.
 *   3. Scripts ad-hoc bien intencionados pero peligrosos.
 *
 * Solo INSERT permitido. Para excepciones genuinas (corrección por DBA
 * tras incidente confirmado), el trigger debe deshabilitarse temporal y
 * explícitamente vía:
 *   ALTER TABLE billing_subscription_state_transitions DISABLE TRIGGER ALL;
 *   -- ... corrección ...
 *   ALTER TABLE billing_subscription_state_transitions ENABLE TRIGGER ALL;
 * Procedimiento documentado en runbooks operativos.
 *
 * @see docs/billing/DECISIONS.md ADR-011 (auditoría inmutable por trigger)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_state_transition_modifications()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION
                    'billing_subscription_state_transitions is append-only. '
                    'UPDATE and DELETE are not permitted on this table.'
                    USING ERRCODE = 'check_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER prevent_update_billing_state_transitions
            BEFORE UPDATE ON billing_subscription_state_transitions
            FOR EACH ROW
            EXECUTE FUNCTION reject_state_transition_modifications();
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER prevent_delete_billing_state_transitions
            BEFORE DELETE ON billing_subscription_state_transitions
            FOR EACH ROW
            EXECUTE FUNCTION reject_state_transition_modifications();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS prevent_update_billing_state_transitions ON billing_subscription_state_transitions');
        DB::statement('DROP TRIGGER IF EXISTS prevent_delete_billing_state_transitions ON billing_subscription_state_transitions');
        DB::statement('DROP FUNCTION IF EXISTS reject_state_transition_modifications()');
    }
};

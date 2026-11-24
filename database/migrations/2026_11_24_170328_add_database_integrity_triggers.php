<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION meter_readings_no_decrease()
            RETURNS trigger AS $$
            DECLARE latest numeric(12,2);
            BEGIN
                SELECT max(reading_hours) INTO latest
                FROM meter_readings
                WHERE asset_id = NEW.asset_id
                  AND (TG_OP = 'INSERT' OR id <> NEW.id);

                IF latest IS NOT NULL AND NEW.reading_hours < latest THEN
                    RAISE EXCEPTION 'meter reading must not decrease below previous reading (%)', latest
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER meter_readings_no_decrease
             BEFORE INSERT OR UPDATE ON meter_readings
             FOR EACH ROW EXECUTE FUNCTION meter_readings_no_decrease()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'append-only table: % is not permitted', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        foreach (['audit_events', 'plan_baseline_history', 'work_order_events', 'spare_movements'] as $table) {
            DB::statement(
                "CREATE TRIGGER {$table}_append_only
                 BEFORE UPDATE OR DELETE ON {$table}
                 FOR EACH ROW EXECUTE FUNCTION prevent_mutation()"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['audit_events', 'plan_baseline_history', 'work_order_events', 'spare_movements'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_append_only ON {$table}");
        }

        DB::statement('DROP TRIGGER IF EXISTS meter_readings_no_decrease ON meter_readings');

        DB::statement('DROP FUNCTION IF EXISTS prevent_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS meter_readings_no_decrease()');
    }
};

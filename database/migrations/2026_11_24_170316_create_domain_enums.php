<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Native PostgreSQL enum types for the CMMS domain.
     *
     * `user_role` is created by the users migration; this migration adds the
     * remaining four types. Each type is created only when absent so a
     * partially applied schema can be recovered safely.
     *
     * @var array<string, array<int, string>>
     */
    private array $types = [
        'criticality' => ['low', 'medium', 'high', 'critical'],
        'service_status' => ['in_service', 'standby', 'withdrawn'],
        'plan_trigger' => ['calendar', 'hours', 'dual'],
        'work_order_state' => ['open', 'in_progress', 'submitted', 'closed'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->types as $type => $values) {
            $labels = collect($values)
                ->map(fn (string $value): string => "'{$value}'")
                ->implode(', ');

            DB::statement(<<<SQL
                DO \$\$
                BEGIN
                    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = '{$type}') THEN
                        CREATE TYPE {$type} AS ENUM ({$labels});
                    END IF;
                END
                \$\$;
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(array_keys($this->types)) as $type) {
            DB::statement("DROP TYPE IF EXISTS {$type}");
        }
    }
};

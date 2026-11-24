<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_order_events', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('event_type', 20);
            $table->string('from_state', 20)->nullable();
            $table->string('to_state', 20)->nullable();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();

            $table->index(['work_order_id', 'occurred_at']);
        });

        DB::statement(
            "ALTER TABLE work_order_events ADD CONSTRAINT work_order_events_type_check
             CHECK (event_type IN ('created','started','submitted','returned','closed','corrected'))"
        );

        DB::statement(
            "ALTER TABLE work_order_events ADD CONSTRAINT work_order_events_return_reason_check
             CHECK (event_type <> 'returned' OR (reason IS NOT NULL AND length(btrim(reason)) > 0))"
        );

        DB::statement(
            'ALTER TABLE work_order_events
             ALTER COLUMN from_state TYPE work_order_state USING from_state::work_order_state'
        );

        DB::statement(
            'ALTER TABLE work_order_events
             ALTER COLUMN to_state TYPE work_order_state USING to_state::work_order_state'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_events');
    }
};

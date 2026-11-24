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
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('reference', 20)->unique();
            $table->foreignUuid('plan_id')->constrained('maintenance_plans')->restrictOnDelete();
            $table->string('state', 20)->default('open');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->decimal('actual_completion_hours', 12, 2)->nullable();
            $table->text('completion_notes')->nullable();
            $table->string('evidence_ref', 255)->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->index('plan_id');
            $table->index('state');
            $table->index(['assigned_to', 'state']);
        });

        DB::statement('ALTER TABLE work_orders ALTER COLUMN state DROP DEFAULT');
        DB::statement('ALTER TABLE work_orders ALTER COLUMN state TYPE work_order_state USING state::work_order_state');
        DB::statement("ALTER TABLE work_orders ALTER COLUMN state SET DEFAULT 'open'");
        DB::statement('ALTER TABLE work_orders ALTER COLUMN state SET NOT NULL');

        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_closed_at_check CHECK (state <> 'closed' OR closed_at IS NOT NULL)");
        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_submitted_at_check CHECK (state <> 'submitted' OR submitted_at IS NOT NULL)");
        DB::statement('ALTER TABLE work_orders ADD CONSTRAINT work_orders_actual_hours_check CHECK (actual_completion_hours IS NULL OR actual_completion_hours >= 0)');

        DB::statement(
            'CREATE UNIQUE INDEX one_active_work_order_per_plan
             ON work_orders (plan_id)
             WHERE state IN (\'open\', \'in_progress\', \'submitted\')'
        );

        DB::statement('CREATE INDEX work_orders_created_at_index ON work_orders (created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};

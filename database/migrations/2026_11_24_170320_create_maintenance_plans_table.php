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
        Schema::create('maintenance_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->text('task_description');
            $table->string('trigger_type', 20);
            $table->integer('interval_days')->nullable();
            $table->decimal('interval_hours', 12, 2)->nullable();
            $table->date('last_completion_date')->nullable();
            $table->decimal('last_completion_hours', 12, 2)->nullable();
            $table->uuid('last_completion_work_order_id')->nullable();
            $table->string('source_label', 120)->default('Synthetic teaching scenario');
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();

            $table->index('asset_id');
            $table->index('is_active');
        });

        DB::statement('ALTER TABLE maintenance_plans ALTER COLUMN trigger_type TYPE plan_trigger USING trigger_type::plan_trigger');
        DB::statement('ALTER TABLE maintenance_plans ALTER COLUMN trigger_type SET NOT NULL');

        DB::statement('ALTER TABLE maintenance_plans ADD CONSTRAINT maintenance_plans_interval_days_check CHECK (interval_days > 0)');
        DB::statement('ALTER TABLE maintenance_plans ADD CONSTRAINT maintenance_plans_interval_hours_check CHECK (interval_hours > 0)');
        DB::statement('ALTER TABLE maintenance_plans ADD CONSTRAINT maintenance_plans_last_completion_hours_check CHECK (last_completion_hours >= 0)');

        DB::statement(
            "ALTER TABLE maintenance_plans ADD CONSTRAINT maintenance_plans_trigger_interval_check CHECK (
                (trigger_type <> 'calendar' OR interval_days IS NOT NULL)
                AND (trigger_type <> 'hours' OR interval_hours IS NOT NULL)
                AND (trigger_type <> 'dual' OR (interval_days IS NOT NULL AND interval_hours IS NOT NULL))
            )"
        );

        DB::statement(
            "ALTER TABLE maintenance_plans ADD CONSTRAINT chk_plan_activation_complete CHECK (
                NOT is_active OR (
                    (trigger_type = 'calendar' AND interval_days IS NOT NULL AND last_completion_date IS NOT NULL)
                    OR (trigger_type = 'hours' AND interval_hours IS NOT NULL AND last_completion_hours IS NOT NULL)
                    OR (trigger_type = 'dual' AND interval_days IS NOT NULL AND last_completion_date IS NOT NULL
                        AND interval_hours IS NOT NULL AND last_completion_hours IS NOT NULL)
                )
            )"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};

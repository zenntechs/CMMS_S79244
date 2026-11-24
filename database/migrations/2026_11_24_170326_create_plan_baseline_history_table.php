<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plan_baseline_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('plan_id')->constrained('maintenance_plans')->restrictOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->restrictOnDelete();
            $table->date('previous_date')->nullable();
            $table->date('new_date')->nullable();
            $table->decimal('previous_hours', 12, 2)->nullable();
            $table->decimal('new_hours', 12, 2)->nullable();
            $table->foreignUuid('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('changed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_baseline_history');
    }
};

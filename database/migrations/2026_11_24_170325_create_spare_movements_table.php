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
        Schema::create('spare_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('spare_item_id')->constrained('spare_items')->restrictOnDelete();
            $table->foreignUuid('work_order_id')->nullable()->constrained('work_orders')->restrictOnDelete();
            $table->string('movement_type', 20);
            $table->decimal('quantity_delta', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();

            $table->index(['spare_item_id', 'occurred_at']);
            $table->index('work_order_id');
        });

        DB::statement(
            "ALTER TABLE spare_movements ADD CONSTRAINT spare_movements_type_check
             CHECK (movement_type IN ('issue','return','receipt','adjustment'))"
        );
        DB::statement('ALTER TABLE spare_movements ADD CONSTRAINT spare_movements_qty_check CHECK (quantity_delta <> 0)');
        DB::statement('ALTER TABLE spare_movements ADD CONSTRAINT spare_movements_balance_check CHECK (balance_after >= 0)');
        DB::statement("ALTER TABLE spare_movements ADD CONSTRAINT spare_movements_issue_sign_check CHECK (movement_type <> 'issue' OR quantity_delta < 0)");
        DB::statement("ALTER TABLE spare_movements ADD CONSTRAINT spare_movements_receipt_sign_check CHECK (movement_type <> 'receipt' OR quantity_delta > 0)");
        DB::statement("ALTER TABLE spare_movements ADD CONSTRAINT spare_movements_return_sign_check CHECK (movement_type <> 'return' OR quantity_delta > 0)");
        DB::statement("ALTER TABLE spare_movements ADD CONSTRAINT spare_movements_adjustment_reason_check CHECK (movement_type <> 'adjustment' OR (reason IS NOT NULL AND length(btrim(reason)) > 0))");
        DB::statement("ALTER TABLE spare_movements ADD CONSTRAINT spare_movements_issue_wo_check CHECK (movement_type <> 'issue' OR work_order_id IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spare_movements');
    }
};

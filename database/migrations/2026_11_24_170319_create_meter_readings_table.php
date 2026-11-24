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
        Schema::create('meter_readings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->decimal('reading_hours', 12, 2);
            $table->timestampTz('reading_at')->useCurrent();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE meter_readings ADD CONSTRAINT meter_readings_hours_check CHECK (reading_hours >= 0)');

        DB::statement(
            'CREATE INDEX meter_readings_asset_reading_index
             ON meter_readings (asset_id, reading_at DESC, id DESC)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};

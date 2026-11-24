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
        Schema::create('assets', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('vessel_id')->constrained('vessels')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('subsystem', 80);
            $table->string('location', 120)->nullable();
            $table->string('criticality', 20);
            $table->string('service_status', 20);
            $table->boolean('has_hour_meter')->default(false);
            $table->string('meter_unit', 10)->default('hours');
            $table->timestampsTz();

            $table->index('subsystem');
            $table->index('criticality');
            $table->index('service_status');
        });

        DB::statement('ALTER TABLE assets ALTER COLUMN criticality TYPE criticality USING criticality::criticality');
        DB::statement('ALTER TABLE assets ALTER COLUMN criticality SET NOT NULL');

        DB::statement('ALTER TABLE assets ALTER COLUMN service_status TYPE service_status USING service_status::service_status');
        DB::statement("ALTER TABLE assets ALTER COLUMN service_status SET DEFAULT 'in_service'");
        DB::statement('ALTER TABLE assets ALTER COLUMN service_status SET NOT NULL');

        DB::statement("ALTER TABLE assets ADD CONSTRAINT assets_meter_unit_check CHECK (meter_unit = 'hours')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

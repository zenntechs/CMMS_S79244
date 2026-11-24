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
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 50);
            $table->string('entity_type', 50);
            $table->uuid('entity_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
        });

        DB::statement(
            'CREATE INDEX audit_events_entity_index
             ON audit_events (entity_type, entity_id, occurred_at DESC)'
        );
        DB::statement('CREATE INDEX audit_events_actor_index ON audit_events (actor_id)');
        DB::statement('CREATE INDEX audit_events_occurred_at_index ON audit_events (occurred_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};

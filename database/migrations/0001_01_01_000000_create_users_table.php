<?php

use App\Enums\UserRole;
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
        $allowedRoles = collect(UserRole::cases())
            ->map(fn (UserRole $role): string => "'{$role->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
                    CREATE TYPE user_role AS ENUM ({$allowedRoles});
                END IF;
            END
            \$\$;
        SQL);

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 160)->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE users ALTER COLUMN role TYPE user_role USING role::user_role');
        DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'viewer'");
        DB::statement('ALTER TABLE users ALTER COLUMN role SET NOT NULL');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 160)->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');

        DB::statement('DROP TYPE IF EXISTS user_role');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security Center (Settings → Security).
 *
 * - users.password_changed_at drives password expiry. Existing users are
 *   backfilled to now, so their expiry clock starts on deploy day -- nobody
 *   is forced to change on the day this ships.
 * - users.must_change_password: a temporary password (set by an Admin, or a
 *   brand-new account) must be replaced at the next login.
 * - password_histories: hashes only (never plain text), so recent passwords
 *   can't be reused.
 * - login_events: successful and failed logins, lockouts, logouts and admin
 *   security actions. The app's DB user (ops_grants.sql) has only SELECT and
 *   INSERT on both new tables, so the log is append-only for the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('password');
            $table->boolean('must_change_password')->default(false)->after('password_changed_at');
        });

        DB::table('users')->update(['password_changed_at' => now()]);

        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
        });

        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Exactly what was typed, kept even when no account matches.
            $table->string('email')->nullable();
            // login, logout, failed, lockout, password_reset_by_admin, sessions_revoked
            $table->string('event', 40);
            // failed: wrong_password, unknown_email, deactivated
            $table->string('reason', 40)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            // The Admin who performed an admin action (reset, sign-out).
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index('created_at');
            $table->index('ip_address');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('password_histories');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_changed_at', 'must_change_password']);
        });
    }
};

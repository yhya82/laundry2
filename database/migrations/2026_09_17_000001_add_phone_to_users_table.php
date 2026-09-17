<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Required + unique from the start, same shape as customers.phone
     * (format check included). Existing users predate this column, so
     * they're backfilled with an obviously-fake +220 90-prefix test number
     * first -- '90' isn't a real assigned prefix per
     * MigratePhoneNumbersTo9Digit's PREFIX_MAP -- so the NOT NULL + UNIQUE
     * constraints below can be added without breaking them. A real number
     * should replace the placeholder the next time each account is edited.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
        });

        DB::table('users')->whereNull('phone')->orderBy('id')->get(['id'])->each(
            fn ($user) => DB::table('users')->where('id', $user->id)->update([
                'phone' => '+22090'.str_pad((string) $user->id, 7, '0', STR_PAD_LEFT),
            ])
        );

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_phone_format CHECK (phone REGEXP '^[+0-9][0-9 ()-]{6,19}$')");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_phone_format');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
        });
    }
};

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
        $trustedTables = array_unique([
            config('pasak.table_names.trusted_ips', 'pasak_trusted_ips'),
            'pasak_trusted_ips',
            'trusted_ips',
        ]);

        foreach ($trustedTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        $blueprint->string('operating_system', 255)->nullable()->change();
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        $blueprint->string('browser', 255)->nullable()->change();
                    }
                });
            }
        }

        $loginTables = array_unique([
            config('pasak.table_names.user_logins', 'pasak_user_logins'),
            'pasak_user_logins',
            'user_logins',
        ]);

        foreach ($loginTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        $blueprint->string('operating_system', 255)->default('Unknown OS')->change();
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        $blueprint->string('browser', 255)->default('Unknown Browser')->change();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $trustedTables = array_unique([
            config('pasak.table_names.trusted_ips', 'pasak_trusted_ips'),
            'pasak_trusted_ips',
            'trusted_ips',
        ]);

        foreach ($trustedTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        $blueprint->string('operating_system', 100)->nullable()->change();
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        $blueprint->string('browser', 100)->nullable()->change();
                    }
                });
            }
        }

        $loginTables = array_unique([
            config('pasak.table_names.user_logins', 'pasak_user_logins'),
            'pasak_user_logins',
            'user_logins',
        ]);

        foreach ($loginTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        $blueprint->string('operating_system', 100)->default('Unknown OS')->change();
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        $blueprint->string('browser', 100)->default('Unknown Browser')->change();
                    }
                });
            }
        }
    }
};

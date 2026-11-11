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
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        $trustedTables = array_unique([
            config('pasak.table_names.trusted_ips', 'pasak_trusted_ips'),
            'pasak_trusted_ips',
            'trusted_ips',
        ]);

        foreach ($trustedTables as $table) {
            if (Schema::hasTable($table)) {
                // SQLite tidak menerapkan batas karakter VARCHAR secara ketat
                // dan tidak memerlukan Doctrine DBAL untuk ubah kolom.
                if ($driver === 'sqlite') {
                    continue;
                }

                if ($driver === 'mysql' || $driver === 'mariadb') {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `operating_system` VARCHAR(255) NULL");
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `browser` VARCHAR(255) NULL");
                    }

                    continue;
                }

                if ($driver === 'pgsql') {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"operating_system\" TYPE VARCHAR(255)");
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"browser\" TYPE VARCHAR(255)");
                    }

                    continue;
                }

                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($table) {
                        if (Schema::hasColumn($table, 'operating_system')) {
                            $blueprint->string('operating_system', 255)->nullable()->change();
                        }
                        if (Schema::hasColumn($table, 'browser')) {
                            $blueprint->string('browser', 255)->nullable()->change();
                        }
                    });
                } catch (Throwable) {
                    // Abaikan jika database engine tidak mendukung change column tanpa doctrine/dbal
                }
            }
        }

        $loginTables = array_unique([
            config('pasak.table_names.user_logins', 'pasak_user_logins'),
            'pasak_user_logins',
            'user_logins',
        ]);

        foreach ($loginTables as $table) {
            if (Schema::hasTable($table)) {
                if ($driver === 'sqlite') {
                    continue;
                }

                if ($driver === 'mysql' || $driver === 'mariadb') {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `operating_system` VARCHAR(255) NOT NULL DEFAULT 'Unknown OS'");
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `browser` VARCHAR(255) NOT NULL DEFAULT 'Unknown Browser'");
                    }

                    continue;
                }

                if ($driver === 'pgsql') {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"operating_system\" TYPE VARCHAR(255)");
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"browser\" TYPE VARCHAR(255)");
                    }

                    continue;
                }

                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($table) {
                        if (Schema::hasColumn($table, 'operating_system')) {
                            $blueprint->string('operating_system', 255)->default('Unknown OS')->change();
                        }
                        if (Schema::hasColumn($table, 'browser')) {
                            $blueprint->string('browser', 255)->default('Unknown Browser')->change();
                        }
                    });
                } catch (Throwable) {
                    // Abaikan jika database engine tidak mendukung change column tanpa doctrine/dbal
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        $trustedTables = array_unique([
            config('pasak.table_names.trusted_ips', 'pasak_trusted_ips'),
            'pasak_trusted_ips',
            'trusted_ips',
        ]);

        foreach ($trustedTables as $table) {
            if (Schema::hasTable($table)) {
                if ($driver === 'sqlite') {
                    continue;
                }

                if ($driver === 'mysql' || $driver === 'mariadb') {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `operating_system` VARCHAR(100) NULL");
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `browser` VARCHAR(100) NULL");
                    }

                    continue;
                }

                if ($driver === 'pgsql') {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"operating_system\" TYPE VARCHAR(100)");
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"browser\" TYPE VARCHAR(100)");
                    }

                    continue;
                }

                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($table) {
                        if (Schema::hasColumn($table, 'operating_system')) {
                            $blueprint->string('operating_system', 100)->nullable()->change();
                        }
                        if (Schema::hasColumn($table, 'browser')) {
                            $blueprint->string('browser', 100)->nullable()->change();
                        }
                    });
                } catch (Throwable) {
                    // Abaikan jika database engine tidak mendukung rollback change column
                }
            }
        }

        $loginTables = array_unique([
            config('pasak.table_names.user_logins', 'pasak_user_logins'),
            'pasak_user_logins',
            'user_logins',
        ]);

        foreach ($loginTables as $table) {
            if (Schema::hasTable($table)) {
                if ($driver === 'sqlite') {
                    continue;
                }

                if ($driver === 'mysql' || $driver === 'mariadb') {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `operating_system` VARCHAR(100) NOT NULL DEFAULT 'Unknown OS'");
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `browser` VARCHAR(100) NOT NULL DEFAULT 'Unknown Browser'");
                    }

                    continue;
                }

                if ($driver === 'pgsql') {
                    if (Schema::hasColumn($table, 'operating_system')) {
                        DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"operating_system\" TYPE VARCHAR(100)");
                    }
                    if (Schema::hasColumn($table, 'browser')) {
                        DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"browser\" TYPE VARCHAR(100)");
                    }

                    continue;
                }

                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($table) {
                        if (Schema::hasColumn($table, 'operating_system')) {
                            $blueprint->string('operating_system', 100)->default('Unknown OS')->change();
                        }
                        if (Schema::hasColumn($table, 'browser')) {
                            $blueprint->string('browser', 100)->default('Unknown Browser')->change();
                        }
                    });
                } catch (Throwable) {
                    // Abaikan jika driver tidak mendukung
                }
            }
        }
    }
};

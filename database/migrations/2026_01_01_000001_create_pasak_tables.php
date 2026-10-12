<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $usersTable = config('pasak.table_names.users', 'users');
        $blockedIpsTable = config('pasak.table_names.blocked_ips', 'blocked_ips');
        $logsTable = config(
            'pasak.table_names.pasak_logs',
            config('pasak.table_names.security_logs', 'pasak_logs')
        );
        $loginAttemptsTable = config('pasak.table_names.login_attempts', 'login_attempts');
        $unblockRequestsTable = config('pasak.table_names.ip_unblock_requests', 'ip_unblock_requests');
        $userLoginsTable = config('pasak.table_names.user_logins', 'user_logins');
        $trustedIpsTable = config('pasak.table_names.trusted_ips', 'trusted_ips');
        $settingsTable = config('pasak.table_names.settings', 'pasak_settings');
        $allowedIpsTable = config('pasak.table_names.allowed_ips', 'allowed_ips');

        // Seamless upgrade from robyajo/pasak installs predating the rename:
        // carry existing data over instead of starting from empty tables.
        foreach ([
            'security_logs' => $logsTable,
            'security_settings' => $settingsTable,
        ] as $legacy => $current) {
            if ($legacy !== $current && Schema::hasTable($legacy) && ! Schema::hasTable($current)) {
                Schema::rename($legacy, $current);
            }
        }

        if (! Schema::hasTable($blockedIpsTable)) {
            Schema::create($blockedIpsTable, function (Blueprint $blueprint) use ($usersTable) {
                $blueprint->id();
                $blueprint->string('ip_address', 45);
                $blueprint->string('local_ip', 45)->nullable();
                $blueprint->string('device_id', 100)->nullable()->index();
                $blueprint->string('block_scope', 20)->default('ip');
                $blueprint->string('reason')->nullable();
                $blueprint->text('notes')->nullable();
                $blueprint->string('source', 20)->default('manual');
                $blueprint->foreignId('blocked_by')->nullable()->constrained($usersTable)->nullOnDelete();
                $blueprint->timestamp('blocked_at')->nullable();
                $blueprint->timestamp('expires_at')->nullable();
                $blueprint->boolean('is_active')->default(true);
                $blueprint->unsignedInteger('hit_count')->default(0);
                $blueprint->timestamp('last_hit_at')->nullable();
                $blueprint->timestamps();

                $blueprint->index(['is_active', 'ip_address']);
                $blueprint->index('expires_at');
            });
        }

        if (! Schema::hasTable($logsTable)) {
            Schema::create($logsTable, function (Blueprint $blueprint) use ($usersTable) {
                $blueprint->id();
                $blueprint->string('ip_address', 45)->index();
                $blueprint->string('local_ip', 45)->nullable();
                $blueprint->string('device_id', 100)->nullable()->index();
                $blueprint->foreignId('user_id')->nullable()->constrained($usersTable)->nullOnDelete();
                $blueprint->string('event_type', 40)->index();
                $blueprint->string('threat_level', 20)->default('low')->index();
                $blueprint->string('method', 10)->nullable();
                $blueprint->string('path', 2048)->nullable();
                $blueprint->text('full_url')->nullable();
                $blueprint->string('rule_label')->nullable();
                $blueprint->text('evidence')->nullable();
                $blueprint->string('user_agent', 512)->nullable();
                $blueprint->string('referer', 512)->nullable();
                $blueprint->boolean('was_blocked')->default(false);
                $blueprint->string('action_taken', 40)->nullable();
                $blueprint->json('meta')->nullable();
                $blueprint->timestamps();

                $blueprint->index(['ip_address', 'created_at']);
                $blueprint->index(['threat_level', 'created_at']);
                $blueprint->index('created_at');
            });
        }

        if (! Schema::hasTable($loginAttemptsTable)) {
            Schema::create($loginAttemptsTable, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('email')->nullable();
                $blueprint->string('ip_address', 45)->nullable();
                $blueprint->unsignedInteger('attempts')->default(0);
                $blueprint->unsignedInteger('lockout_level')->default(0);
                $blueprint->timestamp('locked_until')->nullable();
                $blueprint->timestamp('last_attempt_at')->nullable();
                $blueprint->timestamps();

                $blueprint->unique(['email', 'ip_address']);
                $blueprint->index('locked_until');
                $blueprint->index('last_attempt_at');
            });
        }

        if (! Schema::hasTable($unblockRequestsTable)) {
            Schema::create($unblockRequestsTable, function (Blueprint $blueprint) use ($usersTable, $blockedIpsTable) {
                $blueprint->id();
                $blueprint->string('ticket_number', 40)->unique();
                $blueprint->string('ip_address', 45)->index();
                $blueprint->string('local_ip', 45)->nullable();
                $blueprint->string('device_id', 100)->nullable()->index();
                $blueprint->string('name', 100);
                $blueprint->string('email', 150);
                $blueprint->string('phone', 30)->nullable();
                $blueprint->text('reason');
                $blueprint->string('status', 20)->default('pending')->index();
                $blueprint->text('admin_notes')->nullable();
                $blueprint->foreignId('resolved_by')->nullable()->constrained($usersTable)->nullOnDelete();
                $blueprint->timestamp('resolved_at')->nullable();
                $blueprint->foreignId('blocked_ip_id')->nullable()->constrained($blockedIpsTable)->nullOnDelete();
                $blueprint->text('user_agent')->nullable();
                $blueprint->timestamps();

                $blueprint->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable($userLoginsTable)) {
            Schema::create($userLoginsTable, function (Blueprint $blueprint) use ($usersTable) {
                $blueprint->id();
                $blueprint->foreignId('user_id')->constrained($usersTable)->cascadeOnDelete();
                $blueprint->string('session_id', 255)->nullable()->index();
                $blueprint->string('ip_address', 45)->index();
                $blueprint->string('local_ip', 45)->nullable();
                $blueprint->string('device_id', 100)->nullable()->index();
                $blueprint->text('user_agent')->nullable();
                $blueprint->string('device_type', 50)->default('desktop');
                $blueprint->string('operating_system', 100)->default('Unknown OS');
                $blueprint->string('browser', 100)->default('Unknown Browser');
                $blueprint->string('city', 100)->nullable();
                $blueprint->string('region', 100)->nullable();
                $blueprint->string('country', 100)->nullable();
                $blueprint->string('country_code', 10)->nullable();
                $blueprint->decimal('latitude', 10, 7)->nullable();
                $blueprint->decimal('longitude', 10, 7)->nullable();
                $blueprint->string('isp', 255)->nullable();
                $blueprint->timestamp('login_at');
                $blueprint->timestamp('last_activity_at')->nullable()->index();
                $blueprint->timestamp('logout_at')->nullable();
                $blueprint->timestamps();
            });
        }

        if (! Schema::hasTable($trustedIpsTable)) {
            Schema::create($trustedIpsTable, function (Blueprint $blueprint) use ($usersTable) {
                $blueprint->id();
                $blueprint->foreignId('user_id')->constrained($usersTable)->cascadeOnDelete();
                $blueprint->string('ip_address', 45)->index();
                $blueprint->string('local_ip', 45)->nullable();
                $blueprint->string('device_id', 100)->nullable()->index();
                $blueprint->string('device_name', 255)->nullable();
                $blueprint->string('operating_system', 100)->nullable();
                $blueprint->string('browser', 100)->nullable();
                $blueprint->string('location', 255)->nullable();
                $blueprint->boolean('is_active')->default(true);
                $blueprint->timestamp('verified_at')->nullable();
                $blueprint->timestamps();

                $blueprint->unique(['user_id', 'ip_address']);
            });
        }

        if (! Schema::hasTable($settingsTable)) {
            Schema::create($settingsTable, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('key')->unique();
                $blueprint->text('value')->nullable();
                $blueprint->timestamps();
            });
        }

        if (! Schema::hasTable($allowedIpsTable)) {
            Schema::create($allowedIpsTable, function (Blueprint $blueprint) use ($usersTable) {
                $blueprint->id();
                $blueprint->string('ip_address', 45)->index();
                $blueprint->string('label', 150)->nullable();
                $blueprint->text('notes')->nullable();
                $blueprint->foreignId('allowed_by')->nullable()->constrained($usersTable)->nullOnDelete();
                $blueprint->boolean('is_active')->default(true);
                $blueprint->timestamp('expires_at')->nullable()->index();
                $blueprint->timestamps();

                $blueprint->index(['is_active', 'ip_address']);
            });
        }
    }

    public function down(): void
    {
        $tables = [
            config('pasak.table_names.settings', 'pasak_settings'),
            config('pasak.table_names.allowed_ips', 'allowed_ips'),
            config('pasak.table_names.trusted_ips', 'trusted_ips'),
            config('pasak.table_names.user_logins', 'user_logins'),
            config('pasak.table_names.ip_unblock_requests', 'ip_unblock_requests'),
            config('pasak.table_names.login_attempts', 'login_attempts'),
            config(
                'pasak.table_names.pasak_logs',
                config('pasak.table_names.security_logs', 'pasak_logs')
            ),
            config('pasak.table_names.blocked_ips', 'blocked_ips'),
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};

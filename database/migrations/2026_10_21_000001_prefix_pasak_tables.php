<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Internal\Pasak\Concerns\ResolvesPasakTable;

return new class extends Migration
{
    /**
     * Map of legacy un-prefixed table names to their prefixed counterpart.
     *
     * @return array<string, string>
     */
    protected function tableMap(): array
    {
        return [
            'blocked_ips' => config('pasak.table_names.blocked_ips', 'pasak_blocked_ips'),
            'login_attempts' => config('pasak.table_names.login_attempts', 'pasak_login_attempts'),
            'ip_unblock_requests' => config('pasak.table_names.ip_unblock_requests', 'pasak_ip_unblock_requests'),
            'user_logins' => config('pasak.table_names.user_logins', 'pasak_user_logins'),
            'trusted_ips' => config('pasak.table_names.trusted_ips', 'pasak_trusted_ips'),
            'allowed_ips' => config('pasak.table_names.allowed_ips', 'pasak_allowed_ips'),
            'security_logs' => config('pasak.table_names.pasak_logs', 'pasak_logs'),
            'security_settings' => config('pasak.table_names.settings', 'pasak_settings'),
        ];
    }

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($this->tableMap() as $legacy => $current) {
                if ($legacy !== $current && Schema::hasTable($legacy) && ! Schema::hasTable($current)) {
                    Schema::rename($legacy, $current);
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        ResolvesPasakTable::clearResolvedTableCache();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($this->tableMap() as $legacy => $current) {
                if ($legacy !== $current && Schema::hasTable($current) && ! Schema::hasTable($legacy)) {
                    Schema::rename($current, $legacy);
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        ResolvesPasakTable::clearResolvedTableCache();
    }
};

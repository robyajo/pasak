<?php

namespace Internal\Pasak\Console\Commands;

use Illuminate\Console\Command;
use Internal\Pasak\Models\SecurityLog;

class PruneSecurityLogs extends Command
{
    protected $signature = 'pasak:prune-logs {--days= : Override the configured retention period}';

    protected $description = 'Hapus log keamanan yang lebih lama dari masa retensi yang dikonfigurasi';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('pasak.retention_days', 90));

        if ($days <= 0) {
            $this->info('Retensi log keamanan dinonaktifkan, tidak ada log yang dihapus.');

            return self::SUCCESS;
        }

        $deleted = SecurityLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("{$deleted} log keamanan lebih lama dari {$days} hari berhasil dihapus.");

        return self::SUCCESS;
    }
}

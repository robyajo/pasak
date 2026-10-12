<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('pasak.table_names.allowed_ips', 'allowed_ips');
        $usersTable = config('pasak.table_names.users', 'users');

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $blueprint) use ($usersTable) {
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
        Schema::dropIfExists(config('pasak.table_names.allowed_ips', 'allowed_ips'));
    }
};

<?php

namespace Internal\Pasak\Database\Seeders;

use Illuminate\Database\Seeder;
use Internal\Pasak\Services\PasakService;

/**
 * Seeds the built-in default security settings into the pasak_settings
 * table so every tunable knob lives in the database and can be adjusted
 * from the Pasak dashboard without editing .env.
 *
 * Idempotent: existing setting rows are never overwritten.
 *
 * Manual usage:  php artisan db:seed --class=Internal\Pasak\Database\Seeders\PasakDefaultSettingsSeeder --force
 */
class PasakDefaultSettingsSeeder extends Seeder
{
    public function __construct(
        protected PasakService $security,
    ) {}

    /**
     * Run the seeding.
     *
     * @return array<int, string> newly created setting keys
     */
    public function run(): array
    {
        return $this->security->seedDefaultSettings();
    }
}

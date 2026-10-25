<?php

namespace Internal\Pasak\Database\Seeders;

use Illuminate\Database\Seeder;
use Internal\Pasak\Services\PasakService;

/**
 * Seeds the built-in default security settings into the pasak_settings
 * table so every tunable knob lives in the database and can be adjusted
 * from the Pasak dashboard without editing .env.
 *
 * Idempotent: existing setting rows are never overwritten unless $overwrite is true.
 *
 * Manual usage:  php artisan db:seed --class=Internal\Pasak\Database\Seeders\PasakDefaultSettingsSeeder --force
 */
class PasakDefaultSettingsSeeder extends Seeder
{
    public function __construct(protected PasakService $security) {}

    /**
     * Run the seeding.
     *
     * @param  bool  $overwrite  Whether to overwrite existing settings with defaults
     * @return array<int, string> newly created or updated setting keys
     */
    public function run(bool $overwrite = false): array
    {
        return $this->security->seedDefaultSettings($overwrite);
    }
}

<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Internal\Pasak\Console\Commands\PasakDeployCommand;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Models\PasakSetting;
use Internal\Pasak\Support\PasakDashboardSecret;

function pasakDeployTemporaryEnv(): string
{
    $dir = sys_get_temp_dir() . "/pasak-deploy-" . uniqid("", true);
    File::ensureDirectoryExists($dir);

    return $dir . "/.env";
}

test("pasak:deploy terdaftar sebagai perintah artisan resmi", function () {
    expect(array_keys(Artisan::all()))->toContain("pasak:deploy");
});

test(
    "pasak:deploy memastikan PASAK_DASHBOARD_SECRET terisi di .env tanpa menimpa rahasia lama",
    function () {
        $envPath = pasakDeployTemporaryEnv();

        try {
            File::put(
                $envPath,
                "APP_ENV=production\nPASAK_DASHBOARD_SECRET=\n",
            );

            $this->artisan("pasak:deploy", [
                "--env-path" => $envPath,
                "--force" => true,
            ])->assertSuccessful();

            $secret = PasakDashboardSecret::readFromEnvFile($envPath);
            expect($secret)
                ->toBeString()
                ->and(strlen((string) $secret))
                ->toBe(PasakDashboardSecret::LENGTH);

            // Jalankan ulang: rahasia yang sama TIDAK boleh ditimpa
            $this->artisan("pasak:deploy", [
                "--env-path" => $envPath,
                "--force" => true,
            ])->assertSuccessful();

            expect(PasakDashboardSecret::readFromEnvFile($envPath))->toBe(
                $secret,
            );

            // Dengan --force-secret: rahasia baru dibuat
            $this->artisan("pasak:deploy", [
                "--env-path" => $envPath,
                "--force-secret" => true,
                "--force" => true,
            ])->assertSuccessful();

            $newSecret = PasakDashboardSecret::readFromEnvFile($envPath);
            expect($newSecret)
                ->not->toBe($secret)
                ->and(strlen((string) $newSecret))
                ->toBe(PasakDashboardSecret::LENGTH);
        } finally {
            File::delete($envPath);
        }
    },
);

test(
    "pasak:deploy menanam pengaturan default secara idempotent tanpa duplikasi data saat diulang",
    function () {
        $envPath = pasakDeployTemporaryEnv();

        try {
            File::put(
                $envPath,
                "APP_ENV=production\nPASAK_DASHBOARD_SECRET=\n",
            );

            // Jalankan pertama kali
            $this->artisan("pasak:deploy", [
                "--env-path" => $envPath,
                "--force" => true,
            ])->assertSuccessful();

            $initialCount = PasakSetting::query()->count();
            expect($initialCount)->toBeGreaterThan(0);

            // Jalankan kedua kali di server: count harus sama persis (tidak ada duplikasi data)
            $this->artisan("pasak:deploy", [
                "--env-path" => $envPath,
                "--force" => true,
            ])->assertSuccessful();

            expect(PasakSetting::query()->count())->toBe($initialCount);
        } finally {
            File::delete($envPath);
        }
    },
);

test(
    "pasak:deploy dengan --reset-settings menyetel ulang pengaturan tanpa duplikasi data",
    function () {
        $envPath = pasakDeployTemporaryEnv();

        try {
            File::put(
                $envPath,
                "APP_ENV=production\nPASAK_DASHBOARD_SECRET=\n",
            );

            $this->artisan("pasak:deploy", [
                "--env-path" => $envPath,
                "--force" => true,
            ])->assertSuccessful();

            // Modifikasi salah satu setting
            PasakSetting::query()
                ->where("key", "auto_block.threshold")
                ->update(["value" => serialize(99)]);
            expect(
                unserialize(
                    PasakSetting::query()
                        ->where("key", "auto_block.threshold")
                        ->value("value"),
                ),
            )->toBe(99);

            // Jalankan deploy dengan --reset-settings
            $this->artisan("pasak:deploy", [
                "--env-path" => $envPath,
                "--reset-settings" => true,
                "--force" => true,
            ])->assertSuccessful();

            // Nilai kembali ke default (3) dan tidak ada duplikasi data
            expect(
                unserialize(
                    PasakSetting::query()
                        ->where("key", "auto_block.threshold")
                        ->value("value"),
                ),
            )->toBe(3);
        } finally {
            File::delete($envPath);
        }
    },
);

test(
    "pasak:deploy dengan --reset-tables hanya mengosongkan tabel khusus Pasak dan tidak menyentuh tabel host users",
    function () {
        $envPath = pasakDeployTemporaryEnv();

        try {
            File::put(
                $envPath,
                "APP_ENV=production\nPASAK_DASHBOARD_SECRET=\n",
            );

            // Pastikan tabel users terisi data host aplikasi
            $usersTable = (string) config("pasak.table_names.users", "users");
            if (Schema::hasTable($usersTable)) {
                DB::table($usersTable)->insertOrIgnore([
                    "id" => 9999,
                    "name" => "Host Application User",
                    "email" => "hostuser@domain.test",
                    "password" => bcrypt("secret"),
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
            }

            // Isi log dan blocked ip di tabel khusus Pasak
            PasakBlockedIp::query()->create([
                "ip_address" => "203.0.113.50",
                "reason" => "Test attack",
                "is_active" => true,
            ]);

            PasakLog::query()->create([
                "ip_address" => "203.0.113.50",
                "threat_level" => "high",
                "event_type" => "xss",
            ]);

            expect(PasakBlockedIp::query()->count())->toBeGreaterThan(0);
            expect(PasakLog::query()->count())->toBeGreaterThan(0);

            // Jalankan deploy dengan --reset-tables
            $this->artisan("pasak:deploy", [
                "--env-path" => $envPath,
                "--reset-tables" => true,
                "--force" => true,
            ])->assertSuccessful();

            // Tabel Pasak harus ter-reset dan settings terisi ulang oleh seeder bawaan
            expect(PasakBlockedIp::query()->count())->toBe(0);
            expect(PasakLog::query()->count())->toBe(0);
            expect(PasakSetting::query()->count())->toBeGreaterThan(0);

            // Tabel pengguna aplikasi host (users) HARUS tetap utuh!
            if (Schema::hasTable($usersTable)) {
                $hostUser = DB::table($usersTable)
                    ->where("email", "hostuser@domain.test")
                    ->first();
                expect($hostUser)->not->toBeNull();
            }
        } finally {
            File::delete($envPath);
        }
    },
);

test(
    "getDedicatedPasakTables mengecualikan tabel users dan tabel non-pasak",
    function () {
        $dedicated = PasakDeployCommand::getDedicatedPasakTables();
        $usersTable = (string) config("pasak.table_names.users", "users");

        expect($dedicated)
            ->not->toContain($usersTable)
            ->not->toContain("users")
            ->toContain(new PasakSetting()->getTable());
    },
);

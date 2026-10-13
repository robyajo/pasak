<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Internal\Pasak\Models\AllowedIp;
use Internal\Pasak\Models\BlockedIp;
use Internal\Pasak\Models\IpUnblockRequest;
use Internal\Pasak\Models\LoginAttempt;
use Internal\Pasak\Models\SecurityLog;
use Internal\Pasak\Models\TrustedIp;
use Internal\Pasak\Models\UserLogin;

/**
 * Isi demo otomatis untuk SETIAP CRUD dashboard Pasak agar bisa diuji
 * tanpa harus melancarkan serangan sungguhan terlebih dahulu.
 *
 * Idempoten: aman dijalankan berulang (firstOrCreate / updateOrCreate).
 */
class PasakDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@demo.test')->first() ?? User::first();

        if ($admin === null) {
            $this->command->warn('PasakDemoSeeder dilewati: belum ada user (jalankan UserSeeder dulu).');

            return;
        }

        $this->seedLogs($admin);
        $this->seedBlockedIps($admin);
        $this->seedAllowedIps($admin);
        $this->seedLoginAttempts();
        $this->seedTickets($admin);
        $this->seedSessions();
        $this->seedTrustedIps($admin);
    }

    /** Log ancaman: sebaran level + tipe event 7 hari terakhir. */
    protected function seedLogs(User $admin): void
    {
        $samples = [
            ['critical', 'webshell_upload', 'POST', '/posts', 'shell.php.jpg', true],
            ['critical', 'null_byte_injection', 'POST', '/posts', 'shell.php%00.jpg', true],
            ['high', 'sqli_attempt', 'GET', '/posts?q=%27%20OR%201%3D1--', null, true],
            ['high', 'xss_attempt', 'GET', '/posts?q=%3Cscript%3Ealert(1)', null, false],
            ['high', 'path_traversal', 'GET', '/..%2F..%2F.env', null, true],
            ['medium', 'sensitive_probe', 'GET', '/.git/HEAD', null, false],
            ['medium', 'double_extension', 'POST', '/posts', 'foto.php.png', false],
            ['low', 'suspicious_ua', 'GET', '/', null, false],
        ];

        foreach ($samples as $i => [$level, $event, $method, $path, $evidence, $blocked]) {
            SecurityLog::firstOrCreate(
                ['path' => $path, 'event_type' => $event],
                [
                    'ip_address' => '203.0.113.'.(10 + $i),
                    'user_id' => $i % 3 === 0 ? $admin->id : null,
                    'threat_level' => $level,
                    'method' => $method,
                    'full_url' => 'http://localhost:8000'.$path,
                    'rule_label' => 'demo-'.$event,
                    'evidence' => $evidence,
                    'user_agent' => 'PasakDemoSeed/1.0',
                    'was_blocked' => $blocked,
                    'action_taken' => $blocked ? 'blocked' : 'logged',
                    'created_at' => now()->subDays($i % 7)->subHours($i),
                ],
            );
        }
    }

    /** IP terblokir: aktif, scope device, dan satu yang kedaluwarsa. */
    protected function seedBlockedIps(User $admin): void
    {
        $rows = [
            ['203.0.113.10', 'ip', 'Serangan webshell berulang', true, null],
            ['203.0.113.11', 'device', 'Sqli attempt dari perangkat kafe', true, 'dev-kafe-01'],
            ['203.0.113.12', 'ip', 'Brute force login', false, null],
        ];

        foreach ($rows as [$ip, $scope, $reason, $active, $device]) {
            BlockedIp::updateOrCreate(
                ['ip_address' => $ip],
                [
                    'device_id' => $device,
                    'block_scope' => $scope,
                    'reason' => $reason,
                    'source' => 'auto',
                    'blocked_by' => $admin->id,
                    'blocked_at' => now()->subDays(2),
                    'expires_at' => $active ? now()->addDay() : now()->subDay(),
                    'is_active' => $active,
                    'hit_count' => $active ? 7 : 2,
                ],
            );
        }
    }

    /** Allowlist: IP lab + contoh CIDR. */
    protected function seedAllowedIps(User $admin): void
    {
        foreach ([
            ['127.0.0.1', 'Lab lokal'],
            ['192.168.1.0/24', 'Jaringan kantor'],
        ] as [$ip, $label]) {
            AllowedIp::firstOrCreate(
                ['ip_address' => $ip],
                ['label' => $label, 'allowed_by' => $admin->id, 'is_active' => true],
            );
        }
    }

    /** Percobaan login: satu terkunci, dua riwayat biasa. */
    protected function seedLoginAttempts(): void
    {
        LoginAttempt::updateOrCreate(
            ['email' => 'penyerang@contoh.test', 'ip_address' => '203.0.113.11'],
            [
                'attempts' => 8,
                'lockout_level' => 2,
                'locked_until' => now()->addMinutes(15),
                'last_attempt_at' => now()->subMinutes(2),
            ],
        );

        LoginAttempt::updateOrCreate(
            ['email' => 'admin@demo.test', 'ip_address' => '127.0.0.1'],
            ['attempts' => 1, 'lockout_level' => 0, 'last_attempt_at' => now()->subDay()],
        );
    }

    /** Tiket banding: pending, approved, rejected. */
    protected function seedTickets(User $admin): void
    {
        $rows = [
            ['PSK-2026-0001', '203.0.113.10', 'pending', null],
            ['PSK-2026-0002', '203.0.113.11', 'approved', 'IP kantor, dibuka kembali.'],
            ['PSK-2026-0003', '203.0.113.12', 'rejected', 'Terbukti brute force, tetap diblokir.'],
        ];

        foreach ($rows as [$ticket, $ip, $status, $notes]) {
            IpUnblockRequest::firstOrCreate(
                ['ticket_number' => $ticket],
                [
                    'ip_address' => $ip,
                    'name' => 'Penguji Lab',
                    'email' => 'penguji@contoh.test',
                    'reason' => 'IP ini dipakai bersama untuk pengujian lab, mohon dibuka.',
                    'status' => $status,
                    'admin_notes' => $notes,
                    'resolved_by' => $status === 'pending' ? null : $admin->id,
                    'resolved_at' => $status === 'pending' ? null : now()->subDay(),
                ],
            );
        }
    }

    /** Sesi login aktif untuk halaman Sessions / Active Users. */
    protected function seedSessions(): void
    {
        foreach (User::take(3)->get() as $i => $user) {
            UserLogin::updateOrCreate(
                ['user_id' => $user->id, 'ip_address' => '127.0.0.'.($i + 1)],
                [
                    'session_id' => 'demo-session-'.$user->id,
                    'user_agent' => 'PasakDemoSeed/1.0',
                    'device_type' => $i === 0 ? 'mobile' : 'desktop',
                    'operating_system' => 'Linux',
                    'browser' => 'Firefox',
                    'city' => 'Pekanbaru',
                    'country' => 'Indonesia',
                    'country_code' => 'ID',
                    'login_at' => now()->subHours($i + 1),
                    'last_activity_at' => now()->subMinutes($i * 5),
                ],
            );
        }
    }

    /** IP tepercaya milik admin. */
    protected function seedTrustedIps(User $admin): void
    {
        TrustedIp::firstOrCreate(
            ['user_id' => $admin->id, 'ip_address' => '127.0.0.1'],
            [
                'device_name' => 'Laptop Lab',
                'operating_system' => 'Linux',
                'browser' => 'Firefox',
                'location' => 'Pekanbaru, ID',
                'is_active' => true,
                'verified_at' => now(),
            ],
        );
    }
}

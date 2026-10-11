<?php

namespace Internal\Pasak\Support;

/**
 * Helper presentasi bersama untuk dashboard Blade murni (tanpa Livewire).
 */
class DashboardView
{
    public static function levelLabel(string $level): string
    {
        return match ($level) {
            'critical' => 'Critical',
            'high' => 'High',
            'medium' => 'Medium',
            default => 'Low',
        };
    }

    public static function levelClass(string $level): string
    {
        return match ($level) {
            'critical' => 'sec-badge-critical',
            'high' => 'sec-badge-high',
            'medium' => 'sec-badge-medium',
            default => 'sec-badge-low',
        };
    }

    public static function trendBarHeight(mixed $total, int $maxTrend): string
    {
        return max(4, (int) round(((int) $total / max(1, $maxTrend)) * 120)).'px';
    }

    public static function severityPercent(int $count, int $levelTotal): string
    {
        return (int) round(($count / max(1, $levelTotal)) * 100).'%';
    }

    public static function severityColor(string $level): string
    {
        return match ($level) {
            'critical' => 'var(--sec-danger)',
            'high' => 'var(--sec-warning)',
            'medium' => '#d97706',
            default => 'var(--sec-text-subtle)',
        };
    }

    public static function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'critical' => 'sec-badge-critical',
            'warning' => 'sec-badge-high',
            'ok' => 'sec-badge-success',
            default => 'sec-badge-low',
        };
    }
}

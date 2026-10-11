<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Internal\Pasak\Services\PasakService;

/**
 * Overview page for the Inertia + React monitoring dashboard.
 */
class OverviewController extends Controller
{
    public function __invoke(Request $request, PasakService $security): Response
    {
        $range = (string) $request->query('range', 'week');

        if (! in_array($range, ['week', 'month', 'year'], true)) {
            $range = 'week';
        }

        return Inertia::render('security/overview', [
            'range' => $range,
            'stats' => $security->stats(),
            'levels' => $security->levelBreakdown(),
            'trend' => $security->attackTrend($range),
            'attackers' => $security->topAttackers(5)
                ->map(fn ($row): array => (array) $row)
                ->all(),
            'urls' => [
                'self' => route('pasak.dashboard.overview'),
            ],
        ]);
    }
}

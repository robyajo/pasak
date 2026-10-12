<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Services\PasakService;

/**
 * Overview page for the Blade monitoring dashboard.
 */
class OverviewController extends Controller
{
    use RespondsWithDashboard;

    public function __invoke(Request $request, PasakService $security): View
    {
        $range = (string) $request->query('range', 'week');

        if (! in_array($range, ['week', 'month', 'year'], true)) {
            $range = 'week';
        }

        return $this->dashboardResponse(
            'pages.pasak.overview',
            [
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
            ]
        );
    }
}

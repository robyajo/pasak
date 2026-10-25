<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Internal\Pasak\Models\PasakIpUnblockRequest;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Services\PasakService;

/**
 * Unblock appeal tickets page for the Blade monitoring dashboard.
 */
class PasakTicketController extends Controller
{
    use RespondsWithPasakDashboard;

    public function index(Request $request): View
    {
        $query = PasakIpUnblockRequest::query()->latest('id');

        $search = (string) $request->query('search', '');
        $status = (string) $request->query('status', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $perPage = (int) $request->query('per_page', 25);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($dateFrom !== '') {
            try {
                $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable) {
            }
        }

        if ($dateTo !== '') {
            try {
                $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable) {
            }
        }

        return $this->dashboardResponse(
            'pages.pasak.tickets',
            [
                'tickets' => $query->paginate($perPage)->withQueryString(),
                'stats' => [
                    'pending' => PasakIpUnblockRequest::query()->pending()->count(),
                    'approved' => PasakIpUnblockRequest::query()->approved()->count(),
                    'rejected' => PasakIpUnblockRequest::query()->rejected()->count(),
                ],
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'per_page' => $perPage,
                ],
                'urls' => [
                    'index' => route('pasak.dashboard.tickets'),
                    'show' => route('pasak.dashboard.tickets.show', ['id' => '__ID__']),
                    'respond' => route('pasak.dashboard.tickets.respond', ['id' => '__ID__']),
                    'destroy' => route('pasak.dashboard.tickets.destroy', ['id' => '__ID__']),
                ],
            ]
        );
    }

    public function show(int|string $id): View
    {
        $ticket = PasakIpUnblockRequest::query()->find($id);

        if (! $ticket) {
            abort(404);
        }

        return $this->dashboardResponse(
            'pages.pasak.ticket-detail',
            [
                'ticket' => $ticket->loadMissing(['blockedIp', 'resolver']),
                'relatedLogs' => PasakLog::query()
                    ->where('ip_address', $ticket->ip_address)
                    ->latest('id')
                    ->limit(10)
                    ->get(),
                'urls' => [
                    'index' => route('pasak.dashboard.tickets'),
                    'show' => route('pasak.dashboard.tickets.show', ['id' => '__ID__']),
                    'logShow' => route('pasak.dashboard.logs.show', ['id' => '__ID__']),
                    'blockShow' => route('pasak.dashboard.blocked-ips.show', ['id' => '__ID__']),
                    'respond' => route('pasak.dashboard.tickets.respond', ['id' => $ticket->getKey()]),
                    'destroy' => route('pasak.dashboard.tickets.destroy', ['id' => $ticket->getKey()]),
                ],
            ]
        );
    }

    public function respond(Request $request, int|string $id, PasakService $security): RedirectResponse
    {
        $ticket = PasakIpUnblockRequest::query()->find($id);

        if (! $ticket) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('Ticket not found.'),
            ]);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $approve = $validated['action'] === 'approve';

        $ticket->status = $approve ? 'approved' : 'rejected';
        $ticket->admin_notes = $validated['admin_notes'] ?? ($approve ? __('Request approved.') : __('Request rejected.'));
        $ticket->resolved_by = $request->user()?->getKey();
        $ticket->resolved_at = now();
        $ticket->save();

        if ($approve) {
            $security->unblockIp($ticket->ip_address, $ticket->device_id);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $approve
                ? __('Ticket :ticket approved and IP unblocked.', ['ticket' => $ticket->ticket_number])
                : __('Ticket :ticket rejected.', ['ticket' => $ticket->ticket_number]),
        ]);
    }

    public function destroy(int|string $id): RedirectResponse
    {
        PasakIpUnblockRequest::query()->whereKey($id)->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Ticket deleted.'),
        ]);
    }
}

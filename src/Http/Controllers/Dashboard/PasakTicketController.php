<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        return $this->dashboardResponse(
            'pages.pasak.tickets',
            [
                'tickets' => $query->paginate(20)->withQueryString(),
                'stats' => [
                    'pending' => PasakIpUnblockRequest::query()->pending()->count(),
                    'approved' => PasakIpUnblockRequest::query()->approved()->count(),
                    'rejected' => PasakIpUnblockRequest::query()->rejected()->count(),
                ],
                'filters' => [
                    'search' => $search,
                    'status' => $status,
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

    public function show(int $id): View
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

    public function respond(Request $request, int $id, PasakService $security): RedirectResponse
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

    public function destroy(int $id): RedirectResponse
    {
        PasakIpUnblockRequest::query()->whereKey($id)->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Ticket deleted.'),
        ]);
    }
}

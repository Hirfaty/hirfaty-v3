<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in admin's own notifications. The header bell reads the shared
 * `adminNotifications` prop; this is the full list behind "Voir tout".
 */
class NotificationController extends Controller
{
    public function __construct(private AdminNotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $unreadOnly = $request->input('filter') === 'unread';

        return Inertia::render('admin/notifications/index', [
            'notifications' => $this->notifications->paginate($request->user(), $unreadOnly),
            'counts' => $this->notifications->counts($request->user()),
            'filters' => ['filter' => $unreadOnly ? 'unread' : null],
        ]);
    }

    /**
     * Mark one read and go where it points — clicking an entry is reading it.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        return redirect($this->notifications->open($request->user(), $notification));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user());

        return back();
    }
}

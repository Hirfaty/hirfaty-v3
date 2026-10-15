<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Notifications\Admin\CardRefundNeeded;
use App\Notifications\Admin\NewPurchaseAlert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * The admin panel's in-app notifications: who gets them, and how a stored
 * row reads on screen.
 *
 * Rows live in Laravel's `notifications` table, one per admin, so each admin
 * reads and clears their own. The panel is French only, like the rest of
 * /admin, so present() writes French directly rather than going through the
 * storefront's translation files.
 */
class AdminNotificationService
{
    /** How many rows the header bell lists before "Voir tout". */
    public const BELL_LIMIT = 8;

    /**
     * Put a new purchase in every admin's bell.
     *
     * Never throws: this runs after the checkout has committed, and a failed
     * alert must not turn a paid-for basket into an error page for the buyer.
     *
     * @param  array<int, Order>  $orders  Every order one checkout produced.
     */
    public function notifyNewPurchase(array $orders): void
    {
        if ($orders === []) {
            return;
        }

        try {
            $loaded = Order::whereIn('id', collect($orders)->pluck('id'))
                ->with(['buyer', 'items'])
                ->orderBy('id')
                ->get();

            $this->notifyAdmins(new NewPurchaseAlert($loaded));
        } catch (\Throwable $e) {
            Log::error('Admin in-app purchase notification failed: '.$e->getMessage());
        }
    }

    /**
     * Send one notification to every super_admin account.
     *
     * Through the facade rather than $admin->notify(): User::notify() drops
     * everything for an account with no email address, and the bell does not
     * need one.
     */
    public function notifyAdmins(BaseNotification $notification): void
    {
        $admins = User::where('role', 'super_admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, $notification);
    }

    /**
     * What the header bell needs: the unread count and the latest few rows.
     *
     * @return array{unread_count: int, latest: array<int, array<string, mixed>>}
     */
    public function summary(User $admin): array
    {
        return [
            'unread_count' => $admin->unreadNotifications()->count(),
            'latest' => $admin->notifications()
                ->limit(self::BELL_LIMIT)
                ->get()
                ->map(fn (DatabaseNotification $notification) => $this->present($notification))
                ->all(),
        ];
    }

    /**
     * The full list, newest first, optionally unread only.
     */
    public function paginate(User $admin, bool $unreadOnly = false): LengthAwarePaginator
    {
        $query = $unreadOnly ? $admin->unreadNotifications() : $admin->notifications();

        return $query->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification) => $this->present($notification));
    }

    /**
     * Counts for the list page's tabs.
     *
     * @return array{all: int, unread: int}
     */
    public function counts(User $admin): array
    {
        return [
            'all' => $admin->notifications()->count(),
            'unread' => $admin->unreadNotifications()->count(),
        ];
    }

    /**
     * Mark one of this admin's notifications read and say where it leads.
     *
     * Scoped to the admin's own rows, so one admin can never clear — or even
     * confirm the existence of — another's.
     */
    public function open(User $admin, string $id): string
    {
        $notification = $admin->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->present($notification)['url'];
    }

    public function markAllRead(User $admin): void
    {
        $admin->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * A stored row as the panel shows it.
     *
     * @return array{id: string, kind: string, title: string, body: string, url: string, read_at: ?string, created_at: ?string}
     */
    public function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        [$title, $body, $url] = match ($notification->type) {
            NewPurchaseAlert::KIND => $this->presentPurchase($data),
            CardRefundNeeded::KIND => $this->presentRefund($data),
            default => ['Notification', '', route('admin.notifications.index')],
        };

        return [
            'id' => $notification->id,
            'kind' => $notification->type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string, 2: string}
     */
    private function presentPurchase(array $data): array
    {
        $reference = $data['reference'] ?? '';
        $buyer = $data['buyer_name'] ?: 'Client';
        $items = (int) ($data['item_count'] ?? 0);
        $stores = (int) ($data['store_count'] ?? 0);
        $total = number_format((float) ($data['total'] ?? 0), 2, ',', ' ');

        $parts = [
            $buyer,
            $items.' article'.($items > 1 ? 's' : ''),
            $total.' MAD',
        ];

        // Worth saying only when the desk has several benches to collect from.
        if ($stores > 1) {
            $parts[] = $stores.' ateliers';
        }

        $url = isset($data['order_reference'])
            ? route('admin.orders.show', $data['order_reference'])
            : route('admin.orders.index');

        return [trim("Nouvelle commande {$reference}"), implode(' · ', $parts), $url];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string, 2: string}
     */
    private function presentRefund(array $data): array
    {
        $amount = number_format((float) ($data['amount'] ?? 0), 2, ',', ' ');

        $url = ! empty($data['order_reference'])
            ? route('admin.orders.show', $data['order_reference'])
            : route('admin.orders.index');

        // Rows from before PayPal carry neither key: Payzone, in dirhams.
        $paypal = ($data['provider'] ?? 'payzone') === 'paypal';

        return [
            trim(($paypal ? 'Remboursement PayPal à faire — ' : 'Remboursement carte à faire — ').($data['reference'] ?? '')),
            implode(' · ', array_filter([
                $amount.' '.($data['currency'] ?? 'MAD'),
                $data['reason'] ?? null,
                match (true) {
                    $paypal => 'PayPal '.($data['transaction_id'] ?? $data['charge_id'] ?? ''),
                    isset($data['charge_id']) => 'Payzone '.$data['charge_id'],
                    default => null,
                },
            ])),
            $url,
        ];
    }
}

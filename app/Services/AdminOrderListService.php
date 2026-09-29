<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin orders table and its download.
 *
 * One filter builder serves both, so the file is always what the desk is
 * looking at — same search, status tab and date range — only unpaginated.
 *
 * The file has one row per *order* (one workshop's parcel), not per purchase:
 * that is the level money is split at — commission, the artisan's share and
 * the delivery fee all sit on the order. The purchase reference is its own
 * column, so a spreadsheet can still group a basket back together.
 */
class AdminOrderListService
{
    private const STATUS_LABELS = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'shipped' => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ];

    public function __construct(
        private readonly SpreadsheetExportService $spreadsheets,
    ) {}

    /**
     * The filters and nothing else — no eager loads, no ordering.
     *
     * @param  array{search?: ?string, status?: ?string, from?: ?string, to?: ?string}  $filters
     * @return Builder<Order>
     */
    public function query(array $filters): Builder
    {
        return Order::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($q) use ($search) {
                // References are stored uppercase, so the needle is folded
                // rather than the column — keeps this index-friendly and
                // works on both Postgres and the SQLite used in tests.
                $q->where('reference', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('group_reference', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('buyer', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('store', fn ($q) => $q->where('store_name', 'like', "%{$search}%"));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to));
    }

    /**
     * Every order the filters match, newest first, as CSV or Excel.
     *
     * @param  'csv'|'xlsx'  $format
     */
    public function export(string $format, array $filters): StreamedResponse|BinaryFileResponse
    {
        return $this->spreadsheets->download(
            $format,
            'commandes',
            [
                'Référence achat',
                'Référence commande',
                'Date',
                'Statut',
                'Client',
                'Email client',
                'Téléphone de livraison',
                'Ville',
                'Adresse de livraison',
                'Atelier',
                'Articles',
                'Total produits (MAD)',
                'Frais de livraison (MAD)',
                'Commission Hirfaty (MAD)',
                'Part artisan (MAD)',
                'Transporteur',
                'N° de suivi',
                'Livrée le',
                'Motif d’annulation',
                'Devise affichée',
                'Note du client',
            ],
            fn () => $this->query($filters)
                ->with(['buyer', 'store:id,store_name', 'city:id,name', 'items.product:id,name'])
                ->lazyByIdDesc(500)
                ->map(fn (Order $order) => $this->row($order)),
        );
    }

    /** @return list<string|int|float> */
    private function row(Order $order): array
    {
        $buyer = $order->buyer;

        return [
            $order->group_reference ?? $order->reference,
            $order->reference,
            $order->created_at?->format('Y-m-d H:i') ?? '',
            self::STATUS_LABELS[$order->status] ?? $order->status,
            trim(($buyer?->first_name ?? '').' '.($buyer?->last_name ?? '')),
            $buyer?->email ?? '',
            $order->shipping_phone ?? '',
            $order->city?->name ?? '',
            $order->shipping_address ?? '',
            $order->store?->store_name ?? '',
            // "2 × Tajine bleu (Grand); 1 × Vase" — one cell, so a row stays
            // one parcel.
            $order->items->map(fn (OrderItem $item) => $item->quantity.' × '
                .($item->product?->name ?? 'Produit supprimé')
                .($item->variant_label ? " ({$item->variant_label})" : ''))->implode('; '),
            (float) $order->total_amount,
            (float) $order->delivery_fee,
            (float) $order->platform_fee,
            (float) $order->seller_earnings,
            $order->delivery_service_name ?? '',
            $order->tracking_number ?? '',
            $order->delivered_at?->format('Y-m-d H:i') ?? '',
            $order->cancellation_reason ?? '',
            $order->display_currency ?? 'MAD',
            $order->notes ?? '',
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF invoice behind "Download invoice" on the buyer's order history.
 *
 * One invoice per *purchase*, not per Order row — the same grain as the card
 * it is downloaded from. A basket split across three workshops is one thing
 * the buyer paid for, and three invoices for it would read as three charges.
 *
 * Figures are MAD, always: that is what the order settles in. The currency the
 * buyer happened to be reading at checkout is presentation history, not what
 * they owe (see Order::displayTotal()).
 */
class PurchaseInvoiceService
{
    public function __construct(private BuyerAccountService $account) {}

    /**
     * The invoice as a download, or null when the reference is not one of
     * this buyer's purchases.
     */
    public function download(User $buyer, string $reference): ?Response
    {
        $orders = $this->account->purchaseOrders($buyer, $reference);

        if ($orders->isEmpty()) {
            return null;
        }

        /*
         * Arabic falls back to French. dompdf lays text out glyph by glyph
         * with no shaping, so Arabic would print as disconnected letters in
         * the wrong order — French, the working language of Moroccan
         * invoicing, reads correctly for everyone the desk deals with.
         */
        $locale = app()->getLocale() === 'ar' ? 'fr' : app()->getLocale();

        $pdf = Pdf::loadView('invoices.purchase', [
            'locale' => $locale,
            'reference' => $reference,
            'orders' => $orders,
            'buyer' => $buyer,
            'lines' => $this->lines($orders),
            'itemsTotal' => (float) $orders->sum(fn (Order $order) => (float) $order->total_amount),
            // Parked on the first order of a split — see CheckoutService.
            'deliveryFee' => (float) $orders->sum(fn (Order $order) => (float) $order->delivery_fee),
            'money' => fn (float|string|null $amount) => $this->money((float) $amount, $locale),
        ])
            ->setPaper('a4')
            // Embed only the glyphs used — the whole DejaVu face is ~800 KB.
            ->setOption('enable_font_subsetting', true);

        return $pdf->download(__('buyer.invoice.filename', ['reference' => $reference], $locale).'.pdf');
    }

    /**
     * Every piece in the purchase, flat, with the workshop it came from.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, array<string, mixed>>
     */
    private function lines(Collection $orders): Collection
    {
        return $orders->flatMap(fn (Order $order) => $order->items->map(fn ($item) => [
            // The product may have been deleted since; the line still stands.
            'name' => $item->product?->name ?? '—',
            'variant' => $item->variant_label,
            'store' => $order->store?->store_name,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'subtotal' => (float) $item->subtotal,
        ]));
    }

    private function money(float $amount, string $locale): string
    {
        $figure = $locale === 'fr'
            ? number_format($amount, 2, ',', "\u{00A0}")
            : number_format($amount, 2);

        return $figure.' MAD';
    }
}

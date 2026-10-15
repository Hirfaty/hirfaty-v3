<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminInvoiceListService;
use App\Services\PurchaseInvoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Every invoice Hirfaty has issued — one per purchase, the PDF the buyer gets
 * from their order history. See AdminInvoiceListService.
 */
class InvoiceController extends Controller
{
    public function index(Request $request, AdminInvoiceListService $invoices)
    {
        $filters = $request->only(['search', 'method']);

        return Inertia::render('admin/invoices/index', $invoices->page($filters) + [
            'counts' => $invoices->counts($filters),
            'filters' => $filters,
        ]);
    }

    /**
     * One invoice as a PDF. `{purchase}` is the invoice number — a purchase
     * reference, not an Order route key — so it is a plain string.
     */
    public function download(string $purchase, PurchaseInvoiceService $invoices)
    {
        return $invoices->downloadForDesk($purchase) ?? abort(404);
    }
}
